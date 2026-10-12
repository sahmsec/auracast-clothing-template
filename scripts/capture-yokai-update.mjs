import {chromium} from 'playwright';
import fs from 'node:fs/promises';
const directory='test-results/yokai-update';
await fs.mkdir(directory,{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE||'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
const errors=[];page.on('pageerror',e=>errors.push(e.message));
for (const [name,path] of [['home',''],['category','product-category/graphic-tees/'],['policy','shipping-policy/']]) {
 const response=await page.goto('http://127.0.0.1:8088/'+path,{waitUntil:'networkidle',timeout:60000});
 await page.screenshot({path:`${directory}/${name}-desktop-top.png`});
 await page.screenshot({path:`${directory}/${name}-desktop.png`,fullPage:true});
 if(name==='home') {await page.locator('.yokai-benefits').screenshot({path:`${directory}/benefits-desktop.png`});await page.locator('footer').screenshot({path:`${directory}/footer-desktop.png`});}
 console.log(name,response.status(),await page.evaluate(()=>({viewport:innerWidth,width:document.documentElement.scrollWidth,header:document.querySelector('.site-header').getBoundingClientRect().height,blogLinks:[...document.querySelectorAll('nav a')].filter(a=>/blog|journal/i.test(a.textContent)).length,columns:document.querySelector('main .product-grid,main ul.products')?getComputedStyle(document.querySelector('main .product-grid,main ul.products')).gridTemplateColumns:null})));
 await page.setViewportSize({width:390,height:844});
 await page.evaluate(()=>scrollTo(0,0));
 await page.waitForTimeout(200);
 await page.screenshot({path:`${directory}/${name}-mobile-top.png`});
 await page.screenshot({path:`${directory}/${name}-mobile.png`,fullPage:true});
 if(name==='home'){await page.locator('[aria-labelledby="popular-title"]').scrollIntoViewIfNeeded();await page.screenshot({path:`${directory}/home-rail-mobile.png`});}
 await page.setViewportSize({width:1440,height:1000});
}
console.log('JavaScript errors:',errors);
await browser.close();
