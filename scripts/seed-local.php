<?php
$root = dirname( __DIR__ ) . '/.local/wordpress/wordpress/';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . 'wp-load.php';
wp_set_current_user( 1 );
// Remove only WordPress's default example post in this isolated QA installation.
if ( get_post_field( 'post_name', 1 ) === 'hello-world' ) { wp_trash_post( 1 ); }
auracast_import_pages( true, true );
$catalog = json_decode( file_get_contents( get_template_directory() . '/assets/data/catalog.json' ), true );
foreach ( $catalog as $item ) { auracast_import_product( $item ); echo 'Imported ' . $item['slug'] . PHP_EOL; }
auracast_import_posts(); auracast_import_menu(); auracast_subscribers_table(); flush_rewrite_rules();
// QA store only: native COD permits a checkout test without live money.
update_option( 'woocommerce_currency', 'BDT' ); update_option( 'woocommerce_default_country', 'BD' );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'description' => 'Pay with cash on delivery.', 'instructions' => 'Pay with cash on delivery.', 'enable_for_methods' => array(), 'enable_for_virtual' => 'yes' ) );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_ship_to_countries', 'all' );
update_option( 'woocommerce_default_customer_address', 'base' );
$zone = new WC_Shipping_Zone( 0 );
if ( ! $zone->get_shipping_methods() ) { $method_id = $zone->add_shipping_method( 'flat_rate' ); update_option( 'woocommerce_flat_rate_' . $method_id . '_settings', array( 'title' => 'Standard delivery', 'tax_status' => 'none', 'cost' => '80' ) ); }
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
echo 'Auracast demo store ready: ' . count( $catalog ) . ' products.' . PHP_EOL;
