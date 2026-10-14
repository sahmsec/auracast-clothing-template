import fs from 'node:fs/promises';
import crypto from 'node:crypto';
import assert from 'node:assert/strict';
const base = 'https://auracast.sahmsec.online';
const release = JSON.parse(await fs.readFile('dist/deploy-header.json', 'utf8'));
async function request(path, options = {}) {
  const response = await fetch(`${base}/${path}`, { ...options, signal: AbortSignal.timeout(45000), headers: { 'Cache-Control': 'no-cache', 'User-Agent': 'Auracast-release-verification', ...options.headers } });
  assert.equal(response.status, 200, `Unexpected HTTP status for ${path}`);
  return response;
}
for (let attempt = 0; ; attempt++) {
  try {
    const html = await (await request(`?auracast_release=${release.commit}`)).text();
    assert.match(html, /wp-content\/themes\/auracast\/assets\/css\/yokai\.css/);
    assert.ok(html.includes(`ver=${release.version}`), 'Homepage has not loaded this theme version.');
    assert.doesNotMatch(html, /Fatal error:|Parse error:|There has been a critical error|http:\/\/(?:localhost|127\.0\.0\.1)/i);
    for (const file of ['style.css', 'assets/css/yokai.css', 'assets/css/store.css', 'assets/js/store.js']) {
      const bytes = Buffer.from(await (await request(`wp-content/themes/auracast/${file}?auracast_release=${release.commit}`)).arrayBuffer());
      assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), release.files[file].sha256, `Live bytes differ: ${file}`);
    }
    await request('shop/'); await request('cart/');
    const cart = await (await request('?wc-ajax=get_refreshed_fragments', { method: 'POST' })).json();
    assert.equal(typeof cart.fragments, 'object');
    assert.ok(Object.keys(cart.fragments).length > 0);
    console.log(`Verified HTTPS storefront, exact theme assets and native WooCommerce cart for ${release.commit}.`);
    break;
  } catch (error) {
    if (attempt >= 3) throw error;
    await new Promise(resolve => setTimeout(resolve, 2000));
  }
}
