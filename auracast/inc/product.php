<?php
/** Product and cart presentation, with native WooCommerce purchasing. */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'is_product' ) ) { return; }
    if ( is_product() ) {
        wp_enqueue_style( 'auracast-product', auracast_asset( 'css/product.css' ), array( 'auracast-yokai' ), AURACAST_VERSION );
    }
    if ( is_cart() ) {
        wp_enqueue_style( 'auracast-cart', auracast_asset( 'css/cart.css' ), array( 'auracast-yokai' ), AURACAST_VERSION );
    }
    if ( is_product() || is_cart() ) {
        wp_enqueue_script( 'auracast-product', auracast_asset( 'js/product.js' ), array( 'jquery', 'auracast-store' ), AURACAST_VERSION, true );
    }
}, 30 );

add_action( 'init', function () {
    if ( ! class_exists( 'WooCommerce' ) ) { return; }
    remove_all_actions( 'woocommerce_before_single_product_summary' );
    add_action( 'woocommerce_before_single_product_summary', 'auracast_product_gallery', 20 );
    remove_all_actions( 'woocommerce_single_product_summary' );
    add_action( 'woocommerce_single_product_summary', 'auracast_product_summary', 10 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
    add_action( 'woocommerce_after_single_product_summary', 'auracast_product_reviews', 30 );
}, 30 );

function auracast_product_gallery() {
    global $product;
    $ids = array_values( array_unique( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) ) );
    if ( ! $ids ) { echo '<div class="woocommerce-product-gallery auracast-gallery">' . wc_placeholder_img( 'woocommerce_single' ) . '</div>'; return; }
    ?>
    <div class="woocommerce-product-gallery auracast-gallery" data-product-gallery>
        <button type="button" class="auracast-gallery-main" data-gallery-zoom aria-label="Open product image">
            <?php echo wp_get_attachment_image( $ids[0], 'full', false, array( 'class' => 'auracast-gallery-image', 'fetchpriority' => 'high', 'sizes' => '(max-width: 749px) 86vw, 605px' ) ); ?>
            <span class="gallery-zoom-icon" aria-hidden="true"><?php echo auracast_icon( 'search' ); ?></span>
        </button>
        <div class="auracast-gallery-thumbnails" aria-label="Product images">
            <?php foreach ( $ids as $i => $id ) : ?>
            <button type="button" class="auracast-gallery-thumbnail<?php echo 0 === $i ? ' is-active' : ''; ?>" data-gallery-src="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>" data-gallery-alt="<?php echo esc_attr( get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: $product->get_name() ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Load image %d in gallery view', $i + 1 ) ); ?>" aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>"><?php echo wp_get_attachment_image( $id, 'woocommerce_thumbnail' ); ?></button>
            <?php endforeach; ?>
        </div>
        <dialog class="auracast-gallery-lightbox" id="auracast-gallery-lightbox" aria-label="Product image"><button type="button" class="gallery-lightbox-close" data-close aria-label="Close product image"><?php echo auracast_icon( 'close' ); ?></button><img src="<?php echo esc_url( wp_get_attachment_image_url( $ids[0], 'full' ) ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>"></dialog>
    </div>
    <?php
}

