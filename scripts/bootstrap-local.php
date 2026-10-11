<?php
// One-time installation of the project-local QA store; no production settings.
$root = dirname( __DIR__ ) . '/.local/wordpress/wordpress/';
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . 'wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_blog_installed() ) {
    wp_install( 'Auracast', 'auracast', 'store@example.test', true, '', wp_generate_password( 32, true ) );
}
$credentials_path = dirname( __DIR__ ) . '/.local/admin-credentials.json';
if ( ! is_file( $credentials_path ) ) {
    $admin = get_user_by( 'login', 'auracast' );
    if ( $admin ) { $password = wp_generate_password( 32, true ); wp_set_password( $password, $admin->ID ); file_put_contents( $credentials_path, wp_json_encode( array( 'url' => 'http://127.0.0.1:8088/wp-admin/', 'username' => $admin->user_login, 'password' => $password ), JSON_PRETTY_PRINT ) ); }
}
update_option( 'home', 'http://127.0.0.1:8088' );
update_option( 'siteurl', 'http://127.0.0.1:8088' );
update_option( 'blogdescription', 'Wear your world.' );
update_option( 'timezone_string', 'Asia/Dhaka' );
$result = activate_plugin( 'woocommerce/woocommerce.php' );
if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
switch_theme( 'auracast' );
echo 'WordPress and WooCommerce initialized. Run seed-local.php next.' . PHP_EOL;
