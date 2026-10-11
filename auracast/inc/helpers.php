<?php
defined( 'ABSPATH' ) || exit;

function auracast_asset( $path ) { return get_template_directory_uri() . '/assets/' . ltrim( $path, '/' ); }
function auracast_setting( $key, $default = '' ) { return get_theme_mod( 'auracast_' . $key, $default ); }
function auracast_image( $key, $file ) { return auracast_setting( $key, auracast_asset( 'images/' . $file ) ); }
function auracast_worlds() { return array( array( 'ANIME', 'anime', 'streetwear-overtime-graphic-tee-0.jpg', true ), array( 'MUSIC & CULTURE', 'music-culture', 'streetwear-afterhours-graphic-tee-0.jpg', true ), array( 'ESSENTIALS', 'essentials', 'streetwear-essential-tee-black-0.jpg', false ), array( 'HOODIES', 'hoodies', 'streetwear-essential-hoodie-white-0.jpg', false ), array( 'SWEATPANTS', 'sweatpants', 'streetwear-everyday-sweatpants-black-0.png', false ), array( 'SHIRTS', 'shirts', 'streetwear-monochrome-graphic-shirt-0.png', false ) ); }
function auracast_look_images() { return array( 'streetwear-essential-tee-black-0.jpg', 'streetwear-essential-tee-white-0.jpg', 'streetwear-essential-tee-forest-0.jpg', 'streetwear-essential-hoodie-grey-0.jpg', 'streetwear-essential-tee-black-1.jpg' ); }
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
    if ( $key === 'essentials' ) { $link = auracast_category_url( 'essentials' ); }
    echo '<section class="streetwear-product-section container" aria-labelledby="' . esc_attr( $key ) . '-title"><div class="streetwear-section-heading"><div><h2 id="' . esc_attr( $key ) . '-title">' . esc_html( auracast_setting( $key . '_title', $title ) ) . '</h2><p>' . esc_html( auracast_setting( $key . '_description', $description ) ) . '</p></div><a class="section-view-all" href="' . esc_url( $link ) . '">' . esc_html__( 'View all', 'auracast' ) . ' →</a></div><div class="product-grid">';
    foreach ( $products as $product ) { auracast_product_card( $product ); }
    echo '</div><div class="section-cta"><a class="pill-button" href="' . esc_url( $link ) . '">' . esc_html__( 'View All', 'auracast' ) . '</a></div></section>';
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
    );
    return '<svg class="icon ' . esc_attr( $class ) . '" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? $paths['arrow'] ) . '</svg>';
}
function auracast_logo( $white = false ) {
    if ( has_custom_logo() && ! $white ) { return get_custom_logo(); }
    return '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr__( 'Auracast home', 'auracast' ) . '"><img src="' . esc_url( auracast_asset( 'images/auracast-logo' . ( $white ? '-white' : '' ) . '.svg' ) ) . '" width="220" height="28" alt="AURACAST"></a>';
}
function auracast_fallback_menu() {
    $shop = auracast_shop_url();
    echo '<ul class="primary-menu"><li><a href="' . esc_url( home_url() ) . '">Home</a></li><li><a href="' . esc_url( home_url( '/blog/' ) ) . '">Blog</a></li><li><a href="' . esc_url( $shop ) . '">Shop</a></li><li><a href="' . esc_url( $shop ) . '">Products</a></li><li><a href="' . esc_url( home_url( '/about-us/' ) ) . '">About Us</a></li><li><a href="' . esc_url( home_url( '/contact-us/' ) ) . '">Contact Us</a></li></ul>';
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
    echo '<button class="quick-view" data-quick-view="' . esc_attr( $id ) . '">' . esc_html__( 'Quick View', 'auracast' ) . '</button></div><div class="product-category">' . esc_html( $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '' ) . '</div><div class="product-title-row"><h3><a href="' . esc_url( $url ) . '">' . esc_html( $product->get_name() ) . '</a></h3><button class="wishlist-button" data-wishlist="' . esc_attr( $id ) . '" aria-label="' . esc_attr( sprintf( __( 'Save %s to wishlist', 'auracast' ), $product->get_name() ) ) . '" aria-pressed="false">' . auracast_icon( 'heart' ) . '</button></div><span class="price">' . wp_kses_post( $product->get_price_html() ) . '</span>';
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
