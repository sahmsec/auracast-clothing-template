import {chromium} from 'playwright';
import fs from 'node:fs/promises';
const directory='reference/yokai-refresh';
await fs.mkdir(directory,{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE||'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
for(const [name,url] of [['yokai','https://www.yokaiclothingbd.com/'],['auracast','http://127.0.0.1:8088/']]) {
  await page.goto(url,{waitUntil:'domcontentloaded',timeout:60000});
  await page.waitForTimeout(1500);
  await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=800){scrollTo(0,y);await new Promise(r=>setTimeout(r,100));}scrollTo(0,0);});
  await page.screenshot({path:`${directory}/${name}-home-desktop.png`,fullPage:true});
  await page.screenshot({path:`${directory}/${name}-home-top.png`});
  const data=await page.evaluate(()=>{
    const details=e=>{const s=getComputedStyle(e),r=e.getBoundingClientRect();return {tag:e.tagName,id:e.id,class:e.className,text:e.innerText?.slice(0,500),x:r.x,y:r.y,width:r.width,height:r.height,style:Object.fromEntries(['fontFamily','fontSize','fontWeight','letterSpacing','lineHeight','color','backgroundColor','padding','margin','gap','display','gridTemplateColumns','borderRadius','maxWidth'].map(k=>[k,s[k]]))};};
    return {sections:[...document.querySelector('main').children].map(details),header:[...document.querySelectorAll('header,header a,header nav')].map(details),elements:[...document.querySelectorAll('main h1,main h2,main .card,main .slider,main .grid,main .banner')].slice(0,30).map(details),images:[...document.querySelectorAll('main img')].map(e=>({src:e.currentSrc||e.src,alt:e.alt,width:e.naturalWidth,height:e.naturalHeight,parent:e.closest('section,.shopify-section')?.id})),css:[...document.querySelectorAll('link[rel=stylesheet]')].map(e=>e.href)};
  });
  await fs.writeFile(`${directory}/${name}-home.json`,JSON.stringify(data,null,2));
  await fs.writeFile(`${directory}/${name}-home.html`,await page.content());
  console.log(name,JSON.stringify({sections:data.sections.map(e=>({id:e.id,class:e.class,width:e.width,height:e.height,text:e.text?.slice(0,100)})),fonts:data.elements.slice(0,5).map(e=>e.style.fontFamily)}));
  await page.setViewportSize({width:390,height:844});
  await page.screenshot({path:`${directory}/${name}-home-mobile.png`,fullPage:true});
  await page.screenshot({path:`${directory}/${name}-home-mobile-top.png`});
  await page.setViewportSize({width:1440,height:1000});
}
await browser.close();
