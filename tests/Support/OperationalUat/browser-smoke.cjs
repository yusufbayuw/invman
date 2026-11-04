const { chromium } = require('../../../output/operational-uat-runtime/browser/node_modules/playwright');
const fs = require('node:fs/promises');
const path = require('node:path');
const out = path.resolve(__dirname, '../../../docs/operational-readiness/evidence/2026-11-04');
(async () => {
 const browser = await chromium.launch({...(process.env.UAT_BROWSER_CHANNEL === 'chromium' ? {} : {channel:'msedge'}),headless:true});
 const context = await browser.newContext({viewport:{width:1366,height:900}});
 const page = await context.newPage();
 const errors=[];
 page.on('pageerror',e=>errors.push({type:'javascript',message:e.message}));
 page.on('requestfailed',r=>errors.push({type:'network',url:r.url().split('?')[0],error:r.failure()?.errorText}));
 await page.goto('http://127.0.0.1:8876/admin/login',{waitUntil:'networkidle',timeout:45000});
 await page.screenshot({path:path.join(out,'login-desktop.png'),fullPage:true});
 const fields=await page.locator('input').evaluateAll(es=>es.map(e=>({id:e.id,type:e.type,name:e.name})));
 const text=(await page.locator('body').innerText()).slice(0,5000);
 const result={engine:browser.version(),url:page.url(),fields,text,errors};
 await fs.writeFile(path.join(out,'browser-smoke.json'),JSON.stringify(result,null,2));
 console.log(JSON.stringify(result,null,2));
 await browser.close();
})().catch(e=>{console.error(e.message);process.exitCode=1});

