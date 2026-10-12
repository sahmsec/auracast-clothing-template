<?php
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require dirname( __DIR__ ) . '/.local/wordpress/wordpress/wp-load.php';
wp_set_current_user( 1 );
// Remove only files and posts created by this local browser QA workflow.
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'meta_key' => '_wp_attached_file', 'meta_value' => 'auracast-reel-qa-upload', 'meta_compare' => 'LIKE' ) ) as $attachment ) {
    $file = wp_normalize_path( get_attached_file( $attachment->ID ) );
    $uploads = wp_normalize_path( wp_upload_dir()['basedir'] ) . '/';
    if ( str_starts_with( $file, $uploads ) && preg_match( '/^auracast-reel-qa-upload(?:-\d+)?\.mp4$/', basename( $file ) ) ) { wp_delete_attachment( $attachment->ID, true ); }
}
foreach ( get_posts( array( 'post_type' => 'auracast_reel', 'post_status' => array( 'draft', 'trash', 'auto-draft' ), 'numberposts' => -1 ) ) as $post ) {
    if ( in_array( $post->post_title, array( 'Auracast reel editor QA', '000 Updated reel editor QA' ), true ) ) { wp_delete_post( $post->ID, true ); }
}
function reel_check( $condition, $label ) { if ( ! $condition ) { throw new RuntimeException( $label ); } echo 'PASS ' . $label . PHP_EOL; }
$items = auracast_instagram_items();
reel_check( count( $items ) === 6, 'Six published demo reels' );
auracast_import_instagram_demo();
reel_check( count( auracast_instagram_items() ) === 6, 'Repeated imports retain existing content' );
reel_check( auracast_instagram_url( 'https://instagram.com/reel/ABC123/?igsh=demo' ) === 'https://www.instagram.com/reel/ABC123/', 'Instagram links normalize safely' );
foreach ( array( 'https://example.test/reel/ABC123/', 'javascript:alert(1)', 'https://www.instagram.com.evil.test/reel/ABC123/', 'https://www.instagram.com/direct/inbox/' ) as $url ) { reel_check( ! auracast_instagram_url( $url ), 'Reject unsupported post URL' ); }
$id = wp_insert_post( array( 'post_type' => 'auracast_reel', 'post_status' => 'draft', 'post_title' => 'Reel backend QA' ) );
$settings = get_option( 'auracast_instagram_settings' );
try {
    update_post_meta( $id, '_auracast_reel_link', $items[0]['link'] );
    $_POST = array( 'auracast_instagram_link' => $items[1]['link'] );
    wp_update_post( array( 'ID' => $id, 'post_title' => 'QA without nonce' ) );
    reel_check( get_post_meta( $id, '_auracast_reel_link', true ) === $items[0]['link'], 'Media changes require a valid editor nonce' );
    $_POST = array( 'auracast_reel_nonce' => wp_create_nonce( 'auracast_reel_media' ), 'auracast_instagram_link' => 'https://www.instagram.com/reel/ABC123/', 'auracast_reel_video' => get_post_meta( $items[0]['id'], '_auracast_reel_cover', true ), 'auracast_reel_cover' => get_post_meta( $items[0]['id'], '_auracast_reel_video', true ), 'auracast_reel_video_url' => 'https://example.test/file.html' );
    wp_update_post( array( 'ID' => $id, 'post_title' => 'QA with nonce' ) );
    reel_check( get_post_meta( $id, '_auracast_reel_link', true ) === 'https://www.instagram.com/reel/ABC123/', 'Valid editor changes save' );
    reel_check( ! get_post_meta( $id, '_auracast_reel_video', true ) && ! get_post_meta( $id, '_auracast_reel_cover', true ) && ! get_post_meta( $id, '_auracast_reel_video_url', true ), 'Media fields validate attachment type and video URL' );
    reel_check( count( auracast_instagram_items() ) === 6, 'Draft content stays out of the storefront' );
    update_option( 'auracast_instagram_settings', array_merge( $settings, array( 'enabled' => false ) ) );
    ob_start(); auracast_instagram_feed(); $html = ob_get_clean(); reel_check( $html === '', 'Feed visibility setting hides all cards' );
} finally {
    $_POST = array(); update_option( 'auracast_instagram_settings', $settings ); wp_delete_post( $id, true ); delete_transient( 'auracast_reel_notice_1' );
}
