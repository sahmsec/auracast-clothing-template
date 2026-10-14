<?php
defined( 'ABSPATH' ) || exit;

function auracast_store_structure() {
    static $structure;
    if ( $structure === null ) { $structure = json_decode( file_get_contents( get_template_directory() . '/assets/data/store-collections.json' ), true ); }
    return $structure;
}

function auracast_segment_slug( $category, $segment ) { return $category . '-' . sanitize_title( $segment ); }

function auracast_current_category_spec() {
    $term = is_tax( 'product_cat' ) ? get_queried_object() : get_term_by( 'slug', auracast_facet_value( 'product_cat' ), 'product_cat' );
    if ( ! $term || is_wp_error( $term ) ) { return null; }
    while ( $term->parent ) { $term = get_term( $term->parent, 'product_cat' ); if ( ! $term || is_wp_error( $term ) ) { return null; } }
    foreach ( auracast_store_structure()['categories'] as $category ) { if ( $category['slug'] === $term->slug ) { return $category; } }
    return null;
}

function auracast_store_term( $name, $slug, $parent = 0, $description = '' ) {
    $term = get_term_by( 'slug', $slug, 'product_cat' );
    $args = array( 'slug' => $slug, 'parent' => $parent );
    if ( $description !== '' ) { $args['description'] = $description; }
    $result = $term ? wp_update_term( $term->term_id, 'product_cat', array_merge( $args, array( 'name' => $name ) ) ) : wp_insert_term( $name, 'product_cat', $args );
    if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
    return (int) $result['term_id'];
}

function auracast_provision_store_structure() {
    if ( ! class_exists( 'WooCommerce' ) ) { return; }
    $structure = auracast_store_structure();
    $colors = array();
    foreach ( $structure['categories'] as $category ) {
        $parent = auracast_store_term( $category['name'], $category['slug'] );
        foreach ( $category['segments'] as $segment ) { auracast_store_term( $segment, auracast_segment_slug( $category['slug'], $segment ), $parent ); }
        $colors = array_merge( $colors, $category['colors'] );
    }
    foreach ( $structure['collections'] as $collection ) { auracast_store_term( $collection['name'], $collection['slug'], 0, $collection['description'] ); }
    if ( $colors ) { auracast_import_attribute( 'color', 'Color', array_unique( $colors ) ); }
}

// Update only the original bundled samples; merchant products keep their settings.
function auracast_assign_demo_collections( $id, $item ) {
    if ( get_post_meta( $id, '_auracast_demo', true ) !== '1' ) { return; }
    $product = wc_get_product( $id );
    if ( ! $product ) { return; }
    $ids = array();
    foreach ( $item['categories'] ?? array() as $slug ) { $term = get_term_by( 'slug', $slug, 'product_cat' ); if ( $term ) { $ids[] = (int) $term->term_id; } }
    if ( $ids ) {
        $managed = array( 'graphic-tees', 'shirts', 'sweatpants', 'hoodies', 'essentials', 'joggers', 'sweatshirts', 't-shirts', 'diva-wardrobe' );
        foreach ( $product->get_category_ids() as $term_id ) { $term = get_term( $term_id, 'product_cat' ); if ( $term && ! is_wp_error( $term ) && ! in_array( $term->slug, $managed, true ) ) { $ids[] = $term_id; } }
        $product->set_category_ids( array_unique( $ids ) );
    }
    if ( ! empty( $item['catalogHidden'] ) ) { $product->set_catalog_visibility( 'hidden' ); }
    if ( ! empty( $item['previousName'] ) && $product->get_name() === $item['previousName'] ) { $product->set_name( $item['name'] ); }
    if ( ! empty( $item['color'] ) ) {
        $attributes = $product->get_attributes();
        if ( ! isset( $attributes['pa_color'] ) ) {
            $color = auracast_import_attribute( 'color', 'Color', array( $item['color'] ) );
            $color->set_variation( false );
            $attributes['pa_color'] = $color;
            $product->set_attributes( $attributes );
        }
    }
    $product->save();
}

function auracast_refresh_store_navigation() {
    $locations = get_theme_mod( 'nav_menu_locations', array() );
    $menu = wp_get_nav_menu_object( 'Auracast Main Menu' );
    if ( ! $menu || empty( $locations['primary'] ) || (int) $locations['primary'] !== (int) $menu->term_id ) { auracast_import_menu(); return; }
    $items = wp_get_nav_menu_items( $menu->term_id ) ?: array();
    $titles = array();
    foreach ( $items as $item ) { if ( ! $item->menu_item_parent ) { $titles[] = strtoupper( $item->title ); } }
    $legacy = array( 'SHIRTS', 'ANIME', 'SWEATPANTS', 'MUSIC & CULTURE', 'COLLECTION', 'HOODIE', 'PRODUCTS', 'ESSENTIALS' );
    // A renamed or rearranged merchant menu is left editable in Appearance > Menus.
    $current = array( 'AURACAST ESSENTIALS', 'HOODIES', 'JOGGERS', 'SWEATSHIRTS', 'DIVA WARDROBE', 'T-SHIRTS' );
    if ( $titles !== $legacy && ( $titles !== $current || ! get_term_meta( $menu->term_id, '_auracast_structure_menu', true ) ) ) { return; }
    foreach ( $items as $item ) { wp_delete_post( $item->ID, true ); }
    unset( $locations['primary'] );
    set_theme_mod( 'nav_menu_locations', $locations );
    auracast_import_menu();
    update_term_meta( $menu->term_id, '_auracast_structure_menu', 1 );
}

function auracast_upgrade_store_structure() {
    $structure = auracast_store_structure();
    if ( ! class_exists( 'WooCommerce' ) || (int) get_option( 'auracast_store_structure_version', 0 ) >= $structure['version'] ) { return; }
    auracast_provision_store_structure();
    $catalog = json_decode( file_get_contents( get_template_directory() . '/assets/data/catalog.json' ), true );
    foreach ( $catalog as $item ) { $product = auracast_product_by_slug( $item['slug'] ); if ( $product ) { auracast_assign_demo_collections( $product->get_id(), $item ); } }
    auracast_refresh_store_navigation();
    update_option( 'auracast_store_structure_version', $structure['version'], false );
}

add_action( 'admin_init', function () { if ( current_user_can( 'manage_options' ) ) { auracast_upgrade_store_structure(); } } );
