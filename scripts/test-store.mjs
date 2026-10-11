import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const base=process.env.STORE_URL||'http://127.0.0.1:8088';
const browser=await chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE?{executablePath:process.env.BROWSER_EXECUTABLE}:{})});
const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
const page=await context.newPage(),errors=[],checks=[];
page.setDefaultTimeout(30000);page.setDefaultNavigationTimeout(45000);
page.on('pageerror',e=>errors.push(e.message));
async function go(path=''){const r=await page.goto(base+'/'+path,{waitUntil:'networkidle'});assert.equal(r.status(),200,path);}
async function visible(selector){await page.locator(selector).waitFor({state:'visible'});}
async function check(name,task){await task();checks.push(name);console.log('PASS',name);}
try {
 await check('Demo homepage sections and product collections',async()=>{
  await go();assert.equal(await page.locator('.streetwear-product-section').count(),4);
  for(const id of ['popular','music','anime','essentials'])assert.ok(await page.locator(`section[aria-labelledby="${id}-title"] .product-card`).count()>0,id);
  assert.equal(await page.locator('.world-card').count(),6);
  await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=600){scrollTo(0,y);await new Promise(r=>setTimeout(r,80))}scrollTo(0,0)});
  await page.waitForTimeout(400);
  const broken=await page.locator('main img').evaluateAll(imgs=>imgs.filter(i=>!i.complete||!i.naturalWidth).map(i=>i.src));assert.deepEqual(broken,[]);
 });
 await check('Quick view and persistent wishlist',async()=>{
  const card=page.locator('.streetwear-product-section .product-card').first();await card.hover();await card.locator('[data-quick-view]').click();await visible('#quick-view-content h2');assert.match(await page.locator('#quick-view-content').innerText(),/Essential Tee/);
  await page.keyboard.press('Escape');assert.equal(await page.locator('dialog[open]').count(),0);
  await card.locator('[data-wishlist]').click();assert.equal(await card.locator('[data-wishlist]').getAttribute('aria-pressed'),'true');
  await go('wishlist/');await visible('#wishlist-products .product-card');assert.equal(await page.locator('#wishlist-products .product-card').count(),1);
  await page.reload({waitUntil:'networkidle'});await visible('#wishlist-products .product-card');
 });
 await check('Native product search and size/category/price filters',async()=>{
  await go();await page.locator('[data-dialog="search-dialog"]').click();await page.locator('#product-search').fill('hoodie');await page.locator('#search-dialog button[type="submit"]').click();await page.waitForLoadState('networkidle');
  const names=await page.locator('main .product-title-row h3').allTextContents();assert.ok(names.length);assert.ok(names.every(n=>/hoodie/i.test(n)));
  await go('shop/');await page.locator('[data-dialog="filter-dialog"]').click();await page.locator('#filter-category').selectOption('essentials');await page.locator('#filter-size').selectOption('m');await page.locator('[name="max_price"]').fill('700');await page.locator('#filter-dialog button[type="submit"]').click();await page.waitForLoadState('networkidle');
  assert.ok(await page.locator('main .product-card').count());assert.ok((await page.locator('main .product-category').allTextContents()).every(n=>n==='Essentials'));
 });
 await check('Size variation, product layout and native cart quantity update',async()=>{
  await go('product/essential-tee-black/');
  const gallery=await page.locator('main .woocommerce-product-gallery').boundingBox(),summary=await page.locator('main .summary').boundingBox();assert.ok(summary.x>gallery.x+gallery.width-10);assert.ok(Math.abs(summary.y-gallery.y)<150);
  await page.locator('#pa_size').selectOption('m');await page.waitForFunction(()=>Number(document.querySelector('[name="variation_id"]').value)>0);
  await visible('.single_add_to_cart_button:not(.disabled)');await page.locator('.summary input.qty').fill('2');await page.locator('.single_add_to_cart_button').click();await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent.trim()==='2');assert.equal(await page.locator('.cart-count').innerText(),'2');
  await page.locator('[data-dialog="cart-dialog"]').click();assert.match(await page.locator('#cart-dialog').innerText(),/Essential Tee/);await page.keyboard.press('Escape');
  await go('cart/');await page.locator('main input.qty').fill('3');await page.locator('[name="update_cart"]').click();await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent.trim()==='3');assert.equal(await page.locator('main input.qty').inputValue(),'3');
 });
 await check('Guest COD checkout creates a WooCommerce order',async()=>{
  await go('checkout/');
  for(const [key,value] of Object.entries({first_name:'Auracast',last_name:'QA',address_1:'123 Test Road',city:'Dhaka',postcode:'1207',phone:'01700000000',email:'auracast-qa@example.test'}))await page.locator('#billing_'+key).fill(value);
  await page.locator('#billing_country').selectOption('BD');
  const state=page.locator('#billing_state');if(await state.count()){if(await state.evaluate(e=>e.tagName==='SELECT')){const opts=await state.locator('option').evaluateAll(o=>o.map(x=>({value:x.value,text:x.textContent})));const dhaka=opts.find(o=>/Dhaka/i.test(o.text))||opts.find(o=>o.value);if(dhaka)await state.selectOption(dhaka.value);}else await state.fill('Dhaka');}
  if(await page.locator('#ship-to-different-address-checkbox').isChecked())await page.locator('#ship-to-different-address-checkbox').uncheck();
  await page.waitForTimeout(1500);await page.locator('#payment_method_cod').check();
  await page.locator('#place_order').click();await page.waitForURL(/order-received/, {timeout:45000});
  assert.match(await page.locator('main').innerText(),/Thank you.*order has been received/is);assert.match(await page.locator('main').innerText(),/Essential Tee/);
 });
 await check('Newsletter consent, storage response and invalid AJAX nonce',async()=>{
  await go();const form=page.locator('.streetwear-newsletter form');await form.locator('[name="email"]').fill(`auracast-qa-${Date.now()}@example.test`);await form.locator('[name="consent"]').check();await form.locator('[type="submit"]').click();await page.waitForFunction(()=>document.querySelector('.streetwear-newsletter .form-status').textContent.includes('Thank'));
  const invalid=await context.request.post(base+'/wp-admin/admin-ajax.php',{form:{action:'auracast_quick_view',nonce:'invalid',product_id:'1'}});assert.equal(invalid.status(),403);
 });
 await check('Mobile navigation, product selection and viewport widths',async()=>{
  for(const width of [320,390,768]){await page.setViewportSize({width,height:844});for(const path of ['', 'shop/','product/essential-tee-black/','cart/','checkout/','contact-us/']){await go(path);const dimensions=await page.evaluate(()=>({view:innerWidth,body:document.documentElement.scrollWidth}));assert.ok(dimensions.body<=dimensions.view+1,JSON.stringify({width,path,...dimensions}));}}
  await page.setViewportSize({width:390,height:844});await go();await page.locator('[data-dialog="mobile-menu"]').click();await visible('#mobile-menu[open]');const shop=page.locator('.mobile-nav > li > a').filter({hasText:/^Shop$/});await shop.click();assert.equal(await shop.getAttribute('aria-expanded'),'true');await page.keyboard.press('Escape');assert.equal(await page.locator('dialog[open]').count(),0);
  await go('product/essential-tee-black/');await page.locator('#pa_size').selectOption('s');await page.waitForFunction(()=>Number(document.querySelector('[name="variation_id"]').value)>0);await page.locator('[data-dialog="size-guide-dialog"]').click();await visible('#size-guide-dialog[open]');await page.keyboard.press('Escape');
 });
 assert.deepEqual(errors,[]);checks.push('No browser JavaScript errors');
 await fs.mkdir('test-results',{recursive:true});await fs.writeFile('test-results/verification.json',JSON.stringify({passed:checks,date:new Date().toISOString()},null,2));console.log(`${checks.length} checks passed.`);
}catch(e){await fs.mkdir('test-results',{recursive:true});await page.screenshot({path:'test-results/failure.png',fullPage:true});console.error('At',page.url());throw e;}finally{await browser.close();}
