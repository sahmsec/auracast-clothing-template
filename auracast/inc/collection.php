<?php
defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_default_catalog_orderby', function () { return 'popularity'; } );
add_filter( 'woocommerce_catalog_orderby', function () {
    return array( 'menu_order' => 'Featured', 'popularity' => 'Best selling', 'title' => 'Alphabetically, A–Z', 'title-desc' => 'Alphabetically, Z–A', 'price' => 'Price, low to high', 'price-desc' => 'Price, high to low', 'date-asc' => 'Date, old to new', 'date' => 'Date, new to old' );
} );
add_filter( 'woocommerce_get_catalog_ordering_args', function ( $args, $orderby ) {
    if ( $orderby === 'title-desc' ) { $args['orderby'] = 'title'; $args['order'] = 'DESC'; }
    if ( $orderby === 'date-asc' ) { $args['orderby'] = 'date ID'; $args['order'] = 'ASC'; }
    return $args;
}, 10, 2 );
add_filter( 'woocommerce_product_query_meta_query', function ( $query ) {
    $availability = isset( $_GET['availability'] ) ? sanitize_key( wp_unslash( $_GET['availability'] ) ) : '';
    if ( in_array( $availability, array( 'instock', 'outofstock' ), true ) ) { $query[] = array( 'key' => '_stock_status', 'value' => $availability ); }
    return $query;
} );

