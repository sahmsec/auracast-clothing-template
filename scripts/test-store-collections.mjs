import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { existsSync } from 'node:fs';

const base = process.env.STORE_URL || 'http://127.0.0.1:8088';
const installed = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'].find(existsSync);
const browser = await chromium.launch({ headless: true, ...(process.env.BROWSER_EXECUTABLE || installed ? { executablePath: process.env.BROWSER_EXECUTABLE || installed } : {}) });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
const page = await context.newPage();
const errors = [], checks = [];
page.on('pageerror', error => errors.push(error.message));
page.setDefaultTimeout(20000);
const output = 'test-results/store-collections';
await fs.mkdir(output, { recursive: true });
async function go(path = '') { const response = await page.goto(`${base}/${path}`, { waitUntil: 'networkidle' }); assert.equal(response.status(), 200, path); }
async function check(name, test) { await test(); checks.push(name); console.log('PASS', name); }
async function settle() { await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))); }
const segmentName = name => new RegExp(`^${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replaceAll("'", "['’]")}$`);
const structure = JSON.parse(await fs.readFile('auracast/assets/data/store-collections.json', 'utf8'));

try {
  await check('Essentials is the first product row; all active collections are present', async () => {
    await go();
    const rows = await page.locator('.yokai-product-section').evaluateAll(elements => elements.map(e => e.getAttribute('aria-labelledby')));
    assert.deepEqual(rows, ['essentials-title', 'hoodies-title', 'joggers-title', 'sweatshirts-title', 'diva-title']);
    assert.ok(await page.locator('#essentials-rail .product-card').count() >= 4);
    assert.ok(await page.locator('#joggers-rail .product-card').count() >= 3);
    assert.ok(await page.locator('#diva-rail .product-card').count() >= 3);
    assert.match(await page.locator('[aria-labelledby="sweatshirts-title"]').innerText(), /New pieces are on the way/);
    const first = await page.locator('#essentials-rail').evaluate(e => e.closest('section').getBoundingClientRect().top);
    const heroEnd = await page.locator('.yokai-hero').evaluate(e => e.getBoundingClientRect().bottom);
    assert.ok(first > heroEnd);
    assert.equal(await page.locator('.tees-campaign,.music-campaign,.anime-campaign').count(), 0);
    const broken = await page.locator('main img').evaluateAll(async images => {
      images.forEach(image => image.loading = 'eager');
      await Promise.allSettled(images.map(image => image.decode()));
      return images.filter(image => !image.naturalWidth).map(image => image.src);
    });
    assert.deepEqual(broken, []);
    await page.screenshot({ path: `${output}/home-desktop.png`, fullPage: true });
    await page.screenshot({ path: `${output}/home-top.png` });
  });

  await check('Navbar exposes clothing subcategories; color filtering stays on collection pages', async () => {
    const links = await page.locator('.primary-menu > li > a').allTextContents();
    assert.deepEqual(links, ['AURACAST ESSENTIALS', 'HOODIES', 'JOGGERS', 'SWEATSHIRTS', 'DIVA WARDROBE', 'T-SHIRTS']);
    for (const category of structure.categories.filter(category => category.segments.length)) {
      const top = page.locator('.primary-menu > li > a').filter({ hasText: new RegExp(`^${category.name.toUpperCase()}$`) });
      await top.hover(); await settle();
      const menu = top.locator('..').locator(':scope > .sub-menu');
      assert.equal(await menu.locator('a[href*="filter_color"]').count(), 0);
      assert.equal(await menu.getByRole('link', { name: 'Colors', exact: true }).count(), 0);
      for (const segment of category.segments) assert.equal(await menu.getByRole('link', { name: segmentName(segment) }).isVisible(), true, segment);
      await page.mouse.move(1000, 600); await settle();
      await page.mouse.move(400, 300); await settle();
      const hidden = await page.locator('.primary-menu .sub-menu').evaluateAll(menus => menus.every(menu => getComputedStyle(menu).visibility === 'hidden'));
      assert.equal(hidden, true, 'Hero must not trigger hidden submenus');
      await top.focus(); await settle(); await page.keyboard.press('Tab');
      assert.equal(await menu.locator('a').first().evaluate(element => element === document.activeElement), true);
      await page.evaluate(() => document.activeElement.blur());
    }
  });

  await check('Color filters return only products from the selected category', async () => {
    await go('product-category/hoodies/?filter_color=black');
    assert.equal(await page.locator('.collection-heading h1').innerText(), 'Hoodies');
    const names = await page.locator('main .product-card h3').allTextContents();
    assert.ok(names.length); assert.ok(names.every(name => /Hoodie.*Black/i.test(name)), names.join(', '));
    const colors = await page.locator('.desktop-facets select[name="filter_color"] option').allTextContents();
    assert.deepEqual(colors.slice(1).sort(), structure.categories[0].colors.slice().sort());
    await go('product-category/joggers/?filter_color=white');
    const joggers = await page.locator('main .product-card h3').allTextContents();
    assert.ok(joggers.length); assert.ok(joggers.every(name => /Joggers.*White/i.test(name)), joggers.join(', '));
    await go('product-category/hoodies/?filter_color=maroon');
    assert.match(await page.locator('main').innerText(), /No products/i);
  });

  await check('Every segment opens its own category archive, including upcoming ranges', async () => {
    for (const category of structure.categories) {
      for (const segment of category.segments) {
        const slug = segment.toLowerCase().replace(/'/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        await go(`product-category/${category.slug}/${category.slug}-${slug}/`);
        assert.equal((await page.locator('.collection-heading h1').innerText()).replaceAll('’', "'"), segment);
        assert.equal(await page.locator('.collection-heading .eyebrow').innerText(), category.name.toUpperCase());
      }
    }
    await go('product-category/t-shirts/');
    assert.ok(await page.locator('main .product-card').count());
    await go('product-category/diva-wardrobe/');
    assert.equal(await page.locator('.collection-heading h1').innerText(), 'Diva Wardrobe');
    assert.ok(await page.locator('main .product-card').count());
  });

  await check('Mobile subcategory navigation works; layout fits all checked widths', async () => {
    await page.setViewportSize({ width: 390, height: 844 }); await go();
    await page.getByRole('button', { name: 'Open menu', exact: true }).click();
    const hoodie = page.locator('.mobile-nav > li > a').filter({ hasText: /^HOODIES$/ });
    await hoodie.click(); assert.equal(await hoodie.getAttribute('aria-expanded'), 'true');
    assert.equal(await page.locator('.mobile-nav a[href*="filter_color"]').count(), 0);
    const dance = hoodie.locator('..').getByRole('link', { name: 'Dance & Culture', exact: true });
    assert.equal(await dance.isVisible(), true);
    await dance.click(); await page.waitForLoadState('networkidle');
    assert.match(page.url(), /hoodies-dance-culture/);
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 844 });
      for (const path of ['', 'product-category/hoodies/', 'product-category/sweatshirts/', 'product-category/diva-wardrobe/']) {
        await go(path);
        const size = await page.evaluate(() => ({ viewport: innerWidth, content: document.documentElement.scrollWidth }));
        assert.ok(size.content <= size.viewport + 1, JSON.stringify({ width, path, ...size }));
      }
    }
    await page.setViewportSize({ width: 390, height: 844 }); await go();
    await page.locator('main img').evaluateAll(async images => { images.forEach(image => image.loading = 'eager'); await Promise.allSettled(images.map(image => image.decode())); });
    await page.screenshot({ path: `${output}/home-mobile.png`, fullPage: true });
    await page.evaluate(() => scrollTo(0, document.querySelector('.essentials-campaign').getBoundingClientRect().top + scrollY - 85));
    await page.screenshot({ path: `${output}/essentials-mobile.png` });
  });
  assert.deepEqual(errors, []);
  await fs.writeFile(`${output}/verification.json`, JSON.stringify({ checks, errors, date: new Date().toISOString() }, null, 2));
} catch (error) {
  await page.screenshot({ path: `${output}/failure.png`, fullPage: true });
  console.error('Failure at', page.url()); throw error;
} finally { await browser.close(); }
