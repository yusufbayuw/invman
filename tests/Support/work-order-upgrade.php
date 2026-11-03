<?php

// Explicitly opt-in, disposable database only. Never drops an existing table.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Artisan, DB, Mail, Notification, Schema};
use App\Models\{User, G001M001Unit, G002M007Item, G004M008Activity, G005M009ItemReservation, Ticket};

if (! app()->environment('testing') || getenv('INVMAN_UPGRADE_TEST') !== '1'
    || DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::getDatabaseName(), '_upgrade')) {
    throw new RuntimeException('Requires explicit testing opt-in and isolated MySQL *_upgrade database.');
}
if (Schema::hasTable('migrations')) {
    throw new RuntimeException('Upgrade probe requires an EMPTY disposable database; no automatic deletion.');
}
Mail::fake(); Notification::fake();
$assert = function (bool $condition, string $message): void { if (! $condition) throw new RuntimeException($message); };
$paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => ! str_contains($p, '2026_11_02_210000')));
$assert(Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]) === 0, 'Legacy migrations failed');
$assert(! Schema::hasTable('work_order_approvals'), 'Not a legacy schema');
Artisan::call('db:seed', ['--class' => Database\Seeders\RoleSeeder::class, '--force' => true]);
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
$assert(Artisan::call('migrate', ['--force' => true]) === 0, 'Upgrade failed');
$assert($before === $fingerprint(), 'Existing rows changed during upgrade');
$assert(DB::table('work_order_approvals')->count() === 0, 'Legacy ticket unexpectedly enrolled');
$legacyUser = $user->fresh()->getRawOriginal(); $roles = $user->roles->pluck('name')->all();
config(['security.seed_demo_users' => true, 'security.seed_demo_password' => 'Synthetic!Upgrade2026']);
Artisan::call('db:seed', ['--force' => true]);
$counts = fn () => collect(['users', 'work_order_job_titles', 'work_order_assignments', 'work_order_workflows', 'ticket_team_members'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$first = $counts();
Artisan::call('db:seed', ['--force' => true]);
$assert($first === $counts(), 'Seeder creates duplicate rows');
$assert($user->fresh()->getRawOriginal() === $legacyUser, 'Seeder modified existing user/password/unit');
$assert($user->fresh()->roles->pluck('name')->all() === $roles, 'Seeder modified existing roles');
$assert(DB::table('work_order_workflows')->where('status', 'published')->count() === 0, 'Demo workflow published automatically');
$assert($ticket->events()->count() === 1 && $ticket->fresh()->maintenance->approval_notes === 'Existing history', 'History changed');
config(['app.env' => 'production']);
Artisan::call('db:seed', ['--force' => true]);
Artisan::call('db:seed', ['--class' => Database\Seeders\WorkOrderDemoSeeder::class, '--force' => true]);
$assert($first === $counts(), 'Production guards did not stop example seeders');
echo json_encode(['mysql' => DB::selectOne('select version() v')->v, 'legacy_migrations' => count($paths),
    'upgrade' => 'PASS', 'existing_rows_password_roles_history' => 'unchanged', 'legacy_approval_rows' => 0,
    'seed_twice' => $first, 'automatic_publish' => false, 'production_demo_guard' => 'PASS'], JSON_PRETTY_PRINT).PHP_EOL;
