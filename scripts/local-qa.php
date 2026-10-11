<?php
// Only copied into the isolated QA installation. Never included in the theme.
defined( 'ABSPATH' ) || exit;
if ( wp_get_environment_type() === 'local' && defined( 'DB_ENGINE' ) && DB_ENGINE === 'sqlite' ) {
    // WooCommerce's MySQL row locks are unsupported by the SQLite development adapter.
    // Native inventory validation and final stock reduction remain enabled.
    add_filter( 'woocommerce_order_hold_stock_minutes', '__return_zero' );
    // Test orders and the contact form must not send email from the local QA store.
    add_filter( 'pre_wp_mail', '__return_true' );
}
