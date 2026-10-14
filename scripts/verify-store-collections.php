<?php
$root = dirname( __DIR__ ) . '/.local/wordpress/wordpress/';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . 'wp-load.php';
function collections_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

foreach ( auracast_store_structure()['categories'] as $category ) {
    $parent = get_term_by( 'slug', $category['slug'], 'product_cat' );
    collections_assert( $parent && $parent->name === $category['name'], 'Missing category: ' . $category['name'] );
    foreach ( $category['segments'] as $segment ) {
        $child = get_term_by( 'slug', auracast_segment_slug( $category['slug'], $segment ), 'product_cat' );
        collections_assert( $child && (int) $child->parent === (int) $parent->term_id && html_entity_decode( $child->name, ENT_QUOTES, 'UTF-8' ) === $segment, 'Incorrect subcategory: ' . $segment );
    }
    foreach ( $category['colors'] as $color ) { collections_assert( get_term_by( 'slug', sanitize_title( $color ), 'pa_color' ), 'Missing color: ' . $color ); }
}
echo 'PASS Native categories, subcategory parents and color terms.' . PHP_EOL;

$snapshot = dirname( __DIR__ ) . '/.local/collections-backup/auracast-streetwear.sqlite';
collections_assert( is_file( $snapshot ), 'The original preview database snapshot is missing.' );
$original = new PDO( 'sqlite:' . $snapshot );
$sql = "SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type IN ('product','product_variation') AND m.meta_key IN ('_price','_regular_price','_sale_price','_stock','_thumbnail_id','_product_image_gallery') ORDER BY m.post_id,m.meta_key,m.meta_id";
$normalize = function ( $rows ) { return array_map( function ( $row ) { return array_map( 'strval', $row ); }, $rows ); };
collections_assert( $normalize( $original->query( $sql )->fetchAll( PDO::FETCH_ASSOC ) ) === $normalize( $wpdb->get_results( $sql, ARRAY_A ) ), 'A price, stock quantity or product photo changed during category migration.' );
echo 'PASS Existing product and variation prices, stock and photos match the original database.' . PHP_EOL;

$merchant = new WC_Product_Simple();
$merchant->set_name( 'Collections QA merchant product' );
$merchant->set_regular_price( '987' );
$merchant->set_manage_stock( true ); $merchant->set_stock_quantity( 7 );
$merchant->set_status( 'draft' );
$id = $merchant->save();
$merchant = wc_get_product( $id );
try {
    $before = $merchant->get_data();
    auracast_assign_demo_collections( $id, array( 'categories' => array( 'hoodies', 'essentials' ), 'color' => 'Pink', 'catalogHidden' => true ) );
    $after = wc_get_product( $id )->get_data();
    foreach ( array( 'name', 'status', 'catalog_visibility', 'regular_price', 'stock_quantity', 'category_ids', 'attributes', 'image_id' ) as $key ) { collections_assert( $before[ $key ] === $after[ $key ], 'An unmarked merchant product changed: ' . $key ); }
    echo 'PASS Merchant products are retained.' . PHP_EOL;
} finally { $merchant->delete( true ); }

$black = auracast_product_by_slug( 'essential-hoodie-black' );
collections_assert( has_term( array( 'hoodies' ), 'product_cat', $black->get_id() ) && has_term( array( 'essentials' ), 'product_cat', $black->get_id() ), 'Hoodies must appear in both the category and curated Essentials collection.' );
collections_assert( has_term( 'black', 'pa_color', $black->get_id() ), 'The color filter cannot find the black hoodie.' );
$ids = $black->get_children();
collections_assert( $ids && wc_get_product( $ids[0] )->is_type( 'variation' ), 'Native WooCommerce variations were lost.' );
echo 'PASS Demo collection membership, colors and native size variations.' . PHP_EOL;

$before = array_map( function ( $item ) { return $item->ID; }, wp_get_nav_menu_items( 'Auracast Main Menu' ) );
auracast_upgrade_store_structure();
$after = array_map( function ( $item ) { return $item->ID; }, wp_get_nav_menu_items( 'Auracast Main Menu' ) );
collections_assert( $before === $after, 'Repeated upgrades changed or duplicated navigation items.' );
echo 'PASS Repeated upgrades retain navigation and taxonomy.' . PHP_EOL;
