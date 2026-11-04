<?php
use App\Enums\ReservationStatus;
use App\Models\{G001M001Unit,G002M007Item,G005M009ItemReservation,User};
use App\Services\{LoanRequestService,LoanCheckoutService,LoanAvailabilityService};
use Illuminate\Support\Facades\DB;

define('INVMAN_PERFORMANCE_HELPERS_ONLY',true);
require __DIR__.'/performance.php';
$unit=G001M001Unit::create(['name'=>'QA Parallel Return '.bin2hex(random_bytes(4))]);$reporter=User::factory()->create(['g001_m001_unit_id'=>$unit->id]);$reporter->assignRole(config('role.sarpras'));$manager=User::factory()->create();$manager->assignRole(config('role.fasilitas'));
$item=G002M007Item::create(['name'=>'QA Parallel Return Stock','g001_m001_unit_id'=>$unit->id,'quantity'=>4,'is_borrowable'=>true]);$requests=[];$service=app(LoanRequestService::class);$checkout=app(LoanCheckoutService::class);
for($i=0;$i<4;$i++){
 auth()->setUser($reporter);$activity=$service->submit($reporter,['name'=>'QA concurrent return '.$i,'description'=>'Disposable two-party return contention','start_time'=>now()->addDays(3),'end_time'=>now()->addDays(3)->addHour(),'needs'=>[['type'=>'item','item_id'=>$item->id,'quantity'=>1]]]);$reservation=$activity->item_reservation()->firstOrFail();
 auth()->setUser($manager);$service->processReservation('item',$reservation->id,ReservationStatus::Approved);$reservation->refresh();$checkout->begin('item',$reservation->id,['instances'=>$checkout->instanceOptions($reservation),'receipt_notes'=>'Synthetic initial handover']);
 auth()->setUser($reporter);$checkout->confirm('item',$reservation->id);$requests[]=['action'=>'return_request','user'=>$reporter->id,'reservation'=>$reservation->id];
}
$returnRequests=batch($requests,4);$confirmations=batch(array_map(fn($p)=>['action'=>'return_confirm','user'=>$manager->id,'reservation'=>$p['reservation']],$requests),4);
$ids=array_column($requests,'reservation');$rows=G005M009ItemReservation::whereIn('id',$ids)->with(['outboundReceipt','returnReceipt'])->get();$integrity=[];
foreach($rows as$r){$events=DB::table('loan_reservation_status_histories')->where('reservation_type','item')->where('reservation_id',$r->id)->where('to_status','returned')->count();$integrity[]=['id'=>$r->id,'status'=>$r->status,'returned_events'=>$events,'two_party_outbound'=>$r->outboundReceipt->manager_confirmed_by!==$r->outboundReceipt->borrower_confirmed_by,'two_party_return'=>$r->returnReceipt->manager_confirmed_by!==$r->returnReceipt->borrower_confirmed_by,'pass'=>$r->status==='returned'&&$events===1&&$r->returnReceipt->completed_at!==null&&$r->returnReceipt->manager_confirmed_by!==$r->returnReceipt->borrower_confirmed_by];}
$item->refresh();$errors=($returnRequests['outcomes']['error']??0)+($confirmations['outcomes']['error']??0);$available=app(LoanAvailabilityService::class)->availableItemQuantity($item->id,now()->addDays(3),now()->addDays(3)->addHour());
$result=['scope'=>'bounded real PHP/MySQL parallel two-party item returns; synthetic isolated data','request_returns'=>$returnRequests,'confirm_returns'=>$confirmations,'integrity'=>$integrity,'stock'=>['quantity'=>(int)$item->quantity,'available_quantity'=>(int)$item->available_quantity,'service_available'=>$available,'instances_ready'=>$item->item_instance()->where('is_available',true)->count()],'pass'=>$errors===0&&count($integrity)===4&&!in_array(false,array_column($integrity,'pass'),true)&&$available===4&&(int)$item->available_quantity===4];
file_put_contents(dirname(__DIR__,3).'/docs/audit/evidence/2026-11-03-final/parallel-returns-qa.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;exit($result['pass']?0:1);
