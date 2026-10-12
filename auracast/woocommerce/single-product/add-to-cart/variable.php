<?php
/** Variable product form. WooCommerce template version 11.1.0. */
defined( 'ABSPATH' ) || exit;
global $product;
$variations_json = wp_json_encode( $available_variations );
$variations_attr = function_exists( 'wc_esc_json' ) ? wc_esc_json( $variations_json ) : _wp_specialchars( $variations_json, ENT_QUOTES, 'UTF-8', true );
do_action( 'woocommerce_before_add_to_cart_form' );
?>
<form class="variations_form cart auracast-product-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo $variations_attr; ?>">
<?php do_action( 'woocommerce_before_variations_form' ); ?>
<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
<p class="stock out-of-stock"><?php echo esc_html( apply_filters( 'woocommerce_out_of_stock_message', 'This product is currently out of stock and unavailable.' ) ); ?></p>
<?php else : ?>
<div class="variations auracast-product-attributes">
<?php foreach ( $attributes as $attribute_name => $options ) : ?>
<?php if ( stripos( $attribute_name, 'size' ) !== false ) { $size_order = array( 'xs', 's', 'm', 'l', 'xl', '2xl', 'xxl', '3xl', 'xxxl' ); usort( $options, function ( $a, $b ) use ( $size_order ) { $a_index = array_search( strtolower( $a ), $size_order, true ); $b_index = array_search( strtolower( $b ), $size_order, true ); return ( false === $a_index ? 99 : $a_index ) <=> ( false === $b_index ? 99 : $b_index ); } ); } ?>
<fieldset class="auracast-attribute" data-attribute="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>"><legend><span><?php echo esc_html( wc_attribute_label( $attribute_name ) ); ?></span><?php if ( stripos( $attribute_name, 'size' ) !== false ) : ?><button type="button" class="auracast-size-guide" data-dialog="size-guide-dialog">View Size Guide</button><?php endif; ?></legend><div class="auracast-size-chips" role="group" aria-label="<?php echo esc_attr( wc_attribute_label( $attribute_name ) ); ?>">
<?php foreach ( $options as $option ) : $label = taxonomy_exists( $attribute_name ) ? get_term_by( 'slug', $option, $attribute_name ) : false; ?>
<button type="button" class="auracast-size-chip" data-variation-option="<?php echo esc_attr( $option ); ?>" aria-pressed="false"><?php echo esc_html( $label ? $label->name : $option ); ?></button>
<?php endforeach; ?>
</div><div class="auracast-native-select"><?php wc_dropdown_variation_attribute_options( array( 'options' => $options, 'attribute' => $attribute_name, 'product' => $product, 'selected' => isset( $_POST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ? wc_clean( wp_unslash( $_POST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ) : $product->get_variation_default_attribute( $attribute_name ) ) ); ?></div></fieldset>
<?php endforeach; ?>
</div><a class="reset_variations screen-reader-text" href="#">Clear options</a><div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite"></div>
<?php do_action( 'woocommerce_after_variations_table' ); auracast_product_purchase_top(); auracast_product_bundle_picker(); ?>
<div class="single_variation_wrap">
<?php do_action( 'woocommerce_before_single_variation' ); do_action( 'woocommerce_single_variation' ); do_action( 'woocommerce_after_single_variation' ); ?>
</div>
<?php endif; do_action( 'woocommerce_after_variations_form' ); ?>
</form>
<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
