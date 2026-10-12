import fs from 'node:fs/promises';
import {chromium} from 'playwright';
const directory='reference/yokai-refresh';
const data=JSON.parse(await fs.readFile(`${directory}/yokai-home.json`,'utf8'));
const assets=[
 ['yokai-shirts-desktop.png','Home_Page_Banner_1.png'],
 ['yokai-shirts-mobile.png','Home-Page-Banner-Phone-2_'],
 ['yokai-shirts-compact.png','Home_Page_Banner_f46'],
 ['yokai-tees.png','tshirt.png'],
 ['yokai-sweats.webp','WEB-POST-SWEATPANTS.webp'],
 ['yokai-music.png','web-collection_1.png'],
 ['yokai-anime.webp','jjk.webp'],
 ['yokai-essentials.webp','essentials.webp'],
];
const sources=[];
await Promise.all(assets.map(async([file,part])=>{
 const source=data.images.find(i=>i.src.includes(part)).src.replace(/&width=\d+/,'');
 const response=await fetch(source);if(!response.ok)throw new Error(source);
 await fs.writeFile(`auracast/assets/images/${file}`,Buffer.from(await response.arrayBuffer()));
 sources.push({file,source});
}));
for(const name of ['yokai-homepage.css','base.css']) {
 const url=data.css.find(url=>url.includes(`/assets/${name}?`));
 await fs.writeFile(`${directory}/${name}`,await fetch(url).then(r=>r.text()));
}
await fs.writeFile(`${directory}/campaign-sources.json`,JSON.stringify(sources,null,2));
const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE||'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
await page.goto('https://www.yokaiclothingbd.com/',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(1000);
const section=page.locator('[id*="new_featured_collection_mKtKkR"]');
await section.scrollIntoViewIfNeeded();await page.waitForTimeout(700);
await page.screenshot({path:`${directory}/yokai-popular-desktop.png`});
const layout=await section.evaluate(section=>{
 const describe=e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return {tag:e.tagName,class:e.className,text:e.innerText?.slice(0,100),width:r.width,height:r.height,style:Object.fromEntries(['fontSize','fontWeight','textAlign','padding','margin','gap','display','gridTemplateColumns','color','backgroundColor','borderRadius','letterSpacing'].map(k=>[k,s[k]]))};};
 return [...section.querySelectorAll('h2,.collection__description,.grid,.card,.card__media,.card__heading,.price,.badge,.swipe-message,.button')].slice(0,20).map(describe);
});
await page.setViewportSize({width:390,height:844});await section.scrollIntoViewIfNeeded();await page.waitForTimeout(700);await page.screenshot({path:`${directory}/yokai-popular-mobile.png`});
await fs.writeFile(`${directory}/popular-layout.json`,JSON.stringify(layout,null,2));
console.log(JSON.stringify({assets:sources.map(s=>s.file),layout},null,2));
await browser.close();