function auracast_product_summary() {
    global $product;
    echo '<h1 class="product_title entry-title">' . esc_html( $product->get_name() ) . '</h1>';
    woocommerce_template_single_price();
    echo '<p class="auracast-product-shipping">Shipping: Inside Dhaka ৳70 · Outside Dhaka ৳150</p>';
    $highlights = '230+ GSM cotton · Oversized fit · Drop shoulder';
    if ( has_term( 'hoodies', 'product_cat', $product->get_id() ) || stripos( $product->get_name(), 'hoodie' ) !== false ) { $highlights = 'Heavyweight cotton · Relaxed fit · Everyday comfort'; }
    if ( stripos( $product->get_name(), 'sweatpants' ) !== false ) { $highlights = 'Premium cotton · Relaxed fit · Everyday comfort'; }
    echo '<p class="auracast-product-highlights">' . esc_html( $highlights ) . '</p>';
    woocommerce_template_single_add_to_cart();
    ?>
    <div class="auracast-product-accordions">
        <details><summary><?php echo auracast_product_line_icon( 'details' ); ?><span>Product Details</span><span class="accordion-plus" aria-hidden="true">+</span></summary><div class="product-accordion-content"><?php echo wp_kses_post( wpautop( $product->get_description() ?: $product->get_short_description() ) ); ?></div></details>
        <details><summary><?php echo auracast_product_line_icon( 'truck' ); ?><span>Shipping Details</span><span class="accordion-plus" aria-hidden="true">+</span></summary><div class="product-accordion-content"><p><strong>Inside Dhaka</strong><br>Delivery within 2–4 business days<br>70 Taka delivery charge.</p><p><strong>Outside Dhaka</strong><br>Delivery within 4–6 business days<br>150 Taka delivery charge.</p></div></details>
        <details><summary><?php echo auracast_product_line_icon( 'exchange' ); ?><span>Exchange Policy</span><span class="accordion-plus" aria-hidden="true">+</span></summary><div class="product-accordion-content"><p>Request an eligible exchange within 72 hours of receiving your order. Items must be unworn, unwashed, unused and returned in their original condition with packaging and tags.</p><p>For a size change, manufacturing defect or incorrect item, contact us with your order number and clear photos. Exchanges depend on stock availability.</p><a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Contact information</a></div></details>
        <details class="product-care"><summary><?php echo auracast_product_line_icon( 'care' ); ?><span>Wash &amp; Care Instructions</span><span class="accordion-caret" aria-hidden="true">⌄</span></summary><div class="product-accordion-content"><p>Wash inside out with cold water and similar colors. Use a mild detergent. Do not bleach, tumble dry or iron directly over the print. Dry in the shade.</p></div></details>
    </div>
    <button type="button" class="auracast-product-share" data-share="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo auracast_product_line_icon( 'share' ); ?> Share</button>
    <?php
}

function auracast_product_line_icon( $name ) {
    $paths = array(
        'cash' => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="M6 8h12M6 16h12"/><circle cx="12" cy="12" r="2"/>',
        'exchange' => '<path d="M4 8a8 8 0 0 1 13-3l3 3M20 3v5h-5M20 16a8 8 0 0 1-13 3l-3-3M4 21v-5h5"/>',
        'return' => '<path d="M8 6 3 11l5 5M3 11h12a5 5 0 0 1 0 10"/>',
        'truck' => '<path d="M2 5h12v13H2zM14 10h4l4 5v3h-8"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
        'details' => '<path d="M7 4h3V2h4v2h3v18H7zM10 7h4M10 11h4M10 15h4"/>',
        'care' => '<path d="m3 6 2 14h14l2-14M3 10c3-3 5 3 9 0s6 3 9 0"/>',
        'share' => '<path d="M12 16V3m-4 4 4-4 4 4M5 13v7h14v-7"/>',
    );
    return '<svg class="product-line-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? $paths['details'] ) . '</svg>';
}

function auracast_product_purchase_top() {
    ?>
    <div class="auracast-purchase-mode">
        <div class="auracast-single-buttons"><button type="button" class="button auracast-single-buy" data-product-purchase="single-buy">Buy Now →</button><button type="button" class="button auracast-single-add" data-product-purchase="single-add">Add to Cart</button></div>
        <div class="auracast-trust-strip">
            <?php foreach ( array( 'cash' => 'Cash on Delivery', 'exchange' => 'Free size exchanges', 'return' => 'Easy returns', 'truck' => 'Delivery across Bangladesh' ) as $icon => $label ) : ?><div class="auracast-trust-item"><span><?php echo auracast_product_line_icon( $icon ); ?></span><?php echo esc_html( $label ); ?></div><?php endforeach; ?>
        </div>
    </div>
    <?php
}

function auracast_bundle_discount( $quantity ) {
    return $quantity >= 5 ? 15 : ( $quantity >= 4 ? 12 : ( $quantity >= 3 ? 10 : ( $quantity >= 2 ? 5 : 0 ) ) );
}

