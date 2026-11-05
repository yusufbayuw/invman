<?php
use Illuminate\Support\Facades\{Artisan,DB,Mail,Storage};
use App\Models\{User,G002M007Item};
use App\Services\LoanRequestService;
use Symfony\Component\Process\Process;
$root=dirname(__DIR__,3); $qa=$root.'/output/operational-uat-runtime/app';
require $qa.'/vendor/autoload.php'; $app=require $qa.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(getenv('CI')!=='true' || DB::getDriverName()!=='mysql' || DB::getDatabaseName()!=='invman_uat_20261104') throw new RuntimeException('Disposable CI MySQL only');
$out=$root.'/docs/operational-readiness/evidence/2026-11-04/infra-recovery.json';
$r=['scope'=>'isolated synthetic QA; not production rehearsal','php'=>PHP_VERSION,'mysql'=>DB::selectOne('select version() as v')->v,'stage'=>'queue','pass'=>false];
try {
 $marker='UAT-QUEUE-'.bin2hex(random_bytes(8)); $failedBefore=DB::table('failed_jobs')->count();
 Mail::to('synthetic@example.invalid')->queue((new Illuminate\Mail\Mailable)->html($marker)->subject('Synthetic queue evidence'));
 $r['queue_jobs_before_worker']=DB::table('jobs')->count();
 $p=new Process([PHP_BINARY,$qa.'/artisan','queue:work','--stop-when-empty','--tries=1','--timeout=30'],$qa); $p->setTimeout(90); $p->mustRun();
 $r['queue_jobs_after_worker']=DB::table('jobs')->count();
 $r['failed_jobs_added']=DB::table('failed_jobs')->count()-$failedBefore;
 $r['queue_mail_log_delivered']=str_contains(file_get_contents($qa.'/storage/logs/laravel.log'),$marker);
 $r['queue_pass']=$r['queue_jobs_before_worker']>0 && $r['queue_jobs_after_worker']===0 && $r['failed_jobs_added']===0 && $r['queue_mail_log_delivered'];
 $r['stage']='scheduler';
 $u=User::where('username','sarpras.sd')->firstOrFail(); auth()->setUser($u);
 $item=G002M007Item::where('name','QA Stock 10')->firstOrFail();
 $activity=app(LoanRequestService::class)->submit($u,['name'=>'QA expiring scheduler hold','description'=>'Synthetic scheduler sentinel','start_time'=>now()->addDays(10),'end_time'=>now()->addDays(10)->addHour(),'needs'=>[['type'=>'item','item_id'=>$item->id,'quantity'=>1]]]);
 $activity->forceFill(['hold_expires_at'=>now()->subMinute()])->saveQuietly();
 $r['scheduler_exit']=Artisan::call('schedule:run');
 $r['expired_activity_status']=$activity->fresh()->status;
 $r['scheduler_pass']=$r['scheduler_exit']===0 && $r['expired_activity_status']==='expired';
 $r['stage']='backup';
 Storage::disk('local')->put('uat-recovery/private-sentinel.txt','Synthetic private recovery sentinel');
 $config=DB::connection()->getConfig();
 $client=$root.'/output/operational-uat-runtime/mysql-client.cnf';
 file_put_contents($client,"[client]\nhost=127.0.0.1\nport=3306\nuser=".$config['username']."\npassword=".$config['password']."\n"); chmod($client,0600);
 $snapshot=function($db){
  $tables=DB::select('SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=\'BASE TABLE\' ORDER BY TABLE_NAME',[$db]); $data=[];
  foreach($tables as $t){$rows=DB::select('SELECT * FROM `'.$db.'`.`'.$t->t.'`'); $serialized=array_map(fn($row)=>json_encode($row,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$rows); sort($serialized,SORT_STRING); $data[$t->t]=['rows'=>count($rows),'sha256'=>hash('sha256',implode("\n",$serialized))];}
  return $data;
 };
 $before=$snapshot($config['database']);
 $dump=$root.'/output/operational-uat-runtime/qa-backup.sql'; $stream=fopen($dump,'wb');
 $p=new Process(['mysqldump','--defaults-extra-file='.$client,'--single-transaction','--routines','--events','--triggers','--hex-blob','--no-tablespaces','--set-gtid-purged=OFF',$config['database']]);
 $p->setTimeout(180);$p->mustRun(function($type,$buffer)use($stream){if($type===Process::OUT)fwrite($stream,$buffer);}); fclose($stream);
 $restore='invman_uat_restore_20261104';
 if(DB::select('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[$restore])) throw new RuntimeException('Restore target already exists; refusing overwrite');
 DB::statement('CREATE DATABASE `'.$restore.'`');
 $p=new Process(['mysql','--defaults-extra-file='.$client,$restore]); $p->setInput(fopen($dump,'rb'));$p->setTimeout(180);$p->mustRun();
 $after=$snapshot($restore);
 $r['mysql_dump_bytes']=filesize($dump);$r['mysql_dump_sha256']=hash_file('sha256',$dump);$r['tables']=$before;$r['restored_tables']=$after;$r['database_restored_exactly']=$before===$after;
 $r['stage']='referential integrity'; $r['foreign_key_orphans']=0;$r['foreign_keys_checked']=0;
 $keys=DB::select('SELECT TABLE_NAME AS t,COLUMN_NAME AS c,REFERENCED_TABLE_NAME AS rt,REFERENCED_COLUMN_NAME AS rc FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$restore]);
 foreach($keys as $key){$sql='SELECT COUNT(*) AS n FROM `'.$restore.'`.`'.$key->t.'` c LEFT JOIN `'.$restore.'`.`'.$key->rt.'` p ON c.`'.$key->c.'`=p.`'.$key->rc.'` WHERE c.`'.$key->c.'` IS NOT NULL AND p.`'.$key->rc.'` IS NULL';$r['foreign_key_orphans']+=(int)DB::selectOne($sql)->n;$r['foreign_keys_checked']++;}
 $r['stage']='private file restore';$disk=Storage::disk('local');$files=$disk->allFiles();$zip=new ZipArchive;$zipPath=$root.'/output/operational-uat-runtime/private-backup.zip';$zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE);
 foreach($files as $file)$zip->addFile($disk->path($file),$file);$zip->close();
 $dest=$root.'/output/operational-uat-runtime/restored-private'; if(is_dir($dest))throw new RuntimeException('Refusing existing restore directory');mkdir($dest,0700,true);$zip->open($zipPath);$zip->extractTo($dest);$zip->close();
 $r['private_files']=[];foreach($files as $file)$r['private_files'][$file]=hash_file('sha256',$disk->path($file))===hash_file('sha256',$dest.'/'.$file);
 $r['private_restore_exact']=count($files)>0&&!in_array(false,$r['private_files'],true);
 $r['pass']=$r['queue_pass']&&$r['scheduler_pass']&&$r['database_restored_exactly']&&$r['foreign_key_orphans']===0&&$r['private_restore_exact'];$r['stage']='complete';
} catch(Throwable $e){$r['error_class']=get_class($e);file_put_contents($root.'/output/operational-uat-runtime/infra-error.txt',(string)$e);}
file_put_contents($out,json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));echo json_encode(['stage'=>$r['stage'],'pass'=>$r['pass']]).PHP_EOL;exit($r['pass']?0:1);
