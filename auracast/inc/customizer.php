<?php
defined( 'ABSPATH' ) || exit;
function auracast_palette() { return array( 'hero' => '#dce4dc', 'hero-ink' => '#19291f', 'tees' => '#e3dccd', 'sweats' => '#c7d4cc', 'music' => '#dce5e8', 'anime' => '#253b32', 'essentials' => '#1d2c24', 'newsletter' => '#ecece5', 'accent' => '#305640' ); }
add_action( 'customize_register', function ( $wp_customize ) {
    $wp_customize->add_panel( 'auracast', array( 'title' => 'Auracast', 'priority' => 30 ) );
    foreach ( array( 'branding' => 'Brand, colors & announcement', 'hero' => 'Main hero', 'campaigns' => 'Category & collection banners', 'products' => 'Homepage product sections', 'worlds' => 'Shop your world', 'community' => 'Worn your way gallery', 'benefits' => 'Service benefits', 'newsletter' => 'Newsletter & popup', 'shop' => 'Shop layout', 'contact' => 'Contact & store details' ) as $key => $title ) { $wp_customize->add_section( 'auracast_' . $key, array( 'title' => $title, 'panel' => 'auracast' ) ); }
    $add = function ( $section, $key, $label, $default, $type = 'text', $choices = array() ) use ( $wp_customize ) {
        $sanitize = $type === 'checkbox' ? function ( $v ) { return (bool) $v; } : ( $type === 'color' ? 'sanitize_hex_color' : ( in_array( $type, array( 'image', 'url' ), true ) ? 'esc_url_raw' : ( $type === 'textarea' ? 'sanitize_textarea_field' : 'sanitize_text_field' ) ) );
        $wp_customize->add_setting( 'auracast_' . $key, array( 'default' => $default, 'sanitize_callback' => $sanitize ) );
        $args = array( 'label' => $label, 'section' => 'auracast_' . $section, 'type' => $type, 'choices' => $choices );
        if ( $type === 'image' ) { $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'auracast_' . $key, $args ) ); }
        elseif ( $type === 'color' ) { $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'auracast_' . $key, $args ) ); }
        else { $wp_customize->add_control( 'auracast_' . $key, $args ); }
    };
    $add( 'branding', 'announcement_enabled', 'Show announcement', true, 'checkbox' );
    $add( 'branding', 'announcement', 'Announcement', 'AURACAST / EVERYDAY STYLE. YOUR OWN WORLD.' );
    $add( 'branding', 'copyright', 'Footer copyright', 'Auracast. All Rights Reserved.' );
    $add( 'branding', 'payment_icons_enabled', 'Show payment logos instead of the footer wordmark', false, 'checkbox' );
    $add( 'branding', 'payment_icons_image', 'Your payment logo image', auracast_asset( 'images/payments.svg' ), 'image' );
    foreach ( auracast_palette() as $key => $color ) { $add( 'branding', 'color_' . $key, ucwords( str_replace( '-', ' ', $key ) ) . ' color', $color, 'color' ); }
    $add( 'hero', 'hero_layout', 'Image layout', 'split', 'select', array( 'split' => 'Two product photographs', 'banner' => 'Full campaign image' ) );
    $add( 'hero', 'hero_image', 'Main image (full banner: 1600 × 700 or larger)', auracast_asset( 'images/auracast-model-black-tee-01.webp' ), 'image' );
    $add( 'hero', 'hero_image_alt', 'Image description', 'A man in a relaxed pose wearing a black oversized tee' );
    $add( 'hero', 'hero_secondary_enabled', 'Show second photo in split layout', true, 'checkbox' );
    $add( 'hero', 'hero_secondary_image', 'Second image', auracast_asset( 'images/auracast-model-white-tee-01.webp' ), 'image' );
    $add( 'hero', 'hero_secondary_alt', 'Second image description', 'A woman in a relaxed pose wearing a white oversized tee' );
    $add( 'hero', 'hero_eyebrow', 'Small label', 'THE LATEST DROP · AURACAST' );
    $add( 'hero', 'hero_title', 'Heading (one line per row)', "WEAR YOUR\nWORLD.", 'textarea' );
    $add( 'hero', 'hero_description', 'Description', 'Graphic culture. Everyday comfort. Made for your world.', 'textarea' );
    $add( 'hero', 'hero_button', 'Button label', 'SHOP THE DROP' ); $add( 'hero', 'hero_link', 'Button destination', auracast_shop_url(), 'url' );
    $add( 'hero', 'hero_edition', 'Collection label', 'AURACAST / COLLECTION 01' );
    $campaigns = array(
        'tees' => array( "GRAPHIC\nTEES.", 'EXPRESS YOURSELF', '', 'SHOP NOW', 'streetwear-overtime-graphic-tee-0.jpg', auracast_category_url( 'graphic-tees' ) ),
        'sweats' => array( "SWEAT\nPANTS.", 'YOUR EVERYDAY UNIFORM', '', 'EXPLORE', 'streetwear-everyday-sweatpants-white-0.png', auracast_category_url( 'sweatpants' ) ),
        'music' => array( "ALL DAY.\nALL NIGHT.", 'THE CULTURE COLLECTION', 'Wear what moves you.', 'MUSIC & CULTURE', 'streetwear-encore-graphic-tee-0.jpg', auracast_collection_url( 'music-culture' ) ),
        'anime' => array( "GRAPHIC\nCULTURE.", 'ART MEETS EVERYDAY', 'Stories worth wearing.', 'EXPLORE ANIME', 'streetwear-monochrome-graphic-shirt-0.png', auracast_collection_url( 'anime' ) ),
        'essentials' => array( 'ESSENTIALS', 'AURACAST', 'THE FOUNDATION OF YOUR WARDROBE', 'DISCOVER THE EVERYDAY', '', auracast_category_url( 'essentials' ) ),
    );
    foreach ( $campaigns as $key => $c ) {
        $label = ucfirst( $key ) . ' banner: ';
        $add( 'campaigns', $key . '_banner_enabled', $label . 'show', true, 'checkbox' );
        foreach ( array( 'title' => 0, 'label' => 1, 'description' => 2, 'button' => 3 ) as $field => $index ) { $add( 'campaigns', $key . '_banner_' . $field, $label . $field, $c[$index], $field === 'title' || $field === 'description' ? 'textarea' : 'text' ); }
        $add( 'campaigns', $key . '_banner_link', $label . 'destination', $c[5], 'url' );
        $add( 'campaigns', $key . '_banner_image', $label . 'image', $c[4] ? auracast_asset( 'images/' . $c[4] ) : '', 'image' );
    }
    foreach ( array( 'popular' => array( 'POPULAR PICKS', 'A good place to start. Your next everyday favorites.' ), 'music' => array( 'MUSIC & CULTURE', 'For the songs, the scenes, and the moments that stay with you.' ), 'anime' => array( 'ANIME ESSENTIALS', 'Expressive graphics. Familiar stories. A style of your own.' ), 'essentials' => array( 'AURACAST ESSENTIALS', 'Easy fits. Clean colors. Pieces you’ll reach for every day.' ) ) as $key => $v ) {
        $add( 'products', $key . '_enabled', ucfirst( $key ) . ': show section', true, 'checkbox' );
        $add( 'products', $key . '_title', ucfirst( $key ) . ': heading', $v[0] ); $add( 'products', $key . '_description', ucfirst( $key ) . ': description', $v[1], 'textarea' );
        $add( 'products', $key . '_products', ucfirst( $key ) . ': optional product IDs (comma separated, up to 12)', '' );
    }
    $add( 'worlds', 'worlds_enabled', 'Show collection slider', true, 'checkbox' ); $add( 'worlds', 'worlds_title', 'Heading', 'SHOP YOUR WORLD' ); $add( 'worlds', 'worlds_description', 'Description', 'Find the collection that feels like you.' );
    foreach ( auracast_worlds() as $i => $w ) {
        $add( 'worlds', 'world_' . $i . '_title', 'Collection ' . ( $i + 1 ) . ' title', $w[0] );
        $add( 'worlds', 'world_' . $i . '_image', 'Collection ' . ( $i + 1 ) . ' image', auracast_asset( 'images/' . $w[2] ), 'image' );
        $add( 'worlds', 'world_' . $i . '_link', 'Collection ' . ( $i + 1 ) . ' destination', $w[3] ? auracast_collection_url( $w[1] ) : auracast_category_url( $w[1] ), 'url' );
    }
    $add( 'community', 'community_enabled', 'Show style gallery', true, 'checkbox' ); $add( 'community', 'community_title', 'Heading', 'WORN YOUR WAY' ); $add( 'community', 'community_description', 'Description', 'Different people. One shared love of everyday style.' );
    $add( 'community', 'community_button', 'Button label', 'FIND YOUR FIT' ); $add( 'community', 'community_link', 'Gallery and button destination', auracast_shop_url(), 'url' );
    foreach ( auracast_look_images() as $i => $image ) { $add( 'community', 'look_' . $i . '_image', 'Style photo ' . ( $i + 1 ), auracast_asset( 'images/' . $image ), 'image' ); }
    foreach ( array( 'auracast-model-black-tee-01.webp', 'auracast-model-forest-tee-01.webp', 'auracast-model-white-tee-01.webp', 'auracast-model-grey-hoodie-01.webp' ) as $i => $image ) { $add( 'contact', 'about_' . $i . '_image', 'About page photo ' . ( $i + 1 ), auracast_asset( 'images/' . $image ), 'image' ); }
    $add( 'benefits', 'benefits_enabled', 'Show service benefits', true, 'checkbox' );
    foreach ( array( array( 'FIND YOUR PERFECT FIT', 'Use the size guide to choose your fit. Need help? Get in touch before you order.' ), array( 'REAL HUMAN SUPPORT', 'Questions about a piece or your order? Our team is here to help.' ), array( 'DELIVERY ACROSS BANGLADESH', 'Your everyday favorites, delivered to your door. Shipping options appear at checkout.' ) ) as $i => $b ) { $add( 'benefits', "benefit_{$i}_title", 'Benefit ' . ( $i + 1 ) . ' title', $b[0] ); $add( 'benefits', "benefit_{$i}_text", 'Benefit ' . ( $i + 1 ) . ' description', $b[1], 'textarea' ); }
    $add( 'newsletter', 'newsletter_enabled', 'Show newsletter section', true, 'checkbox' ); $add( 'newsletter', 'newsletter_title', 'Heading', 'Never miss a drop.' ); $add( 'newsletter', 'newsletter_description', 'Description', 'New collections, fresh graphics, and a little inspiration from Auracast.', 'textarea' );
    $add( 'newsletter', 'popup_enabled', 'Show newsletter popup once per browser', false, 'checkbox' ); $add( 'newsletter', 'popup_title', 'Popup heading', 'Your next favorite is coming.' ); $add( 'newsletter', 'popup_image', 'Popup photo', auracast_asset( 'images/auracast-model-forest-tee-01.webp' ), 'image' );
    $add( 'shop', 'shop_columns', 'Products per row', 4, 'select', array( '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ) );
    $add( 'shop', 'shop_layout', 'Catalog layout', 'grid', 'select', array( 'grid' => 'Uniform grid', 'alternating-4-3' => 'Alternating 4 / 3', 'alternating-3-4' => 'Alternating 3 / 4', 'alternating-4-5' => 'Alternating 4 / 5', 'alternating-5-6' => 'Alternating 5 / 6' ) );
    foreach ( array( 'email' => 'Public email address', 'phone' => 'Telephone', 'address' => 'Store address', 'hours' => 'Store hours' ) as $key => $label ) { $add( 'contact', 'contact_' . $key, $label, '', $key === 'address' || $key === 'hours' ? 'textarea' : 'text' ); }
    $add( 'contact', 'contact_recipient', 'Contact form recipient', get_option( 'admin_email' ) ); $add( 'contact', 'map_query', 'Map location', '' );
} );
add_action( 'wp_head', function () {
    $variables = '';
    foreach ( auracast_palette() as $key => $default ) { $value = sanitize_hex_color( auracast_setting( 'color_' . $key, $default ) ); if ( $value ) { $variables .= '--a-' . $key . ':' . $value . ';'; } }
    echo '<style id="auracast-palette">:root{' . $variables . '}</style>';
}, 20 );
