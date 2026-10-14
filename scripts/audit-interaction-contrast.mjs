import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { existsSync } from 'node:fs';

const base = process.env.STORE_URL || 'http://127.0.0.1:8088';
const executablePath = process.env.BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Users/sakib/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'].find(existsSync);
const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
const page = await context.newPage();
const results = [], manualChecks = [];
const output = 'test-results/contrast';
await fs.mkdir(output, { recursive: true });
async function go(path = '') { const response = await page.goto(`${base}/${path}`, { waitUntil: 'networkidle' }); assert.equal(response.status(), 200); }
const linear = value => value / 255 <= .04045 ? value / 255 / 12.92 : ((value / 255 + .055) / 1.055) ** 2.4;
const luminance = rgb => .2126 * linear(rgb[0]) + .7152 * linear(rgb[1]) + .0722 * linear(rgb[2]);
const ratio = (a, b) => (Math.max(luminance(a), luminance(b)) + .05) / (Math.min(luminance(a), luminance(b)) + .05);
async function audit(name) {
  const report = await new AxeBuilder({ page }).withRules(['color-contrast']).analyze();
  const nodes = report.violations.flatMap(rule => rule.nodes.map(node => ({ target: node.target, message: node.failureSummary })));
  results.push({ name, url: page.url(), violations: nodes });
  console.log(`${nodes.length ? 'FAIL' : 'PASS'} ${name}: ${nodes.length} contrast failures`);
}
async function checkControl(locator, name, property = 'borderTopColor', minimum = 3) {
  const sample = await locator.evaluate((element, property) => {
    const parse = color => { const numbers = color.match(/[\d.]+/g)?.map(Number) || [0, 0, 0, 0]; return [...numbers.slice(0, 3), numbers[3] ?? 1]; };
    const blend = (fg, bg) => [0, 1, 2].map(index => fg[index] * fg[3] + bg[index] * (1 - fg[3]));
    const background = element => { if (!element) return [255, 255, 255]; const style = getComputedStyle(element); const raw = parse(style.backgroundColor); return blend(raw, background(element.parentElement)); };
    const style = getComputedStyle(element), bg = background(property === 'color' ? element : element.parentElement);
    return { foreground: blend(parse(style[property]), bg), background: bg, style: property === 'outlineColor' ? style.outlineStyle : style.borderTopStyle, width: property === 'outlineColor' ? style.outlineWidth : style.borderTopWidth };
  }, property);
  const value = ratio(sample.foreground, sample.background);
  manualChecks.push({ name, type: property, ratio: value, minimum, ...sample });
  if (property !== 'color') assert.notEqual(sample.style, 'none', `${name} needs a visible boundary/focus indicator`);
  assert.ok(value >= minimum, `${name}: ${value.toFixed(2)}:1`);
}
async function inspectBanner(width, name, container, selectors, hideSelector, includeFocus = false) {
  await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 }); await go();
  await page.evaluate(async () => { await document.fonts.ready; scrollTo(0, 0); });
  await page.locator(container).scrollIntoViewIfNeeded();
  await page.locator(container).evaluate(async element => {
    await Promise.all([...element.querySelectorAll('img')].map(image => image.decode().catch(() => {})));
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  });
  await page.screenshot();
  const samples = [];
  for (const selector of selectors) {
    samples.push(await page.locator(selector).evaluate(element => {
      const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT), rects = [];
      while (walker.nextNode()) {
        if (!walker.currentNode.textContent.trim()) continue;
        const range = document.createRange(); range.selectNodeContents(walker.currentNode);
        rects.push(...Array.from(range.getClientRects(), rect => rect.toJSON()).filter(rect => rect.width > 0 && rect.height > 0));
      }
      return { rects, color: getComputedStyle(element).color.match(/[\d.]+/g).map(Number).slice(0, 3), text: element.innerText, minimum: 4.5 };
    }));
  }
  if (includeFocus) samples.push(await page.locator(container).evaluate(element => ({ rects: [element.getBoundingClientRect().toJSON()], color: [255, 255, 255], text: 'Keyboard focus on hero background', minimum: 3 })));
  await page.locator(hideSelector).evaluateAll(elements => elements.forEach(element => { element.dataset.auditVisibility = element.style.visibility; element.style.visibility = 'hidden'; }));
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  const screenshot = await page.screenshot();
  await fs.writeFile(`${output}/${name.toLowerCase()}-background-${width}.png`, screenshot);
  const backgrounds = await page.evaluate(async ({ source, samples }) => {
    const image = new Image(); image.src = source; await image.decode();
    const canvas = document.createElement('canvas'); canvas.width = image.width; canvas.height = image.height;
    const context = canvas.getContext('2d'); context.drawImage(image, 0, 0);
    return samples.map(sample => sample.rects.flatMap(rect => {
      const pixels = context.getImageData(Math.floor(rect.x), Math.floor(rect.y), Math.ceil(rect.width), Math.ceil(rect.height)).data;
      return Array.from({ length: pixels.length / 4 }, (_, index) => [pixels[index * 4], pixels[index * 4 + 1], pixels[index * 4 + 2]]);
    }));
  }, { source: `data:image/png;base64,${screenshot.toString('base64')}`, samples });
  for (let index = 0; index < samples.length; index++) {
    const value = backgrounds[index].reduce((minimum, background) => Math.min(minimum, ratio(samples[index].color, background)), Infinity);
    manualChecks.push({ name: `${name} ${width}: ${samples[index].text}`, type: 'rendered background', ratio: value, minimum: samples[index].minimum });
    assert.ok(value >= samples[index].minimum, `${name} ${width} contrast ${value.toFixed(2)}:1`);
  }
  await page.locator(hideSelector).evaluateAll(elements => elements.forEach(element => { element.style.visibility = element.dataset.auditVisibility; delete element.dataset.auditVisibility; }));
}

