# auracast-clothing-template

An installable **AURACAST** WordPress theme for WooCommerce. The clothing collections and merchandising follow [Yokai](https://www.yokaiclothingbd.com/), with the centered navigation, dropdowns, quick view, and drawers inspired by [North](https://newnorth.wpthemestudios.com/). Auracast uses its own charcoal, sage, cream, and forest palette, new banner compositions, and the supplied brand wordmark.

![Auracast storefront](auracast/screenshot.png)

## Install

1. Install and activate **WooCommerce** on WordPress.
2. Upload [dist/auracast.zip](dist/auracast.zip) through **Appearance → Themes → Add New → Upload Theme**, and activate **Auracast**. Alternatively, upload the `auracast/` folder to `wp-content/themes/`.
3. Open **Appearance → Auracast Setup → Import demo store**. Select the **BDT / Bangladesh** option for the demo prices. Keep **classic cart and checkout** selected to use the theme's styled WooCommerce shopping pages; this replaces the content of the configured cart and checkout pages with native shortcodes.
4. Open **Appearance → Customize → Auracast** to update imagery, colors, homepage text, collections, service details, contact information, and footer. Use **Site Identity** for a replacement logo and site icon, and **Menus** for navigation.
5. Configure actual shipping, payments, taxes, policies, and email delivery in WooCommerce/WordPress before opening the store to customers.

The importer adds 30 clothing products with size variations, photographs, five clothing categories, Anime and Music & Culture collections, and the homepage, shop, cart, checkout, account, wishlist, journal, about, contact, and editable size guide. Repeat imports retain existing products, images, and menus. The homepage and cart/checkout changes are controlled by the setup checkboxes. No payment gateway is enabled by the theme.

Requires WordPress 6.6+, PHP 8.0+, and WooCommerce. No paid reference theme, page builder, or wishlist plugin is required.

## Replace demo images and products

Every campaign image can be changed from the WordPress Media Library without editing code.

| Area | Where to edit |
| --- | --- |
| Main hero | Customize → Auracast → Main hero. Replace both photographs, or select **Full campaign image** and upload one banner. |
| Graphic tees, sweatpants, music, anime, essentials banners | Customize → Auracast → Category & collection banners. Edit images, headings, labels, buttons, and links. |
| Collection slider | Customize → Auracast → Shop your world. Six editable images, titles, and destinations. |
| Style gallery | Customize → Auracast → Worn your way gallery. Five editable photographs. |
| Homepage product selections | Customize → Auracast → Homepage product sections. Automatic collections, or choose specific WooCommerce product IDs. |
| Product photos, price, inventory, sizes, colors | Products → Edit product. Use the product image, gallery, attributes, and variations controls. |
| About and newsletter popup images | Customize → Auracast → Contact & store details / Newsletter & popup. |
| Size guide | Pages → Size Guide. Its content also appears in the product size guide dialog. |

Use approximately **1600 × 700** or larger for a full hero banner, **1200 × 1500** for portrait product/style photos, and **800 × 800** for collection tiles. Landscape and portrait crops adapt to mobile. All demo photos are bundled locally; no reference site connection is required when the storefront runs.

Wishlist favorites persist in the shopper's browser. Product sizes, inventory, cart, checkout, accounts, and orders use native WooCommerce. Newsletter subscriptions are saved with consent and can be exported as CSV from Auracast Setup. Newsletter campaigns require your chosen email provider. The contact form uses WordPress mail; configure your host's mail service or SMTP.

## Local preview and checks (Windows)

```powershell
npm.cmd install
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/setup-local.ps1
npm.cmd run preview
```

Open **http://127.0.0.1:8088**. The setup downloads an isolated PHP, WordPress, WooCommerce, and SQLite runtime to `.local/`. It uses a separate local database and enables demo cash on delivery and an 80 BDT shipping rate for checkout verification. Local administrator credentials are saved in `.local/admin-credentials.json`; they are excluded from Git and the theme package.

The local QA plugin suppresses outgoing mail and temporary stock reservation, whose MySQL row locks are unsupported by the SQLite adapter. Inventory validation and final stock reduction remain enabled. This plugin is excluded from the installable theme. Use standard WordPress MySQL/MariaDB hosting for the production store; live gateways, SMTP delivery, and concurrent inventory reservations require verification there.

In another terminal:

```powershell
npm.cmd run check
npx.cmd playwright install chromium
npm.cmd run test:browser
& .local/php/php.exe -c .local/php/php.ini scripts/verify-store.php
node scripts/capture-store.mjs
npm.cmd run package
```

`BROWSER_EXECUTABLE` can point to an existing Chromium installation. Browser checks cover homepage assets, collections, quick view, wishlist persistence, search, filters, size selection, cart quantity updates, a guest COD order, newsletter signup, nonce validation, and viewport widths from 320 to 1440 pixels. Test output remains under `test-results/`.

## Brand and demo assets

Vector wordmarks: `auracast/assets/images/auracast-logo.svg` and `auracast-logo-white.svg`. Black-background A icon: `auracast-icon.svg` and a 512-pixel PNG. Poppins is bundled under its SIL Open Font License. Theme code is GPL-2.0-or-later.

Reference photographs are temporary demo assets for this build. Their source URLs are recorded in `reference/asset-sources.json`; photograph ownership remains with the respective owners. Replace demo photos with your own product and campaign imagery when the catalog is ready. No AI-generated campaign image is used in this version.

WooCommerce integration uses public hooks and native templates, following the [classic theme developer handbook](https://developer.woocommerce.com/docs/theming/theme-development/classic-theme-developer-handbook/).
