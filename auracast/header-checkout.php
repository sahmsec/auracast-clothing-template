<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class( 'auracast-checkout-page' ); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main-content"><?php esc_html_e( 'Skip to content', 'auracast' ); ?></a>
<header class="checkout-header"><div class="checkout-header-inner"><a class="checkout-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Auracast home', 'auracast' ); ?>"><img src="<?php echo esc_url( auracast_asset( 'images/auracast-icon.svg' ) ); ?>" width="62" height="62" alt="AURACAST"></a><a class="checkout-cart-link" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" aria-label="<?php esc_attr_e( 'Return to cart', 'auracast' ); ?>"><?php echo auracast_icon( 'bag' ); ?></a></div></header>
