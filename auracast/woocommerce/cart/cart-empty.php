<?php
/** Empty cart. WooCommerce template version 7.0.1. */
defined( 'ABSPATH' ) || exit;
?>
<div class="auracast-empty-cart"><h1>Your cart is empty</h1><a class="button" href="<?php echo esc_url( auracast_shop_url() ); ?>">Continue shopping</a><?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?><h2>Have an account?</h2><p><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">Log in</a> to check out faster.</p><?php endif; ?></div>
