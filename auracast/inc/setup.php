<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () { add_theme_page( 'Auracast Setup', 'Auracast Setup', 'manage_options', 'auracast-setup', 'auracast_setup_page' ); } );
add_action( 'after_switch_theme', function () { update_option( 'auracast_setup_notice', 1, false ); } );
add_action( 'admin_notices', function () {
    if ( get_option( 'auracast_setup_notice' ) && current_user_can( 'manage_options' ) && ( $_GET['page'] ?? '' ) !== 'auracast-setup' ) { echo '<div class="notice notice-info"><p><strong>Auracast is ready.</strong> <a href="' . esc_url( admin_url( 'themes.php?page=auracast-setup' ) ) . '">Open Auracast Setup</a> to import the demo storefront and configure your brand.</p></div>'; }
} );

function auracast_setup_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    delete_option( 'auracast_setup_notice' );
    $nonce = wp_create_nonce( 'auracast_import' );
    $catalog = json_decode( file_get_contents( get_template_directory() . '/assets/data/catalog.json' ), true );
    $empty_store = class_exists( 'WooCommerce' ) && ! wc_get_products( array( 'limit' => 1, 'status' => 'publish', 'return' => 'ids' ) );
    ?>
    <div class="wrap" style="max-width:900px"><h1>Auracast Setup</h1><p style="font-size:16px">Your fashion storefront, ready to make your own.</p><div style="background:white;padding:30px;margin:24px 0;border:1px solid #ddd"><h2>Import demo store</h2><p>Add the homepage, <?php echo count( $catalog ); ?> sample products with photos and size variations, collections, journal, about, contact, wishlist, shopping pages, and navigation. Products and pages that already exist are retained. Images and products are editable after import. Shipping, payments, and taxes are configured separately in WooCommerce.</p>
    <?php if ( ! class_exists( 'WooCommerce' ) ) : ?><p><strong>Install and activate WooCommerce first.</strong></p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">Install WooCommerce</a><?php else : ?><p><label><input id="auracast-use-home" type="checkbox" checked> Use the Auracast demo homepage and journal as the site's front page and posts page.</label></p><p><label><input id="auracast-use-classic" type="checkbox" checked> Use the styled classic WooCommerce cart and checkout. This replaces the content of your existing cart and checkout pages with WooCommerce shortcodes.</label></p><p><label><input id="auracast-use-bd" type="checkbox" <?php checked( $empty_store ); ?>> Set currency to Bangladeshi taka (BDT) and store country to Bangladesh. Demo prices are in BDT.</label></p><button id="auracast-import" class="button button-primary">Import demo store</button><p id="auracast-progress" role="status" aria-live="polite"></p><progress id="auracast-meter" max="<?php echo count( $catalog ) + 3; ?>" value="0" style="width:100%;display:none"></progress><?php endif; ?></div>
    <div style="background:white;padding:30px;margin:24px 0;border:1px solid #ddd"><h2>Make it yours</h2><p>Edit every homepage section, upload your own campaign images, set contact details, choose the catalog grid, and enable the optional newsletter popup.</p><a class="button" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=auracast' ) ); ?>">Customize Auracast</a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings' ) ); ?>">WooCommerce settings</a><a class="button" href="<?php echo esc_url( home_url() ); ?>">View storefront</a></div>
    <div style="background:white;padding:30px;margin:24px 0;border:1px solid #ddd"><h2>Newsletter subscribers</h2><p>Signups are saved in WordPress with their consent and date. Export them for your email provider. The form does not automatically send marketing emails.</p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=auracast_export_subscribers' ), 'auracast_export_subscribers' ) ); ?>">Export subscribers as CSV</a></div></div>
    <script>
    document.getElementById('auracast-import')?.addEventListener('click', async function(){
        const button=this,status=document.getElementById('auracast-progress'),meter=document.getElementById('auracast-meter');button.disabled=true;meter.style.display='block';
        const count=<?php echo (int) count( $catalog ); ?>;
        const steps=['pages',...Array.from({length:count},(_,i)=>String(i)),'posts','finish'];
        try { for(let i=0;i<steps.length;i++){status.textContent='Importing '+(i+1)+' of '+steps.length+'…';const body=new URLSearchParams({action:'auracast_import_step',nonce:<?php echo wp_json_encode( $nonce ); ?>,step:steps[i],use_classic:document.getElementById('auracast-use-classic').checked?'1':'0',use_bd:document.getElementById('auracast-use-bd').checked?'1':'0',use_home:document.getElementById('auracast-use-home').checked?'1':'0'});const response=await fetch(ajaxurl,{method:'POST',body});const result=await response.json();if(!result.success)throw new Error(result.data?.message||'Import failed');meter.value=i+1;status.textContent=result.data.message;}status.textContent='Your demo storefront is ready. Open Customize Auracast to make it yours.';button.textContent='Import again'; }
        catch(error){status.textContent=error.message+' You can retry safely; completed items are retained.';}finally{button.disabled=false;}
    });
    </script>
    <?php
}

