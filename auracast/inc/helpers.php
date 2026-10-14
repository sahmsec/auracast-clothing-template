<?php
defined( 'ABSPATH' ) || exit;

function auracast_asset( $path ) { return get_template_directory_uri() . '/assets/' . ltrim( $path, '/' ); }
function auracast_setting( $key, $default = '' ) { return get_theme_mod( 'auracast_' . $key, $default ); }
function auracast_demo_model_replacements() {
    static $replacements;
    if ( $replacements === null ) { $replacements = json_decode( file_get_contents( get_template_directory() . '/assets/data/model-replacements.json' ), true ) ?: array(); }
    return $replacements;
}
function auracast_image( $key, $file ) {
    $url = auracast_setting( $key, auracast_asset( 'images/' . $file ) );
    // Refresh a saved bundled demo image while retaining merchant-uploaded images.
    if ( is_string( $url ) && str_starts_with( $url, auracast_asset( 'images/' ) ) ) {
        $replacements = auracast_demo_model_replacements();
        $original = basename( wp_parse_url( $url, PHP_URL_PATH ) ?: '' );
        if ( isset( $replacements[ $original ] ) ) { return auracast_asset( 'images/' . $replacements[ $original ] ); }
    }
    return $url;
}
function auracast_worlds() {
    return array(
        array( 'AURACAST ESSENTIALS', 'essentials', 'streetwear-essential-hoodie-brown-0.jpg', false ),
        array( 'HOODIES', 'hoodies', 'streetwear-essential-hoodie-white-0.jpg', false ),
        array( 'JOGGERS', 'joggers', 'streetwear-everyday-sweatpants-black-0.png', false ),
        array( 'SWEATSHIRTS', 'sweatshirts', 'auracast-sweatshirt-preview.svg', false ),
        array( 'DIVA WARDROBE', 'diva-wardrobe', 'auracast-model-white-tee-01.webp', false ),
    );
}
function auracast_look_images() { return array( 'auracast-model-black-tee-01.webp', 'auracast-model-white-tee-01.webp', 'auracast-model-forest-tee-01.webp', 'auracast-model-grey-hoodie-01.webp', 'auracast-model-black-tee-02.webp' ); }
function auracast_shop_url() { return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ); }
function auracast_collection_url( $slug ) {
    $term = get_term_by( 'slug', $slug, 'product_tag' );
    return $term && ! is_wp_error( $term ) ? get_term_link( $term ) : add_query_arg( 'product_tag', $slug, auracast_shop_url() );
}
function auracast_home_product_section( $key, $title, $description, $query ) {
    if ( ! auracast_setting( $key . '_enabled', true ) ) { return; }
    $ids = array_slice( array_filter( array_map( 'absint', explode( ',', auracast_setting( $key . '_products', '' ) ) ) ), 0, 12 );
    if ( $ids ) { $query = array( 'include' => $ids, 'limit' => count( $ids ), 'orderby' => 'include' ); }
    $products = function_exists( 'wc_get_products' ) ? wc_get_products( array_merge( array( 'status' => 'publish', 'visibility' => 'visible' ), $query ) ) : array();
    $link = auracast_shop_url();
    if ( ! empty( $query['tag'] ) ) { $link = auracast_collection_url( $query['tag'][0] ); }
    if ( ! empty( $query['category'] ) ) { $link = auracast_category_url( $query['category'][0] ); }
    if ( $key === 'essentials' ) { $link = auracast_category_url( 'essentials' ); }
    echo '<section class="streetwear-product-section yokai-product-section" aria-labelledby="' . esc_attr( $key ) . '-title"><div class="streetwear-section-heading container"><h2 id="' . esc_attr( $key ) . '-title">' . esc_html( auracast_setting( $key . '_title', $title ) ) . '</h2><p>' . esc_html( auracast_setting( $key . '_description', $description ) ) . '</p></div><div id="' . esc_attr( $key ) . '-rail" class="product-grid" data-product-rail tabindex="0" aria-label="' . esc_attr( $title ) . ' products">';
    foreach ( $products as $product ) { auracast_product_card( $product ); }
    echo '</div>';
    if ( ! $products ) { echo '<p class="collection-coming-soon container">New pieces are on the way. Explore the collection for the next drop.</p>'; }
    else { echo '<p class="swipe-cue">Swipe for more <span aria-hidden="true">☝ →</span></p><div class="rail-page-dots" data-rail-dots="' . esc_attr( $key ) . '-rail"></div>'; }
    echo '<div class="section-cta"><a class="yokai-view-all" href="' . esc_url( $link ) . '">' . esc_html__( 'View all', 'auracast' ) . '</a></div></section>';
}
function auracast_category_url( $slug ) {
    $term = get_term_by( 'slug', $slug, 'product_cat' );
    return $term && ! is_wp_error( $term ) ? get_term_link( $term ) : add_query_arg( 'product_cat', $slug, auracast_shop_url() );
}
function auracast_icon( $name, $class = '' ) {
    $paths = array(
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'bag' => '<path d="M5 7h14l1 14H4L5 7Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'arrow' => '<path d="M3 12h18m-6-6 6 6-6 6"/>',
        'filter' => '<path d="M3 6h18M3 12h18M3 18h18M8 3v6m8 0v6m-8 0v6"/>',
        'chevron' => '<path d="m7 10 5 5 5-5"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="m3 6 9 7 9-7"/>',
        'share' => '<path d="M12 15V2m-4 4 4-4 4 4M8 9H4v12h16V9h-4"/>',
        'exchange' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
        'support' => '<circle cx="12" cy="7" r="4"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/>',
        'delivery' => '<path d="M1 5h13v13H1zm13 4h5l4 5v4h-9"/><circle cx="5" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
        'account' => '<circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3Z"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>',
        'reel' => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M3 9h18M8 3l4 6m3-6 4 6m-9 4 5 3-5 3Z"/>',
        'play' => '<path d="m9 5 11 7-11 7Z"/>',
        'pause' => '<path d="M8 5v14M16 5v14"/>',
    );
    return '<svg class="icon ' . esc_attr( $class ) . '" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? $paths['arrow'] ) . '</svg>';
}
function auracast_logo( $white = false ) {
    if ( has_custom_logo() && ! $white ) { return get_custom_logo(); }
    return '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr__( 'Auracast home', 'auracast' ) . '"><img src="' . esc_url( auracast_asset( 'images/auracast-logo' . ( $white ? '-white' : '' ) . '.svg' ) ) . '" width="220" height="28" alt="AURACAST"></a>';
}
function auracast_fallback_menu() {
    echo '<ul class="primary-menu mobile-nav">';
    $render = function ( $items ) use ( &$render ) { foreach ( $items as $item ) {
        echo '<li' . ( ! empty( $item[2] ) ? ' class="menu-item-has-children"' : '' ) . '><a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a>';
        if ( ! empty( $item[2] ) ) { echo '<ul class="sub-menu">'; $render( $item[2] ); echo '</ul>'; }
        echo '</li>';
    } };
    $render( auracast_navigation_items() );
    echo '</ul>';
}
function auracast_navigation_items() {
    $items = array( array( 'AURACAST ESSENTIALS', auracast_category_url( 'essentials' ) ) );
    $tees = array();
    foreach ( auracast_store_structure()['categories'] as $category ) {
        $url = auracast_category_url( $category['slug'] );
        $children = array();
        if ( $category['segments'] ) { $children[] = array( 'Shop all ' . $category['name'], $url ); }
        if ( $category['segments'] ) {
            foreach ( $category['segments'] as $segment ) { $children[] = array( $segment, auracast_category_url( auracast_segment_slug( $category['slug'], $segment ) ) ); }
        }
        $item = array( strtoupper( $category['name'] ), $url, $children );
        if ( $category['slug'] === 't-shirts' ) { $tees = $item; } else { $items[] = $item; }
    }
    $items[] = array( 'DIVA WARDROBE', auracast_category_url( 'diva-wardrobe' ) );
    $items[] = $tees;
    return $items;
}
function auracast_product_by_slug( $slug ) {
    $post = get_page_by_path( $slug, OBJECT, 'product' );
    return $post && function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : false;
}
function auracast_product_card( $product ) {
    if ( ! $product || ! $product->is_visible() ) { return; }
    $id = $product->get_id();
    $url = $product->get_permalink();
    $image = $product->get_image( 'auracast-card', array( 'loading' => 'lazy', 'class' => 'product-image' ) );
    $gallery = $product->get_gallery_image_ids();
    $terms = get_the_terms( $id, 'product_cat' );
    echo '<article class="product-card" data-product-id="' . esc_attr( $id ) . '"><div class="product-media"><a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $product->get_name() ) . '">' . wp_kses_post( $image );
    if ( $gallery ) { echo wp_get_attachment_image( $gallery[0], 'auracast-card', false, array( 'class' => 'product-hover', 'loading' => 'lazy', 'alt' => '' ) ); }
    echo '</a>';
    if ( $product->is_on_sale() ) { echo '<span class="product-badge">' . esc_html__( 'Sale', 'auracast' ) . '</span>'; }
    elseif ( get_post_meta( $id, '_auracast_new', true ) ) { echo '<span class="product-badge new-badge">' . esc_html__( 'New', 'auracast' ) . '</span>'; }
    if ( ! $product->is_in_stock() ) { echo '<span class="product-badge">' . esc_html__( 'Sold out', 'auracast' ) . '</span>'; }
    echo '<button class="quick-view" data-quick-view="' . esc_attr( $id ) . '">' . esc_html__( 'Quick View', 'auracast' ) . '</button></div><div class="product-category">' . esc_html( $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '' ) . '</div><div class="product-title-row"><h3><a href="' . esc_url( $url ) . '">' . esc_html( $product->get_name() ) . '</a></h3><button class="wishlist-button" data-wishlist="' . esc_attr( $id ) . '" aria-label="' . esc_attr( sprintf( __( 'Save %s to wishlist', 'auracast' ), $product->get_name() ) ) . '" aria-pressed="false">' . auracast_icon( 'heart' ) . '</button></div>';
    if ( $product->get_rating_count() ) { echo wp_kses_post( wc_get_rating_html( $product->get_average_rating(), $product->get_rating_count() ) ); }
    echo '<span class="price">' . wp_kses_post( $product->get_price_html() ) . ( get_woocommerce_currency() === 'BDT' ? '<span class="currency-code"> BDT</span>' : '' ) . '</span>';
    if ( $product->is_in_stock() ) {
        $classes = 'card-add button';
        if ( $product->is_type( 'simple' ) && $product->is_purchasable() ) { $classes .= ' add_to_cart_button ajax_add_to_cart'; }
        echo '<a href="' . esc_url( $product->add_to_cart_url() ) . '" class="' . esc_attr( $classes ) . '" data-product_id="' . esc_attr( $id ) . '" data-quantity="1" aria-label="' . esc_attr( $product->add_to_cart_description() ) . '">' . esc_html( $product->add_to_cart_text() ) . '</a>';
    }
    echo '</article>';
}
function auracast_post_card( $post_id ) {
    echo '<article class="journal-card"><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . get_the_post_thumbnail( $post_id, 'auracast-journal', array( 'loading' => 'lazy' ) ) . '</a><div class="post-meta">' . esc_html( get_the_date( 'F j, Y', $post_id ) ) . ' · ' . wp_kses_post( get_the_category_list( ', ', '', $post_id ) ) . '</div><h3><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></h3><p>' . esc_html( wp_trim_words( get_the_excerpt( $post_id ), 27 ) ) . '</p></article>';
}
function auracast_newsletter_form( $context = 'footer' ) {
    ?>
    <form class="newsletter-form ajax-form" data-form-action="auracast_newsletter" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
        <label class="screen-reader-text" for="email-<?php echo esc_attr( $context ); ?>"><?php esc_html_e( 'Your email address', 'auracast' ); ?></label>
        <div class="newsletter-field"><input id="email-<?php echo esc_attr( $context ); ?>" name="email" type="email" placeholder="<?php esc_attr_e( 'Your E-Mail', 'auracast' ); ?>" autocomplete="email" required maxlength="254"><button type="submit" aria-label="<?php esc_attr_e( 'Subscribe', 'auracast' ); ?>"><?php echo auracast_icon( 'arrow' ); ?></button></div>
        <label class="consent"><input type="checkbox" name="consent" value="1" required> <?php esc_html_e( 'I would like to receive news and special offers.', 'auracast' ); ?></label>
        <input type="hidden" name="action" value="auracast_newsletter"><?php wp_nonce_field( 'auracast_public', 'nonce' ); ?>
        <div class="honey" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <p class="form-status" role="status" aria-live="polite"></p>
    </form>
    <?php
}
function auracast_size_guide() {
    $page = get_page_by_path( 'size-guide' );
    if ( $page && $page->post_status === 'publish' && trim( $page->post_content ) ) { echo wp_kses_post( apply_filters( 'the_content', $page->post_content ) ); }
    else { echo '<p>Fit varies between pieces. Compare the measurements in the product description with a garment you already wear, or contact us for help choosing your size.</p>'; }
}
function auracast_community_strip( $title = 'WORN OUT THERE' ) {
    auracast_instagram_feed( $title );
}
add_action( 'woocommerce_after_single_product_summary', function () { auracast_community_strip(); }, 40 );
