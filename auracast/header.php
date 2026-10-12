<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main-content"><?php esc_html_e( 'Skip to content', 'auracast' ); ?></a>
<?php if ( auracast_setting( 'announcement_enabled', true ) ) : ?>
<div class="announcement yokai-announcement" id="announcement"><div class="announcement-track"><?php for ( $i = 0; $i < 6; $i++ ) : ?><span <?php echo $i ? 'aria-hidden="true"' : ''; ?>><?php echo esc_html( auracast_setting( 'announcement', 'FREE SHIPPING ON ORDERS ABOVE 2000 TK !!!' ) ); ?></span><?php endfor; ?></div></div>
<?php endif; ?>
<header class="site-header">
    <button class="mobile-menu-toggle icon-button" data-dialog="mobile-menu" aria-label="<?php esc_attr_e( 'Open menu', 'auracast' ); ?>"><?php echo auracast_icon( 'menu' ); ?></button>
    <div class="header-brand"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Auracast home" class="header-icon-brand"><img src="<?php echo esc_url( auracast_asset( 'images/auracast-icon.svg' ) ); ?>" width="40" height="40" alt="AURACAST"></a></div>
    <nav class="desktop-navigation" aria-label="<?php esc_attr_e( 'Main navigation', 'auracast' ); ?>"><?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'primary-menu', 'fallback_cb' => 'auracast_fallback_menu', 'depth' => 3 ) ); ?></nav>
    <div class="header-actions"><button class="search-toggle icon-button" data-dialog="search-dialog" aria-label="Search products"><?php echo auracast_icon( 'search' ); ?></button><a class="account-toggle icon-button" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ); ?>" aria-label="My account"><?php echo auracast_icon( 'account' ); ?></a><a class="cart-toggle icon-button" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" data-dialog="cart-dialog" aria-label="Open cart"><?php echo auracast_icon( 'bag' ); ?><span class="cart-count"><?php echo class_exists( 'WooCommerce' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?></span></a></div>
</header>
