import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { existsSync } from 'node:fs';

const base = process.env.STORE_URL || 'http://127.0.0.1:8088';
const phase = process.argv.find(arg => arg.startsWith('--phase='))?.split('=')[1] || 'after';
const reportOnly = process.argv.includes('--report-only');
const desktopOnly = process.argv.includes('--desktop-only');
const executablePath = process.env.BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'].find(existsSync);
const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
const page = await context.newPage();
const output = 'test-results/contrast';
await fs.mkdir(output, { recursive: true });
const results = [], errors = [];
page.on('pageerror', error => errors.push(error.message));
page.setDefaultTimeout(20000);
async function go(path = '') {
  const response = await page.goto(`${base}/${path}`, { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200, path);
}
async function audit(name) {
  const analysis = await new AxeBuilder({ page }).withRules(['color-contrast']).analyze();
  const result = {
    name, url: page.url(), width: page.viewportSize().width,
    violations: analysis.violations.flatMap(rule => rule.nodes.map(node => ({ target: node.target, html: node.html, message: node.failureSummary, checks: node.any.map(check => check.data) }))),
    review: analysis.incomplete.flatMap(rule => rule.nodes.map(node => ({ target: node.target, message: node.failureSummary, checks: node.any.map(check => check.data) }))),
  };
  results.push(result);
  console.log(`${result.violations.length ? 'FAIL' : 'PASS'} ${name}: ${result.violations.length} contrast failures; ${result.review.length} manual-review nodes`);
}
const routes = [
  ['Home', ''], ['Shop', 'shop/'], ['Hoodies', 'product-category/hoodies/'],
  ['Joggers', 'product-category/joggers/'], ['Sweatshirts', 'product-category/sweatshirts/'],
  ['Diva', 'product-category/diva-wardrobe/'], ['T-Shirts', 'product-category/t-shirts/'],
  ['Hoodie product', 'product/essential-hoodie-black/'], ['Tee product', 'product/essential-tee-black/'],
  ['About', 'about-us/'], ['Contact', 'contact-us/'], ['Account', 'my-account/'],
  ['Privacy', 'privacy-policy/'], ['Shipping', 'shipping-policy/'], ['Refunds', 'refund-policy/'],
  ['Terms', 'terms-of-service/'], ['Size guide', 'size-guide/'], ['Wishlist', 'wishlist/'],
];
try {
  for (const width of desktopOnly ? [1440] : [1440, 390]) {
    await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
    for (const [name, path] of routes) { await go(path); await audit(`${name} ${width}`); }
    await go();
    if (width === 1440) {
      await page.locator('.primary-menu > li > a').filter({ hasText: /^HOODIES$/ }).hover();
      await audit('Desktop navbar dropdown');
      await page.mouse.move(1000, 600);
    } else {
      await page.getByRole('button', { name: 'Open menu', exact: true }).click();
      await page.locator('.mobile-nav > li > a').filter({ hasText: /^HOODIES$/ }).click();
      await audit('Mobile expanded menu');
      await page.keyboard.press('Escape');
    }
    await page.getByRole('button', { name: 'Search products', exact: true }).click();
    await page.locator('#search-dialog input[type="search"]').fill('hoodie');
    await page.locator('.predictive-search-results a').first().waitFor();
    await audit(`Search results ${width}`); await page.keyboard.press('Escape');
    await go('product-category/hoodies/');
    if (width === 1440) {
      await page.locator('.collection-facet').filter({ has: page.locator('summary').filter({ hasText: /^Price/ }) }).locator('summary').click();
      await audit('Desktop price filter');
    } else {
      await page.locator('[data-dialog="filter-dialog"]').click();
      await page.locator('.mobile-facet').filter({ has: page.locator('summary').filter({ hasText: /^Color/ }) }).locator('summary').click();
      await audit('Mobile color filter'); await page.keyboard.press('Escape');
    }
    await go('product/essential-hoodie-black/');
    await page.locator('[data-dialog="size-guide-dialog"]').click(); await audit(`Size dialog ${width}`); await page.keyboard.press('Escape');
    await page.locator('.auracast-size-chip').filter({ hasText: /^S$/ }).click();
    await page.waitForFunction(() => Number(document.querySelector('[name="variation_id"]').value) > 0);
    await page.locator('[data-product-purchase="single-add"]').click();
    await page.locator('#cart-dialog[open]').waitFor(); await audit(`Populated cart drawer ${width}`); await page.keyboard.press('Escape');
    await go('cart/'); await audit(`Populated cart ${width}`);
    await go('checkout/'); await audit(`Checkout ${width}`);
    if (width === 390) {
      await page.locator('.checkout-summary-toggle').click(); await audit('Mobile expanded checkout summary');
    }
  }
  const failures = results.reduce((sum, result) => sum + result.violations.length, 0);
  await fs.writeFile(`${output}/${phase}.json`, JSON.stringify({ phase, date: new Date().toISOString(), results, failures, errors }, null, 2));
  console.log(`Audited ${results.length} page/state combinations: ${failures} contrast failures.`);
  if (!reportOnly) { assert.equal(failures, 0, 'Text contrast issues remain; see test-results/contrast/after.json'); assert.deepEqual(errors, []); }
} catch (error) {
  await fs.writeFile(`${output}/${phase}-partial.json`, JSON.stringify({ results, errors }, null, 2));
  console.error('Audit stopped at', page.url()); throw error;
} finally { await browser.close(); }
