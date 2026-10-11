import {chromium} from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
const root='auracast/assets';
await fs.mkdir(root+'/images',{recursive:true});await fs.mkdir(root+'/data',{recursive:true});await fs.mkdir(root+'/fonts',{recursive:true});
const report=JSON.parse(await fs.readFile('reference/analysis.json','utf8'));
const products=new Map();
const add=p=>{if(!p.name||!p.link)return;const slug=p.link.split('/').filter(Boolean).at(-1);products.set(slug,{slug,name:p.name.split('\n')[0],category:p.category||'Clothing',price:Number((p.price.match(/[\d,.]+/g)||['50']).at(-1).replaceAll(',','')),regularPrice:Number((p.price.match(/[\d,.]+/g)||['50'])[0].replaceAll(',','')),image:p.image,hover:p.hover});};
report.home.products.forEach(add);report.shop.products.forEach(add);
const browser=await chromium.launch({headless:true,executablePath:'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
const page=await browser.newPage();
for(const n of [2,3]){
 await page.goto(`https://newnorth.wpthemestudios.com/shop/page/${n}/`,{waitUntil:'domcontentloaded'});
 const list=await page.evaluate(()=>[...document.querySelectorAll('.products li.product')].map(e=>({name:e.querySelector('h3 > a')?.textContent.trim(),category:e.querySelector('.product-category')?.textContent.trim(),price:e.querySelector('.price')?.textContent.trim(),image:e.querySelector('img')?.src,hover:e.querySelectorAll('img')[1]?.src,link:e.querySelector('h3 a')?.href})));
 list.forEach(add);
}
await browser.close();
const urls=new Set(['https://newnorth.wpthemestudios.com/wp-content/uploads/2025/01/sh1.jpg','https://newnorth.wpthemestudios.com/wp-content/uploads/2025/01/home_1_cat_bg_.jpg','https://newnorth.wpthemestudios.com/wp-content/uploads/2025/02/newsletter-1.jpg']);
for(const r of Object.values(report)){r.images.forEach(i=>{if(i.src.includes('/uploads/')&&!i.src.includes('/logo-'))urls.add(i.src)});r.sections.forEach(s=>{const m=s.bg.match(/url\("([^\"]+)/);if(m)urls.add(m[1].split('?')[0]);});}
for(const p of products.values()){urls.add(p.image);if(p.hover)urls.add(p.hover);}
const manifest=[];const list=[...urls].filter(Boolean);let index=0;
async function worker(){while(index<list.length){const url=list[index++];try{const res=await fetch(url);if(!res.ok)throw new Error(res.status);const file=path.basename(new URL(url).pathname);await fs.writeFile(root+'/images/'+file,Buffer.from(await res.arrayBuffer()));manifest.push({file,source:url});}catch(e){console.error('Failed:',url,String(e));}}}
await Promise.all(Array.from({length:6},worker));
for(const p of products.values()){p.image=path.basename(new URL(p.image).pathname);if(p.hover)p.hover=path.basename(new URL(p.hover).pathname);p.type=/Women|Men/.test(p.category)&&!/(wallet|bag|hat|shoes|sneaker|achilles)/i.test(p.name)?'variable':'simple';p.sizes=p.type==='variable'?['S','M','L','XL']:[];}
const flower=products.get('flower-embroidery-w-chest-pocket-shirt');if(flower){flower.gallery=['v_1-600x740.jpg','v-2-600x740.jpg','v-3-600x740.jpg','v-4-600x740.jpg'];flower.image=flower.gallery[0];flower.colors=['Black','Green','Purple'];}
const fonts=await fetch('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',{headers:{'User-Agent':'Mozilla/5.0'}}).then(r=>r.text());
let fontCSS=fonts;let fontIndex=0;for(const url of [...new Set(fonts.match(/https:\/\/[^)]+/g))]){const ext=new URL(url).pathname.split('.').at(-1);const file=`poppins-${fontIndex++}.${ext}`;const r=await fetch(url);await fs.writeFile(root+'/fonts/'+file,Buffer.from(await r.arrayBuffer()));fontCSS=fontCSS.replaceAll(url,file);}
await fs.writeFile(root+'/fonts/fonts.css',fontCSS);
await fs.writeFile(root+'/data/catalog.json',JSON.stringify([...products.values()],null,2));
await fs.writeFile('reference/asset-sources.json',JSON.stringify(manifest,null,2));
console.log(JSON.stringify({products:products.size,assets:manifest.length,fonts:fontIndex}));
