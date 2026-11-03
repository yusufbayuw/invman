<?php

// Real MySQL workers on disposable QA data. Barriers observe database lock waits, not timing guesses.
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e): never {
    fwrite(STDERR, 'Readiness probe failed: '.$e->getMessage().PHP_EOL);
    exit(1);
});

use App\Models\G001M001Unit;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\TicketService;
use App\Services\WorkOrderEstimateService;
use App\Services\WorkOrderRevisionService;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

if (getenv('INVMAN_FINAL_QA') !== '1' || ! in_array(app()->environment(), ['testing', 'local'], true)
    || DB::getDriverName() !== 'mysql' || ! preg_match('/^invman_(?:final_(qa|tests|ci)|ci)$/D', DB::getDatabaseName())) {
    throw new RuntimeException('Explicit isolated final QA MySQL database required.');
}
Notification::fake();
function barrier(string $prefix): void
{
    file_put_contents($prefix.'.ready', 'ready');
    $until = microtime(true) + 20;
    while (! file_exists($prefix.'.release')) {
        if (microtime(true) > $until) {
            throw new RuntimeException('Barrier release timeout');
        }
        usleep(10000);
    }
}
if (($argv[1] ?? '') === 'worker') {
    $p = json_decode(file_get_contents($argv[2]), true);
    $prefix = $p['prefix'];
    $actor = User::findOrFail($p['actor']);
    $actor->load('roles');
    $approval = isset($p['approval']) ? WorkOrderApproval::findOrFail($p['approval']) : null;
    if (($p['barrier'] ?? '') === 'approval_write') {
        DB::connection()->beforeExecuting(function ($sql) use ($prefix): void {
            if (preg_match('/^update [`"]work_order_approvals[`"] /i', $sql)) {
                barrier($prefix);
            }
        });
    } elseif (($p['barrier'] ?? '') === 'estimate_insert') {
        DB::connection()->beforeExecuting(function ($sql) use ($prefix): void {
            if (preg_match('/^insert into [`"]work_order_estimates[`"] /i', $sql)) {
                barrier($prefix);
            }
        });
    } elseif (($p['barrier'] ?? '') === 'workflow_write') {
        DB::connection()->beforeExecuting(function ($sql) use ($prefix): void {
            if (preg_match('/^update [`"]work_order_workflows[`"] /i', $sql)) {
                barrier($prefix);
            }
        });
    }
    file_put_contents($prefix.'.started', 'started');
    try {
        DB::transaction(function () use ($p, $actor, $approval, $prefix): void {
            $w = app(WorkOrderWorkflowService::class);
            switch ($p['action']) {
                case 'approve': case 'reject':
                    $w->decide($approval, $actor, $p['action'] === 'approve', 'Concurrent QA decision', $p['estimate'] ?? null);
                    break;
                case 'title_revoke': WorkOrderJobTitle::findOrFail($p['title'])->update(['is_active' => false]);
                    break;
                case 'assignment_move': WorkOrderAssignment::findOrFail($p['assignment'])->update(['g001_m001_unit_id' => $p['other_unit']]);
                    break;
                case 'supervisor_change': WorkOrderAssignment::findOrFail($p['reporter_assignment'])->update(['supervisor_user_id' => $p['replacement']]);
                    break;
                case 'assignment_revoke': WorkOrderAssignment::findOrFail($p['assignment'])->update(['is_active' => false]);
                    break;
                case 'role_revoke': User::findOrFail($p['target'])->removeRole(config('role.admin'));
                    break;
                case 'disable': User::findOrFail($p['target'])->update(['is_enabled' => false]);
                    break;
                case 'reassign': $w->reassign($approval, $actor, User::findOrFail($p['replacement']), 'Concurrent reassignment with documented reason');
                    break;
                case 'publish': $w->publish(WorkOrderWorkflow::findOrFail($p['workflow']), $actor);
                    break;
                case 'estimate': app(WorkOrderEstimateService::class)->record(Ticket::findOrFail($p['ticket']), $actor, ['execution_mode' => 'internal', 'diagnosis' => 'Revised QA estimate', 'materials' => [], 'amount' => '2000000'], 1);
                    break;
                case 'resubmit':
                    $t = Ticket::findOrFail($p['ticket']);
                    app(WorkOrderRevisionService::class)->resubmit($t, $actor, $p['approval'], 'Corrected report after QA rejection', array_merge($t->only(['title', 'location', 'reported_urgency']), ['description' => 'Corrected detailed synthetic QA report for resubmission.']));
                    break;
                default: throw new RuntimeException('Unknown action');
            }
            if (($p['barrier'] ?? '') === 'after_mutation') {
                barrier($prefix);
            }
        }, 3);
        $r = ['outcome' => 'committed'];
    } catch (ValidationException $e) {
        $r = ['outcome' => 'conflict', 'fields' => array_keys($e->errors())];
    } catch (HttpException $e) {
        $r = ['outcome' => 'denied', 'status' => $e->getStatusCode()];
    } catch (Throwable $e) {
        $r = ['outcome' => 'error', 'type' => get_class($e), 'message' => $e->getMessage()];
    }
    file_put_contents($prefix.'.done', json_encode($r));
    echo json_encode($r);
    exit($r['outcome'] === 'error' ? 1 : 0);
}
$app->make(Kernel::class)->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
$dir = sys_get_temp_dir().'/invman-authority-'.bin2hex(random_bytes(5));
mkdir($dir);
function fixture(bool $supervisor = false, bool $estimate = false): array
{
    $unit = G001M001Unit::create(['name' => 'QA race '.bin2hex(random_bytes(4))]);
    $other = G001M001Unit::create(['name' => 'QA unrelated '.bin2hex(random_bytes(4))]);
    $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
    $reporter->assignRole(config('role.sarpras'));
    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole(config('role.admin'));
    $title = WorkOrderJobTitle::create(['name' => 'QA reviewer '.bin2hex(random_bytes(4))]);
    $assignment = WorkOrderAssignment::create(['user_id' => $a->id, 'work_order_job_title_id' => $title->id, 'g001_m001_unit_id' => $unit->id]);
    foreach ([$b, $c] as $u) {
        WorkOrderAssignment::create(['user_id' => $u->id, 'work_order_job_title_id' => $title->id, 'g001_m001_unit_id' => $unit->id]);
    }
    $ra = WorkOrderAssignment::create(['user_id' => $reporter->id, 'work_order_job_title_id' => $title->id, 'g001_m001_unit_id' => $unit->id, 'supervisor_user_id' => $a->id]);
    $step = ['key' => 'review', 'label' => 'QA review', 'checkpoint' => 'start', 'resolver' => $supervisor ? 'reporter_supervisor' : 'job_title', 'job_title_id' => $supervisor ? null : $title->id];
    if ($estimate) {
        $step['requires_estimate'] = true;
    }
    $wf = WorkOrderWorkflow::create(['g001_m001_unit_id' => $unit->id, 'version' => 1, 'status' => 'published', 'created_by' => $admin->id, 'steps' => [$step]]);
    $ticket = app(TicketService::class)->open($reporter, ['ticket_category_id' => TicketCategory::where('slug', 'maintenance_hvac')->value('id'), 'title' => 'Synthetic concurrency QA ticket', 'description' => 'Disposable parallel authority verification', 'location' => 'QA isolated']);
    $approval = $ticket->workOrderApprovals()->firstOrFail();
    $eid = null;
    if ($estimate) {
        app(WorkOrderEstimateService::class)->record($ticket, $admin, ['execution_mode' => 'internal', 'diagnosis' => 'Initial estimate QA', 'materials' => [], 'amount' => '1000000'], 0);
        $eid = app(WorkOrderEstimateService::class)->current($ticket)->id;
    }

    return ['actor' => $a->id, 'b' => $b->id, 'c' => $c->id, 'admin' => $admin->id, 'reporter' => $reporter->id, 'approval' => $approval->id, 'ticket' => $ticket->id, 'assignment' => $assignment->id, 'reporter_assignment' => $ra->id, 'title' => $title->id, 'other_unit' => $other->id, 'replacement' => $b->id, 'target' => $a->id, 'workflow' => $wf->id, 'estimate' => $eid];
}
function startWorker(array $p): array
{
    global $dir;
    $prefix = $dir.'/'.bin2hex(random_bytes(4));
    $p['prefix'] = $prefix;
    file_put_contents($prefix.'.json', json_encode($p));
    $proc = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, 'worker', $prefix.'.json'], [1 => ['file', $prefix.'.stdout', 'w'], 2 => ['file', $prefix.'.stderr', 'w']], $pipes);

    return ['proc' => $proc, 'prefix' => $prefix];
}
function waitFile(string $f): void
{
    $until = microtime(true) + 20;
    while (! file_exists($f)) {
        if (microtime(true) > $until) {
            throw new RuntimeException('Worker signal timeout: '.basename($f));
        }usleep(10000);
    }
}
function stateOfContender(array $worker): string
{
    waitFile($worker['prefix'].'.started');
    $until = microtime(true) + 15;
    do {
        if (file_exists($worker['prefix'].'.done')) {
            return 'completed_before_release';
        }
        if ((int) DB::selectOne('select count(*) n from performance_schema.data_lock_waits')->n > 0) {
            return 'mysql_lock_wait';
        }
        usleep(10000);
    } while (microtime(true) < $until);
    throw new RuntimeException('No observable lock wait or completed contender');
}
function finishWorker(array $w): array
{
    waitFile($w['prefix'].'.done');
    $r = json_decode(file_get_contents($w['prefix'].'.done'), true);
    $r['exit_code'] = proc_close($w['proc']);

    return $r;
}
function pair(array $first, array $second): array
{
    $a = startWorker($first);
    waitFile($a['prefix'].'.ready');
    $b = startWorker($second);
    $state = stateOfContender($b);
    file_put_contents($a['prefix'].'.release', 'go');

    return ['first' => finishWorker($a), 'second' => finishWorker($b), 'contender_state' => $state];
}
$cases = [];
$onlyBefore = in_array('--record-before', $argv, true);
foreach (['title_revoke', 'assignment_move', 'supervisor_change', 'disable', 'assignment_revoke'] as $action) {
    foreach (['approval_first', 'revocation_first'] as $order) {
        $d = fixture($action === 'supervisor_change');
        if ($action === 'assignment_revoke') {
            User::findOrFail($d['actor'])->assignRole([config('role.admin'), config('role.fasilitas')]);
        }
        $approve = $d + ['action' => 'approve'];
        $revoke = array_merge($d, ['action' => $action, 'actor' => $d['admin']]);
        if ($order === 'approval_first') {
            $r = pair($approve + ['barrier' => 'approval_write'], $revoke);
        } else {
            $r = pair($revoke + ['barrier' => 'after_mutation'], $approve);
        }
        $pass = $r['contender_state'] === 'mysql_lock_wait' && $r['first']['outcome'] === 'committed'
            && ($order === 'approval_first' ? $r['second']['outcome'] === 'committed' : in_array($r['second']['outcome'], ['conflict', 'denied'], true));
        $events = Ticket::findOrFail($d['ticket'])->events()->where('action', 'work_order_approval_granted')->count();
        $pass = $pass && $events === ($order === 'approval_first' ? 1 : 0);
        $cases[] = ['case' => $action, 'order' => $order, 'pass' => $pass, 'approval_events' => $events] + $r;
        if ($onlyBefore) {
            break 2;
        }
    }
}
if (! $onlyBefore) {
    foreach (['reassignment_first', 'role_revocation_first'] as $order) {
        $d = fixture();
        $reassign = array_merge($d, ['action' => 'reassign', 'actor' => $d['admin'], 'replacement' => $d['b']]);
        $revoke = array_merge($d, ['action' => 'role_revoke', 'actor' => $d['admin'], 'target' => $d['admin']]);
        $r = $order === 'reassignment_first' ? pair($reassign + ['barrier' => 'approval_write'], $revoke) : pair($revoke + ['barrier' => 'after_mutation'], $reassign);
        $cases[] = ['case' => 'admin_role_revocation', 'order' => $order, 'pass' => $r['contender_state'] === 'mysql_lock_wait' && $r['first']['outcome'] === 'committed' && ($order === 'reassignment_first' ? $r['second']['outcome'] === 'committed' : $r['second']['outcome'] === 'denied')] + $r;
    }
    $d = fixture();
    $r = pair($d + ['action' => 'approve', 'barrier' => 'approval_write'], array_merge($d, ['action' => 'approve', 'actor' => $d['b']]));
    $cases[] = ['case' => 'two_approvers', 'pass' => $r['first']['outcome'] === 'committed' && $r['second']['outcome'] === 'conflict' && Ticket::find($d['ticket'])->events()->where('action', 'work_order_approval_granted')->count() === 1] + $r;
    $d = fixture();
    $r = pair($d + ['action' => 'reject', 'barrier' => 'approval_write'], array_merge($d, ['action' => 'resubmit', 'actor' => $d['reporter']]));
    $t = Ticket::find($d['ticket']);
    $cases[] = ['case' => 'reject_resubmit', 'pass' => $r['first']['outcome'] === 'committed' && $r['second']['outcome'] === 'committed' && $t->workOrderRevisions()->count() === 1 && WorkOrderApproval::find($d['approval'])->status === 'rejected' && $t->workOrderApprovals()->where('status', 'pending')->count() === 1] + $r;
    $d = fixture();
    $u = startWorker(array_merge($d, ['action' => 'resubmit', 'actor' => $d['reporter']]));
    $r = finishWorker($u);
    $cases[] = ['case' => 'resubmit_before_rejection', 'pass' => $r['outcome'] === 'conflict' && Ticket::find($d['ticket'])->workOrderRevisions()->count() === 0, 'worker' => $r];
    foreach (['estimate_first', 'approval_first'] as $order) {
        $d = fixture(false, true);
        $approve = $d + ['action' => 'approve'];
        $estimate = array_merge($d, ['action' => 'estimate', 'actor' => $d['admin']]);
        $r = $order === 'estimate_first' ? pair($estimate + ['barrier' => 'estimate_insert'], $approve) : pair($approve + ['barrier' => 'approval_write'], $estimate);
        $cases[] = ['case' => 'estimate_approval', 'order' => $order, 'pass' => $r['first']['outcome'] === 'committed' && $r['second']['outcome'] === 'conflict' && $r['contender_state'] === 'mysql_lock_wait'] + $r;
    }
    $d = fixture();
    $old = WorkOrderWorkflow::find($d['workflow']);
    $new = WorkOrderWorkflow::create(['g001_m001_unit_id' => $old->g001_m001_unit_id, 'version' => 2, 'status' => 'draft', 'steps' => $old->steps, 'created_by' => $d['admin']]);
    $r = pair(array_merge($d, ['action' => 'publish', 'workflow' => $new->id, 'actor' => $d['admin'], 'barrier' => 'workflow_write']), $d + ['action' => 'approve']);
    $cases[] = ['case' => 'publish_does_not_rewrite_old_ticket', 'pass' => $r['first']['outcome'] === 'committed' && $r['second']['outcome'] === 'committed' && WorkOrderApproval::find($d['approval'])->work_order_workflow_id === $old->id] + $r;
    $d = fixture();
    $r = pair(array_merge($d, ['action' => 'reassign', 'actor' => $d['admin'], 'replacement' => $d['b'], 'barrier' => 'approval_write']), array_merge($d, ['action' => 'reassign', 'actor' => $d['admin'], 'replacement' => $d['c']]));
    $cases[] = ['case' => 'double_reassign', 'pass' => $r['first']['outcome'] === 'committed' && $r['second']['outcome'] === 'conflict' && Ticket::find($d['ticket'])->events()->where('action', 'work_order_approver_reassigned')->count() === 1] + $r;
}
$result = ['baseline' => 'd35b49e', 'mysql' => DB::selectOne('select version() v')->v, 'ordering' => 'Revocation effective at COMMIT. Current authority rows remain locked until approval commits; approval-first must block revoker, revocation-first must make approval wait and then deny.', 'barriers' => 'Explicit signals and performance_schema.data_lock_waits; no delay-based success criteria.', 'cases' => $cases, 'pass' => ! in_array(false, array_column($cases,'pass'), true)];
echo json_encode($result,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($result['pass'] || $onlyBefore ? 0 : 1);
