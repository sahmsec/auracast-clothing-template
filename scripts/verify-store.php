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
foreach ( array( 'hero_image', 'hero_secondary_image', 'tees_banner_image', 'sweats_banner_image', 'music_banner_image', 'anime_banner_image', 'essentials_banner_image', 'world_0_image', 'world_5_image', 'look_0_image', 'look_4_image', 'about_0_image', 'popup_image', 'color_hero', 'hero_layout', 'popular_products' ) as $key ) { verify( (bool) $manager->get_control( 'auracast_' . $key ), 'Customizer control: ' . $key ); }
$changes = array( 'auracast_hero_title' => 'CUSTOMIZER QA HEADING', 'auracast_hero_image' => auracast_asset( 'images/streetwear-essential-tee-forest-0.jpg' ), 'auracast_color_hero' => '#c9dbc4' );
$saved = get_theme_mods();
try {
    foreach ( $changes as $key => $value ) { set_theme_mod( $key, $value ); }
    $html = file_get_contents( 'http://127.0.0.1:8088/' );
    verify( str_contains( $html, 'CUSTOMIZER QA HEADING' ) && str_contains( $html, '--a-hero:#c9dbc4' ) && preg_match( '/class="campaign-image"[^>]+streetwear-essential-tee-forest-0.jpg/', $html ), 'Saved hero image, heading and background color render on the storefront' );
} finally {
    foreach ( $changes as $key => $value ) { if ( array_key_exists( $key, $saved ) ) { set_theme_mod( $key, $saved[$key] ); } else { remove_theme_mod( $key ); } }
}
global $wpdb;
verify( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}auracast_subscribers WHERE consent = 'newsletter-form-v1'" ) > 0, 'Newsletter signup is persisted with consent' );
$orders = wc_get_orders( array( 'limit' => -1, 'billing_email' => 'auracast-qa@example.test', 'status' => 'processing' ) );
verify( count( $orders ) > 0, 'Guest checkout order exists in WooCommerce' );
$product = auracast_product_by_slug( 'essential-tee-black' );
$size_m = array_filter( $product->get_children(), function ( $id ) { return wc_get_product( $id )->get_attribute( 'pa_size' ) === 'M'; } );
$variation = wc_get_product( reset( $size_m ) ); $ordered = 0;
foreach ( $orders as $order ) { foreach ( $order->get_items() as $item ) { if ( $item->get_variation_id() === $variation->get_id() ) { $ordered += $item->get_quantity(); } } }
verify( (int) $variation->get_stock_quantity() === 15 - $ordered, 'Guest order reduces the selected size inventory' );
