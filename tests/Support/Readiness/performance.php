<?php

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e): never {
    fwrite(STDERR, 'QA performance failed: '.$e->getMessage().PHP_EOL);
    exit(1);
});
use App\Filament\Resources\WorkOrderWorkflowResource\Pages\CreateWorkflow;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\LoanRequestService;
use App\Services\TicketService;
use App\Services\WorkOrderWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

if (getenv('INVMAN_FINAL_QA') !== '1' || DB::getDriverName() !== 'mysql' || ! in_array(DB::getDatabaseName(), ['invman_final_ci', 'invman_ci'], true)) {
    throw new RuntimeException('Isolated CI QA database required');
}
Notification::fake();
$rt = getenv('QA_RUNTIME') ?: sys_get_temp_dir().'/invman-perf-'.bin2hex(random_bytes(5));
if (! is_dir($rt)) {
    mkdir($rt, 0700, true);
}
$out = dirname(__DIR__, 3).'/docs/audit/evidence/2026-11-03-final';
if (($argv[1] ?? '') === 'worker') {
    $p = json_decode(file_get_contents($argv[2]), true);
    $n = 0;
    DB::listen(function () use (&$n) {
        $n++;
    });
    $start = microtime(true);
    try {
        $u = User::findOrFail($p['user']);
        auth()->setUser($u);
        $id = null;
        switch ($p['action']) {
            case 'open':$t = app(TicketService::class)->open($u, ['ticket_category_id' => $p['category'], 'title' => 'QA parallel report '.$p['index'], 'description' => 'Synthetic performance report from multiple units', 'location' => 'QA controlled load']);
                $id = $t->id;
                break;
            case 'approve':app(WorkOrderWorkflowService::class)->decide(WorkOrderApproval::findOrFail($p['approval']), $u, true, 'Controlled QA approval');
                break;
            case 'draft':$page = new CreateWorkflow;
                $m = new ReflectionMethod($page, 'handleRecordCreation');
                $m->invoke($page, ['g001_m001_unit_id' => $p['unit'], 'steps' => [['key' => 'review', 'label' => 'QA review', 'checkpoint' => 'start', 'resolver' => 'job_title', 'job_title_id' => $p['title']]]]);
                break;
            case 'loan':$a = app(LoanRequestService::class)->submit($u, ['name' => 'QA parallel booking', 'description' => 'Controlled quantity contention', 'start_time' => $p['start'], 'end_time' => $p['end'], 'needs' => [['type' => 'item', 'item_id' => $p['item'], 'quantity' => 1]]]);
                $id = $a->id;
                break;
            case 'cancel':app(LoanRequestService::class)->cancel(G004M008Activity::findOrFail($p['activity']));
                break;
        }
        $r = ['outcome' => 'committed', 'id' => $id];
    } catch (ValidationException $e) {
        $r = ['outcome' => 'validation_conflict'];
    } catch (Throwable $e) {
        $r = ['outcome' => 'error', 'type' => get_class($e), 'sqlstate' => $e instanceof QueryException ? $e->errorInfo[0] : null];
    }
    echo json_encode($r + ['service_ms' => round((microtime(true) - $start) * 1000, 3), 'queries' => $n, 'peak_php_bytes' => memory_get_peak_usage(true)]).PHP_EOL;
    exit($r['outcome'] === 'error' ? 1 : 0);
}
function batch(array $tasks, int $concurrency): array
{
    global $rt;
    $pending = $tasks;
    $results = [];
    $running = [];
    $start = microtime(true);
    while ($pending || $running) {
        while ($pending && count($running) < $concurrency) {
            $p = array_shift($pending);
            $file = $rt.'/perf-'.bin2hex(random_bytes(5));
            file_put_contents($file.'.json', json_encode($p));
            $proc = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, 'worker', $file.'.json'], [1 => ['file', $file.'.out', 'w'], 2 => ['file', $file.'.err', 'w']], $pipes);
            $running[] = ['proc' => $proc, 'file' => $file];
        }
        foreach ($running as $i => $w) {
            $s = proc_get_status($w['proc']);
            if (! $s['running']) {
                $row = json_decode(file_get_contents($w['file'].'.out'), true);
                if (! $row) {
                    throw new RuntimeException('No valid worker result');
                }$results[] = $row;
                proc_close($w['proc']);
                unset($running[$i]);
            }
        }
        $running = array_values($running);
        if ($running) {
            usleep(10000);
        }
    }
    $ms = array_column($results, 'service_ms');
    sort($ms);
    $q = array_column($results, 'queries');
    sort($q);
    $wall = microtime(true) - $start;

    return ['workers' => $concurrency, 'requests' => count($results), 'outcomes' => array_count_values(array_column($results, 'outcome')), 'wall_seconds' => round($wall, 3), 'process_throughput_per_second' => round(count($results) / $wall, 3), 'service_latency_ms' => ['p50' => $ms[(int) floor((count($ms) - 1) * .5)], 'p95' => $ms[(int) floor((count($ms) - 1) * .95)], 'max' => max($ms)], 'queries' => ['min' => min($q), 'max' => max($q)], 'peak_worker_php_bytes' => max(array_column($results, 'peak_php_bytes')), 'results' => $results];
}
$title = WorkOrderJobTitle::create(['name' => 'QA Perf Reviewer '.bin2hex(random_bytes(4))]);
$manager = User::factory()->create();
$manager->assignRole(config('role.admin'));
WorkOrderAssignment::create(['user_id' => $manager->id, 'work_order_job_title_id' => $title->id]);
$users = [];
for ($i = 0; $i < 8; $i++) {
    $unit = G001M001Unit::create(['name' => 'Perf Unit '.bin2hex(random_bytes(3))]);
    $u = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
    $u->assignRole(config('role.sarpras'));
    $users[] = $u;
    WorkOrderWorkflow::create(['g001_m001_unit_id' => $unit->id, 'version' => 1, 'status' => 'published', 'created_by' => $manager->id, 'steps' => [['key' => 'review', 'label' => 'Review', 'checkpoint' => 'start', 'resolver' => 'job_title', 'job_title_id' => $title->id]]]);
}
$cat = TicketCategory::firstOrCreate(['slug' => 'maintenance_hvac'], ['name' => 'QA HVAC', 'is_active' => true])->id;
$tasks = [];
for ($i = 0; $i < 8; $i++) {
    $tasks[] = ['action' => 'open', 'user' => $users[$i]->id, 'category' => $cat, 'index' => $i];
}$sequential = batch($tasks, 1);
$tasks = [];
for ($i = 0; $i < 32; $i++) {
    $tasks[] = ['action' => 'open', 'user' => $users[$i % 8]->id, 'category' => $cat, 'index' => $i];
}$parallel = batch($tasks, 8);
$tasks = [];
foreach ($parallel['results'] as $r) {
    if ($r['outcome'] === 'committed') {
        $tasks[] = ['action' => 'approve', 'user' => $manager->id, 'approval' => Ticket::findOrFail($r['id'])->workOrderApprovals()->value('id')];
    }
}$approvals = batch($tasks, 8);
$draftUnit = G001M001Unit::create(['name' => 'Perf Draft Scope '.bin2hex(random_bytes(3))]);
$tasks = array_fill(0, 8, ['action' => 'draft', 'user' => $manager->id, 'unit' => $draftUnit->id, 'title' => $title->id]);
$drafts = batch($tasks, 8);
$item = G002M007Item::create(['name' => 'QA Contended Stock', 'g001_m001_unit_id' => $users[0]->g001_m001_unit_id, 'quantity' => 4, 'available_quantity' => 4, 'is_borrowable' => true, 'status' => 'tersedia']);
$tasks = array_fill(0, 12, ['action' => 'loan', 'user' => $users[0]->id, 'item' => $item->id, 'start' => now()->addDays(4)->format('Y-m-d H:i:s'), 'end' => now()->addDays(4)->addHours(2)->format('Y-m-d H:i:s')]);
$loans = batch($tasks, 6);
$cancel = [];
foreach ($loans['results'] as $r) {
    if ($r['outcome'] === 'committed') {
        $cancel[] = ['action' => 'cancel', 'user' => $users[0]->id, 'activity' => $r['id']];
    }
}$cancellations = $cancel ? batch($cancel, 4) : null;
$errors = 0;
foreach ([$sequential, $parallel, $approvals, $drafts, $loans] as $r) {
    $errors += ($r['outcomes']['error'] ?? 0);
}
$approvedIds = array_column($parallel['results'], 'id');
$events = DB::table('ticket_events')->whereIn('ticket_id', $approvedIds)->where('action', 'work_order_approval_granted')->count();
$invariants = ['unexpected_errors' => $errors, 'parallel_tickets_committed' => ($parallel['outcomes']['committed'] ?? 0) === 32, 'one_approval_event_per_ticket' => $events === 32, 'one_draft_per_scope' => WorkOrderWorkflow::where('g001_m001_unit_id', $draftUnit->id)->where('status', 'draft')->count() === 1, 'stock_not_overcommitted' => ($loans['outcomes']['committed'] ?? 0) === 4];
$r = ['scope' => 'controlled QA; not production capacity certification', 'thresholds' => 'Zero unexpected errors, no duplicate decision/event, no draft duplication or stock overcommit. Latency is descriptive; production latency/SLA and hardware targets were not supplied.', 'environment' => ['php' => PHP_VERSION, 'mysql' => DB::selectOne('select version() v')->v, 'parallel_php_workers' => 8, 'shared_host' => true, 'proc_metrics_available' => is_dir('/proc/self')], 'sequential_open' => $sequential, 'parallel_open' => $parallel, 'parallel_approvals' => $approvals, 'parallel_drafts' => $drafts, 'parallel_bookings' => $loans, 'parallel_cancellations' => $cancellations, 'invariants' => $invariants, 'production_load_verdict' => 'BLOCKED: no agreed production workload/SLA or matching infrastructure'];
file_put_contents($out.'/performance-qa.json', json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($r, JSON_PRETTY_PRINT).PHP_EOL;
exit($errors === 0 && ! in_array(false, $invariants, true) ? 0 : 1);
