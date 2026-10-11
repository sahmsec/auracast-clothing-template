<?php
/** Auracast theme bootstrap. */
defined( 'ABSPATH' ) || exit;

define( 'AURACAST_VERSION', '1.0.1' );
require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/woocommerce.php';
require_once get_template_directory() . '/inc/forms.php';
require_once get_template_directory() . '/inc/setup.php';

add_action( 'after_setup_theme', function () {
    load_theme_textdomain( 'auracast', get_template_directory() . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'custom-logo', array( 'height' => 90, 'width' => 720, 'flex-width' => true, 'flex-height' => true ) );
    add_theme_support( 'woocommerce', array( 'thumbnail_image_width' => 600, 'single_image_width' => 900, 'product_grid' => array( 'default_columns' => 4, 'min_columns' => 2, 'max_columns' => 6, 'default_rows' => 4 ) ) );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
    register_nav_menus( array( 'primary' => __( 'Main navigation', 'auracast' ), 'footer' => __( 'Footer navigation', 'auracast' ) ) );
    add_image_size( 'auracast-card', 600, 800, false );
    add_image_size( 'auracast-journal', 860, 529, array( 'center', 'top' ) );
    $GLOBALS['content_width'] = 1340;
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'auracast-fonts', auracast_asset( 'fonts/fonts.css' ), array(), AURACAST_VERSION );
    wp_enqueue_style( 'auracast-store', auracast_asset( 'css/store.css' ), array( 'auracast-fonts' ), AURACAST_VERSION );
    wp_enqueue_style( 'auracast-streetwear', auracast_asset( 'css/streetwear.css' ), array( 'auracast-store' ), AURACAST_VERSION );
    wp_enqueue_script( 'auracast-store', auracast_asset( 'js/store.js' ), array(), AURACAST_VERSION, true );
    wp_localize_script( 'auracast-store', 'auracast', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'auracast_public' ),
        'wishlistUrl' => home_url( '/wishlist/' ),
        'popupEnabled' => (bool) get_theme_mod( 'auracast_popup_enabled', false ),
        'popupDelay' => 8000,
        'labels' => array( 'saved' => __( 'Saved to your wishlist', 'auracast' ), 'removed' => __( 'Removed from your wishlist', 'auracast' ), 'error' => __( 'Something went wrong. Please try again.', 'auracast' ) ),
    ) );
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_script( 'wc-add-to-cart' );
        wp_enqueue_script( 'wc-cart-fragments' );
        if ( is_product() ) { wp_enqueue_script( 'wc-add-to-cart-variation' ); }
    }
} );

add_filter( 'body_class', function ( $classes ) {
    if ( is_front_page() ) { $classes[] = 'auracast-home'; }
    $classes[] = 'auracast-streetwear';
    if ( class_exists( 'WooCommerce' ) && ( is_shop() || is_product_taxonomy() ) ) {
        $classes[] = 'catalog-layout-' . auracast_shop_layout();
        $classes[] = 'catalog-columns-' . auracast_shop_columns();
    }
    return $classes;
} );

add_action( 'wp_head', function () {
    if ( ! has_site_icon() ) {
        echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( auracast_asset( 'images/auracast-icon.svg' ) ) . '">';
    }
}, 2 );

add_action( 'widgets_init', function () {
    register_sidebar( array( 'name' => __( 'Shop filters', 'auracast' ), 'id' => 'shop-filters', 'before_widget' => '<section class="filter-widget">', 'after_widget' => '</section>', 'before_title' => '<h3>', 'after_title' => '</h3>' ) );
} );
