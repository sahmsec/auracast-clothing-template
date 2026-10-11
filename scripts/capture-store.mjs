import {chromium} from 'playwright';
import fs from 'node:fs/promises';
await fs.mkdir('test-results',{recursive:true});
const browser=await chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE?{executablePath:process.env.BROWSER_EXECUTABLE}:{})});
const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
const errors=[];page.on('pageerror',e=>errors.push(e.message));
for(const [name,url] of [['home',''],['shop','shop/'],['product','product/essential-tee-black/'],['contact','contact-us/']]){
 const response=await page.goto('http://127.0.0.1:8088/'+url,{waitUntil:'networkidle'});
 await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=600){scrollTo(0,y);await new Promise(r=>setTimeout(r,80))}scrollTo(0,0)});
 await page.screenshot({path:`test-results/${name}-desktop.png`,fullPage:true});
 if(name==='home'){await page.screenshot({path:'test-results/home-top.png'});await page.setViewportSize({width:1200,height:900});await page.screenshot({path:'auracast/screenshot.png'});await page.setViewportSize({width:1440,height:1000});}
 console.log(name,response.status(),await page.title(),await page.evaluate(()=>({width:innerWidth,scrollWidth:document.documentElement.scrollWidth})))
}
await page.setViewportSize({width:390,height:844});await page.goto('http://127.0.0.1:8088/',{waitUntil:'networkidle'});await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=500){scrollTo(0,y);await new Promise(r=>setTimeout(r,100))}scrollTo(0,0)});await page.waitForTimeout(500);await page.screenshot({path:'test-results/home-mobile.png',fullPage:true});
console.log('Errors',errors);
await page.setViewportSize({width:512,height:512});await page.goto('http://127.0.0.1:8088/wp-content/themes/auracast/assets/images/auracast-icon.svg');await page.screenshot({path:'auracast/assets/images/auracast-icon.png'});
await browser.close();
