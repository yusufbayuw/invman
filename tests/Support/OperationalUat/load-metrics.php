<?php
use Illuminate\Support\Facades\DB;
$root=dirname(__DIR__,3);$qa=$root.'/output/operational-uat-runtime/app';
require $qa.'/vendor/autoload.php';$app=require $qa.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(getenv('CI')!=='true'||DB::getDriverName()!=='mysql'||DB::getDatabaseName()!=='invman_uat_20261104')throw new RuntimeException('Isolated CI only');
$phase=$argv[1]??'';if(!in_array($phase,['before','after'],true))throw new RuntimeException('Expected before or after');
if($phase==='before'){
    DB::statement('SET GLOBAL slow_query_log = ON');
    DB::statement('SET GLOBAL long_query_time = 0.1');
}
DB::statement('SET SESSION long_query_time = 0.1');
$r=['at'=>gmdate('c'),'scope'=>'QA MySQL global counters around read load; shared only within this disposable job','long_query_time_seconds'=>(float)DB::selectOne('select @@GLOBAL.long_query_time as t')->t,'counters'=>[],'queue_jobs'=>DB::table('jobs')->count(),'failed_jobs'=>DB::table('failed_jobs')->count()];
foreach(DB::select("SHOW GLOBAL STATUS WHERE Variable_name IN ('Slow_queries','Innodb_row_lock_waits','Threads_running','Threads_connected')")as$row)$r['counters'][$row->Variable_name]=(int)$row->Value;
$deadlocks=DB::selectOne("SELECT `COUNT` AS total, STATUS AS status FROM information_schema.INNODB_METRICS WHERE NAME='lock_deadlocks'");
$r['counters']['Innodb_deadlocks']=$deadlocks?(int)$deadlocks->total:null;
$r['deadlock_counter_status']=$deadlocks?->status;
file_put_contents($root.'/docs/operational-readiness/evidence/2026-11-04/load-database-'.$phase.'.json',json_encode($r,JSON_PRETTY_PRINT));
echo "QA database load counters saved; 100ms slow-query threshold and explicit InnoDB deadlock metric.\n";
