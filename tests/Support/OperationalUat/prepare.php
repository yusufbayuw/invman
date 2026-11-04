<?php
$root = dirname(__DIR__, 3);
$qa = $root.'/output/operational-uat-runtime/app';
require $root.'/vendor/autoload.php';
if (!is_file($qa.'/artisan') || is_file($qa.'/.env')) throw new RuntimeException('Fresh QA clone required');
$env = file_get_contents($root.'/.env.example');
$settings = [
 'APP_NAME'=>'InvMan Synthetic QA','APP_ENV'=>'local','APP_KEY'=>'base64:'.base64_encode(random_bytes(32)),
 'APP_DEBUG'=>'false','APP_URL'=>'http://127.0.0.1:8876','DB_CONNECTION'=>'sqlite',
 'DB_DATABASE'=>str_replace('\\','/',$qa.'/database/uat.sqlite'),
 'SESSION_DRIVER'=>'database','SESSION_COOKIE'=>'invman_synthetic_uat','SESSION_SECURE_COOKIE'=>'false','SESSION_DOMAIN'=>'null',
 'CACHE_STORE'=>'database','QUEUE_CONNECTION'=>'database','MAIL_MAILER'=>'log','BROADCAST_CONNECTION'=>'log',
 'SEED_DEMO_USERS'=>'true','SEEDED_USER_PASSWORD'=>'Qa!'.bin2hex(random_bytes(16)),'BCRYPT_ROUNDS'=>'4',
 'TURNSTILE_SITE_KEY'=>'1x00000000000000000000AA','TURNSTILE_SECRET_KEY'=>'1x0000000000000000000000000000000AA',
];
foreach($settings as $key=>$value){ $env=preg_replace('/^'.preg_quote($key,'/').'=.*/m','',$env); $env.="\n{$key}=\"{$value}\"\n"; }
file_put_contents($qa.'/.env',$env);
touch($qa.'/database/uat.sqlite');
file_put_contents($qa.'/config/base_urls.php',"<?php return ['login_turnstile'=>true];\n");
foreach(['framework/cache/data','framework/sessions','framework/views','logs','app/private','app/public'] as $dir) if(!is_dir($qa.'/storage/'.$dir)) mkdir($qa.'/storage/'.$dir,0700,true);
echo "Disposable app prepared; secrets excluded from evidence.\n";
