<?php
/** Editable Instagram posts and reels; media stays in the merchant's WordPress library. */
defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
    register_post_type( 'auracast_reel', array(
        'labels' => array( 'name' => 'Instagram & reels', 'singular_name' => 'Instagram post or reel', 'add_new_item' => 'Add Instagram post or reel', 'edit_item' => 'Edit Instagram post or reel', 'all_items' => 'All posts & reels', 'add_new' => 'Add post or reel', 'menu_name' => 'Instagram & reels' ),
        'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-instagram', 'menu_position' => 59,
        'show_in_rest' => false, 'exclude_from_search' => true, 'publicly_queryable' => false, 'rewrite' => false,
        'supports' => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
    ) );
} );

function auracast_instagram_url( $url ) {
    $url = esc_url_raw( $url, array( 'https' ) ); $parts = wp_parse_url( $url );
    if ( ! $parts || ! in_array( strtolower( $parts['host'] ?? '' ), array( 'instagram.com', 'www.instagram.com', 'm.instagram.com' ), true ) ) { return ''; }
    if ( ! preg_match( '#^/(reel|p|tv)/([A-Za-z0-9_-]{3,64})/?$#', $parts['path'] ?? '', $matches ) ) { return ''; }
    return 'https://www.instagram.com/' . $matches[1] . '/' . $matches[2] . '/';
}
function auracast_instagram_settings() {
    return wp_parse_args( get_option( 'auracast_instagram_settings', array() ), array( 'enabled' => 1, 'heading' => 'Follow us on Instagram', 'profile' => 'https://www.instagram.com/yokaiclothingbd/', 'speed' => 24 ) );
}

