<?php
// Apply the requested category structure only to the isolated local preview.
$root = dirname( __DIR__ ) . '/.local/wordpress/wordpress/';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . 'wp-load.php';
wp_set_current_user( 1 );
auracast_upgrade_store_structure();
flush_rewrite_rules();
echo 'Updated the clothing categories, colors, subcategories and curated collections.' . PHP_EOL;
