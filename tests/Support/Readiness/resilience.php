<?php

use App\Models\{Ticket,User,WorkOrderApproval};
use App\Services\WorkOrderWorkflowService;
use Illuminate\Support\Facades\{DB,Storage};

define('INVMAN_AUTHORITY_FIXTURES_ONLY', true);
require __DIR__.'/authority-concurrency.php';
$rt = getenv('QA_RUNTIME') ?: sys_get_temp_dir().'/invman-fault-'.bin2hex(random_bytes(5));
if (!is_dir($rt)) mkdir($rt,0700,true);
if (($argv[1]??'')==='fault-worker') {
    $p=json_decode(file_get_contents($argv[2]),true);
    DB::connection()->beforeExecuting(function($sql)use($p){if(preg_match('/^update [`"]work_order_approvals[`"] /i',$sql)){
        file_put_contents($p['prefix'].'.connection',(string)DB::connection()->getPdo()->query('SELECT CONNECTION_ID()')->fetchColumn());
        barrier($p['prefix']);
    }});
    try{app(WorkOrderWorkflowService::class)->decide(WorkOrderApproval::findOrFail($p['approval']),User::findOrFail($p['actor']),true,'Interrupted synthetic approval');$r=['unexpected_commit'=>true];}
    catch(Throwable $e){$r=['outcome'=>'interrupted','type'=>get_class($e),'sqlstate'=>$e instanceof Illuminate\Database\QueryException?$e->errorInfo[0]:null];}
    echo json_encode($r);exit;
}
if (($argv[1]??'')==='storage-worker') {
    config(['filesystems.disks.quota'=>['driver'=>'local','root'=>$rt.'/quota','throw'=>true]]);
    try{Storage::disk('quota')->put('bounded-write.bin',str_repeat('q',8192));$r=['write_failed'=>false];}
    catch(Throwable $e){$r=['write_failed'=>true,'type'=>get_class($e)];}
    echo json_encode($r);exit;
}
$d=fixture();$prefix=$rt.'/disconnect-'.bin2hex(random_bytes(4));$d['prefix']=$prefix;file_put_contents($prefix.'.json',json_encode($d));
$proc=proc_open([PHP_BINARY,'-c',php_ini_loaded_file(),__FILE__,'fault-worker',$prefix.'.json'],[1=>['file',$prefix.'.out','w'],2=>['file',$prefix.'.err','w']],$pipes);
waitFile($prefix.'.ready');$connection=(int)file_get_contents($prefix.'.connection');if($connection<=0)throw new RuntimeException('Missing owned child connection');
DB::unprepared('KILL CONNECTION '.$connection);file_put_contents($prefix.'.release','go');$exit=proc_close($proc);$interrupted=json_decode(file_get_contents($prefix.'.out'),true);
$ticket=Ticket::findOrFail($d['ticket']);$pending=WorkOrderApproval::findOrFail($d['approval'])->status==='pending'&&$ticket->events()->where('action','work_order_approval_granted')->count()===0;
app(WorkOrderWorkflowService::class)->decide(WorkOrderApproval::findOrFail($d['approval']),User::findOrFail($d['actor']),true,'Explicit retry after recovered connection');
try{app(WorkOrderWorkflowService::class)->decide(WorkOrderApproval::findOrFail($d['approval']),User::findOrFail($d['actor']),true,'Duplicate retry');$duplicate=false;}catch(Illuminate\Validation\ValidationException $e){$duplicate=true;}
$one=$ticket->events()->where('action','work_order_approval_granted')->count()===1;
$python='import os,resource,signal,sys; resource.setrlimit(resource.RLIMIT_FSIZE,(4096,4096)); signal.signal(signal.SIGXFSZ,signal.SIG_IGN); os.execv(sys.argv[1],sys.argv[1:])';
$proc=proc_open(['python3','-c',$python,PHP_BINARY,'-c',php_ini_loaded_file(),__FILE__,'storage-worker'],[1=>['pipe','w'],2=>['file',$rt.'/storage-limit.err','w']],$pipes);$quota=json_decode(stream_get_contents($pipes[1]),true);fclose($pipes[1]);$quotaExit=proc_close($proc);
$result=['scope'=>'isolated QA fault injection; production outage/failover is not exercised','connection_killed'=>'only the owned disposable worker connection','worker_exit'=>$exit,'worker'=>$interrupted,'interrupted_approval_rolled_back'=>$pending,'retry_produces_one_event'=>$one,'duplicate_retry_rejected'=>$duplicate,'storage_process_file_limit_bytes'=>4096,'storage_write_bytes'=>8192,'storage'=>$quota,'storage_exit'=>$quotaExit,'pass'=>$pending&&$one&&$duplicate&&($interrupted['outcome']??'')==='interrupted'&&($quota['write_failed']??false),'limits'=>'Per-process file size failure tests the private filesystem write primitive, not full-disk production upload recovery. No production database was interrupted.'];
file_put_contents(dirname(__DIR__,3).'/docs/audit/evidence/2026-11-03-final/resilience-qa.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;exit($result['pass']?0:1);