add_action( 'wp_ajax_auracast_import_step', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Not permitted.' ), 403 ); }
    check_ajax_referer( 'auracast_import', 'nonce' );
    if ( ! class_exists( 'WooCommerce' ) ) { wp_send_json_error( array( 'message' => 'WooCommerce must be active.' ), 400 ); }
    $step = sanitize_text_field( wp_unslash( $_POST['step'] ?? '' ) );
    try {
        if ( $step === 'pages' ) { auracast_import_pages( ! empty( $_POST['use_home'] ), ! empty( $_POST['use_classic'] ) ); if ( ! empty( $_POST['use_bd'] ) ) { update_option( 'woocommerce_currency', 'BDT' ); update_option( 'woocommerce_default_country', 'BD' ); } $message = 'Pages created.'; }
        elseif ( $step === 'posts' ) { auracast_import_posts(); $message = 'Journal stories created.'; }
        elseif ( $step === 'finish' ) { auracast_import_menu(); flush_rewrite_rules(); $message = 'Store setup complete.'; }
        elseif ( ctype_digit( $step ) ) { $catalog = json_decode( file_get_contents( get_template_directory() . '/assets/data/catalog.json' ), true ); $item = $catalog[ (int) $step ] ?? null; if ( ! $item ) { throw new RuntimeException( 'Unknown product.' ); } auracast_import_product( $item ); $message = 'Imported ' . $item['name'] . '.'; }
        else { throw new RuntimeException( 'Unknown import step.' ); }
        wp_send_json_success( array( 'message' => $message ) );
    } catch ( Throwable $e ) { wp_send_json_error( array( 'message' => $e->getMessage() ), 500 ); }
} );

function auracast_import_image( $file, $title = '' ) {
    $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_auracast_source_file', 'meta_value' => $file, 'fields' => 'ids', 'numberposts' => 1 ) );
    if ( $existing ) { return $existing[0]; }
    $source = get_template_directory() . '/assets/images/' . basename( $file );
    if ( ! is_file( $source ) ) { throw new RuntimeException( 'Demo image is missing: ' . $file ); }
    $upload = wp_upload_bits( basename( $file ), null, file_get_contents( $source ) );
    if ( $upload['error'] ) { throw new RuntimeException( $upload['error'] ); }
    $type = wp_check_filetype( $file );
    $id = wp_insert_attachment( array( 'post_mime_type' => $type['type'], 'post_title' => $title ?: pathinfo( $file, PATHINFO_FILENAME ), 'post_status' => 'inherit' ), $upload['file'] );
    if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Could not import image.' ); }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
    update_post_meta( $id, '_wp_attachment_image_alt', $title );
    update_post_meta( $id, '_auracast_source_file', $file );
    return $id;
}

