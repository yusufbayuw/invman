const fs=require('node:fs/promises'),path=require('node:path');
const file=path.resolve(__dirname,'../../../output/operational-uat-runtime/login-attempt-time.json'),lock=file+'.lock';
module.exports=async function paceLogin(){let handle;for(;;){try{handle=await fs.open(lock,'wx');break}catch(e){if(e.code!=='EEXIST')throw e;await new Promise(r=>setTimeout(r,200))}}
 try{let last=0;try{last=JSON.parse(await fs.readFile(file,'utf8')).at}catch{}const wait=Math.max(0,15000-(Date.now()-last));if(wait)await new Promise(r=>setTimeout(r,wait));await fs.writeFile(file,JSON.stringify({at:Date.now(),minimumIntervalMs:15000,reason:'Respect the actual application login limit of five attempts per minute; no limiter/cache bypass'}));}finally{await handle.close();await fs.unlink(lock);}
};
