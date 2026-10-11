<?php
defined( 'ABSPATH' ) || exit;

/** Rate-limit submissions without storing raw visitor IP addresses. */
function auracast_check_form_request() {
    check_ajax_referer( 'auracast_public', 'nonce' );
    if ( ! empty( $_POST['website'] ) ) { wp_send_json_error( array( 'message' => 'Please try again.' ), 400 ); }
    $key = 'auracast_form_' . substr( hash_hmac( 'sha256', ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) . ( $_POST['action'] ?? '' ), wp_salt() ), 0, 32 );
    $count = (int) get_transient( $key );
    if ( $count >= 5 ) { wp_send_json_error( array( 'message' => __( 'Please wait a few minutes before trying again.', 'auracast' ) ), 429 ); }
    set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );
}

function auracast_newsletter() {
    auracast_check_form_request();
    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    if ( ! is_email( $email ) || empty( $_POST['consent'] ) ) { wp_send_json_error( array( 'message' => __( 'Enter a valid email and agree to receive the newsletter.', 'auracast' ) ), 400 ); }
    global $wpdb;
    $table = $wpdb->prefix . 'auracast_subscribers';
    auracast_subscribers_table();
    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) );
    if ( ! $exists ) {
        $inserted = $wpdb->insert( $table, array( 'email' => $email, 'created_at' => current_time( 'mysql', true ), 'consent' => 'newsletter-form-v1' ), array( '%s', '%s', '%s' ) );
        if ( ! $inserted ) { wp_send_json_error( array( 'message' => __( 'Could not save your subscription. Please try again.', 'auracast' ) ), 500 ); }
    }
    wp_send_json_success( array( 'message' => __( 'You’re on the list. Thank you for joining Auracast!', 'auracast' ) ) );
}
add_action( 'wp_ajax_auracast_newsletter', 'auracast_newsletter' );
add_action( 'wp_ajax_nopriv_auracast_newsletter', 'auracast_newsletter' );

function auracast_subscribers_table() {
    if ( get_option( 'auracast_subscriber_db_version' ) === '1' ) { return; }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = $wpdb->prefix . 'auracast_subscribers';
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE {$table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, email varchar(254) NOT NULL, created_at datetime NOT NULL, consent varchar(80) NOT NULL, PRIMARY KEY  (id), UNIQUE KEY email (email)) {$charset};" );
    update_option( 'auracast_subscriber_db_version', '1', false );
}

function auracast_contact() {
    auracast_check_form_request();
    $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    if ( ! $name || ! is_email( $email ) || strlen( $message ) < 10 || strlen( $message ) > 10000 ) { wp_send_json_error( array( 'message' => __( 'Enter your name, a valid email, and a message of 10–10,000 characters.', 'auracast' ) ), 400 ); }
    $recipient = sanitize_email( auracast_setting( 'contact_recipient', get_option( 'admin_email' ) ) );
    $sent = wp_mail( $recipient, sprintf( '[Auracast] Message from %s', $name ), "Name: {$name}\nEmail: {$email}\n\n{$message}", array( 'Reply-To: ' . $email ) );
    if ( ! $sent ) { wp_send_json_error( array( 'message' => __( 'We could not send your message. Please contact us by email instead.', 'auracast' ) ), 500 ); }
    wp_send_json_success( array( 'message' => __( 'Thank you. Your message has been sent.', 'auracast' ) ) );
}
add_action( 'wp_ajax_auracast_contact', 'auracast_contact' );
add_action( 'wp_ajax_nopriv_auracast_contact', 'auracast_contact' );

add_action( 'admin_post_auracast_export_subscribers', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not permitted.', 403 ); }
    check_admin_referer( 'auracast_export_subscribers' );
    auracast_subscribers_table();
    global $wpdb;
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=auracast-subscribers.csv' );
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, array( 'Email', 'Subscribed at (UTC)', 'Consent' ), ',', '"', '' );
    $last_id = 0;
    do {
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,email,created_at,consent FROM {$wpdb->prefix}auracast_subscribers WHERE id > %d ORDER BY id LIMIT 500", $last_id ), ARRAY_A );
        foreach ( $rows as $row ) { $last_id = (int) $row['id']; unset( $row['id'] ); foreach ( $row as &$value ) { if ( preg_match( '/^[=+@\-\t\r]/', $value ) ) { $value = "'" . $value; } } unset( $value ); fputcsv( $output, $row, ',', '"', '' ); }
    } while ( count( $rows ) === 500 );
    fclose( $output ); exit;
} );