add_action( 'add_meta_boxes_auracast_reel', function () {
    add_meta_box( 'auracast-reel-media', 'Post or reel media', 'auracast_reel_media_box', 'auracast_reel', 'normal', 'high' );
} );
function auracast_reel_media_box( $post ) {
    wp_nonce_field( 'auracast_reel_media', 'auracast_reel_nonce' );
    $video = (int) get_post_meta( $post->ID, '_auracast_reel_video', true );
    $cover = (int) get_post_meta( $post->ID, '_auracast_reel_cover', true );
    ?>
    <p>Add a video from your Media Library for autoplay in the scrolling cards. You can also paste an Instagram post or reel link. A cover image is optional for videos and gives Instagram links their own card thumbnail.</p>
    <p><label for="auracast-instagram-link"><strong>Instagram post or reel link</strong></label><br><input class="widefat" type="url" id="auracast-instagram-link" name="auracast_instagram_link" placeholder="https://www.instagram.com/reel/.../" value="<?php echo esc_attr( get_post_meta( $post->ID, '_auracast_reel_link', true ) ); ?>"></p>
    <?php foreach ( array( 'video' => array( 'Video', $video, 'video', 'Choose or upload video' ), 'cover' => array( 'Cover image / photo', $cover, 'image', 'Choose or upload image' ) ) as $key => $field ) : $url = $field[1] ? wp_get_attachment_url( $field[1] ) : ''; ?>
    <div class="auracast-reel-media-field" data-reel-media-field="<?php echo esc_attr( $key ); ?>"><p><strong><?php echo esc_html( $field[0] ); ?></strong></p><input type="hidden" name="auracast_reel_<?php echo esc_attr( $key ); ?>" value="<?php echo (int) $field[1]; ?>"><div class="auracast-reel-preview"><?php if ( $url ) : if ( $key === 'video' ) : ?><video controls preload="metadata" src="<?php echo esc_url( $url ); ?>"></video><?php else : ?><img src="<?php echo esc_url( $url ); ?>" alt="Selected cover"><?php endif; endif; ?></div><p><button type="button" class="button" data-reel-pick="<?php echo esc_attr( $field[2] ); ?>"><?php echo esc_html( $field[3] ); ?></button> <button type="button" class="button-link-delete" data-reel-remove>Remove</button></p></div>
    <?php endforeach; ?>
    <p><label for="auracast-reel-video-url"><strong>Or paste a direct video URL</strong></label><br><input type="url" class="widefat" id="auracast-reel-video-url" name="auracast_reel_video_url" placeholder="https://example.com/video.mp4" value="<?php echo esc_attr( get_post_meta( $post->ID, '_auracast_reel_video_url', true ) ); ?>"></p>
    <p>Use the description above for a caption. Choose an Order number in the Attributes box to arrange the cards, then click Publish or Update.</p>
    <?php
}
add_action( 'save_post_auracast_reel', function ( $id ) {
    if ( ! isset( $_POST['auracast_reel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['auracast_reel_nonce'] ) ), 'auracast_reel_media' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return; }
    foreach ( array( 'video' => 'video/', 'cover' => 'image/' ) as $key => $mime ) {
        $attachment = absint( $_POST[ 'auracast_reel_' . $key ] ?? 0 );
        if ( $attachment && ( get_post_type( $attachment ) !== 'attachment' || ! str_starts_with( (string) get_post_mime_type( $attachment ), $mime ) ) ) { $attachment = 0; }
        update_post_meta( $id, '_auracast_reel_' . $key, $attachment );
    }
    $input = sanitize_text_field( wp_unslash( $_POST['auracast_instagram_link'] ?? '' ) );
    $link = auracast_instagram_url( $input );
    if ( $input && ! $link ) { set_transient( 'auracast_reel_notice_' . get_current_user_id(), 'Use a public Instagram post or reel link, such as https://www.instagram.com/reel/ABC123/.', 60 ); }
    else { update_post_meta( $id, '_auracast_reel_link', $link ); }
    $video_url = esc_url_raw( wp_unslash( $_POST['auracast_reel_video_url'] ?? '' ), array( 'https' ) );
    if ( $video_url && ! preg_match( '/\.(mp4|webm)(?:$|\?)/i', $video_url ) ) { $video_url = ''; set_transient( 'auracast_reel_notice_' . get_current_user_id(), 'Use an MP4 or WebM video link, or choose a video from the Media Library.', 60 ); }
    update_post_meta( $id, '_auracast_reel_video_url', $video_url );
} );
add_action( 'admin_notices', function () {
    $key = 'auracast_reel_notice_' . get_current_user_id(); $notice = get_transient( $key );
    if ( $notice ) { delete_transient( $key ); echo '<div class="notice notice-error"><p>' . esc_html( $notice ) . '</p></div>'; }
} );
add_action( 'admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'auracast_reel' ) { return; }
    wp_enqueue_media();
    wp_enqueue_script( 'auracast-reel-editor', auracast_asset( 'js/reel-editor.js' ), array( 'jquery', 'media-views' ), AURACAST_VERSION, true );
    wp_enqueue_style( 'auracast-reel-editor', auracast_asset( 'css/reel-editor.css' ), array(), AURACAST_VERSION );
} );
add_filter( 'manage_auracast_reel_posts_columns', function ( $columns ) { return array( 'cb' => $columns['cb'], 'title' => 'Post or reel', 'auracast_cover' => 'Preview', 'auracast_source' => 'Source', 'auracast_order' => 'Order', 'date' => 'Published' ); } );
add_action( 'manage_auracast_reel_posts_custom_column', function ( $column, $id ) {
    if ( $column === 'auracast_cover' ) { $cover = (int) get_post_meta( $id, '_auracast_reel_cover', true ); if ( $cover ) { echo wp_get_attachment_image( $cover, array( 48, 64 ) ); } }
    if ( $column === 'auracast_source' ) { echo get_post_meta( $id, '_auracast_reel_video', true ) || get_post_meta( $id, '_auracast_reel_video_url', true ) ? 'Video' : ( get_post_meta( $id, '_auracast_reel_link', true ) ? 'Instagram link' : 'Photo' ); }
    if ( $column === 'auracast_order' ) { echo (int) get_post_field( 'menu_order', $id ); }
}, 10, 2 );
add_action( 'pre_get_posts', function ( $query ) { if ( is_admin() && $query->is_main_query() && $query->get( 'post_type' ) === 'auracast_reel' && ! $query->get( 'orderby' ) ) { $query->set( 'orderby', 'menu_order title' ); $query->set( 'order', 'ASC' ); } } );

add_action( 'admin_menu', function () { add_submenu_page( 'edit.php?post_type=auracast_reel', 'Instagram feed settings', 'Feed settings', 'manage_options', 'auracast-instagram-settings', 'auracast_instagram_settings_page' ); } );
add_action( 'admin_init', function () {
    register_setting( 'auracast_instagram', 'auracast_instagram_settings', array( 'sanitize_callback' => function ( $value ) {
        $value = is_array( $value ) ? $value : array(); $profile = esc_url_raw( $value['profile'] ?? '', array( 'https' ) );
        if ( $profile && ! in_array( strtolower( (string) wp_parse_url( $profile, PHP_URL_HOST ) ), array( 'instagram.com', 'www.instagram.com' ), true ) ) { add_settings_error( 'auracast_instagram_settings', 'profile', 'Use an Instagram profile URL.' ); $profile = auracast_instagram_settings()['profile']; }
        return array( 'enabled' => ! empty( $value['enabled'] ), 'heading' => sanitize_text_field( $value['heading'] ?? 'Follow us on Instagram' ), 'profile' => $profile, 'speed' => min( 60, max( 0, (int) ( $value['speed'] ?? 24 ) ) ) );
    } ) );
} );
function auracast_instagram_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; } $settings = auracast_instagram_settings();
    ?>
    <div class="wrap"><h1>Instagram feed settings</h1><p>Add, replace or remove cards in <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=auracast_reel' ) ); ?>">All posts &amp; reels</a>. Published items appear on the storefront automatically.</p><form method="post" action="options.php"><?php settings_fields( 'auracast_instagram' ); settings_errors(); ?><table class="form-table"><tr><th>Show feed</th><td><label><input type="checkbox" name="auracast_instagram_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> Show Instagram posts and reels</label></td></tr><tr><th><label for="reel-heading">Heading</label></th><td><input class="regular-text" id="reel-heading" name="auracast_instagram_settings[heading]" value="<?php echo esc_attr( $settings['heading'] ); ?>"></td></tr><tr><th><label for="reel-profile">Instagram profile</label></th><td><input type="url" class="regular-text" id="reel-profile" name="auracast_instagram_settings[profile]" value="<?php echo esc_attr( $settings['profile'] ); ?>"><p class="description">The destination of the Visit Instagram button.</p></td></tr><tr><th><label for="reel-speed">Scrolling speed</label></th><td><select id="reel-speed" name="auracast_instagram_settings[speed]"><?php foreach ( array( 0 => 'Off — drag or use arrows', 12 => 'Slow', 24 => 'Normal', 40 => 'Fast' ) as $speed => $label ) : ?><option value="<?php echo (int) $speed; ?>" <?php selected( (int) $settings['speed'], $speed ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr></table><?php submit_button(); ?></form></div>
    <?php
}

