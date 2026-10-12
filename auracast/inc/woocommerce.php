<?php
defined( 'ABSPATH' ) || exit;

function auracast_shop_columns() {
    $columns = isset( $_GET['columns'] ) ? absint( $_GET['columns'] ) : absint( auracast_setting( 'shop_columns', 6 ) );
    return min( 6, max( 2, $columns ) );
}
function auracast_shop_layout() {
    $layout = isset( $_GET['layout'] ) ? sanitize_key( wp_unslash( $_GET['layout'] ) ) : auracast_setting( 'shop_layout', 'grid' );
    return in_array( $layout, array( 'grid', 'alternating-3-4', 'alternating-4-3', 'alternating-4-5', 'alternating-5-6' ), true ) ? $layout : 'grid';
}
add_filter( 'loop_shop_columns', 'auracast_shop_columns' );
add_filter( 'loop_shop_per_page', function () { return 20; }, 20 );
add_filter( 'woocommerce_show_page_title', '__return_false' );
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

add_action( 'init', function () {
    remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
    remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
    remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
    remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
    foreach ( array( 'woocommerce_before_shop_loop_item', 'woocommerce_before_shop_loop_item_title', 'woocommerce_shop_loop_item_title', 'woocommerce_after_shop_loop_item_title', 'woocommerce_after_shop_loop_item' ) as $hook ) { remove_all_actions( $hook ); }
    add_action( 'woocommerce_before_shop_loop_item', function () { global $product; auracast_product_card( $product ); } );
} );

