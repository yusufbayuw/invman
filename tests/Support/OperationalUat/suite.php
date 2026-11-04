<?php
$root=dirname(__DIR__,3);
$qa=$root.'/output/operational-uat-runtime/app';
$_ENV['APP_BASE_PATH']=$qa;
putenv('APP_BASE_PATH='.$qa);
chdir($qa);
require $qa.'/vendor/phpunit/phpunit/phpunit';