try {
  if (!process.argv.includes('--gradients-only')) {
  await go();
  for (const [name, selector] of [
    ['Hero CTA hover', '.drop-hero-copy .button'], ['Essentials banner hover', '.essentials-editorial'],
    ['Joggers banner hover', '.joggers-editorial'], ['Sweatshirts banner hover', '.sweatshirts-editorial'],
    ['Diva banner hover', '.diva-editorial'], ['View all button hover', '.yokai-view-all'],
    ['Product title hover', '.product-title-row a'], ['Footer policy hover', '.yokai-footer ul a'],
  ]) {
    const target = page.locator(selector).first(); await target.hover();
    assert.equal(await target.evaluate(element => getComputedStyle(element).textDecorationLine.includes('underline')), false, name);
    if (selector.includes('editorial')) {
      assert.equal(await target.locator('h2').evaluate(element => getComputedStyle(element).textDecorationLine.includes('underline')), false, name);
    }
    await audit(name);
  }
  await page.evaluate(() => scrollTo(0, 0));
  await checkControl(page.locator('.drop-hero-copy .button'), 'Hero CTA text', 'color', 4.5);
  await page.keyboard.press('Tab');
  const nav = page.locator('.primary-menu > li > a').filter({ hasText: /^HOODIES$/ }); await nav.focus();
  await checkControl(nav, 'Navbar keyboard focus', 'outlineColor'); await page.evaluate(() => document.activeElement.blur());
  for (const selector of ['.header-actions .cart-toggle', '.header-actions .account-toggle']) {
    await page.locator(selector).hover(); await checkControl(page.locator(selector), `${selector} icon hover`, 'color');
  }
  await page.getByRole('button', { name: 'Search products', exact: true }).click();
  await page.locator('#search-dialog input[type="search"]').fill('hoodie'); await page.locator('.predictive-search-results a').first().waitFor();
  await page.locator('.predictive-search-results a').first().hover(); await audit('Predictive search result hover');
  await page.keyboard.press('Escape');
  await go('about-us/');
  for (const index of [0, 1]) { await page.locator('.about-promos > a').nth(index).hover(); await audit(`About promotion ${index + 1} hover`); }
  await go('product/essential-hoodie-black/');
  await page.locator('.auracast-size-chip').filter({ hasText: /^S$/ }).click();
  await page.waitForFunction(() => Number(document.querySelector('[name="variation_id"]').value) > 0);
  await page.locator('[data-product-purchase="single-add"]').hover(); await audit('Product add-to-cart hover');
  await page.locator('[data-product-purchase="single-add"]').click(); await page.locator('#cart-dialog[open]').waitFor();
  await page.locator('#cart-dialog .checkout').hover(); await audit('Cart checkout CTA hover'); await page.keyboard.press('Escape');
  await go('checkout/');
  await checkControl(page.locator('#billing_first_name'), 'Checkout input boundary');
  await checkControl(page.locator('#billing_country'), 'Country selector boundary');
  await checkControl(page.locator('input[type="checkbox"]:visible').first(), 'Checkout checkbox boundary');
  await page.locator('#billing_first_name').focus(); await checkControl(page.locator('#billing_first_name'), 'Checkout keyboard focus', 'outlineColor');
  await page.locator('#place_order').hover(); await audit('Place order CTA hover');
  await go('contact-us/');
  const contact = page.locator('main input:not([type="hidden"]):visible').first();
  if (await contact.count()) {
    await checkControl(contact, 'Contact input boundary');
    await contact.focus(); await checkControl(contact, 'Contact keyboard focus', 'outlineColor');
    await page.locator('main button[type="submit"]').hover(); await audit('Contact submit hover');
  }
  await go('my-account/');
  await checkControl(page.locator('#username'), 'Account input boundary'); await page.locator('#username').focus();
  await checkControl(page.locator('#username'), 'Account keyboard focus', 'outlineColor');
  }
  for (const width of [1440, 390]) {
    await inspectBanner(width, 'Hero', '.drop-hero-copy', ['.drop-hero-copy > .eyebrow', '.drop-hero-copy h1', '.drop-hero-copy > p'], '.drop-hero-copy, .drop-hero-copy *', true);
    await inspectBanner(width, 'Essentials', '.essentials-editorial', ['.essentials-editorial > .eyebrow', '.essentials-editorial h2', '.essentials-editorial > p', '.essentials-editorial .editorial-cta'], '.essentials-editorial > *, .essentials-editorial > * *');
    for (const name of ['Joggers', 'Sweatshirts', 'Diva']) {
      const selector = `.${name.toLowerCase()}-editorial`;
      await inspectBanner(width, name, selector, [`${selector} .eyebrow`, `${selector} h2`, `${selector} p`, `${selector} .editorial-cta`], `${selector} > div, ${selector} > div *`);
    }
  }
  const failures = results.reduce((sum, result) => sum + result.violations.length, 0);
  await fs.writeFile(`${output}/interaction.json`, JSON.stringify({ results, manualChecks, failures, date: new Date().toISOString() }, null, 2));
  console.log(`Audited ${results.length} hover states and ${manualChecks.length} gradient/control checks.`);
  assert.equal(failures, 0, 'Hover text contrast failures remain');
} catch (error) {
  await fs.writeFile(`${output}/interaction-partial.json`, JSON.stringify({ results, manualChecks }, null, 2));
  console.error('Interaction audit stopped at', page.url()); throw error;
} finally { await browser.close(); }
