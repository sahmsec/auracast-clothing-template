import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const base='http://127.0.0.1:8088', output='test-results/instagram';await fs.mkdir(output,{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE||'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
const context=await browser.newContext({viewport:{width:1440,height:1000}}),page=await context.newPage();
const errors=[],checks=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
async function check(name,task){await task();checks.push(name);console.log('PASS',name);}
const feed=page.locator('[data-instagram-feed]');
let testId,uploadedId,admin;
try{
 await page.goto(base,{waitUntil:'networkidle'});await feed.scrollIntoViewIfNeeded();
 await check('Six Yokai reels, matching stacked cards, movement and actual video playback',async()=>{
  const items=JSON.parse(await feed.locator('.instagram-feed-data').textContent());assert.equal(items.length,6);assert.ok(items.every(i=>i.video&&i.cover&&i.link.includes('instagram.com/reel/')));
  const geometry=await feed.locator('.instagram-marquee').boundingBox();assert.equal(geometry.width,1100);assert.ok(Math.abs(geometry.height-605)<3);
  const start=await feed.locator('.instagram-marquee-track').getAttribute('style');await page.waitForTimeout(1000);assert.notEqual(await feed.locator('.instagram-marquee-track').getAttribute('style'),start);
  await page.waitForFunction(()=>[...document.querySelectorAll('.instagram-reel-card video')].some(v=>!v.paused&&v.currentTime>.1));
  assert.equal(await feed.locator('.instagram-visit').getAttribute('href'),'https://www.instagram.com/yokaiclothingbd/');
  await feed.screenshot({path:output+'/desktop.png'});
 });
 await check('Pause, arrow navigation, reel modal, controls and Escape cleanup',async()=>{
  const pause=feed.locator('.instagram-pause');await pause.click();assert.equal(await pause.getAttribute('aria-pressed'),'true');
  const before=await feed.locator('.instagram-marquee-track').getAttribute('style');await page.waitForTimeout(300);assert.equal(await feed.locator('.instagram-marquee-track').getAttribute('style'),before);
  await feed.locator('.instagram-next').click();assert.notEqual(await feed.locator('.instagram-marquee-track').getAttribute('style'),before);
  const index=await feed.locator('.instagram-reel-card').evaluateAll(cards=>cards.reduce((best,c,i)=>Number(c.style.zIndex)>Number(cards[best].style.zIndex)?i:best,0));
  await feed.locator('.instagram-reel-card').nth(index).click();const dialog=page.locator('.instagram-reel-dialog[open]');await dialog.waitFor();
  assert.equal(await dialog.locator('video').getAttribute('controls'),'');assert.match(await dialog.locator('a').getAttribute('href'),/instagram\.com\/reel/);
  await page.waitForFunction(()=>document.querySelector('.instagram-reel-dialog video')?.currentTime>.1);
  await page.keyboard.press('Escape');assert.equal(await page.locator('.instagram-reel-dialog[open]').count(),0);assert.equal(await page.locator('.instagram-reel-dialog video').count(),0);
 });
 await check('Mobile proportions, touch drag and no horizontal page overflow',async()=>{
  await page.setViewportSize({width:390,height:844});await feed.scrollIntoViewIfNeeded();await page.waitForTimeout(300);
  const geometry=await feed.locator('.instagram-marquee').boundingBox();assert.equal(geometry.width,360);assert.ok(Math.abs(geometry.height-239.3)<3);
  const before=await feed.locator('.instagram-marquee-track').getAttribute('style');
  await page.mouse.move(geometry.x+geometry.width/2,geometry.y+80);await page.mouse.down();await page.mouse.move(geometry.x+40,geometry.y+80,{steps:8});await page.mouse.up();assert.notEqual(await feed.locator('.instagram-marquee-track').getAttribute('style'),before);assert.equal(await page.locator('dialog[open]').count(),0);
  await page.mouse.move(0,0);await feed.screenshot({path:output+'/mobile.png'});
  for(const width of [320,390,768,1440]){await page.setViewportSize({width,height:1000});await page.waitForTimeout(100);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));}
 });
 await check('Reduced motion respects preference and reels pause offscreen',async()=>{
  await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await feed.locator('.instagram-pause').getAttribute('aria-pressed'),'true');
  await page.waitForFunction(()=>[...document.querySelectorAll('.instagram-reel-card video')].every(v=>v.paused));
  await page.emulateMedia({reducedMotion:'no-preference'});await page.evaluate(()=>scrollTo(0,0));await page.waitForTimeout(300);assert.ok(await page.locator('.instagram-reel-card video').evaluateAll(videos=>videos.every(v=>v.paused)));
 });
 await check('No-code WordPress media picker, publish, update, reorder and draft',async()=>{
  const credentials=JSON.parse(await fs.readFile('.local/admin-credentials.json','utf8'));
  const adminContext=await browser.newContext({viewport:{width:1440,height:1000}});admin=await adminContext.newPage();admin.setDefaultTimeout(25000);admin.on('pageerror',e=>errors.push(e.message));
  await admin.goto(base+'/wp-login.php');await admin.locator('#user_login').fill(credentials.username);await admin.locator('#user_pass').fill(credentials.password);await admin.locator('#wp-submit').click();await admin.locator('#wpadminbar').waitFor();
  await admin.goto(base+'/wp-admin/edit.php?post_type=auracast_reel');assert.equal(await admin.locator('#the-list tr').count(),6);
  await admin.goto(base+'/wp-admin/post-new.php?post_type=auracast_reel');await admin.locator('#title').fill('Auracast reel editor QA');
  testId=await admin.locator('#post_ID').inputValue();
  await admin.locator('#auracast-instagram-link').fill('https://www.instagram.com/reel/DeW0xpWAe4J/?utm_source=qa');
  for(const type of ['video','image']){
   await admin.locator(`[data-reel-pick="${type}"]`).click();await admin.locator('.media-modal:visible').waitFor();
   if(type==='video'){
    await admin.locator('.media-modal:visible input[type="file"]').setInputFiles({name:'auracast-reel-qa-upload.mp4',mimeType:'video/mp4',buffer:await fs.readFile('auracast/assets/videos/instagram-demo-03.mp4')});
    await admin.locator('.media-modal:visible .attachment.uploading').waitFor({state:'hidden'});
   }else await admin.getByRole('tab',{name:'Media Library',exact:true}).click();
   const attachment=admin.locator('.media-modal:visible .attachments .attachment').first();await attachment.waitFor();
   await admin.waitForFunction(()=>{const modal=[...document.querySelectorAll('.media-modal')].find(e=>e.getBoundingClientRect().width);const card=modal?.querySelector('.attachments .attachment');return card?.dataset.id&&!card.classList.contains('uploading');});
   if(type==='video')uploadedId=Number(await attachment.getAttribute('data-id'));
   if(!await attachment.evaluate(e=>e.classList.contains('selected')))await attachment.click();
   await admin.locator('.media-modal:visible .media-button-select').click();
   if(type==='video')uploadedId=Number(await admin.locator('[name="auracast_reel_video"]').inputValue());
  }
  assert.ok(Number(await admin.locator('[name="auracast_reel_video"]').inputValue())>0);assert.ok(Number(await admin.locator('[name="auracast_reel_cover"]').inputValue())>0);
  console.log('Verified native video upload and cover selection.');
  await admin.locator('#content-html').click();await admin.locator('#content').fill('This caption was added in WordPress without code.');await admin.locator('#menu_order').fill('9');
  await admin.locator('#publish').click();await admin.waitForURL(/post\.php.*message=6/);await page.goto(base,{waitUntil:'networkidle'});
  let items=JSON.parse(await feed.locator('.instagram-feed-data').textContent());assert.equal(items.length,7);assert.equal(items.at(-1).id,Number(testId));assert.equal(items.at(-1).link,'https://www.instagram.com/reel/DeW0xpWAe4J/');assert.match(items.at(-1).caption,/without code/);
  await admin.locator('#title').fill('000 Updated reel editor QA');await admin.locator('#menu_order').fill('0');await admin.locator('#publish').click();await admin.waitForURL(/message=1/);await page.goto(base,{waitUntil:'networkidle'});
  items=JSON.parse(await feed.locator('.instagram-feed-data').textContent());assert.equal(items[0].id,Number(testId));assert.equal(items[0].title,'000 Updated reel editor QA');
  await admin.locator('#edit-slug-box').count();await admin.locator('.edit-post-status').click();await admin.locator('#post_status').selectOption('draft');await admin.locator('.save-post-status').click();await admin.locator('#publish').click();await admin.waitForURL(/message=1/);await page.goto(base,{waitUntil:'networkidle'});
  items=JSON.parse(await feed.locator('.instagram-feed-data').textContent());assert.equal(items.length,6);assert.ok(items.every(i=>i.id!==Number(testId)));
  await admin.goto(base+'/wp-admin/edit.php?post_type=auracast_reel');const row=admin.locator('#post-'+testId);await row.hover();await row.locator('.submitdelete').click();testId=null;
  await admin.goto(base+'/wp-admin/post-new.php?post_type=auracast_reel');await admin.evaluate(async id=>{const media=wp.media.attachment(id);await media.fetch();await media.destroy();},uploadedId);uploadedId=null;
  await admin.goto(base+'/wp-admin/edit.php?post_type=auracast_reel&page=auracast-instagram-settings');await admin.locator('#reel-speed').selectOption('12');await admin.locator('#submit').click();await page.goto(base,{waitUntil:'networkidle'});assert.equal(await feed.getAttribute('data-speed'),'12');
  await admin.locator('#reel-speed').selectOption('24');await admin.locator('#submit').click();await adminContext.close();
 });
 assert.deepEqual(errors,[]);await fs.writeFile(output+'/verification.json',JSON.stringify({passed:checks,date:new Date().toISOString()},null,2));console.log(checks.length+' checks passed; no JavaScript errors.');
}catch(error){await page.screenshot({path:output+'/failure.png',fullPage:true});if(admin)await admin.screenshot({path:output+'/admin-failure.png',fullPage:true});throw error;}
finally{if(testId&&admin&&!admin.isClosed()){try{await admin.goto(base+'/wp-admin/edit.php?post_type=auracast_reel');const row=admin.locator('#post-'+testId);await row.hover();await row.locator('.submitdelete').click();}catch{}}if(uploadedId&&admin&&!admin.isClosed()){try{await admin.goto(base+'/wp-admin/post-new.php?post_type=auracast_reel');await admin.evaluate(async id=>{const media=wp.media.attachment(id);await media.fetch();await media.destroy();},uploadedId);}catch{}}await browser.close();}