function auracast_import_pages( $use_home = true, $use_classic = false ) {
    $pages = array( 'size-guide' => array( 'Size Guide', '<h3>Find your fit</h3><p>Fit varies between pieces. Check the measurements in the product description, and compare them with a garment you already wear.</p><h3>How to measure</h3><p>Lay your garment flat. Measure the chest across from armpit to armpit, the length from shoulder to hem, and the sleeve from shoulder seam to cuff.</p><p>For sweatpants, compare the waist and inseam. Contact us before ordering if you need help choosing your size.</p>' ), 'home' => array( 'Home', '' ), 'shop' => array( 'Shop', '' ), 'cart' => array( 'Cart', '[woocommerce_cart]' ), 'checkout' => array( 'Checkout', '[woocommerce_checkout]' ), 'my-account' => array( 'My Account', '[woocommerce_my_account]' ), 'wishlist' => array( 'Wishlist', '' ), 'blog' => array( 'Journal', '' ), 'about-us' => array( 'About Auracast', '' ), 'contact-us' => array( 'Contact Us', '' ) );
    $ids = array();
    foreach ( $pages as $slug => $info ) {
        $existing = get_page_by_path( $slug );
        $ids[ $slug ] = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $info[0], 'post_content' => $info[1] ) );
        if ( is_wp_error( $ids[ $slug ] ) || ! $ids[ $slug ] ) { throw new RuntimeException( 'Could not create ' . $info[0] . '.' ); }
    }
    foreach ( array( 'shop', 'cart', 'checkout', 'my-account' ) as $slug ) { $option = 'woocommerce_' . str_replace( '-', '', $slug ) . '_page_id'; if ( ! get_option( $option ) || ! get_post( get_option( $option ) ) ) { update_option( $option, $ids[ $slug ] ); } }
    if ( $use_classic ) { foreach ( array( 'cart', 'checkout' ) as $slug ) { wp_update_post( array( 'ID' => get_option( 'woocommerce_' . $slug . '_page_id' ), 'post_content' => '[woocommerce_' . $slug . ']' ) ); } }
    if ( $use_home ) { update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $ids['home'] ); update_option( 'page_for_posts', $ids['blog'] ); }
    if ( ! get_option( 'permalink_structure' ) ) { update_option( 'permalink_structure', '/%postname%/' ); }
    update_option( 'auracast_demo_pages', $ids, false );
}

function auracast_import_attribute( $slug, $label, $options ) {
    $taxonomy = 'pa_' . $slug;
    $attribute_id = wc_attribute_taxonomy_id_by_name( $taxonomy );
    if ( ! $attribute_id ) { $attribute_id = wc_create_attribute( array( 'name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order' ) ); delete_transient( 'wc_attribute_taxonomies' ); }
    if ( is_wp_error( $attribute_id ) ) { throw new RuntimeException( $attribute_id->get_error_message() ); }
    if ( ! taxonomy_exists( $taxonomy ) ) { register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'label' => $label, 'public' => true ) ); }
    $term_ids = array();
    foreach ( $options as $name ) { $term = term_exists( $name, $taxonomy ); if ( ! $term ) { $term = wp_insert_term( $name, $taxonomy, array( 'slug' => sanitize_title( $name ) ) ); } if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); } $term_ids[] = (int) $term['term_id']; }
    $attribute = new WC_Product_Attribute(); $attribute->set_id( $attribute_id ); $attribute->set_name( $taxonomy ); $attribute->set_options( $term_ids ); $attribute->set_visible( true ); $attribute->set_variation( true );
    return $attribute;
}

