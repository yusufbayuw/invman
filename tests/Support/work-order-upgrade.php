<?php

use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrderWorkflow;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkOrderDemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

// Explicitly opt-in, disposable database only. Never drops an existing table.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e): never {
    fwrite(STDERR, 'Readiness probe failed: '.$e->getMessage().PHP_EOL);
    exit(1);
});


if (! app()->environment('testing') || getenv('INVMAN_UPGRADE_TEST') !== '1'
    || DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::getDatabaseName(), '_upgrade')) {
    throw new RuntimeException('Requires explicit testing opt-in and isolated MySQL *_upgrade database.');
}
if (Schema::hasTable('migrations')) {
    throw new RuntimeException('Upgrade probe requires an EMPTY disposable database; no automatic deletion.');
}
Mail::fake();
Notification::fake();
$assert = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => basename($p) < '2026_11_02_210000'));
$assert(Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]) === 0, 'Legacy migrations failed');
$assert(! Schema::hasTable('work_order_approvals'), 'Not a legacy schema');
Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
$unit = G001M001Unit::create(['name' => 'Synthetic legacy unit']);
$user = User::factory()->create(['username' => 'admin', 'email' => 'legacy@example.test', 'g001_m001_unit_id' => $unit->id]);
$user->assignRole(config('role.sarpras'));
$item = G002M007Item::create(['name' => 'Legacy asset', 'g001_m001_unit_id' => $unit->id, 'quantity' => 2, 'is_borrowable' => true]);
$activity = G004M008Activity::create(['user_id' => $user->id, 'g001_m001_unit_id' => $unit->id,
    'name' => 'Legacy booking', 'start_time' => now()->addDay(), 'end_time' => now()->addDays(2), 'status' => 'submitted']);
$reservation = G005M009ItemReservation::create(['g004_m008_activity_id' => $activity->id, 'g002_m007_item_id' => $item->id,
    'quantity' => 1, 'start_time' => $activity->start_time, 'end_time' => $activity->end_time, 'status' => 'submitted']);
$ticket = Ticket::create(['number' => 'UPGRADE-LEGACY', 'ticket_category_id' => DB::table('ticket_categories')->value('id'),
    'reporter_id' => $user->id, 'g001_m001_unit_id' => $unit->id, 'title' => 'Legacy repair', 'description' => 'Synthetic history', 'status' => 'open']);
$case = $ticket->maintenance()->make();
$case->forceFill(['approval_notes' => 'Existing history'])->save();
$ticket->events()->create(['actor_id' => $user->id, 'action' => 'opened', 'notes' => 'Legacy event']);
$tables = ['users', 'model_has_roles', 'g004_m008_activities', 'g005_m009_item_reservations', 'tickets', 'ticket_events', 'ticket_maintenance_cases'];
$fingerprint = fn () => array_map(fn ($table) => hash('sha256', json_encode(DB::table($table)->get()->all())), $tables);
$before = $fingerprint();
// Upgrade through the original #32 schema with a real rejected approval row.
// The new revision migration must retain that decision, not only empty tables.
$assert(Artisan::call('migrate', ['--path' => 'database/migrations/2026_11_02_210000_create_dynamic_work_order_workflows.php', '--force' => true]) === 0, 'Original dynamic schema migration failed');
$historicalApprover = User::factory()->create();
$dynamicTicket = Ticket::create(['number' => 'UPGRADE-DYNAMIC', 'ticket_category_id' => $ticket->ticket_category_id,
    'reporter_id' => $user->id, 'g001_m001_unit_id' => $unit->id, 'title' => 'Existing dynamic repair', 'description' => 'Preserve rejected decision', 'status' => 'open']);
$dynamicCase = $dynamicTicket->maintenance()->make();
$dynamicCase->forceFill(['approval_notes' => 'Dynamic legacy history'])->save();
$workflow = WorkOrderWorkflow::create(['g001_m001_unit_id' => $unit->id, 'version' => 1, 'status' => 'published',
    'created_by' => $historicalApprover->id, 'steps' => [['key' => 'review', 'label' => 'Legacy review', 'checkpoint' => 'start', 'resolver' => 'reporter_supervisor']]]);
$approvalId = DB::table('work_order_approvals')->insertGetId(['ticket_id' => $dynamicTicket->id, 'work_order_workflow_id' => $workflow->id,
    'step_key' => 'review', 'label' => 'Legacy review', 'checkpoint' => 'start', 'resolver' => 'reporter_supervisor',
    'candidate_user_ids' => json_encode([$historicalApprover->id]), 'sequence' => 0, 'status' => 'rejected',
    'decided_by' => $historicalApprover->id, 'decided_at' => now(), 'notes' => 'Preserve original rejection reason', 'created_at' => now(), 'updated_at' => now()]);
$originalApproval = (array) DB::table('work_order_approvals')->where('id', $approvalId)->first();
$before = $fingerprint();
$assert(Artisan::call('migrate', ['--force' => true]) === 0, 'Upgrade failed');
$assert($before === $fingerprint(), 'Existing rows changed during upgrade');
$upgradedApproval = (array) DB::table('work_order_approvals')->where('id', $approvalId)->first();
$assert(array_intersect_key($upgradedApproval, $originalApproval) === $originalApproval, 'Original approval identity/decision changed');
$assert((int) $upgradedApproval['revision'] === 1 && $upgradedApproval['superseded_at'] === null, 'Original approval not retained as active revision one');
$assert($ticket->workOrderApprovals()->count() === 0, 'Legacy ticket unexpectedly enrolled');
$legacyUser = $user->fresh()->getRawOriginal();
$roles = $user->roles->pluck('name')->all();
config(['security.seed_demo_users' => true, 'security.seed_demo_password' => 'Synthetic!Upgrade2026']);
Artisan::call('db:seed', ['--force' => true]);
$counts = fn () => collect(['users', 'work_order_job_titles', 'work_order_assignments', 'work_order_workflows', 'ticket_team_members'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$first = $counts();
Artisan::call('db:seed', ['--force' => true]);
$assert($first === $counts(), 'Seeder creates duplicate rows');
$assert($user->fresh()->getRawOriginal() === $legacyUser, 'Seeder modified existing user/password/unit');
$assert($user->fresh()->roles->pluck('name')->all() === $roles, 'Seeder modified existing roles');
$assert(DB::table('work_order_workflows')->where('status', 'published')->count() === 1, 'Demo workflow published automatically or existing published workflow changed');
$assert($ticket->events()->count() === 1 && $ticket->fresh()->maintenance->approval_notes === 'Existing history', 'History changed');
config(['app.env' => 'production']);
Artisan::call('db:seed', ['--force' => true]);
Artisan::call('db:seed', ['--class' => WorkOrderDemoSeeder::class, '--force' => true]);
$assert($first === $counts(), 'Production guards did not stop example seeders');
echo json_encode(['mysql' => DB::selectOne('select version() v')->v, 'legacy_migrations' => count($paths),
    'upgrade' => 'PASS', 'existing_rows_password_roles_history' => 'unchanged', 'legacy_approval_rows' => 0,
    'existing_rejected_approval_preserved_as_revision_one' => true,
    'seed_twice' => $first, 'automatic_publish' => false, 'production_demo_guard' => 'PASS'], JSON_PRETTY_PRINT).PHP_EOL;
