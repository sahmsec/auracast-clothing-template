<?php
defined( 'ABSPATH' ) || exit;

function auracast_update_mini_cart() {
    check_ajax_referer( 'auracast_public', 'nonce' );
    if ( ! WC()->cart ) { wc_load_cart(); }
    $key = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
    $quantity = isset( $_POST['quantity'] ) ? filter_var( $_POST['quantity'], FILTER_VALIDATE_INT ) : false;
    $item = WC()->cart->get_cart_item( $key );
    if ( ! $item || $quantity === false || $quantity < 0 || $quantity > 100000 ) { wp_send_json_error( array( 'message' => 'Choose a valid cart quantity.' ), 400 ); }
    $product = $item['data'];
    if ( $product->is_sold_individually() && $quantity > 1 ) { wp_send_json_error( array( 'message' => 'This product can only be bought individually.' ), 400 ); }
    if ( $quantity === 0 ) { WC()->cart->remove_cart_item( $key ); }
    else {
        $old_quantity = $item['quantity'];
        WC()->cart->set_quantity( $key, $quantity, false );
        $stock = WC()->cart->check_cart_item_stock();
        if ( is_wp_error( $stock ) ) { WC()->cart->set_quantity( $key, $old_quantity, false ); wp_send_json_error( array( 'message' => wp_strip_all_tags( $stock->get_error_message() ) ), 400 ); }
    }
    WC()->cart->calculate_totals();
    WC_AJAX::get_refreshed_fragments();
}
add_action( 'wp_ajax_auracast_update_mini_cart', 'auracast_update_mini_cart' );
add_action( 'wp_ajax_nopriv_auracast_update_mini_cart', 'auracast_update_mini_cart' );
