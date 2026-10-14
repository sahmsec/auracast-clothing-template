# auracast-clothing-template

An installable **AURACAST** WordPress theme for WooCommerce. Home, category, product, cart, checkout, service benefits, and footer layouts follow [Yokai](https://www.yokaiclothingbd.com/), including mobile product rails, filters, size chips, bundles, cart drawers, and checkout summaries. Auracast uses forest green, black and white throughout, with green campaign banners separated by white product grids.

![Auracast storefront](auracast/screenshot.png)

## Install

1. Install and activate **WooCommerce** on WordPress.
2. Upload [dist/auracast.zip](dist/auracast.zip) through **Appearance → Themes → Add New → Upload Theme**, and activate **Auracast**. Alternatively, upload the `auracast/` folder to `wp-content/themes/`.
3. Open **Appearance → Auracast Setup → Import demo store**. Select the **BDT / Bangladesh** option for the demo prices. Keep **classic cart and checkout** selected to use the theme's styled WooCommerce shopping pages; this replaces the content of the configured cart and checkout pages with native shortcodes.
4. Open **Appearance → Customize → Auracast** to update imagery, colors, homepage text, collections, service details, contact information, and footer. Use **Site Identity** for a replacement logo and site icon, and **Menus** for navigation.
5. Configure actual shipping, payments, taxes, policies, and email delivery in WooCommerce/WordPress before opening the store to customers.

The importer adds 30 sample products with size variations and photographs, the clothing categories and curated collections described below, and the homepage, shop, cart, checkout, account, wishlist, about, contact, privacy, shipping, refund, and terms policies, and editable size guide. The older button-down shirt samples stay hidden from the catalog. Repeat imports retain existing prices, photos, variations, and inventory. The homepage and cart/checkout changes are controlled by the setup checkboxes. The importer also adds six Yokai reels as temporary content. No payment gateway is enabled by the theme.

Requires WordPress 6.6+, PHP 8.0+, and WooCommerce. No paid reference theme, page builder, or wishlist plugin is required.

## Automatic GitHub deployment

Push changes to `main` in `sahmsec/auracast-clothing-template`. The **Deploy Auracast to cPanel** workflow validates the theme, runs deployment/rollback tests, builds a ZIP and file checksum manifest, and sends that exact release to the private hosting receiver. GitHub records the deployment under **auracast-production**. Pull requests run validation without hosting credentials.

The configured destination is **https://auracast.sahmsec.online**, with WordPress at `/home/treigxvw/auracast.sahmsec.online`. Releases replace only `wp-content/themes/auracast`. Products, customers, orders, uploaded media, plugins and WordPress configuration stay in the hosted store. The previous theme is retained outside the web root; native verification failures restore it. The workflow also checks the public storefront and exact CSS/JS bytes after installation. An HTTP verification failure is reported in Actions; its retained theme backup remains available for operator recovery.

Hosting uses a separate forced-command SSH identity, a pinned host key and four encrypted **auracast-production** environment secrets: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, and `SSH_KNOWN_HOSTS`. This environment accepts deployments from `main` only. Repository variable `AURACAST_AUTO_DEPLOY=true` enables automatic updates; set it to `false` to stop deployment while keeping validation. Never add credentials or hosting configuration to Git.

For a manual check, open **Actions → Deploy Auracast to cPanel → Run workflow**, select `main`, and choose `plan`. Choose `deploy` to install the current revision. Older queued revisions are rejected when a newer `main` commit exists.

Private hosting tooling and release state live at `/home/treigxvw/auracast-deployment`; previous themes are under its `backups` folder. This is a theme backup, not a database or uploads backup. Retain the accepted backups needed for recovery and manage old backups through cPanel. Receiver upgrades require the operator to update the private PHP scripts separately; ordinary theme pushes do not overwrite this tooling. [deployment/config.php.example](deployment/config.php.example) documents the path configuration.

The installation procedure uses a dedicated cPanel login window once to install the receiver and its restricted public key. `scripts/open-hosting-login.ps1` opens that window on Windows. The deployment key and operator records are private files under ignored `.local/deployment/`; GitHub Actions runs independently of that browser and the development PC afterward.

Run `npm.cmd run test:deployment` locally to check the release allowlist, path/archive refusals, maintenance ownership, actual installation and native-check rollback in disposable fixtures. `npm.cmd run deploy:bundle` produces the ignored deployment ZIP and manifest. `npm.cmd run package` builds the normal installable theme ZIP on Windows or Linux. To move hosts, verify the document root and host key, install the private receiver/config and WP-CLI, authorize a new restricted SSH key, update the environment secrets, and run `plan` before enabling updates. See [GitHub deployment environments](https://docs.github.com/en/actions/concepts/workflows-and-actions/deployment-environments).

## Clothing collections (1.2.0)

Auracast Essentials is the first product row, directly after the dark forest-green hero and its Essentials banner. It is a curated collection for best sellers and wardrobe staples. Diva Wardrobe is the women's collection. T-Shirts remains in the menu and catalog without a dedicated homepage promotion. Navbar dropdowns contain product subcategories; color selection is available through the collection-page filters.

| Category | Colors | Subcategories |
| --- | --- | --- |
| Hoodies | Black, White, Maroon, Brown, Bottle Green, Pink | Anime, Music, Dance & Culture |
| Joggers | Black, Off White, White | CN, Anime, Cyber Sigilism, Hollywood |
| Sweatshirts | Set per product | Bold Y2K Vector Flames, Modern Cyber Sigilism, Owners' Favourites |
| T-Shirts | Set per product | Add as needed |

Manage these through **Products → Categories**. Select both a clothing category and a curated collection when editing a product; each product can belong to Auracast Essentials or Diva Wardrobe as well as its main category. Set its Color attribute through **Products → Attributes / Edit product**. Colors filter the relevant category; each segment has its own category archive. Sweatshirts shows a preview until real products are assigned.

When updating an existing Auracast installation, upload the new ZIP and open the WordPress dashboard once. The theme creates missing categories, subcategories and color terms, updates the original demo menu and reorganizes marked demo products. Merchant products, custom menus, prices, stock, variations and uploaded photos are retained. The default structure is recorded in `auracast/assets/data/store-collections.json`.

For the local preview, sync the theme folder and run `.\.local\php\php.exe -c .local/php/php.ini scripts/apply-store-collections-local.php`. Verify the changes with `npm.cmd run test:collections`.

## Replace demo images and products

Every campaign image can be changed from the WordPress Media Library without editing code.

| Area | Where to edit |
| --- | --- |
| Main hero | Customize → Auracast → Main hero. Replace the two hoodie photographs, or upload a custom full campaign image and optional mobile version. |
| Joggers, sweatshirts, Diva Wardrobe, essentials banners | Customize → Auracast → Category & collection banners. Replace images and edit collection text and links. |
| Collection slider | Customize → Auracast → Shop your world. Five editable images, titles, and destinations. |
| Style gallery | Customize → Auracast → Worn your way gallery. Five editable photographs. |
| Homepage product selections | Customize → Auracast → Homepage product sections. Automatic collections, or choose specific WooCommerce product IDs. |
| Product photos, price, inventory, sizes, colors | Products → Edit product. Use the product image, gallery, attributes, and variations controls. |
| About and newsletter popup images | Customize → Auracast → Contact & store details / Newsletter & popup. |
| Instagram posts and reels | Instagram & reels in the WordPress sidebar. Add a title, paste an Instagram URL, choose/upload a video and cover from Media Library, set the Order number, then Publish. Feed settings control the heading, profile link, speed, and visibility. |
| Size guide | Pages → Size Guide. Its content also appears in the product size guide dialog. |

Use approximately **1600 × 700** or larger for a full hero banner, **1200 × 1500** for portrait product/style photos, and **800 × 800** for collection tiles. Landscape and portrait crops adapt to mobile. All demo photos are bundled locally; no reference site connection is required when the storefront runs.

The default Essentials banner uses editable text on a green background. Upload a banner image to replace it, or clear the image to restore the text layout. Uploaded hero and Essentials banners retain their own colors. Banner titles and descriptions stay free of hover underlines; arrows provide hover feedback.

Published Instagram cards appear automatically in the moving feed. Uploaded videos autoplay muted and open with sound and playback controls when clicked. Link-only cards open the official Instagram embed; adding a video and cover lets the card play directly in the marquee. Edit, draft, trash, or reorder the cards from WordPress. This is an editable feed; new Instagram uploads are added by the store owner.

Wishlist favorites persist in the shopper's browser. Product sizes, inventory, cart, checkout, accounts, and orders use native WooCommerce. Newsletter subscriptions are saved with consent and can be exported as CSV from Auracast Setup. Newsletter campaigns require your chosen email provider. The contact form uses WordPress mail; configure your host's mail service or SMTP.

## Local preview and checks (Windows)

```powershell
npm.cmd install
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/setup-local.ps1
npm.cmd run preview
```

Open **http://127.0.0.1:8088**. The setup downloads an isolated PHP, WordPress, WooCommerce, and SQLite runtime to `.local/`. It uses a separate local database and enables demo cash on delivery, 70 BDT inside-Dhaka delivery, 150 BDT outside-Dhaka delivery, and free delivery above 2,000 BDT or for four-piece bundles. Run `& .local/php/php.exe -c .local/php/php.ini scripts/apply-yokai-local.php` to update an existing local demo. Local administrator credentials are saved in `.local/admin-credentials.json`; they are excluded from Git and the theme package.

The local QA plugin suppresses outgoing mail and temporary stock reservation, whose MySQL row locks are unsupported by the SQLite adapter. Inventory validation and final stock reduction remain enabled. This plugin is excluded from the installable theme. Use standard WordPress MySQL/MariaDB hosting for the production store; live gateways, SMTP delivery, and concurrent inventory reservations require verification there.

In another terminal:

```powershell
npm.cmd run check
npx.cmd playwright install chromium
npm.cmd run test:browser
npm.cmd run test:instagram
npm.cmd run test:collections
npm.cmd run test:contrast
npm.cmd run test:contrast:states
& .local/php/php.exe -c .local/php/php.ini scripts/verify-store.php
node scripts/capture-store.mjs
npm.cmd run package
```

`BROWSER_EXECUTABLE` can point to an existing Chromium installation. Instagram checks cover moving cards, desktop/mobile proportions, playback, reduced motion, native Media Library uploads, publishing, updates, ordering, draft visibility, and feed settings. Browser checks cover homepage assets, collections, quick view, wishlist persistence, search, filters, size selection, cart quantity updates, guest COD orders, native shipping/tip totals, separate billing, policy disclosures, and viewport widths from 320 to 1440 pixels. Test output remains under `test-results/`.

Contrast checks cover 51 desktop/mobile page and dialog states, hover states, visible form boundaries and keyboard focus indicators. The interaction check also samples rendered banner backgrounds, including gradients. Text is checked against 4.5:1 and control boundaries/focus against 3:1. Results are scoped to the demo content and tested states; run these checks again after replacing campaign images or brand colors. This is a contrast audit, not a full accessibility certification.

## Share a temporary preview

With the local setup installed, run this from the project folder:

```powershell
npm.cmd run share
```

The helper downloads the official Windows Cloudflare tunnel client into `.local/`,
starts the local store if needed, and prints a temporary HTTPS link to send to
your client. Keep the terminal open and your PC awake with internet access.
Press **Ctrl+C** to stop sharing. A new session receives a new URL.

The sharing adapter rewrites local WordPress links and media URLs for visitors
without changing your database or local address. WordPress administration and
private database/log files are excluded from the public preview; use
`http://127.0.0.1:8088/wp-admin/` to edit the store. Demo WooCommerce settings and
the local QA mail suppression remain active.

Cloudflare Quick Tunnels are free temporary development previews, with no domain
or Cloudflare account required. See the [official Quick Tunnel guide](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/).

For a permanent site that stays online when your computer is off, install
WordPress and WooCommerce on a web host, upload `dist/auracast.zip`, import the
demo, and configure the actual store settings and domain.

## Brand and demo assets

Vector wordmarks: `auracast/assets/images/auracast-logo.svg` and `auracast-logo-white.svg`. Black-background A icon: `auracast-icon.svg` and a 512-pixel PNG. Poppins is bundled under its SIL Open Font License. Theme code is GPL-2.0-or-later.

The hero, style gallery, about page, plain tee photos, and grey hoodie model photo use seven original AI-generated portraits of adult male and female models in relaxed streetwear poses. These were created with the built-in image generation tool. The complete prompt set and saved asset filenames are recorded in [model-generation-prompts.json](auracast/assets/data/model-generation-prompts.json). Final WebP files are bundled under `auracast/assets/images/`.

The six temporary reels and their cover images come from Yokai Clothing BD, as requested for the preview. Their original Instagram links are recorded in [instagram-demo.json](auracast/assets/data/instagram-demo.json). Replace them through Instagram & reels whenever your own content is ready.

The remaining flat clothing photographs are temporary reference assets. Their source URLs are recorded in `reference/asset-sources.json`; photograph ownership remains with the respective owners. Replace demo clothing and model photos with your actual product photography when the catalog is ready.

WooCommerce integration uses public hooks and native templates, following the [classic theme developer handbook](https://developer.woocommerce.com/docs/theming/theme-development/classic-theme-developer-handbook/).

## Yokai layout update (1.1.0)

Blog links and demo posts are removed from the storefront. Policy and contact pages use the reference layout. WordPress/WooCommerce continue to process cart, accounts, payment methods, inventory, and orders. Checkout accepts email or mobile contact, optional tips, separate billing, discount codes, and optional browser-saved delivery details. Marketing consent is stored on the order; connect your email provider for campaigns.

Bundle prices are calculated from native product prices on every cart update: two pieces 5%, three 10%, four 12%, and five or more 15%. Configure actual delivery and payment methods in WooCommerce; the theme gives configured free-shipping methods precedence and permits four-piece bundles to qualify.

Campaign artwork and service icons are bundled and listed in `auracast/assets/data/campaign-sources.json`; original ownership remains with Yokai and the respective artists. Banner colors shift through CSS; all campaign images remain replaceable in the Customizer. Configure the footer social links and store contact information for your own brand. Poppins weight 800 is bundled under the existing OFL.
