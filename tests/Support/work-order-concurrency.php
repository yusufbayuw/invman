<?php

use App\Filament\Resources\WorkOrderWorkflowResource\Pages\CreateWorkflow;
use App\Models\G001M001Unit;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\TicketService;
use App\Services\WorkOrderRevisionService;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

// Opt-in, non-destructive MySQL concurrency probe for disposable test databases.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e): never {
    fwrite(STDERR, 'Readiness probe failed: '.$e->getMessage().PHP_EOL);
    exit(1);
});


if (! app()->environment('testing') || getenv('INVMAN_CONCURRENCY_TEST') !== '1'
    || DB::getDriverName() !== 'mysql' || ! preg_match('/_(tests?|ci)$/D', DB::getDatabaseName())) {
    throw new RuntimeException('Requires explicit opt-in and a disposable MySQL test/CI database.');
}
Notification::fake();
$mode = $argv[1] ?? 'parent';
if ($mode === 'revision') {
    try {
        $ticket = Ticket::findOrFail($argv[2]);
        app(WorkOrderRevisionService::class)->resubmit($ticket, User::findOrFail($argv[3]), (int) $argv[4],
            'Laporan diperbaiki sesuai penolakan.', array_merge($ticket->only(['title', 'location', 'reported_urgency']), [
                'description' => 'Lokasi kerusakan dan kondisi diperjelas untuk persetujuan ulang.',
            ]));
        echo 'committed';
    } catch (ValidationException $exception) {
        echo 'rejected';
    }
    exit;
}
if ($mode === 'approval') {
    try {
        app(WorkOrderWorkflowService::class)->decide(WorkOrderApproval::findOrFail($argv[2]), User::findOrFail($argv[3]), true, 'Concurrent synthetic decision');
        echo 'committed';
    } catch (ValidationException $exception) {
        echo 'rejected';
    }
    exit;
}
if ($mode === 'draft') {
    auth()->setUser(User::findOrFail($argv[2]));
    $marker = $argv[4];
    DB::connection()->beforeExecuting(function (string $sql) use ($marker): void {
        if (str_starts_with(strtolower($sql), 'insert into `work_order_workflows`')) {
            file_put_contents($marker, 'ready');
            usleep(700000);
        }
    });
    try {
        $page = new CreateWorkflow;
        $method = new ReflectionMethod($page, 'handleRecordCreation');
        $method->invoke($page, ['g001_m001_unit_id' => null, 'steps' => [
            ['key' => 'approve', 'label' => 'Approval', 'checkpoint' => 'start', 'resolver' => 'job_title', 'job_title_id' => (int) $argv[3]],
        ]]);
        echo 'committed';
    } catch (ValidationException $exception) {
        echo 'rejected';
    }
    exit;
}
$app->make(Kernel::class)->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
$unit = G001M001Unit::create(['name' => 'QA Concurrency '.bin2hex(random_bytes(4))]);
$reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
$reporter->assignRole(config('role.sarpras'));
$title = WorkOrderJobTitle::create(['name' => 'QA Concurrent '.bin2hex(random_bytes(4))]);
$actors = [User::factory()->create(), User::factory()->create()];
foreach ($actors as $actor) {
    $actor->assignRole(config('role.admin'));
    WorkOrderAssignment::create(['user_id' => $actor->id, 'work_order_job_title_id' => $title->id, 'g001_m001_unit_id' => $unit->id]);
}
$workflow = WorkOrderWorkflow::create(['g001_m001_unit_id' => $unit->id, 'version' => 1, 'status' => 'published', 'created_by' => $actors[0]->id,
    'steps' => [['key' => 'approve', 'label' => 'Approval', 'checkpoint' => 'start', 'resolver' => 'job_title', 'job_title_id' => $title->id]]]);
$ticket = app(TicketService::class)->open($reporter, ['ticket_category_id' => TicketCategory::where('slug', 'maintenance_hvac')->firstOrFail()->id,
    'title' => 'Concurrent QA work', 'description' => 'Disposable concurrency test', 'location' => 'QA']);
$approval = $ticket->workOrderApprovals()->firstOrFail();
function worker(array $args): array
{
    $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    return [$process, $pipes];
}
function finish(array $worker): string
{
    [$process, $pipes] = $worker;
    $out = trim(stream_get_contents($pipes[1]));
    $err = trim(stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0) {
        throw new RuntimeException('Worker failed: '.$err);
    }

    return $out;
}
$a = worker(['approval', $approval->id, $actors[0]->id]);
$b = worker(['approval', $approval->id, $actors[1]->id]);
$outcomes = [finish($a), finish($b)];
sort($outcomes);
$approvalResult = $outcomes === ['committed', 'rejected'] && $ticket->events()->where('action', 'work_order_approval_granted')->count() === 1;
$revisionTicket = app(TicketService::class)->open($reporter, ['ticket_category_id' => TicketCategory::where('slug', 'maintenance_hvac')->firstOrFail()->id,
    'title' => 'Concurrent QA revision', 'description' => 'Disposable resubmission race test', 'location' => 'QA']);
$rejected = $revisionTicket->workOrderApprovals()->firstOrFail();
app(WorkOrderWorkflowService::class)->decide($rejected, $actors[0], false, 'Perbaiki keterangan laporan.');
$a = worker(['revision', $revisionTicket->id, $reporter->id, $rejected->id]);
$b = worker(['revision', $revisionTicket->id, $reporter->id, $rejected->id]);
$revisionOutcomes = [finish($a), finish($b)];
sort($revisionOutcomes);
$revisionResult = $revisionOutcomes === ['committed', 'rejected'] && $revisionTicket->workOrderRevisions()->count() === 1
    && $revisionTicket->workOrderApprovals()->count() === 1 && $rejected->fresh()->status === 'rejected'
    && $revisionTicket->events()->where('action', 'work_order_revision_submitted')->count() === 1;
$marker = tempnam(sys_get_temp_dir(), 'invman-race-');
unlink($marker);
if (WorkOrderWorkflow::whereNull('g001_m001_unit_id')->exists()) {
    throw new RuntimeException('Global scope must be unused in this disposable probe database.');
}
$a = worker(['draft', $actors[0]->id, $title->id, $marker]);
$deadline = microtime(true) + 8;
while (! file_exists($marker) && microtime(true) < $deadline) {
    usleep(10000);
}
if (! file_exists($marker)) {
    throw new RuntimeException('First draft worker did not reach insert barrier');
}
$b = worker(['draft', $actors[1]->id, $title->id, $marker.'-second']);
$draftOutcomes = [finish($a), finish($b)];
sort($draftOutcomes);
$draftCount = WorkOrderWorkflow::whereNull('g001_m001_unit_id')->count();
$draftResult = $draftOutcomes === ['committed', 'rejected'] && $draftCount === 1;
echo json_encode(['mysql' => DB::selectOne('select version() as version')->version,
    'approval_race' => ['pass' => $approvalResult, 'outcomes' => $outcomes, 'committed_events' => $ticket->events()->where('action', 'work_order_approval_granted')->count()],
    'revision_race' => ['pass' => $revisionResult, 'outcomes' => $revisionOutcomes, 'revisions' => $revisionTicket->workOrderRevisions()->count()],
    'global_draft_race' => ['pass' => $draftResult, 'outcomes' => $draftOutcomes, 'drafts' => $draftCount]], JSON_PRETTY_PRINT).PHP_EOL;
exit($approvalResult && $draftResult && $revisionResult ? 0 : 1);
