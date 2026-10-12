import {chromium} from 'playwright';
import fs from 'node:fs/promises';
const directory='reference/yokai-instagram';await fs.mkdir(directory,{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE||'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}});
 const requests=[],responses=[];
 page.on('request',request=>{if(/gsc|instafeed|instagram|gomes|socialwidget/i.test(request.url()))requests.push({method:request.method(),url:request.url(),body:request.postData()});});
 const pending=[];
 page.on('response',response=>{if(/gsc|instafeed|instagram|gomes|socialwidget/i.test(response.url())&&/json/.test(response.headers()['content-type']||''))pending.push(response.text().then(body=>responses.push({url:response.url(),status:response.status(),body})).catch(()=>{}));});
 await page.goto('https://www.yokaiclothingbd.com/',{waitUntil:'domcontentloaded',timeout:60000});
 const feed=page.locator('[id*="1789290167a8eb97da"]');await feed.scrollIntoViewIfNeeded();await page.waitForTimeout(3500);
 await fs.writeFile(`${directory}/requests.json`,JSON.stringify(requests,null,2));
 await Promise.allSettled(pending);await fs.writeFile(`${directory}/responses.json`,JSON.stringify(responses,null,2));
 await fs.writeFile(`${directory}/feed.html`,await feed.innerHTML());
 const data=await feed.evaluate(section=>{
  const describe=e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return {tag:e.tagName,class:e.className,text:e.innerText?.slice(0,80),rect:{x:r.x,y:r.y,width:r.width,height:r.height},style:Object.fromEntries(['display','position','fontSize','fontWeight','fontFamily','letterSpacing','gap','padding','margin','borderRadius','animation','overflow','aspectRatio','gridTemplateColumns'].map(k=>[k,s[k]]))};};
  return {html:section.outerHTML,structure:[...section.querySelectorAll('*')].slice(0,80).map(describe),links:[...section.querySelectorAll('a')].map(a=>({href:a.href,text:a.innerText})),images:[...section.querySelectorAll('img')].slice(0,8).map(i=>({src:i.currentSrc||i.src,alt:i.alt})),videos:[...section.querySelectorAll('video')].map(v=>({src:v.currentSrc||v.src,poster:v.poster,autoplay:v.autoplay,loop:v.loop,muted:v.muted}))};
 });
 await fs.writeFile(`${directory}/feed.json`,JSON.stringify(data,null,2));
 await feed.screenshot({path:`${directory}/feed-desktop.png`});
 console.log(JSON.stringify({requests:requests.map(r=>({method:r.method,url:r.url.replace(/([?&](?:access_token|token|key)=)[^&]+/gi,'$1[redacted]')})),responses:responses.map(r=>({url:r.url,status:r.status,bytes:r.body.length})),structure:data.structure.slice(0,14),links:data.links.slice(0,5),videos:data.videos.slice(0,2)},null,2));
 await page.setViewportSize({width:390,height:844});await feed.scrollIntoViewIfNeeded();await page.waitForTimeout(500);await feed.screenshot({path:`${directory}/feed-mobile.png`});
}finally{await browser.close();}
