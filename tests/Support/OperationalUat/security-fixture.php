<?php
use App\Models\User;use Illuminate\Support\Facades\DB;
$root=dirname(__DIR__,3);$qa=$root.'/output/operational-uat-runtime/app';require $qa.'/vendor/autoload.php';$app=require $qa.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!str_contains(DB::getDatabaseName(),'operational-uat-runtime')&&DB::getDatabaseName()!=='invman_uat_20261104')throw new RuntimeException('Isolated QA only');
$user=User::where('username','qa.security')->where('email','qa.security@invman.local')->firstOrFail();
switch($argv[1]??''){
 case'expire-session':$n=DB::table('sessions')->where('user_id',$user->id)->update(['last_activity'=>time()-(int)config('session.lifetime')*60-2]);if(!$n)throw new RuntimeException('No owned synthetic session found');break;
 case'disable':$user->update(['is_enabled'=>false]);break;
 case'restore':$user->update(['is_enabled'=>true]);$user->assignRole(config('role.sarpras'));break;
 case'revoke-role':$user->removeRole(config('role.sarpras'));break;
 default:throw new RuntimeException('Unknown synthetic mutation');
}
echo "Synthetic security fixture updated; no session values or credentials logged.\n";
