<?php
// Apply the requested storefront changes only to the isolated local demo store.
$root = dirname( __DIR__ ) . '/.local/wordpress/wordpress/';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . 'wp-load.php';
wp_set_current_user( 1 );
update_option( 'page_for_posts', 0 );
update_option( 'woocommerce_currency_pos', 'left' );
update_option( 'woocommerce_allowed_countries', 'specific' );
update_option( 'woocommerce_specific_allowed_countries', array( 'BD' ) );
update_option( 'woocommerce_ship_to_countries', 'specific' );
update_option( 'woocommerce_specific_ship_to_countries', array( 'BD' ) );
$cod = get_option( 'woocommerce_cod_settings', array() );
$cod['title'] = 'Cash on Delivery (COD)'; $cod['description'] = 'Cash on Delivery (COD)';
update_option( 'woocommerce_cod_settings', $cod );
foreach ( array( 'blog', 'a-graphic-tee-your-own-way', 'the-everyday-uniform', 'made-for-your-world' ) as $slug ) {
    $post = get_page_by_path( $slug, OBJECT, $slug === 'blog' ? 'page' : 'post' );
    if ( $post ) { wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ) ); }
}
if ( get_option( 'auracast_yokai_local_version' ) !== '1.1.0' ) {
    $locations = get_theme_mod( 'nav_menu_locations', array() );
    $menu = wp_get_nav_menu_object( 'Auracast Main Menu' );
    if ( $menu && ( empty( $locations['primary'] ) || (int) $locations['primary'] === (int) $menu->term_id ) ) {
        foreach ( wp_get_nav_menu_items( $menu->term_id ) ?: array() as $item ) { wp_delete_post( $item->ID, true ); }
        unset( $locations['primary'] ); set_theme_mod( 'nav_menu_locations', $locations ); auracast_import_menu();
    }
    set_theme_mod( 'auracast_shop_columns', 6 );
    update_option( 'auracast_yokai_local_version', '1.1.0' );
}
$policies = array(
    'refund-policy' => array( 'Refund policy', '<p>Need another size? Request an eligible exchange within 72 hours of receiving your order. Keep the garment unworn and retain its original packaging.</p><p>Contact us with your order details for help with an exchange or an order problem.</p>' ),
    'privacy-policy' => array( 'Privacy policy', '<p>We use the details you provide to process your order, arrange delivery and help with customer support. Checkout information is handled by the store and its payment and delivery providers.</p><p>Newsletter updates are optional. Contact us to ask about the information you have shared with the store.</p>' ),
    'shipping-policy' => array( 'Shipping policy', '<p>Delivery is available across Bangladesh. Inside Dhaka: 2–4 business days. Outside Dhaka: 4–6 business days.</p><p>Choose inside Dhaka (Tk 70) or outside Dhaka (Tk 150) at checkout. Orders above Tk 2,000 qualify for free delivery. The final shipping amount appears before you complete your order.</p>' ),
    'terms-of-service' => array( 'Terms of service', '<p>Choose your product, size and quantity before ordering. Prices and delivery charges are shown before you complete your order.</p><p>Need another size? Contact us to request an eligible exchange within 72 hours of receiving your order. Keep the garment unworn and retain its original packaging.</p><p>For order and product questions, use our contact page.</p>' ),
);
foreach ( $policies as $slug => $policy ) {
    $post = get_page_by_path( $slug );
    if ( ! $post ) { $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $policy[0], 'post_content' => $policy[1] ) ); }
    elseif ( $post->post_status === 'draft' && $slug === 'privacy-policy' ) { $id = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish', 'post_content' => $policy[1] ) ); }
    else { $id = $post->ID; }
    if ( $slug === 'privacy-policy' && ! is_wp_error( $id ) ) { update_option( 'wp_page_for_privacy_policy', $id ); }
}
$zone = new WC_Shipping_Zone( 0 );
$methods = $zone->get_shipping_methods();
$inside = 0; $outside = 0; $free = 0;
foreach ( $methods as $method ) {
    if ( $method->id === 'flat_rate' ) { if ( ! $inside ) { $inside = $method->instance_id; } elseif ( ! $outside ) { $outside = $method->instance_id; } }
    if ( $method->id === 'free_shipping' ) { $free = $method->instance_id; }
}
if ( ! $inside ) { $inside = $zone->add_shipping_method( 'flat_rate' ); }
if ( ! $outside ) { $outside = $zone->add_shipping_method( 'flat_rate' ); }
if ( ! $free ) { $free = $zone->add_shipping_method( 'free_shipping' ); }
foreach ( array( $inside => array( 'INSIDE DHAKA', '70' ), $outside => array( 'OUTSIDE DHAKA', '150' ) ) as $id => $shipping ) { update_option( 'woocommerce_flat_rate_' . $id . '_settings', array( 'title' => $shipping[0], 'tax_status' => 'none', 'cost' => $shipping[1] ) ); }
update_option( 'woocommerce_free_shipping_' . $free . '_settings', array( 'title' => 'FREE SHIPPING', 'requires' => 'min_amount', 'min_amount' => '2000', 'ignore_discounts' => 'no' ) );
WC_Cache_Helper::get_transient_version( 'shipping', true );
flush_rewrite_rules();
echo 'Updated the demo navigation, removed the blog, added policies and configured 70/150 BDT delivery plus free shipping above 2000 BDT.' . PHP_EOL;