function auracast_facet_value( $key ) {
    return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
}
function auracast_collection_url_current() {
    if ( is_product_taxonomy() ) { $url = get_term_link( get_queried_object() ); if ( ! is_wp_error( $url ) ) { return $url; } }
    return auracast_shop_url();
}
function auracast_facet_hidden( $excluded = array() ) {
    foreach ( array( 'product_cat', 'product_tag', 's', 'post_type', 'orderby', 'availability', 'min_price', 'max_price', 'filter_color', 'filter_size' ) as $key ) {
        $value = auracast_facet_value( $key );
        if ( $value !== '' && ! in_array( $key, $excluded, true ) ) { echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
    }
}
function auracast_facet_fields( $facet, $prefix ) {
    if ( $facet === 'availability' ) {
        echo '<label><input type="radio" name="availability" value="" ' . checked( auracast_facet_value( 'availability' ), '', false ) . '> All products</label>';
        foreach ( array( 'instock' => 'In stock', 'outofstock' => 'Out of stock' ) as $value => $label ) { echo '<label><input type="radio" name="availability" value="' . esc_attr( $value ) . '" ' . checked( auracast_facet_value( 'availability' ), $value, false ) . '> ' . esc_html( $label ) . '</label>'; }
    } elseif ( $facet === 'price' ) {
        echo '<div class="price-range"><label>From<input type="number" name="min_price" min="0" step="1" placeholder="0" value="' . esc_attr( auracast_facet_value( 'min_price' ) ) . '"></label><label>To<input type="number" name="max_price" min="0" step="1" placeholder="Any" value="' . esc_attr( auracast_facet_value( 'max_price' ) ) . '"></label></div>';
    } else {
        $taxonomy = 'pa_' . $facet;
        $args = array( 'taxonomy' => $taxonomy, 'hide_empty' => true );
        if ( $facet === 'color' ) {
            $spec = auracast_current_category_spec();
            if ( $spec && $spec['colors'] ) { $args['slug'] = array_map( 'sanitize_title', $spec['colors'] ); $args['hide_empty'] = false; }
        }
        $terms = taxonomy_exists( $taxonomy ) ? get_terms( $args ) : array();
        echo '<label class="screen-reader-text" for="' . esc_attr( $prefix . '-' . $facet ) . '">' . esc_html( ucfirst( $facet ) ) . '</label><select id="' . esc_attr( $prefix . '-' . $facet ) . '" name="filter_' . esc_attr( $facet ) . '"><option value="">All ' . esc_html( $facet . 's' ) . '</option>';
        if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { echo '<option value="' . esc_attr( $term->slug ) . '" ' . selected( auracast_facet_value( 'filter_' . $facet ), $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>'; } }
        echo '</select>';
    }
}
function auracast_collection_toolbar() {
    echo '<div class="collection-toolbar"><button class="mobile-filter-toggle" data-dialog="filter-dialog">' . auracast_icon( 'filter' ) . ' Filter and sort</button><div class="desktop-facets"><span>Filter:</span>';
    foreach ( array( 'availability' => 'Availability', 'price' => 'Price', 'color' => 'Color', 'size' => 'Size' ) as $facet => $label ) {
        if ( $facet === 'color' && ( ! taxonomy_exists( 'pa_color' ) || ! get_terms( array( 'taxonomy' => 'pa_color', 'hide_empty' => true ) ) ) ) { continue; }
        $fields = $facet === 'price' ? array( 'min_price', 'max_price' ) : array( $facet === 'availability' ? $facet : 'filter_' . $facet );
        echo '<details class="collection-facet"><summary>' . esc_html( $label ) . ' ' . auracast_icon( 'chevron' ) . '</summary><form class="facet-popover" method="get" action="' . esc_url( auracast_collection_url_current() ) . '"><div class="facet-popover-heading">' . esc_html( $label ) . '<button type="button" data-close-facet aria-label="Close ' . esc_attr( $label ) . '">' . auracast_icon( 'close' ) . '</button></div>';
        auracast_facet_hidden( $fields ); auracast_facet_fields( $facet, 'desktop' );
        echo '<button type="submit" class="button">Apply</button></form></details>';
    }
    echo '</div><div class="collection-sort"><label for="collection-sort">Sort by:</label>';
    woocommerce_catalog_ordering();
    echo '</div><span class="collection-count">' . (int) wc_get_loop_prop( 'total' ) . ' products</span></div>';
    $active = array_filter( array( 'availability' => auracast_facet_value( 'availability' ), 'min_price' => auracast_facet_value( 'min_price' ), 'max_price' => auracast_facet_value( 'max_price' ), 'filter_color' => auracast_facet_value( 'filter_color' ), 'filter_size' => auracast_facet_value( 'filter_size' ) ), function ( $value ) { return $value !== ''; } );
    if ( $active ) {
        echo '<div class="active-filters">';
        foreach ( $active as $key => $value ) { echo '<a href="' . esc_url( remove_query_arg( array( $key, 'paged', 'product-page' ) ) ) . '">' . esc_html( ucwords( str_replace( array( 'filter_', '_' ), array( '', ' ' ), $key ) ) . ': ' . $value ) . ' ' . auracast_icon( 'close' ) . '</a>'; }
        echo '<a href="' . esc_url( auracast_collection_url_current() ) . '">Remove all</a></div>';
    }
}
function auracast_collection_filter_dialog() {
    ?>
    <dialog id="filter-dialog" class="drawer collection-filter-dialog" aria-labelledby="filter-title"><div class="dialog-heading"><h2 id="filter-title">Filter and sort</h2><button class="icon-button" data-close aria-label="Close filters"><?php echo auracast_icon( 'close' ); ?></button></div><form class="filter-form" method="get" action="<?php echo esc_url( auracast_shop_url() ); ?>">
        <?php auracast_facet_hidden( array( 'availability', 'min_price', 'max_price', 'filter_color', 'filter_size', 'orderby', 'product_cat' ) ); ?>
        <?php if ( is_product_tag() && ! auracast_facet_value( 'product_tag' ) ) : ?><input type="hidden" name="product_tag" value="<?php echo esc_attr( get_queried_object()->slug ); ?>"><?php endif; ?>
        <label for="filter-category">Collection</label><select id="filter-category" name="product_cat"><option value="">All collections</option><?php foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) ) as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( auracast_facet_value( 'product_cat' ) ?: ( is_product_category() ? get_queried_object()->slug : '' ), $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select>
        <?php foreach ( array( 'availability' => 'Availability', 'price' => 'Price', 'color' => 'Color', 'size' => 'Size' ) as $facet => $label ) : if ( $facet === 'color' && ! taxonomy_exists( 'pa_color' ) ) { continue; } ?><details class="mobile-facet" open><summary><?php echo esc_html( $label ); ?> <?php echo auracast_icon( 'chevron' ); ?></summary><div><?php auracast_facet_fields( $facet, 'filter' ); ?></div></details><?php endforeach; ?>
        <label for="filter-orderby">Sort by</label><select id="filter-orderby" name="orderby"><?php foreach ( apply_filters( 'woocommerce_catalog_orderby', array() ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( auracast_facet_value( 'orderby' ) ?: 'popularity', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
        <button type="submit" class="button">Apply</button><a class="reset-filters" href="<?php echo esc_url( auracast_collection_url_current() ); ?>">Remove all</a>
    </form></dialog>
    <?php
}

// Hide the removed journal from menus, including previously imported menus.
add_filter( 'wp_nav_menu_objects', function ( $items ) {
    return array_values( array_filter( $items, function ( $item ) { return ! preg_match( '#/(blog|journal)(/|\?|$)#i', $item->url ) && ! in_array( strtolower( trim( $item->title ) ), array( 'blog', 'journal' ), true ); } ) );
} );
add_filter( 'woocommerce_currency_symbol', function ( $symbol, $currency ) {
    if ( $currency === 'BDT' && ! is_admin() && ! is_checkout() ) { return 'Tk'; }
    return $symbol;
}, 10, 2 );

function auracast_search_products() {
    check_ajax_referer( 'auracast_public', 'nonce' );
    $query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
    if ( strlen( $query ) < 2 || strlen( $query ) > 120 ) { wp_send_json_success( array( 'html' => '' ) ); }
    $visibility = wc_get_product_visibility_term_ids();
    $search = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 6, 's' => $query, 'tax_query' => array( array( 'taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => array( $visibility['exclude-from-search'] ), 'operator' => 'NOT IN' ) ) ) );
    ob_start();
    echo '<h3>Products</h3><ul>';
    foreach ( $search->posts as $post ) {
        $product = wc_get_product( $post->ID ); if ( ! $product || ! $product->is_visible() ) { continue; }
        echo '<li><a href="' . esc_url( $product->get_permalink() ) . '">' . $product->get_image( 'woocommerce_thumbnail' ) . '<span><strong>' . esc_html( $product->get_name() ) . '</strong><span>' . wp_kses_post( $product->get_price_html() ) . '</span></span></a></li>';
    }
    echo '</ul><a class="predictive-search-all" href="' . esc_url( add_query_arg( array( 's' => $query, 'post_type' => 'product' ), home_url( '/' ) ) ) . '">Search for “' . esc_html( $query ) . '” ' . auracast_icon( 'arrow' ) . '</a>';
    wp_send_json_success( array( 'html' => ob_get_clean() ) );
}
add_action( 'wp_ajax_auracast_search_products', 'auracast_search_products' );
add_action( 'wp_ajax_nopriv_auracast_search_products', 'auracast_search_products' );
