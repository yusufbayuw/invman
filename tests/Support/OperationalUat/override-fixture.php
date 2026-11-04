<?php
use App\Models\{User,Ticket,TicketCategory,G003M006Room,G002M003ItemManagement,OperationalOverride};
use App\Services\TicketService;
use Illuminate\Support\Facades\DB;
$root=dirname(__DIR__,3);$qa=$root.'/output/operational-uat-runtime/app';require $qa.'/vendor/autoload.php';$app=require $qa.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!str_contains(DB::getDatabaseName(),'operational-uat-runtime')&&DB::getDatabaseName()!=='invman_uat_20261104')throw new RuntimeException('Isolated QA required');
$tag=getenv('UAT_HIERARCHY_TAG')?:'default';$title='QA Browser audited hierarchy '.$tag;
$out=$root.'/docs/operational-readiness/evidence/2026-11-04/override-fixture.json';
if(($argv[1]??'')==='prepare'){
 if(Ticket::where('title',$title)->exists())throw new RuntimeException('Fresh fixture required');
 $reporter=User::where('username','sarpras.sma')->firstOrFail();$room=G003M006Room::create(['name'=>'QA Override Safety Room','g001_m001_unit_id'=>$reporter->g001_m001_unit_id,'g002_m003_item_management_id'=>G002M003ItemManagement::where('name','Fasilitas')->value('id'),'is_borrowable'=>true]);
 auth()->setUser($reporter);$ticket=app(TicketService::class)->open($reporter,['ticket_category_id'=>TicketCategory::where('slug','maintenance_building')->value('id'),'title'=>$title,'description'=>'Synthetic hierarchy exception; preserve real actor and safety hold.','location'=>'QA Safety Room','asset_type'=>'room','asset_id'=>$room->id,'reported_urgency'=>'normal']);
 file_put_contents($out,json_encode(['ticket'=>$ticket->id,'room'=>$room->id,'reporter'=>$reporter->id,'admin'=>User::where('username','admin')->value('id'),'technician'=>User::where('username','wo.demo.teknisi.petugas')->value('id')],JSON_PRETTY_PRINT));
}elseif(($argv[1]??'')==='verify'){
 $r=json_decode(file_get_contents($out),true);$t=Ticket::findOrFail($r['ticket']);$audit=OperationalOverride::where('entity_type','ticket')->where('entity_id',$t->id)->get();$room=G003M006Room::findOrFail($r['room']);
 $r+=['status'=>$t->status,'reporter_unchanged'=>$t->reporter_id===$r['reporter'],'assignee_unchanged'=>$t->assigned_to===$r['technician'],'audit_count'=>$audit->count(),'actors'=>$audit->pluck('actor_id')->unique()->values()->all(),'actions'=>$audit->pluck('action')->all(),'room_borrowable'=>$room->is_borrowable,'room_status'=>$room->status,'audit'=>$audit->map(fn($a)=>$a->only(['actor_id','subject_user_id','action','from_status','to_status','reason','details']))->all()];
 $r['safety_hold_active']=app(App\Services\TicketAssetSafetyService::class)->isBlocked('room',$room->id);$r['not_in_loan_options']=!array_key_exists($room->id,app(App\Services\LoanAvailabilityService::class)->roomOptions(now()->addDay(),now()->addDay()->addHour()));$r['asset_released_at']=$t->maintenance->asset_released_at;
 $r['pass']=$r['status']==='closed'&&$r['reporter_unchanged']&&$r['assignee_unchanged']&&$r['actors']===[$r['admin']]&&$r['audit_count']>=8&&$r['safety_hold_active']&&$r['not_in_loan_options']&&$r['asset_released_at']===null;file_put_contents($out,json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));if(!$r['pass'])throw new RuntimeException('Override actor/history/safety invariant failed');
}else throw new RuntimeException('prepare or verify required');