function auracast_product_bundle_picker() {
    global $product;
    if ( $product->is_sold_individually() ) { return; }
    $price = (float) $product->get_price();
    $offers = array( 1 => array( 'Single', '', array( 'Just One' ) ), 2 => array( 'Duo', 'Popular', array() ), 3 => array( 'Trio', 'MOST POPULAR', array( 'FREE DELIVERY ABOVE TK 2,000', 'Fan Favorite Bundle' ) ), 4 => array( '4 Pack', 'BEST VALUE', array( 'FREE DELIVERY ALL OVER BANGLADESH', 'Best Balance of Value & Variety', 'Perfect for Building Your Rotation' ) ), 5 => array( '5 Pack', 'MAXIMUM VALUE', array( 'FREE DELIVERY ALL OVER BANGLADESH', 'Lowest Price Per Piece', 'Ultimate Stock-Up Bundle' ) ) );
    ?>
    <section class="auracast-bundles" aria-labelledby="bundle-title" data-base-price="<?php echo esc_attr( $price ); ?>"><h2 id="bundle-title">Buy more &amp; save</h2><p>Choose a bundle below to unlock a discount</p><div class="auracast-bundle-offers">
    <?php foreach ( $offers as $quantity => $offer ) : $discount = auracast_bundle_discount( $quantity ); ?>
    <div class="auracast-bundle<?php echo 1 === $quantity ? ' is-selected' : ''; ?>" data-bundle="<?php echo (int) $quantity; ?>">
        <?php if ( $offer[1] ) : ?><span class="auracast-bundle-badge"><?php echo esc_html( $offer[1] ); ?></span><?php endif; ?>
        <label class="auracast-bundle-heading"><input type="radio" name="auracast_bundle" value="<?php echo (int) $quantity; ?>" <?php checked( 1, $quantity ); ?>><span class="auracast-bundle-copy"><?php if ( 1 === $quantity ) { echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'bundle-small-image' ) ); } ?><span><span class="bundle-title"><?php echo esc_html( $offer[0] ); ?></span> <span class="bundle-discount"><?php echo (int) $discount; ?>% OFF</span><small><?php echo 1 === $quantity ? 'Just One' : esc_html( sprintf( 'Mix & Match Any %d Products From Our Collection', $quantity ) ); ?></small></span></span><span class="auracast-bundle-price"><span><?php echo wp_kses_post( wc_price( round( $price * ( 1 - $discount / 100 ), wc_get_price_decimals() ) ) ); ?></span><?php if ( $discount ) { echo '<del>' . wp_kses_post( wc_price( $price ) ) . '</del>'; } ?></span></label>
        <?php if ( 1 === $quantity ) : ?><div class="auracast-bundle-sizes" data-bundle-size-chips></div><?php endif; ?>
        <?php if ( 2 === $quantity ) : ?><div class="auracast-bundle-duo-preview"><div class="bundle-preview-item"><?php echo $product->get_image( 'woocommerce_thumbnail' ); ?><strong><?php echo esc_html( $product->get_name() ); ?></strong><span><?php echo wp_kses_post( wc_price( $price * .95 ) ); ?><del><?php echo wp_kses_post( wc_price( $price ) ); ?></del></span></div><span class="bundle-divider">+</span><button type="button" class="bundle-choose-preview" data-bundle-choose="2">⊕ Choose product</button></div><?php endif; ?>
        <?php if ( $quantity >= 3 ) : ?><div class="auracast-bundle-benefits"><span>♧ SAVE <?php echo wp_kses_post( wc_price( $price * $discount / 100 ) ); ?></span><?php foreach ( $offer[2] as $benefit ) { echo '<span>♧ ' . esc_html( $benefit ) . '</span>'; } ?></div><?php endif; ?>
        <div class="auracast-bundle-items" data-bundle-items="<?php echo (int) $quantity; ?>" hidden></div>
    </div>
    <?php endforeach; ?>
    </div><input type="hidden" name="auracast_bundle_items" value=""><input type="hidden" name="auracast_buy_now" value="0"></section>
    <?php
    auracast_bundle_products_dialog();
}

