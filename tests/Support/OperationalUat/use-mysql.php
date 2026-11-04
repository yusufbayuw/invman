<?php
$qa=dirname(__DIR__,3).'/output/operational-uat-runtime/app';
if(!is_file($qa.'/.env') || getenv('CI')!=='true') throw new RuntimeException('Disposable CI only');
$env=file_get_contents($qa.'/.env');
foreach(['DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3306','DB_DATABASE'=>'invman_uat_20261104','DB_USERNAME'=>'root','DB_PASSWORD'=>'root'] as $k=>$v) $env=preg_replace('/^'.preg_quote($k,'/').'=.*/m','',$env)."\n{$k}=\"{$v}\"\n";
file_put_contents($qa.'/.env',$env);
echo "QA configured for isolated MySQL service.\n";
