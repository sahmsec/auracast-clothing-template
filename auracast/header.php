<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main-content"><?php esc_html_e( 'Skip to content', 'auracast' ); ?></a>
<?php if ( auracast_setting( 'announcement_enabled', true ) ) : ?>
<div class="announcement" id="announcement"><span><?php echo esc_html( auracast_setting( 'announcement', 'AURACAST / EVERYDAY STYLE. YOUR OWN WORLD.' ) ); ?></span><button data-dismiss="announcement" aria-label="<?php esc_attr_e( 'Dismiss announcement', 'auracast' ); ?>"><?php echo auracast_icon( 'close' ); ?></button></div>
<?php endif; ?>
<header class="site-header">
    <button class="mobile-menu-toggle icon-button" data-dialog="mobile-menu" aria-label="<?php esc_attr_e( 'Open menu', 'auracast' ); ?>"><?php echo auracast_icon( 'menu' ); ?></button>
    <nav class="desktop-navigation" aria-label="<?php esc_attr_e( 'Main navigation', 'auracast' ); ?>"><?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'primary-menu', 'fallback_cb' => 'auracast_fallback_menu', 'depth' => 3 ) ); ?></nav>
    <div class="header-brand"><div class="dark-logo"><?php echo auracast_logo(); ?></div><div class="light-logo"><?php echo auracast_logo( true ); ?></div></div>
    <div class="header-actions"><button class="search-toggle" data-dialog="search-dialog"><span><?php esc_html_e( 'Search', 'auracast' ); ?></span><?php echo auracast_icon( 'search' ); ?></button><a class="cart-toggle" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" data-dialog="cart-dialog"><span class="cart-label"><?php esc_html_e( 'Cart', 'auracast' ); ?></span><?php echo auracast_icon( 'bag' ); ?><span class="cart-count"><?php echo class_exists( 'WooCommerce' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?></span></a></div>
</header>
