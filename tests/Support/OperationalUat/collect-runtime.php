<?php
$root=dirname(__DIR__,3);$runtime=$root.'/output/operational-uat-runtime';$env=is_file($runtime.'/app/.env')?file_get_contents($runtime.'/app/.env'):'';
$secrets=[];preg_match_all('/^(?:[A-Z_]*(?:PASSWORD|SECRET|TOKEN|KEY))="?([^\r\n"]+)"?$/m',$env,$matches);foreach($matches[1]as$value)if(strlen($value)>8)$secrets[]=$value;
foreach(['http.log','app/storage/logs/laravel.log']as$file){if(!is_file($runtime.'/'.$file))continue;$text=file_get_contents($runtime.'/'.$file);$text=str_replace($secrets,'[REDACTED]',$text);$text=preg_replace('/([?&](?:signature|token|key|secret)=)[^\s&"]+/i','$1[REDACTED]',$text);file_put_contents($root.'/docs/operational-readiness/evidence/2026-11-04/'.(str_contains($file,'laravel')?'application-sanitized.log':'http-server-sanitized.log'),$text);}
echo "Sanitized QA server logs collected; runtime secrets and database dumps excluded.\n";
