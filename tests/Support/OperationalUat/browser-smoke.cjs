const { chromium } = require('../../../output/operational-uat-runtime/browser/node_modules/playwright');
const fs = require('node:fs/promises');
const path = require('node:path');
const out = path.resolve(__dirname, '../../../docs/operational-readiness/evidence/2026-11-04');
const qa = path.resolve(__dirname,'../../../output/operational-uat-runtime/app');
let browser;
(async()=>{
 browser=await chromium.launch({...(process.env.UAT_BROWSER_CHANNEL==='chromium'?{}:{channel:'msedge'}),headless:true,timeout:45000});
 const context=await browser.newContext({viewport:{width:1366,height:900}});
 const page=await context.newPage(); const errors=[];
 page.on('pageerror',e=>errors.push({type:'javascript',message:e.message}));
 page.on('requestfailed',r=>errors.push({type:'network',url:new URL(r.url()).origin+new URL(r.url()).pathname,error:r.failure()?.errorText}));
 const result={engine:browser.version(),scope:'real browser with official provider test keys; no auth/session injection',pass:false};
 try{
  await page.goto('http://127.0.0.1:8876/admin/login',{waitUntil:'domcontentloaded',timeout:45000});
  await page.waitForLoadState('networkidle',{timeout:10000}).catch(()=>{});
  await page.screenshot({path:path.join(out,'login-desktop.png'),fullPage:true,timeout:15000});
  result.fields=await page.locator('input').evaluateAll(es=>es.map(e=>({id:e.id,type:e.type,name:e.name})));
  result.loginText=(await page.locator('body').innerText()).slice(0,4000);
  const env=await fs.readFile(path.join(qa,'.env'),'utf8');
  const password=env.match(/^SEEDED_USER_PASSWORD="([^"]+)"/m)?.[1]; if(!password)throw Error('Missing synthetic password');
  await page.getByLabel('Email/Username',{exact:true}).fill('admin');
  await page.locator('input[type=password]').fill(password);
  await page.waitForFunction(()=>!!document.querySelector('input[name="cf-turnstile-response"]')?.value,{},{timeout:30000});
  await page.getByRole('button',{name:/Masuk|Sign in/i}).click();
  await page.waitForURL(u=>u.pathname!=='/admin/login',{timeout:45000});
  result.authenticatedUrl=page.url();
  result.dashboard=(await page.locator('body').innerText()).slice(0,7000);
  result.links=await page.locator('a[href]').evaluateAll(es=>es.map(e=>({text:e.innerText.trim(),href:e.getAttribute('href')})));
  await page.screenshot({path:path.join(out,'admin-authenticated.png'),fullPage:true});
  result.pass=new URL(page.url()).pathname==='/admin';
 }catch(e){result.error=e.message;await page.screenshot({path:path.join(out,'browser-error.png'),fullPage:true,timeout:10000}).catch(()=>{});result.errorText=(await page.locator('body').innerText().catch(()=>'' )).slice(0,5000);}
 result.errors=errors;await fs.writeFile(path.join(out,'browser-smoke.json'),JSON.stringify(result,null,2));
 console.log(JSON.stringify(result,null,2));await browser.close();if(!result.pass)process.exitCode=1;
})().catch(async e=>{console.error(e.message);if(browser)await browser.close().catch(()=>{});process.exit(1)});
