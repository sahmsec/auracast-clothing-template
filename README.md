# auracast-clothing-template

An installable **Auracast** WordPress theme for WooCommerce, recreating the section order, fashion imagery, typography, staggered collections, and responsive layouts of [the North reference](https://newnorth.wpthemestudios.com/).

## Install on WordPress

1. Install and activate the free **WooCommerce** plugin.
2. In **Appearance → Themes → Add New → Upload Theme**, upload `dist/auracast.zip`, then activate **Auracast**.
3. Open **Appearance → Auracast Setup** and select **Import demo store**. This creates the homepage, shop, cart, checkout, account, wishlist, journal, about, contact, demo products, images, and navigation. Existing products and content are retained; the import can be repeated without creating duplicates.
4. Open **Appearance → Customize → Auracast** to replace demo photography and edit collection content, the announcement, newsletter, contact details, and footer.
5. Configure your currency, shipping, tax, payment gateway, and store policies in **WooCommerce → Settings**. The theme does not configure live payments or claim that demo delivery/return terms are your policies.

The theme is standalone. It does not require the paid North theme, Elementor, WPBakery, or a wishlist plugin. WordPress and WooCommerce handle the catalog, inventory, variations, customer accounts, cart, checkout, order records, and gateway integrations.

## Local development

Source theme: `auracast/`. Development and browser-check scripts: `scripts/`.

```powershell
npm.cmd install
npm.cmd run check
npm.cmd run package
```

The generated theme archive contains the `auracast` folder at its root and can be uploaded directly to WordPress. The isolated `.local/` runtime and test database are excluded from the archive and Git.

## Brand files

- `auracast/assets/images/auracast-logo.svg`: black vector wordmark recreated from the supplied brand reference.
- `auracast/assets/images/auracast-logo-white.svg`: white wordmark for image overlays.
- `auracast/assets/images/auracast-icon.svg`: white Auracast A on a black background.

## Demo assets

Reference photographs are included as demo content because the requested build uses the reference website's imagery. Their original source URLs are listed in `reference/asset-sources.json`; image ownership remains with its respective owner. Use your own licensed product and campaign photography for the production store. Poppins is bundled under its SIL Open Font License.

## Integration approach

WooCommerce functionality uses public hooks and current native templates rather than embedding copied WooCommerce templates. See [WooCommerce classic theme development](https://developer.woocommerce.com/docs/theming/theme-development/classic-theme-developer-handbook/). Newsletter signups are stored in WordPress for CSV export; contact forms use the site's configured WordPress mail transport.
