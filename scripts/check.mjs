import fs from 'node:fs';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import phpParser from 'php-parser';
const parser=new phpParser.Engine({parser:{extractDoc:true,phpVersion:'8.0'},ast:{withPositions:true}});
function files(dir){return fs.readdirSync(dir,{withFileTypes:true}).flatMap(e=>e.isDirectory()?files(path.join(dir,e.name)):[path.join(dir,e.name)]);}
const source=files('auracast');let phpCount=0;
for(const file of source.filter(p=>p.endsWith('.php'))){parser.parseCode(fs.readFileSync(file,'utf8'),file);phpCount++;if(fs.existsSync('.local/php/php.exe')){const lint=spawnSync(path.resolve('.local/php/php.exe'),['-n','-l',path.resolve(file)],{encoding:'utf8'});if(lint.status!==0)throw new Error(lint.stdout+lint.stderr);}}
const js=spawnSync(process.execPath,['--check','auracast/assets/js/store.js'],{encoding:'utf8'});if(js.status!==0)throw new Error(js.stderr);
const catalog=JSON.parse(fs.readFileSync('auracast/assets/data/catalog.json','utf8'));
for(const p of catalog){for(const file of [p.image,p.hover,...p.gallery||[]].filter(Boolean)){if(!fs.existsSync('auracast/assets/images/'+file))throw new Error('Missing product image: '+file);}if(p.price<0||!p.name||!p.slug)throw new Error('Invalid catalog entry');}
const required=['style.css','functions.php','index.php','header.php','footer.php','front-page.php','assets/css/store.css','assets/js/store.js','screenshot.png'];for(const file of required){if(!fs.existsSync('auracast/'+file)&&file!=='screenshot.png')throw new Error('Missing theme file: '+file);}
for(const file of ['auracast-logo.svg','auracast-logo-white.svg','auracast-icon.svg']){const svg=fs.readFileSync('auracast/assets/images/'+file,'utf8');if(!svg.includes('<svg')||svg.includes('<script')||svg.includes('http://newnorth'))throw new Error('Invalid logo');}
for(const file of ['store.css','streetwear.css']){const css=fs.readFileSync('auracast/assets/css/'+file,'utf8');if((css.match(/{/g)||[]).length!==(css.match(/}/g)||[]).length)throw new Error('Unbalanced CSS: '+file);}
for(const file of source.filter(p=>p.endsWith('.php'))){const text=fs.readFileSync(file,'utf8');for(const match of text.matchAll(/['"](streetwear-[a-z0-9-]+\.(?:jpg|png|webp))['"]/g)){if(!fs.existsSync('auracast/assets/images/'+match[1]))throw new Error('Missing section image: '+match[1]);}}
console.log(`Validated ${phpCount} PHP files, storefront JavaScript, CSS, brand SVGs, and ${catalog.length} product asset sets.`);
