<?php
// Re-encode downloaded campaign artwork for the preview, preserving its geometry.
$images = dirname( __DIR__ ) . '/auracast/assets/images/';
foreach ( array( 'yokai-shirts-desktop', 'yokai-shirts-mobile', 'yokai-shirts-compact', 'yokai-tees', 'yokai-music' ) as $name ) {
    $image = imagecreatefrompng( $images . $name . '.png' );
    if ( ! $image || ! imagewebp( $image, $images . $name . '.webp', 92 ) ) { throw new RuntimeException( 'Could not optimize ' . $name ); }
    imagedestroy( $image );
    echo $name . ': ' . filesize( $images . $name . '.webp' ) . ' bytes' . PHP_EOL;
}
