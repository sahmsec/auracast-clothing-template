import fs from 'node:fs/promises';
import path from 'node:path';
const source=JSON.parse(await fs.readFile('reference/yokai-products.json','utf8')).products;
const selectors=[/ESSENTIALS BLACK/,/ESSENTIALS WHITE/,/ESSENTIALS GREEN/,/T-SHIRT \/ Overtime/,/FINGERS UP/,/T-SHIRT \/ Bieber/,/^SHIRTS \/ Essential \(BLACK\)/,/^SHIRTS \/ Essential \(WHITE\)/,/^SHIRTS \/ BERSERK/,/SWEATPANTS \/ Essential \(Black\)/,/SWEATPANTS \/ Essential \(White\)/,/SWEATPANTS \/ THE SHADOWS/,/^HOODIE \/ ESSENTIAL BLACK/,/^HOODIE \/ ESSENTIAL WHITE/,/^HOODIE \/ ESSENTIAL GREY/,/^HOODIE \/ ESSENTIAL BROWN/,/T-SHIRT \/ Jackpot/,/T-SHIRT \/ Megumi/,/T-SHIRT \/ The Cursed Child/,/T-SHIRT \/ GOJO \(WHITE\)/,/T-SHIRT \/ Toji Awakened/,/T-SHIRT \/ The Headliner/,/T-SHIRT \/ Purposeful Sips/,/T-SHIRT \/ I Love JB \(Black\)/,/SWEATPANTS \/ SPIDER DOODLE/,/SWEATPANTS \/ ZOMBIE SPIDY/,/SWEATPANTS \/ Berserk \(WHITE\)/,/^HOODIE \/ ESSENTIAL RED/,/^SHIRTS \/ ONE PEICE WHITE BEARD/,/^SHIRTS \/ RORONOA ZORO/];
const names=['Essential Tee — Black','Essential Tee — White','Essential Tee — Forest','Overtime Graphic Tee','Afterhours Graphic Tee','Encore Graphic Tee','Essential Shirt — Black','Essential Shirt — White','Monochrome Graphic Shirt','Everyday Sweatpants — Black','Everyday Sweatpants — White','Shadow Graphic Sweatpants','Essential Hoodie — Black','Essential Hoodie — White','Essential Hoodie — Grey','Essential Hoodie — Brown','Jackpot Graphic Tee','Shadow Heir Graphic Tee','Cursed Graphic Tee','Limitless Graphic Tee','Awakened Graphic Tee','Headliner Graphic Tee','Purpose Graphic Tee','Backstage Graphic Tee','Doodle Graphic Sweatpants','Midnight Graphic Sweatpants','Monochrome Graphic Sweatpants','Essential Hoodie — Red','Whitebeard Graphic Shirt','Blade Graphic Shirt'];
const catalog=[],manifest=JSON.parse(await fs.readFile('reference/asset-sources.json','utf8'));
const modelReplacements=JSON.parse(await fs.readFile('auracast/assets/data/model-replacements.json','utf8'));
for(let i=0;i<selectors.length;i++){
 const p=source.find(p=>selectors[i].test(p.title));if(!p){console.log('Missing',String(selectors[i]));continue;}
 const slug=names[i].toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/-$/,'');
 const category=/^HOODIE/.test(p.title)?'Hoodies':/^SWEATPANTS/.test(p.title)?'Sweatpants':/^SHIRTS/.test(p.title)?'Shirts':/ESSENTIAL/.test(p.title)?'Essentials':'Graphic Tees';
 const files=[];
 for(let j=0;j<Math.min(p.images.length,2);j++){
  const url=new URL(p.images[j].src);url.searchParams.set('width','1200');
  const ext=path.extname(url.pathname);const filename=`streetwear-${slug}-${j}${ext}`;
  if(modelReplacements[filename]){const replacement=modelReplacements[filename];await fs.access('auracast/assets/images/'+replacement);files.push(replacement);continue;}
  try{await fs.access('auracast/assets/images/'+filename);}catch{const r=await fetch(url);if(!r.ok)throw new Error('Image '+r.status);await fs.writeFile('auracast/assets/images/'+filename,Buffer.from(await r.arrayBuffer()));}
  files.push(filename);manifest.push({file:filename,source:p.images[j].src,reference:'Yokai clothing direction'});
 }
 const v=p.variants[0];catalog.push({slug,name:names[i],category,collection:category==='Graphic Tees'?([4,5,21,22,23].includes(i)?'Music & Culture':'Anime'):category,menuOrder:i,isNew:i<6,price:Number(v.price),regularPrice:Number(v.compare_at_price)||Number(v.price),image:files[0],hover:files[1],gallery:files,type:'variable',sizes:p.options.find(o=>o.name.toLowerCase()==='size')?.values||['S','M','L','XL']});
}
await fs.writeFile('auracast/assets/data/catalog.json',JSON.stringify(catalog,null,2));
await fs.writeFile('reference/asset-sources.json',JSON.stringify([...new Map(manifest.map(m=>[m.file,m])).values()],null,2));
console.log(JSON.stringify({products:catalog.length,categories:[...new Set(catalog.map(p=>p.category))]}));
