<?php

use App\Models\{G001M001Unit,G002M007Item,G004M008Activity,User};
use App\Services\LoanRequestService;
use Illuminate\Support\Facades\{DB,Notification};
use Illuminate\Validation\ValidationException;

define('INVMAN_AUTHORITY_FIXTURES_ONLY',true);
require __DIR__.'/authority-concurrency.php';
if (($argv[1]??'')==='snapshot-worker') {
 $p=json_decode(file_get_contents($argv[2]),true);$u=User::findOrFail($p['user']);
 try{DB::transaction(function()use($p,$u){DB::table('g004_m008_activities')->count();barrier($p['prefix']);app(LoanRequestService::class)->submitDraft($u,G004M008Activity::findOrFail($p['draft']));});$r=['outcome'=>'committed'];}
 catch(ValidationException $e){$r=['outcome'=>'conflict'];}
 echo json_encode($r);exit;
}
$unit=G001M001Unit::create(['name'=>'QA Snapshot '.bin2hex(random_bytes(4))]);$u=User::factory()->create(['g001_m001_unit_id'=>$unit->id]);$u->assignRole(config('role.sarpras'));auth()->setUser($u);
$item=G002M007Item::create(['name'=>'QA single stock','g001_m001_unit_id'=>$unit->id,'quantity'=>1,'is_borrowable'=>true]);
$data=['name'=>'QA stale read booking','description'=>'Synthetic stale outer transaction snapshot','start_time'=>now()->addDays(5),'end_time'=>now()->addDays(5)->addHour(),'needs'=>[['type'=>'item','item_id'=>$item->id,'quantity'=>1]]];
$draft=app(LoanRequestService::class)->saveDraft($u,$data);$prefix=$dir.'/snapshot';file_put_contents($prefix.'.json',json_encode(['user'=>$u->id,'draft'=>$draft->id,'prefix'=>$prefix]));
$proc=proc_open([PHP_BINARY,'-c',php_ini_loaded_file(),__FILE__,'snapshot-worker',$prefix.'.json'],[1=>['file',$prefix.'.out','w'],2=>['file',$prefix.'.err','w']],$pipes);
waitFile($prefix.'.ready');$first=app(LoanRequestService::class)->submit($u,$data);file_put_contents($prefix.'.release','go');$code=proc_close($proc);$worker=json_decode(file_get_contents($prefix.'.out'),true);
$count=DB::table('g005_m009_item_reservations')->where('g002_m007_item_id',$item->id)->where('status','submitted')->sum('quantity');
$r=['interleaving'=>'B establishes REPEATABLE READ snapshot; A books and commits all stock; B submits its preexisting draft in the older transaction','worker'=>$worker,'worker_exit'=>$code,'stock'=>1,'submitted_quantity'=>(int)$count,'pass'=>$code===0&&($worker['outcome']??'')==='conflict'&&(int)$count===1];echo json_encode($r,JSON_PRETTY_PRINT).PHP_EOL;exit($r['pass']?0:1);
