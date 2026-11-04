<?php
use App\Models\{G001M001Unit,G002M003ItemManagement,G002M007Item,G002M015ItemInstance,G003M006Room,G008M017Vehicle,G008M018Driver,VehicleAssistant,User,TicketCategory,WorkOrderWorkflow};
use App\Services\{TicketService,WorkOrderWorkflowService};
use Illuminate\Support\Facades\{DB,Hash};
$root=dirname(__DIR__,3); $qa=$root.'/output/operational-uat-runtime/app';
require $qa.'/vendor/autoload.php';
$app=require $qa.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!str_contains(DB::getDatabaseName(),'operational-uat-runtime') && DB::getDatabaseName()!=='invman_uat_20261104') throw new RuntimeException('QA database required');
$password=config('security.seed_demo_password');
$unit=G001M001Unit::where('name','SD')->firstOrFail();
foreach(['qa.employee','qa.disabled','qa.multi','qa.driver'] as $username) {
 $u=User::firstOrCreate(['username'=>$username],['name'=>'Synthetic '.$username,'email'=>$username.'@invman.local','password'=>Hash::make($password),'g001_m001_unit_id'=>$unit->id,'email_verified_at'=>now()]);
 if($username==='qa.disabled') $u->update(['is_enabled'=>false]);
 if($username==='qa.multi') $u->assignRole([config('role.sarpras'),config('role.fasilitas')]);
}
$group=G002M003ItemManagement::where('name','Fasilitas')->firstOrFail();
$room=G003M006Room::firstOrCreate(['name'=>'QA Room Ready'],['g001_m001_unit_id'=>$unit->id,'g002_m003_item_management_id'=>$group->id,'is_borrowable'=>true]);
foreach([['QA Stock 10',10],['QA Stock 5',5],['QA Stock 1',1],['QA Damaged',1],['QA Repair',1]] as [$name,$count]) {
 $item=G002M007Item::firstOrCreate(['name'=>$name],['g001_m001_unit_id'=>$unit->id,'g002_m003_item_management_id'=>$group->id,'g003_m006_room_id'=>$room->id,'is_borrowable'=>true]);
 if(!$item->item_instance()->exists()) for($i=1;$i<=$count;$i++) G002M015ItemInstance::create(['name'=>$name.' #'.$i,'code'=>'QA-'.$item->id.'-'.$i,'g002_m007_item_id'=>$item->id,'g001_m001_unit_id'=>$unit->id,'g003_m006_room_id'=>$room->id,'is_available'=>!in_array($name,['QA Damaged','QA Repair']),'is_borrowable'=>!in_array($name,['QA Damaged','QA Repair']),'status'=>match($name){'QA Damaged'=>'broken','QA Repair'=>'maintenance',default=>'available'}]);
}
$driver=G008M018Driver::firstOrCreate(['user_id'=>User::where('username','qa.driver')->value('id')],['sim_number'=>'SYNTHETIC-ONLY','sim_type'=>'A']);
VehicleAssistant::firstOrCreate(['name'=>'QA Assistant'],['is_active'=>true,'notes'=>'Synthetic']);
G008M017Vehicle::firstOrCreate(['name'=>'QA Vehicle'],['license_plate'=>'QA 0001 TEST','g001_m001_unit_id'=>$unit->id,'g002_m003_item_management_id'=>$group->id,'is_borrowable'=>true]);
$admin=User::where('username','admin')->firstOrFail();
$w=WorkOrderWorkflow::whereNull('g001_m001_unit_id')->firstOrFail();
if($w->status==='draft') app(WorkOrderWorkflowService::class)->publish($w,$admin);
foreach(['daycare','sd','smp','sma','tbu','adm'] as $slug) {
 $reporter=User::where('username','sarpras.'.$slug)->firstOrFail(); auth()->setUser($reporter);
 if(!App\Models\Ticket::where('title','QA Operational '.$slug)->exists()) app(TicketService::class)->open($reporter,['ticket_category_id'=>TicketCategory::where('slug','maintenance_hvac')->value('id'),'title'=>'QA Operational '.$slug,'description'=>'Synthetic operational evidence; no real asset or person.','location'=>'QA '.$slug,'reported_urgency'=>'normal']);
}
foreach(['IT','Perpustakaan'] as $groupName){
 $management=G002M003ItemManagement::where('name',$groupName)->firstOrFail();
 $asset=G002M007Item::firstOrCreate(['name'=>'QA Managed '.$groupName],['g001_m001_unit_id'=>$unit->id,'g002_m003_item_management_id'=>$management->id,'is_borrowable'=>true,'quantity'=>1,'available_quantity'=>1]);
 $reporter=User::where('username','sarpras.sd')->firstOrFail();auth()->setUser($reporter);
 if(!App\Models\Ticket::where('title','QA Managed '.$groupName)->exists())app(TicketService::class)->open($reporter,['ticket_category_id'=>TicketCategory::where('slug','maintenance_equipment')->value('id'),'title'=>'QA Managed '.$groupName,'description'=>'Synthetic group-owned asset maintenance report','location'=>'QA SD '.$groupName,'asset_type'=>'item','asset_id'=>$asset->id,'reported_urgency'=>'normal']);
}
$manifest=['database_driver'=>DB::getDriverName(),'database_is_isolated'=>true,'units'=>G001M001Unit::pluck('name'),'users'=>User::get()->map(fn($u)=>['id'=>$u->id,'username'=>$u->username,'enabled'=>$u->is_enabled,'roles'=>$u->roles->pluck('name'),'titles'=>$u->workOrderAssignments()->with('jobTitle')->get()->pluck('jobTitle.name')]),'items'=>G002M007Item::where('name','like','QA %')->get(['id','name','quantity','available_quantity']),'tickets'=>App\Models\Ticket::where('title','like','QA Operational %')->get(['id','number','title','status']),'workflow_published'=>true];
file_put_contents($root.'/docs/operational-readiness/evidence/2026-11-04/fixtures.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo 'Synthetic organization prepared: '.User::count().' users, '.G001M001Unit::count()." units.\n";