function auracast_instagram_items() {
    $items = array();
    foreach ( get_posts( array( 'post_type' => 'auracast_reel', 'post_status' => 'publish', 'numberposts' => 30, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $post ) {
        $video_id = (int) get_post_meta( $post->ID, '_auracast_reel_video', true ); $cover_id = (int) get_post_meta( $post->ID, '_auracast_reel_cover', true ) ?: get_post_thumbnail_id( $post->ID );
        $item = array( 'id' => $post->ID, 'title' => $post->post_title, 'caption' => wp_strip_all_tags( $post->post_content ), 'video' => $video_id ? wp_get_attachment_url( $video_id ) : get_post_meta( $post->ID, '_auracast_reel_video_url', true ), 'cover' => $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '', 'link' => auracast_instagram_url( get_post_meta( $post->ID, '_auracast_reel_link', true ) ) );
        if ( $item['video'] || $item['cover'] || $item['link'] ) { $items[] = $item; }
    }
    return $items;
}
function auracast_instagram_feed( $title = 'Follow us on Instagram' ) {
    $settings = auracast_instagram_settings(); $items = auracast_instagram_items();
    if ( empty( $settings['enabled'] ) || ! $items ) { return; }
    if ( $title === 'Follow us on Instagram' ) { $title = $settings['heading']; }
    $uid = wp_unique_id( 'instagram-feed-' );
    ?>
    <section class="instagram-feed" id="<?php echo esc_attr( $uid ); ?>" data-instagram-feed data-speed="<?php echo (int) $settings['speed']; ?>" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title"><h2 id="<?php echo esc_attr( $uid ); ?>-title"><?php echo esc_html( $title ); ?></h2><div class="instagram-marquee" role="region" aria-label="Instagram posts and reels" tabindex="0"><div class="instagram-marquee-track"><?php foreach ( $items as $index => $item ) : ?><button class="instagram-reel-card" type="button" data-reel-index="<?php echo (int) $index; ?>" aria-label="<?php echo esc_attr( 'Open ' . $item['title'] ); ?>"><?php if ( $item['cover'] ) : ?><img src="<?php echo esc_url( $item['cover'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" width="485" height="606" loading="lazy"><?php else : ?><span class="instagram-card-placeholder"><?php echo auracast_icon( 'instagram' ); ?><span><?php echo $item['video'] || str_contains( $item['link'], '/reel/' ) ? 'Watch reel' : 'View post'; ?></span></span><?php endif; ?><?php if ( $item['video'] ) : ?><video data-reel-video="<?php echo esc_url( $item['video'] ); ?>" <?php echo $item['cover'] ? 'poster="' . esc_url( $item['cover'] ) . '"' : ''; ?> muted loop playsinline preload="none" aria-hidden="true"></video><?php endif; ?><span class="instagram-reel-type" aria-hidden="true"><?php echo auracast_icon( $item['video'] || str_contains( $item['link'], '/reel/' ) ? 'reel' : 'instagram' ); ?></span><span class="instagram-card-play" aria-hidden="true"><?php echo auracast_icon( 'play' ); ?></span></button><?php endforeach; ?></div></div><div class="instagram-feed-controls"><button type="button" class="instagram-previous" aria-label="Previous Instagram post"><?php echo auracast_icon( 'chevron' ); ?></button><?php if ( $settings['profile'] ) : ?><a class="instagram-visit" href="<?php echo esc_url( $settings['profile'] ); ?>" target="_blank" rel="noopener noreferrer">Visit Instagram</a><?php endif; ?><button type="button" class="instagram-next" aria-label="Next Instagram post"><?php echo auracast_icon( 'chevron' ); ?></button><button type="button" class="instagram-pause" aria-pressed="false" aria-label="Pause scrolling"><?php echo auracast_icon( 'pause' ); ?></button></div><script type="application/json" class="instagram-feed-data"><?php echo wp_json_encode( $items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script></section>
    <?php
}
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_front_page() && ! ( function_exists( 'is_product' ) && is_product() ) ) { return; }
    wp_enqueue_style( 'auracast-instagram', auracast_asset( 'css/instagram.css' ), array( 'auracast-yokai' ), AURACAST_VERSION );
    wp_enqueue_script( 'auracast-instagram', auracast_asset( 'js/instagram.js' ), array( 'auracast-store' ), AURACAST_VERSION, true );
}, 30 );

function auracast_import_instagram_demo() {
    if ( get_option( 'auracast_instagram_demo_imported' ) || get_posts( array( 'post_type' => 'auracast_reel', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) { return; }
    $file = get_template_directory() . '/assets/data/instagram-demo.json';
    if ( ! is_file( $file ) ) { return; } $demo = json_decode( file_get_contents( $file ), true );
    foreach ( $demo['items'] ?? array() as $order => $item ) {
        $id = wp_insert_post( array( 'post_type' => 'auracast_reel', 'post_status' => 'publish', 'post_title' => $item['title'], 'post_content' => $item['caption'], 'menu_order' => $order ) );
        if ( is_wp_error( $id ) || ! $id ) { continue; }
        update_post_meta( $id, '_auracast_reel_link', auracast_instagram_url( $item['link'] ) );
        $cover = auracast_import_image( $item['cover'], $item['title'] ); update_post_meta( $id, '_auracast_reel_cover', $cover ); set_post_thumbnail( $id, $cover );
        if ( ! empty( $item['video'] ) ) {
            $path = get_template_directory() . '/assets/videos/' . basename( $item['video'] ); $upload = wp_upload_bits( basename( $item['video'] ), null, file_get_contents( $path ) );
            if ( ! $upload['error'] ) { $video = wp_insert_attachment( array( 'post_mime_type' => 'video/mp4', 'post_title' => $item['title'], 'post_status' => 'inherit' ), $upload['file'] ); if ( ! is_wp_error( $video ) && $video ) { update_post_meta( $id, '_auracast_reel_video', $video ); } }
        }
        update_post_meta( $id, '_auracast_reel_demo', 1 );
    }
    if ( ! get_option( 'auracast_instagram_settings' ) ) { update_option( 'auracast_instagram_settings', array( 'enabled' => 1, 'heading' => 'Follow us on Instagram', 'profile' => $demo['profile'], 'speed' => 24 ) ); }
    update_option( 'auracast_instagram_demo_imported', 1 );
}
