<?php
$root = dirname( __DIR__ ) . '/.local/wordpress/wordpress/';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . 'wp-load.php';
wp_set_current_user( 1 );
auracast_import_instagram_demo();
echo 'Published Instagram posts and reels: ' . count( auracast_instagram_items() ) . PHP_EOL;
