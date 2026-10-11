<?php
// Router for the isolated PHP development server. Never upload this as a theme.
$root = dirname( __DIR__ ) . '/.local/wordpress/wordpress';
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = realpath( $root . '/' . ltrim( urldecode( $path ), '/' ) );
if ( $file && str_starts_with( str_replace( '\\', '/', $file ), str_replace( '\\', '/', realpath( $root ) ) . '/' ) && is_file( $file ) ) { return false; }
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
