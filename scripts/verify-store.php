<?php
// Integration checks against the isolated project-local WordPress installation.
$root = dirname( __DIR__ ) . '/.local/wordpress/wordpress/';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . 'wp-load.php'; wp_set_current_user( 1 );
function verify( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } echo 'PASS ' . $message . PHP_EOL; }
$catalog = json_decode( file_get_contents( get_template_directory() . '/assets/data/catalog.json' ), true );
$before = count( wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) ) );
foreach ( $catalog as $item ) { auracast_import_product( $item ); }
verify( $before === 30 && count( wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) ) ) === 30, 'Repeated demo import preserves 30 products without duplicates' );
verify( count( wc_get_products( array( 'tag' => array( 'music-culture' ), 'limit' => -1 ) ) ) === 5, 'Music collection has the expected products' );
$hoodie = auracast_product_by_slug( 'essential-hoodie-grey' ); verify( $hoodie && $hoodie->is_on_sale(), 'Variable product sale prices are active' );
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$manager = new WP_Customize_Manager( array( 'settings_previewed' => false ) );
do_action( 'customize_register', $manager );
foreach ( array( 'hero_image', 'hero_mobile_image', 'hero_secondary_image', 'tees_banner_image', 'sweats_banner_image', 'music_banner_image', 'anime_banner_image', 'essentials_banner_image', 'world_0_image', 'world_5_image', 'look_0_image', 'look_4_image', 'about_0_image', 'popup_image', 'color_hero', 'color_accent', 'color_header', 'campaign_hue', 'hero_layout', 'popular_products' ) as $key ) { verify( (bool) $manager->get_control( 'auracast_' . $key ), 'Customizer control: ' . $key ); }
$changes = array( 'auracast_hero_title' => 'CUSTOMIZER QA HEADING', 'auracast_hero_image' => auracast_asset( 'images/auracast-model-forest-tee-01.webp' ), 'auracast_color_accent' => '#c9dbc4' );
$saved = get_theme_mods();
try {
    foreach ( $changes as $key => $value ) { set_theme_mod( $key, $value ); }
    $html = file_get_contents( 'http://127.0.0.1:8088/' );
    verify( str_contains( $html, 'CUSTOMIZER QA HEADING' ) && str_contains( $html, '--a-accent:#c9dbc4' ) && preg_match( '/class="campaign-image"[^>]+auracast-model-forest-tee-01.webp/', $html ), 'Saved campaign image, heading and brand color render on the storefront' );
} finally {
    foreach ( $changes as $key => $value ) { if ( array_key_exists( $key, $saved ) ) { set_theme_mod( $key, $saved[$key] ); } else { remove_theme_mod( $key ); } }
}
verify( (int) get_option( 'page_for_posts' ) === 0, 'Blog is not configured as a storefront section' );
foreach ( array( 'privacy-policy', 'shipping-policy', 'refund-policy', 'terms-of-service' ) as $slug ) { $page = get_page_by_path( $slug ); verify( $page && $page->post_status === 'publish', 'Published store policy: ' . $slug ); }
$orders = wc_get_orders( array( 'limit' => 100, 'status' => 'processing', 'orderby' => 'date', 'order' => 'DESC' ) );
$qa_orders = array_filter( $orders, function ( $order ) { return str_starts_with( $order->get_billing_email(), 'auracast-yokai-qa-' ); } );
verify( count( $qa_orders ) > 0, 'Matching storefront guest COD order exists in WooCommerce' );
$order = reset( $qa_orders );
verify( $order->get_payment_method() === 'cod' && abs( (float) $order->get_total() - 569 ) < .01, 'Native COD order stores the expected 499 BDT product and 70 BDT delivery' );
foreach ( $order->get_items() as $item ) { $variation = wc_get_product( $item->get_variation_id() ); verify( $variation && (int) $variation->get_stock_quantity() < 15, 'Guest order reduces the selected size inventory' ); }
$mobile_orders = array_filter( $orders, function ( $order ) { return $order->get_meta( '_auracast_contact' ) === '01701111111'; } );
verify( count( $mobile_orders ) > 0, 'Mobile contact checkout creates a real WooCommerce order' );
$mobile = reset( $mobile_orders );
verify( $mobile->get_billing_address_1() !== $mobile->get_shipping_address_1() && abs( (float) $mobile->get_total() - 679 ) < .01, 'Separate billing, outside Dhaka delivery and 30 BDT tip are persisted' );
