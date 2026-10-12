import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const base=process.env.STORE_URL||'http://127.0.0.1:8088';
const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE||'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
const page=await context.newPage(),errors=[],checks=[];
page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
page.on('pageerror',e=>errors.push(e.message));
async function go(path=''){const r=await page.goto(base+'/'+path,{waitUntil:'networkidle'});assert.equal(r.status(),200,path);}
async function check(name,task){await task();checks.push(name);console.log('PASS',name);}
async function settled(){await page.waitForFunction(()=>!document.querySelector('.woocommerce-checkout-processing,.woocommerce-checkout .blockUI,.woocommerce-cart-form .blockUI'));await page.waitForTimeout(400);}
const output='test-results/yokai-update';await fs.mkdir(output,{recursive:true});
try{
 await check('Reference homepage proportions, section order, assets and removed blog',async()=>{
  await go();assert.equal(await page.locator('.streetwear-product-section').count(),4);
  const dimensions=await page.locator('.yokai-hero').boundingBox();assert.ok(Math.abs(dimensions.height-675)<3);
  assert.equal((await page.locator('.site-header').boundingBox()).height,85);
  for(const key of ['popular','music','anime','essentials'])assert.ok(await page.locator(`[aria-labelledby="${key}-title"] .product-card`).count());
  assert.equal(await page.locator('nav a').filter({hasText:/^(Blog|Journal)$/i}).count(),0);
  const broken=await page.locator('main img').evaluateAll(async images=>{images.forEach(i=>i.loading='eager');await Promise.allSettled(images.map(i=>i.decode()));return images.filter(i=>!i.complete||!i.naturalWidth).map(i=>i.src);});assert.deepEqual(broken,[]);
 });
 await check('Category six-column grid, availability/price filtering and sorting',async()=>{
  await go('product-category/graphic-tees/');
  const columns=await page.locator('main ul.products').evaluate(e=>getComputedStyle(e).gridTemplateColumns.split(' ').length);assert.equal(columns,6);
  const availability=page.locator('.collection-facet').filter({has:page.locator('summary').filter({hasText:'Availability'})});await availability.locator('summary').click();await availability.locator('[value="instock"]').check();await availability.locator('[type="submit"]').click();await page.waitForLoadState('networkidle');assert.ok(page.url().includes('availability=instock'));
  const price=page.locator('.collection-facet').filter({has:page.locator('summary').filter({hasText:'Price'})});await price.locator('summary').click();await price.locator('[name="max_price"]').fill('700');await price.locator('[type="submit"]').click();await page.waitForLoadState('networkidle');assert.ok(await page.locator('main .product-card').count());
  await Promise.all([page.waitForURL(url=>url.searchParams.get('orderby')==='price-desc'),page.locator('.woocommerce-ordering select').selectOption('price-desc')]);await page.waitForLoadState('networkidle');
  const amounts=await page.locator('main .product-card .price ins .amount,main .product-card .price>.amount').allTextContents();assert.ok(amounts.every(text=>Number(text.replace(/[^\d.]/g,''))<=700));
 });
 await check('Mobile two-row product rail, page dots and expandable navigation',async()=>{
  await page.setViewportSize({width:390,height:844});await go();
  const rail=page.locator('#music-rail');await rail.scrollIntoViewIfNeeded();
  assert.ok(await rail.evaluate(e=>e.scrollWidth>e.clientWidth));await page.locator('[data-rail-dots="music-rail"] button').last().click();await page.waitForTimeout(200);assert.ok(await rail.evaluate(e=>e.scrollLeft>0));
  await page.locator('[data-dialog="mobile-menu"]').click();const products=page.locator('.mobile-nav>li>a').filter({hasText:/^PRODUCTS$/});await products.click();assert.equal(await products.getAttribute('aria-expanded'),'true');await page.keyboard.press('Escape');assert.equal(await page.locator('dialog[open]').count(),0);
 });
 await check('Native size selection, product gallery, guide and cart drawer',async()=>{
  await page.setViewportSize({width:1440,height:1000});await go('product/essential-tee-black/');
  const gallery=await page.locator('.auracast-gallery').boundingBox(),summary=await page.locator('.summary').boundingBox();assert.ok(summary.x>gallery.x+gallery.width-5);assert.ok(Math.abs(summary.y-gallery.y)<40);
  const before=await page.locator('.auracast-gallery-image').getAttribute('src');await page.locator('[data-gallery-src]').nth(1).click();assert.notEqual(await page.locator('.auracast-gallery-image').getAttribute('src'),before);
  await page.locator('[data-dialog="size-guide-dialog"]').click();await page.locator('#size-guide-dialog[open]').waitFor();await page.keyboard.press('Escape');
  await page.locator('.auracast-size-chip').filter({hasText:/^S$/}).click();await page.waitForFunction(()=>Number(document.querySelector('[name="variation_id"]').value)>0);
  await page.locator('[data-product-purchase="single-add"]').click();await page.waitForLoadState('networkidle');await page.locator('#cart-dialog[open]').waitFor();assert.match(await page.locator('#cart-dialog').innerText(),/Essential Tee/i);await page.locator('#cart-dialog .checkout').waitFor({state:'visible'});
  await page.locator('#cart-dialog [data-mini-quantity="1"]').click();await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent.trim()==='2');await page.locator('#cart-dialog [data-mini-quantity="-1"]').click();await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent.trim()==='1');await page.keyboard.press('Escape');
 });
 await check('Cart quantity controls recalculate actual bundle prices',async()=>{
  await go('cart/');await page.locator('main [data-cart-quantity="1"]').click();await page.waitForFunction(()=>document.querySelector('main input.qty')?.value==='2');await settled();
  await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent.trim()==='2');
  assert.match(await page.locator('main').innerText(),/948\.10/); // Two 499 BDT tees with the actual 5% bundle reduction.
  await page.locator('main [data-cart-quantity="-1"]').click();await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent.trim()==='1');await settled();
 });
 await check('Checkout exact field order, live shipping, tip and invalid coupon feedback',async()=>{
  await go('checkout/');assert.equal(await page.locator('.site-header').count(),0);assert.equal(await page.locator('#billing_country option').count(),1);
  for(const [key,value] of Object.entries({email:`auracast-yokai-qa-${Date.now()}@example.test`,first_name:'Auracast',last_name:'Yokai QA',address_1:'123 QA Road',city:'Dhaka',phone:'01700000000'}))await page.locator('#billing_'+key).fill(value);
  await page.locator('.auracast-shipping-methods label').filter({hasText:'OUTSIDE DHAKA'}).locator('input').check();await settled();await page.waitForFunction(()=>document.querySelector('.checkout-total-value')?.textContent.includes('649.00'));
  await page.locator('[name="auracast_tip_choice"][value="10"]').check();await settled();await page.waitForFunction(()=>document.querySelector('.checkout-total-value')?.textContent.includes('698.90'));
  await page.locator('[name="auracast_tip_choice"][value="none"]').check();await settled();
  await page.locator('#checkout-discount-code').fill('INVALID-CODE');await page.locator('#order_review .checkout-apply-coupon').click();await page.locator('#order_review .checkout-coupon-status .woocommerce-error').waitFor();
  await page.locator('.auracast-shipping-methods label').filter({hasText:'INSIDE DHAKA'}).locator('input').check();await settled();await page.waitForFunction(()=>document.querySelector('.checkout-total-value')?.textContent.includes('569.00'));
  await page.screenshot({path:`${output}/checkout-desktop.png`,fullPage:true});
 });
 await check('Checkout mobile summary and widths from 320 to 1440',async()=>{
  for(const width of [320,390,768,1440]){await page.setViewportSize({width,height:844});for(const path of ['', 'product-category/graphic-tees/','product/essential-tee-black/','cart/','checkout/','contact-us/','shipping-policy/','my-account/']){await go(path);const geometry=await page.evaluate(()=>({view:innerWidth,width:document.documentElement.scrollWidth}));assert.ok(geometry.width<=geometry.view+1,JSON.stringify({path,width,...geometry}));}}
  await page.setViewportSize({width:390,height:844});await go('checkout/');await page.locator('.checkout-summary-toggle').click();assert.equal(await page.locator('.checkout-summary-toggle').getAttribute('aria-expanded'),'true');await page.locator('.checkout-summary-toggle').click();assert.equal(await page.locator('.checkout-summary-toggle').getAttribute('aria-expanded'),'false');
  await page.screenshot({path:`${output}/checkout-mobile.png`,fullPage:true});
 });
 await check('Guest COD checkout creates an order with the correct final total',async()=>{
  await page.setViewportSize({width:1440,height:1000});await go('checkout/');
  for(const [key,value] of Object.entries({email:`auracast-yokai-qa-${Date.now()}@example.test`,first_name:'Auracast',last_name:'Yokai QA',address_1:'123 QA Road',city:'Dhaka',phone:'01700000000'}))await page.locator('#billing_'+key).fill(value);
  await settled();if(await page.locator('#terms').count())await page.locator('#terms').check();await page.locator('#place_order').click();await page.waitForURL(/order-received/,{timeout:45000});assert.match(await page.locator('main').innerText(),/Thank you.*order has been received/is);assert.match(await page.locator('main').innerText(),/569\.00/);
  await page.screenshot({path:`${output}/order-received.png`,fullPage:true});
 });
 await check('Policy links and blog removal persist on public pages',async()=>{
  for(const path of ['privacy-policy/','shipping-policy/','terms-of-service/','refund-policy/'])await go(path);
  const response=await context.request.get(base+'/blog/');assert.equal(response.status(),404);
 });
 assert.deepEqual(errors,[]);checks.push('No browser JavaScript errors');
 await fs.writeFile(`${output}/verification.json`,JSON.stringify({passed:checks,date:new Date().toISOString()},null,2));console.log(`${checks.length} checks passed.`);
}catch(error){await page.screenshot({path:`${output}/failure.png`,fullPage:true});console.error('Failure at',page.url());throw error;}finally{await browser.close();}
