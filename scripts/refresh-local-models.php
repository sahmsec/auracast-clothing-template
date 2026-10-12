<?php
// Refresh only the old bundled demo model attachments in the isolated local store.
$_SERVER['HTTP_HOST'] = '127.0.0.1:8088';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require dirname( __DIR__ ) . '/.local/wordpress/wordpress/wp-load.php';
wp_set_current_user( 1 );
$catalog = json_decode( file_get_contents( get_template_directory() . '/assets/data/catalog.json' ), true );
foreach ( $catalog as $item ) { auracast_import_product( $item ); }

echo 'Updated existing demo model photos; product data and merchant images retained.' . PHP_EOL;