add_action( 'woocommerce_before_main_content', function () {
    echo '<main id="main-content" class="woocommerce-main container">';
    if ( ! is_product() ) { echo '<h1 class="screen-reader-text">' . esc_html( woocommerce_page_title( false ) ) . '</h1><div class="collection-view"><label><input type="checkbox" data-model-view> Model</label></div>'; }
}, 10 );
add_action( 'woocommerce_after_main_content', function () { echo '</main>'; }, 10 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
add_action( 'woocommerce_before_shop_loop', 'auracast_collection_toolbar', 20 );
add_action( 'woocommerce_after_shop_loop', 'auracast_collection_filter_dialog', 30 );
add_action( 'woocommerce_no_products_found', 'auracast_collection_filter_dialog', 30 );
add_action( 'woocommerce_no_products_found', 'auracast_collection_toolbar', 5 );

function auracast_filter_dialog() {
    ?>
    <dialog id="filter-dialog" class="drawer filter-dialog" aria-labelledby="filter-title"><div class="dialog-heading"><h2 id="filter-title"><?php esc_html_e( 'Filter Products', 'auracast' ); ?></h2><button class="icon-button" data-close aria-label="Close filters"><?php echo auracast_icon( 'close' ); ?></button></div>
    <form class="filter-form" method="get" action="<?php echo esc_url( auracast_shop_url() ); ?>">
        <label for="filter-category">Collection</label><select id="filter-category" name="product_cat"><option value="">All collections</option><?php foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) ) as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( isset( $_GET['product_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['product_cat'] ) ) : '', $term->slug ); ?>><?php echo esc_html( $term->name ); ?> (<?php echo (int) $term->count; ?>)</option><?php endforeach; ?></select>
        <label for="filter-size">Size</label><select id="filter-size" name="filter_size"><option value="">All sizes</option><?php if ( taxonomy_exists( 'pa_size' ) ) { foreach ( get_terms( array( 'taxonomy' => 'pa_size', 'hide_empty' => true ) ) as $term ) { echo '<option value="' . esc_attr( $term->slug ) . '" ' . selected( isset( $_GET['filter_size'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_size'] ) ) : '', $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>'; } } ?></select>
        <fieldset><legend>Price range (<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>)</legend><div class="price-range"><label>From<input type="number" name="min_price" min="0" step="1" placeholder="0" value="<?php echo isset( $_GET['min_price'] ) ? esc_attr( max( 0, (float) $_GET['min_price'] ) ) : ''; ?>"></label><label>To<input type="number" name="max_price" min="0" step="1" placeholder="Any" value="<?php echo isset( $_GET['max_price'] ) ? esc_attr( max( 0, (float) $_GET['max_price'] ) ) : ''; ?>"></label></div></fieldset>
        <label for="filter-layout">Grid layout</label><select id="filter-layout" name="layout"><?php foreach ( array( 'grid' => 'Uniform grid', 'alternating-4-3' => 'Alternating 4 / 3', 'alternating-3-4' => 'Alternating 3 / 4', 'alternating-4-5' => 'Alternating 4 / 5', 'alternating-5-6' => 'Alternating 5 / 6' ) as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( auracast_shop_layout(), $key, false ) . '>' . esc_html( $label ) . '</option>'; } ?></select>
        <label for="filter-columns">Products per row</label><select id="filter-columns" name="columns"><?php for ( $i = 2; $i <= 6; $i++ ) { echo '<option ' . selected( auracast_shop_columns(), $i, false ) . '>' . (int) $i . '</option>'; } ?></select>
        <input type="hidden" name="query_type_size" value="or"><button type="submit" class="button">Apply filters</button><a class="reset-filters" href="<?php echo esc_url( auracast_shop_url() ); ?>">Clear all filters</a>
    </form><?php if ( is_active_sidebar( 'shop-filters' ) ) { dynamic_sidebar( 'shop-filters' ); } ?></dialog>
    <?php
}

add_action( 'woocommerce_single_product_summary', function () {
    global $product;
    if ( $product && $product->is_type( 'variable' ) ) { echo '<button class="size-guide-link" data-dialog="size-guide-dialog">' . esc_html__( 'Size guide', 'auracast' ) . '</button>'; }
}, 25 );
add_action( 'woocommerce_single_product_summary', function () {
    global $product;
    echo '<div class="single-product-tools"><button class="wishlist-button" data-wishlist="' . esc_attr( $product->get_id() ) . '" aria-pressed="false">' . auracast_icon( 'heart' ) . ' ' . esc_html__( 'Add To Wishlist', 'auracast' ) . '</button><button class="share-button" data-share="' . esc_url( $product->get_permalink() ) . '">' . auracast_icon( 'share' ) . ' ' . esc_html__( 'Share This', 'auracast' ) . '</button></div>';
}, 35 );
add_action( 'woocommerce_before_single_product_summary', function () {
    echo '<div class="single-breadcrumb">'; woocommerce_breadcrumb( array( 'delimiter' => ' / ' ) ); echo '</div>';
}, 5 );

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
    $fragments['span.cart-count'] = '<span class="cart-count">' . (int) WC()->cart->get_cart_contents_count() . '</span>';
    return $fragments;
} );
add_filter( 'woocommerce_output_related_products_args', function ( $args ) { $args['posts_per_page'] = 4; $args['columns'] = 4; return $args; } );
add_filter( 'woocommerce_shipping_free_shipping_is_available', function ( $available ) {
    return $available || ( WC()->cart && WC()->cart->get_cart_contents_count() >= 4 );
} );
add_filter( 'woocommerce_package_rates', function ( $rates ) {
    $free = array_filter( $rates, function ( $rate ) { return $rate->get_method_id() === 'free_shipping'; } );
    return $free ?: $rates;
}, 100 );

function auracast_quick_view() {
    check_ajax_referer( 'auracast_public', 'nonce' );
    $product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $_POST['product_id'] ?? 0 ) ) : false;
    if ( ! $product || ! $product->is_visible() || 'publish' !== $product->get_status() ) { wp_send_json_error( array( 'message' => 'Product unavailable.' ), 404 ); }
    ob_start();
    echo '<div class="quick-view-layout"><a class="quick-view-image" href="' . esc_url( $product->get_permalink() ) . '">' . $product->get_image( 'woocommerce_single' ) . '</a><div class="quick-view-copy"><h2>' . esc_html( $product->get_name() ) . '</h2><div class="price">' . wp_kses_post( $product->get_price_html() ) . '</div><div class="quick-view-description">' . wp_kses_post( wpautop( $product->get_short_description() ) ) . '</div>';
    if ( $product->is_purchasable() && $product->is_in_stock() && $product->is_type( 'simple' ) ) {
        echo '<a class="button add_to_cart_button ajax_add_to_cart" href="' . esc_url( $product->add_to_cart_url() ) . '" data-product_id="' . esc_attr( $product->get_id() ) . '" data-quantity="1">' . esc_html__( 'Add to cart', 'auracast' ) . '</a>';
    } else { echo '<a class="button" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->is_in_stock() ? __( 'Choose options', 'auracast' ) : __( 'View product', 'auracast' ) ) . '</a>'; }
    echo '<a class="text-link" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'View full details', 'auracast' ) . ' →</a></div></div>';
    wp_send_json_success( array( 'html' => ob_get_clean() ) );
}
add_action( 'wp_ajax_auracast_quick_view', 'auracast_quick_view' );
add_action( 'wp_ajax_nopriv_auracast_quick_view', 'auracast_quick_view' );

function auracast_wishlist_products() {
    check_ajax_referer( 'auracast_public', 'nonce' );
    $ids = array_slice( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ) ) ), 0, 100 );
    ob_start();
    foreach ( $ids as $id ) { $product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : false; if ( $product && 'publish' === $product->get_status() ) { auracast_product_card( $product ); } }
    wp_send_json_success( array( 'html' => ob_get_clean() ) );
}
add_action( 'wp_ajax_auracast_wishlist_products', 'auracast_wishlist_products' );
add_action( 'wp_ajax_nopriv_auracast_wishlist_products', 'auracast_wishlist_products' );
