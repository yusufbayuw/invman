<?php
$root=dirname(__DIR__,3);
$qa=$root.'/output/operational-uat-runtime/app';
$_ENV['APP_BASE_PATH']=$qa;
putenv('APP_BASE_PATH='.$qa);
putenv('SEED_DEMO_USERS=false');
$_ENV['SEED_DEMO_USERS']='false';
$_SERVER['SEED_DEMO_USERS']='false';
chdir($qa);
require $qa.'/vendor/phpunit/phpunit/phpunit';

