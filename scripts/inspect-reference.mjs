import { chromium } from 'playwright';
import fs from 'node:fs/promises';
const browser = await chromium.launch({headless:true, executablePath:'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
const page = await browser.newPage({viewport:{width:1440,height:1000},deviceScaleFactor:1});
const pages = [['home',''],['shop','shop/'],['product','product/flower-embroidery-w-chest-pocket-shirt/'],['about','about-us/'],['contact','contact-us/']];
const report = {};
for (const [name,path] of pages) {
  await page.goto('https://newnorth.wpthemestudios.com/'+path,{waitUntil:'networkidle'});
  await page.waitForTimeout(1800);
  await page.locator('.mfp-wrap .mfp-close').click({timeout:1500}).catch(()=>{});
  await page.evaluate(()=>{document.querySelectorAll('.mfp-wrap,.mfp-bg').forEach(e=>e.remove());document.documentElement.style.overflow='';document.body.style.overflow='';});
  await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=700){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,100));}window.scrollTo(0,0);});
  await page.waitForTimeout(500);
  await page.screenshot({path:`reference/${name}-desktop.png`,fullPage:true});
  if(name==='home') await page.screenshot({path:'reference/home-top.png',fullPage:false});
  report[name] = await page.evaluate(()=>({
    title:document.title,
    headings:[...document.querySelectorAll('h1,h2,h3,h4')].map(e=>({text:e.textContent.trim(),tag:e.tagName,font:getComputedStyle(e).fontSize,weight:getComputedStyle(e).fontWeight,rect:{x:e.getBoundingClientRect().x,y:e.getBoundingClientRect().y,width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height}})),
    images:[...document.images].map(e=>({src:e.src,alt:e.alt,width:e.width,height:e.height})),
    sections:[...document.querySelectorAll('.wpb_row')].map(e=>({text:e.textContent.trim().slice(0,150),classes:e.className,bg:getComputedStyle(e).backgroundImage,color:getComputedStyle(e).backgroundColor,height:e.getBoundingClientRect().height,width:e.getBoundingClientRect().width})),
    products:[...document.querySelectorAll('li.product')].map(e=>({name:e.querySelector('h3 > a')?.textContent.trim(),category:e.querySelector('.product-category')?.textContent.trim(),price:e.querySelector('.price')?.textContent.trim(),image:e.querySelector('img')?.src,hover:e.querySelectorAll('img')[1]?.src,link:e.querySelector('h3 a')?.href})),
    bodyFont:getComputedStyle(document.body).font,
    header:{height:document.querySelector('header')?.getBoundingClientRect().height},
  }));
}
await page.setViewportSize({width:390,height:844});
await page.goto('https://newnorth.wpthemestudios.com/',{waitUntil:'networkidle'});
await page.waitForTimeout(1800);
await page.locator('.mfp-wrap .mfp-close').click({timeout:1500}).catch(()=>{});
await page.evaluate(async()=>{document.querySelectorAll('.mfp-wrap,.mfp-bg').forEach(e=>e.remove());for(let y=0;y<document.body.scrollHeight;y+=700){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,100));}window.scrollTo(0,0);});
await page.screenshot({path:'reference/home-mobile.png',fullPage:true});
await fs.writeFile('reference/analysis.json',JSON.stringify(report,null,2));
console.log(JSON.stringify({sections:report.home.sections,headings:report.home.headings.slice(0,15),products:report.home.products.slice(0,4)},null,2));
await browser.close();