function auracast_bundle_products_dialog() {
    global $product;
    $products = wc_get_products( array( 'limit' => 60, 'status' => 'publish', 'stock_status' => 'instock', 'orderby' => 'menu_order', 'order' => 'ASC' ) );
    $catalog = array();
    foreach ( $products as $item ) {
        if ( ! $item->is_purchasable() || ! $item->is_visible() || ! $item->is_type( array( 'simple', 'variable' ) ) ) { continue; }
        $variants = array();
        if ( $item->is_type( 'variable' ) ) {
            foreach ( $item->get_available_variations() as $variation ) {
                if ( $variation['is_purchasable'] && $variation['is_in_stock'] ) { $variants[] = array( 'id' => $variation['variation_id'], 'attributes' => $variation['attributes'], 'price' => $variation['display_price'] ); }
            }
        }
        $catalog[] = array( 'id' => $item->get_id(), 'name' => $item->get_name(), 'price' => (float) $item->get_price(), 'image' => wp_get_attachment_image_url( $item->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src(), 'variants' => $variants );
    }
    ?>
    <dialog id="auracast-bundle-products" class="auracast-bundle-products" aria-labelledby="bundle-products-title"><div class="dialog-heading"><h2 id="bundle-products-title">Choose product</h2><button type="button" class="icon-button" data-close aria-label="Close products"><?php echo auracast_icon( 'close' ); ?></button></div><input type="search" data-bundle-search placeholder="Search products" aria-label="Search products"><div class="bundle-products-grid" data-bundle-products></div></dialog>
    <script type="application/json" id="auracast-bundle-catalog"><?php echo wp_json_encode( array( 'current' => $product->get_id(), 'currency' => get_woocommerce_currency_symbol(), 'products' => $catalog ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
    <?php
}

function auracast_product_reviews() {
    global $product;
    if ( ! $product || ! comments_open( $product->get_id() ) ) { return; }
    $store_reviews = get_comments( array( 'type' => 'review', 'status' => 'approve', 'post__not_in' => array( $product->get_id() ), 'number' => 20, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
    $store_count = get_comments( array( 'type' => 'review', 'status' => 'approve', 'post__not_in' => array( $product->get_id() ), 'count' => true ) );
    echo '<section class="auracast-customer-reviews" aria-labelledby="customer-reviews-title"><h2 id="customer-reviews-title">Customer Reviews</h2><div class="auracast-reviews-toolbar"><div class="auracast-review-tabs" role="tablist" aria-label="Reviews"><button type="button" role="tab" aria-selected="true" data-reviews-tab="product">Product reviews (' . (int) $product->get_review_count() . ')</button><button type="button" role="tab" aria-selected="false" data-reviews-tab="store">Store reviews (' . (int) $store_count . ')</button></div><select data-review-sort aria-label="Sort reviews by"><option value="recent">Most Recent</option><option value="highest">Highest Rating</option><option value="lowest">Lowest Rating</option><option value="pictures">Only Pictures</option><option value="pictures-first">Pictures First</option><option value="videos-first">Videos First</option><option value="helpful">Most Helpful</option></select></div><div data-review-panel="product">';
    if ( ! $product->get_review_count() ) { echo '<div class="auracast-review-invitation"><p>Be the first to write a review</p><button type="button" class="button" data-write-review>Write a review</button></div>'; }
    else { echo '<button type="button" class="button auracast-write-review" data-write-review>Write a review</button>'; }
    comments_template();
    echo '</div><div data-review-panel="store" hidden>';
    if ( ! $store_reviews ) { echo '<p class="auracast-no-store-reviews">There are no store reviews yet.</p>'; }
    foreach ( $store_reviews as $review ) {
        echo '<article class="auracast-store-review">' . wc_get_rating_html( (int) get_comment_meta( $review->comment_ID, 'rating', true ) ) . '<p class="store-review-author">' . esc_html( $review->comment_author ) . '<time datetime="' . esc_attr( $review->comment_date_gmt ) . '">' . esc_html( get_comment_date( 'm/d/Y', $review ) ) . '</time></p><div>' . wp_kses_post( wpautop( $review->comment_content ) ) . '</div><p class="store-review-product">Review for <a href="' . esc_url( get_permalink( $review->comment_post_ID ) ) . '">' . esc_html( get_the_title( $review->comment_post_ID ) ) . '</a></p></article>';
    }
    echo '</div></section>';
}

add_filter( 'woocommerce_product_related_products_heading', function () { return __( 'You may also like', 'auracast' ); } );
add_filter( 'woocommerce_related_products', function ( $ids, $product_id ) {
    if ( count( $ids ) < 4 ) {
        $more = wc_get_products( array( 'limit' => 8, 'status' => 'publish', 'exclude' => array_merge( $ids, array( $product_id ) ), 'return' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC' ) );
        $ids = array_slice( array_unique( array_merge( $ids, $more ) ), 0, 4 );
    }
    return $ids;
}, 20, 2 );

add_filter( 'woocommerce_add_to_cart_redirect', function ( $url ) {
    if ( isset( $_POST['auracast_buy_now'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['auracast_buy_now'] ) ) ) { return wc_get_checkout_url(); }
    return $url;
}, 20 );

/** Percent savings are calculated from catalog prices on every cart update. */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) { return; }
    $discount = auracast_bundle_discount( $cart->get_cart_contents_count() );
    foreach ( $cart->get_cart() as $key => $item ) {
        $fresh = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
        if ( ! $fresh ) { continue; }
        $base = (float) $fresh->get_price();
        $cart->cart_contents[ $key ]['auracast_catalog_price'] = $base;
        $item['data']->set_price( round( $base * ( 1 - $discount / 100 ), wc_get_price_decimals() ) );
    }
}, 25 );

function auracast_requested_bundle_items() {
    if ( empty( $_POST['auracast_bundle_items'] ) ) { return array(); }
    $raw = json_decode( wp_unslash( $_POST['auracast_bundle_items'] ), true );
    if ( ! is_array( $raw ) || count( $raw ) > 4 ) { return array(); }
    $items = array();
    foreach ( $raw as $item ) {
        $id = absint( $item['id'] ?? 0 ); $variation_id = absint( $item['variation'] ?? 0 );
        $product = wc_get_product( $variation_id ?: $id );
        $parent = wc_get_product( $id );
        if ( ! $product || ! $parent || 'publish' !== $parent->get_status() || ! $product->is_purchasable() || ! $product->is_in_stock() || ( $variation_id && $product->get_parent_id() !== $id ) || ( $parent->is_type( 'variable' ) && ! $variation_id ) ) { return false; }
        $attributes = $variation_id ? $product->get_variation_attributes() : array();
        foreach ( $attributes as $name => $value ) {
            if ( '' === $value ) {
                $posted = sanitize_text_field( $item['attributes'][ $name ] ?? '' );
                $allowed = $parent->get_variation_attributes()[ str_replace( 'attribute_', '', $name ) ] ?? array();
                if ( ! in_array( $posted, $allowed, true ) ) { return false; }
                $attributes[ $name ] = $posted;
            }
        }
        $items[] = array( 'id' => $id, 'variation' => $variation_id, 'attributes' => $attributes );
    }
    return $items;
}

add_filter( 'woocommerce_add_to_cart_validation', function ( $valid, $product_id, $quantity ) {
    if ( ! empty( $GLOBALS['auracast_adding_bundle'] ) || empty( $_POST['auracast_bundle_items'] ) ) { return $valid; }
    $items = auracast_requested_bundle_items();
    if ( false === $items ) { wc_add_notice( 'Please choose an available product and size for every bundle item.', 'error' ); return false; }
    $counts = array();
    foreach ( $items as $item ) { $id = $item['variation'] ?: $item['id']; $counts[ $id ] = ( $counts[ $id ] ?? 0 ) + 1; }
    $main_id = absint( $_POST['variation_id'] ?? $product_id );
    if ( ! $main_id ) { $main_id = $product_id; }
    $counts[ $main_id ] = ( $counts[ $main_id ] ?? 0 ) + $quantity;
    foreach ( $counts as $id => $count ) {
        $product = wc_get_product( $id );
        if ( ! $product || ( $product->is_sold_individually() && $count > 1 ) ) { wc_add_notice( 'A selected product can only be bought individually.', 'error' ); return false; }
        $existing = 0;
        foreach ( WC()->cart->get_cart() as $cart_item ) { if ( $id === ( $cart_item['variation_id'] ?: $cart_item['product_id'] ) ) { $existing += $cart_item['quantity']; } }
        if ( ! $product->has_enough_stock( $count + $existing ) ) { wc_add_notice( 'There is not enough stock for the selected bundle.', 'error' ); return false; }
    }
    return $valid;
}, 20, 3 );

add_action( 'woocommerce_add_to_cart', function () {
    if ( ! empty( $GLOBALS['auracast_adding_bundle'] ) || empty( $_POST['auracast_bundle_items'] ) ) { return; }
    $items = auracast_requested_bundle_items();
    if ( ! is_array( $items ) ) { return; }
    $GLOBALS['auracast_adding_bundle'] = true;
    foreach ( $items as $item ) { WC()->cart->add_to_cart( $item['id'], 1, $item['variation'], $item['attributes'] ); }
    $GLOBALS['auracast_adding_bundle'] = false;
}, 20 );