function auracast_import_product( $item ) {
    $sku = 'AURA-' . strtoupper( substr( md5( $item['slug'] ), 0, 8 ) );
    $existing = wc_get_product_id_by_sku( $sku );
    if ( ! $existing ) { $post = get_page_by_path( $item['slug'], OBJECT, 'product' ); $existing = $post ? $post->ID : 0; }
    if ( $existing ) { return $existing; }
    $term = term_exists( $item['category'], 'product_cat' );
    if ( ! $term ) { $term = wp_insert_term( $item['category'], 'product_cat', array( 'slug' => sanitize_title( $item['category'] ) ) ); }
    if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
    $product = $item['type'] === 'variable' ? new WC_Product_Variable() : new WC_Product_Simple();
    $product->set_name( $item['name'] ); $product->set_slug( $item['slug'] ); $product->set_sku( $sku );
    $product->set_status( 'publish' ); $product->set_catalog_visibility( 'visible' ); $product->set_category_ids( array( (int) $term['term_id'] ) );
    $product->set_menu_order( (int) ( $item['menuOrder'] ?? 0 ) );
    if ( ! empty( $item['collection'] ) && in_array( $item['collection'], array( 'Music & Culture', 'Anime' ), true ) ) {
        $tag = term_exists( $item['collection'], 'product_tag' );
        if ( ! $tag ) { $tag = wp_insert_term( $item['collection'], 'product_tag', array( 'slug' => $item['collection'] === 'Anime' ? 'anime' : 'music-culture' ) ); }
        if ( ! is_wp_error( $tag ) ) { $product->set_tag_ids( array( (int) $tag['term_id'] ) ); }
    }
    $product->set_short_description( 'A considered addition to your everyday wardrobe. Beautiful texture, an effortless silhouette, and thoughtful details — made to be worn your way.' );
    $product->set_description( '<p>' . esc_html( $item['name'] ) . ' from Auracast. A versatile piece to make your own, designed with a considered silhouette and an emphasis on everyday comfort.</p><p>Style with your favorite essentials for a look that feels effortlessly yours.</p><h3>Details & care</h3><ul><li>Regular fit</li><li>Check the care label before washing</li><li>Store in a cool, dry place</li></ul>' );
    $product->set_image_id( auracast_import_image( $item['image'], $item['name'] ) );
    $gallery = array(); foreach ( $item['gallery'] ?? array_filter( array( $item['hover'] ?? '' ) ) as $file ) { if ( $file !== $item['image'] ) { $gallery[] = auracast_import_image( $file, $item['name'] ); } } $product->set_gallery_image_ids( $gallery );
    $regular = max( $item['price'], $item['regularPrice'] );
    if ( $item['type'] === 'simple' ) { $product->set_regular_price( $regular ); if ( $item['price'] < $regular ) { $product->set_sale_price( $item['price'] ); } $product->set_manage_stock( true ); $product->set_stock_quantity( 25 ); }
    else {
        $attributes = array( auracast_import_attribute( 'size', 'Size', $item['sizes'] ) );
        if ( ! empty( $item['colors'] ) ) { $attributes[] = auracast_import_attribute( 'color', 'Color', $item['colors'] ); }
        $product->set_attributes( $attributes );
    }
    $id = $product->save();
    if ( ! $id ) { throw new RuntimeException( 'Could not create product.' ); }
    if ( $item['type'] === 'variable' ) {
        foreach ( $item['colors'] ?? array( '' ) as $color_index => $color ) { foreach ( $item['sizes'] as $size ) {
            $variation = new WC_Product_Variation(); $variation->set_parent_id( $id );
            $attributes = array( 'pa_size' => sanitize_title( $size ) ); if ( $color ) { $attributes['pa_color'] = sanitize_title( $color ); }
            $variation->set_attributes( $attributes ); $variation->set_regular_price( $regular );
            if ( $item['price'] < $regular ) { $variation->set_sale_price( $item['price'] ); }
            $variation->set_manage_stock( true ); $variation->set_stock_quantity( 15 ); $variation->set_status( 'publish' ); $variation->save();
        } }
        WC_Product_Variable::sync( $id );
    }
    update_post_meta( $id, '_auracast_demo', '1' );
    if ( ! empty( $item['isNew'] ) ) { update_post_meta( $id, '_auracast_new', '1' ); }
    return $id;
}

function auracast_import_posts() {
    $stories = array(
        array( 'a-graphic-tee-your-own-way', 'A Graphic Tee, Your Own Way', 'streetwear-overtime-graphic-tee-0.jpg', 'A bold graphic, a relaxed fit, and the everyday pieces that make the look yours.', 'Style', '2026-10-03 12:00:00' ),
        array( 'the-everyday-uniform', 'The Everyday Uniform', 'streetwear-essential-tee-forest-0.jpg', 'Build your rotation around easy fits, clean colors, and pieces you enjoy wearing.', 'Inspiration', '2026-10-02 12:00:00' ),
        array( 'made-for-your-world', 'Made for Your World', 'streetwear-essential-hoodie-grey-0.jpg', 'Music, art, and the stories we carry with us. Find inspiration for your next look.', 'Journal', '2026-10-01 12:00:00' ),
    );
    foreach ( $stories as $story ) {
        if ( get_page_by_path( $story[0], OBJECT, 'post' ) ) { continue; }
        $category = get_cat_ID( $story[4] ) ?: wp_insert_term( $story[4], 'category' ); if ( is_array( $category ) ) { $category = $category['term_id']; }
        $content = '<p>' . esc_html( $story[3] ) . '</p><h2>A fresh perspective on everyday style</h2><p>The pieces we reach for most often have a way of becoming part of our story. A familiar silhouette, a beautiful texture, a detail that feels distinctly our own — the best wardrobes are built from these small discoveries.</p><p>At Auracast, we look for the balance between expression and ease. Start with a thoughtful foundation, add an unexpected accessory, and make room for the pieces that make you feel like yourself.</p><blockquote>Style is personal. Wear the pieces that feel like you.</blockquote><h2>Make it your own</h2><p>Explore our collections for considered essentials and new favorites. Look for shapes that work together, textures you enjoy wearing, and colors that make getting dressed feel effortless.</p>';
        $id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $story[0], 'post_title' => $story[1], 'post_excerpt' => $story[3], 'post_content' => $content, 'post_category' => array( (int) $category ), 'post_date' => $story[5] ) );
        if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Could not create journal story.' ); }
        set_post_thumbnail( $id, auracast_import_image( $story[2], $story[1] ) );
    }
}

