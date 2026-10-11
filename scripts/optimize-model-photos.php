<?php
// Convert generated originals to WebP for delivery without changing dimensions or framing.
$root = dirname( __DIR__ );
$replacements = json_decode( file_get_contents( $root . '/auracast/assets/data/model-replacements.json' ), true );
foreach ( $replacements as $file ) {
    $key = str_replace( array( 'auracast-model-', '.webp' ), array( '', '' ), $file );
    $source = $root . '/.local/generated-originals/' . $key . '.png';
    if ( ! is_file( $source ) ) { throw new RuntimeException( 'Missing generated original: ' . $key ); }
    $image = imagecreatefrompng( $source );
    if ( ! $image || ! imagewebp( $image, $root . '/auracast/assets/images/' . $file, 88 ) ) { throw new RuntimeException( 'Could not encode model photo: ' . $file ); }
    echo $file . ' ' . imagesx( $image ) . 'x' . imagesy( $image ) . PHP_EOL;
}
