<?php
/** Simple product form. WooCommerce template version 10.2.0. */
defined( 'ABSPATH' ) || exit;
global $product;
if ( ! $product->is_purchasable() ) { return; }
echo wc_get_stock_html( $product );
if ( ! $product->is_in_stock() ) { return; }
do_action( 'woocommerce_before_add_to_cart_form' );
?>
<form class="cart auracast-product-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data">
<?php auracast_product_purchase_top(); auracast_product_bundle_picker(); do_action( 'woocommerce_before_add_to_cart_button' ); do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>
<div class="auracast-product-quantity"><?php woocommerce_quantity_input( array( 'min_value' => $product->get_min_purchase_quantity(), 'max_value' => $product->get_max_purchase_quantity(), 'input_value' => $product->get_min_purchase_quantity() ) ); ?></div>
<?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>
<button type="submit" name="add-to-cart" value="<?php echo absint( $product->get_id() ); ?>" class="single_add_to_cart_button button alt">Add to cart</button><button type="button" class="button auracast-bundle-buy" data-product-purchase="bundle-buy">Buy it now →</button>
<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
</form>
<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