function auracast_import_menu() {
    $pages = get_option( 'auracast_demo_pages', array() );
    if ( ! $pages ) { return; }
    $locations = get_theme_mod( 'nav_menu_locations', array() );
    if ( empty( $locations['primary'] ) ) {
        $menu = wp_get_nav_menu_object( 'Auracast Main Menu' ); $menu_id = $menu ? $menu->term_id : wp_create_nav_menu( 'Auracast Main Menu' );
        if ( is_wp_error( $menu_id ) ) { throw new RuntimeException( $menu_id->get_error_message() ); }
        $items = wp_get_nav_menu_items( $menu_id );
        if ( ! $items ) {
            $add = function ( $label, $url, $parent = 0 ) use ( $menu_id ) { return wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $label, 'menu-item-url' => $url, 'menu-item-parent-id' => $parent, 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) ); };
            $add( 'Home', home_url() ); $add( 'Blog', get_permalink( $pages['blog'] ) );
            $shop = $add( 'Shop', auracast_shop_url() );
            $collections = $add( 'Collections', auracast_shop_url(), $shop ); foreach ( array( 'Graphic Tees', 'Essentials', 'Shirts', 'Sweatpants', 'Hoodies' ) as $category ) { $add( $category, auracast_category_url( sanitize_title( $category ) ), $collections ); } $add( 'Anime', auracast_collection_url( 'anime' ), $collections ); $add( 'Music & Culture', auracast_collection_url( 'music-culture' ), $collections );
            $grids = $add( 'Listing Layouts', auracast_shop_url(), $shop ); for ( $i = 2; $i <= 6; $i++ ) { $add( $i . ' Products per Row', add_query_arg( array( 'layout' => 'grid', 'columns' => $i ), auracast_shop_url() ), $grids ); } foreach ( array( '3-4', '4-5', '5-6' ) as $layout ) { $add( 'Alternating ' . $layout, add_query_arg( 'layout', 'alternating-' . $layout, auracast_shop_url() ), $grids ); }
            $shop_pages = $add( 'Shop Pages', auracast_shop_url(), $shop ); foreach ( array( 'cart' => 'Cart', 'checkout' => 'Checkout', 'my-account' => 'My Account', 'wishlist' => 'Wishlist' ) as $slug => $label ) { $add( $label, get_permalink( $pages[ $slug ] ), $shop_pages ); }
            $products = $add( 'Products', auracast_shop_url() ); foreach ( array( 'essential-tee-black', 'overtime-graphic-tee', 'everyday-sweatpants-black', 'essential-hoodie-grey' ) as $slug ) { $p = auracast_product_by_slug( $slug ); if ( $p ) { $add( $p->get_name(), $p->get_permalink(), $products ); } }
            $page_parent = $add( 'Pages', get_permalink( $pages['about-us'] ) ); foreach ( array( 'about-us' => 'About Us', 'contact-us' => 'Contact Us', 'wishlist' => 'Wishlist', 'my-account' => 'My Account' ) as $slug => $label ) { $add( $label, get_permalink( $pages[ $slug ] ), $page_parent ); }
        }
        $locations['primary'] = $menu_id;
    }
    if ( empty( $locations['footer'] ) ) {
        $menu = wp_get_nav_menu_object( 'Auracast Footer' ); $id = $menu ? $menu->term_id : wp_create_nav_menu( 'Auracast Footer' );
        if ( ! wp_get_nav_menu_items( $id ) ) { foreach ( array( 'home' => 'Home', 'blog' => 'Blog', 'contact-us' => 'Contact Us' ) as $slug => $label ) { wp_update_nav_menu_item( $id, 0, array( 'menu-item-title' => $label, 'menu-item-url' => $slug === 'home' ? home_url() : get_permalink( $pages[ $slug ] ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) ); } }
        $locations['footer'] = $id;
    }
    set_theme_mod( 'nav_menu_locations', $locations );
}
