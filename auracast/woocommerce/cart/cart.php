<?php
/** Native cart layout. WooCommerce template version 11.2.0. */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_before_cart' );
?>
<div class="auracast-cart-heading"><h1>Your cart</h1><a href="<?php echo esc_url( auracast_shop_url() ); ?>">Continue shopping</a></div>
<form class="woocommerce-cart-form auracast-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
<?php do_action( 'woocommerce_before_cart_table' ); ?>
<table class="shop_table cart woocommerce-cart-form__contents auracast-cart-table"><thead><tr><th scope="col" class="product-name">Product</th><th scope="col" class="product-quantity">Quantity</th><th scope="col" class="product-subtotal">Total</th></tr></thead><tbody>
<?php do_action( 'woocommerce_before_cart_contents' ); ?>
<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
    $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
    $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
    if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) { continue; }
    $parent_product = wc_get_product( $cart_item['product_id'] );
    $product_name = apply_filters( 'woocommerce_cart_item_name', $parent_product ? $parent_product->get_name() : $_product->get_name(), $cart_item, $cart_item_key );
    $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
?>
<tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
<td class="product-name" data-title="Product"><a class="auracast-cart-image" href="<?php echo esc_url( $product_permalink ); ?>"><?php echo apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key ); ?></a><div class="auracast-cart-product-copy"><a class="auracast-cart-product-name" href="<?php echo esc_url( $product_permalink ); ?>"><?php echo wp_kses_post( $product_name ); ?></a><div class="auracast-cart-unit-price"><?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?></div><?php do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key ); echo wc_get_formatted_cart_item_data( $cart_item, false, $product_name ); if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) { echo '<p class="backorder_notification">Available on backorder</p>'; } ?></div></td>
<td class="product-quantity" data-title="Quantity"><div class="auracast-cart-quantity-group"><div class="auracast-cart-quantity"><button type="button" data-cart-quantity="-1" aria-label="<?php echo esc_attr( 'Decrease quantity for ' . $_product->get_name() ); ?>">−</button><?php echo apply_filters( 'woocommerce_cart_item_quantity', woocommerce_quantity_input( array( 'input_name' => "cart[{$cart_item_key}][qty]", 'input_value' => $cart_item['quantity'], 'max_value' => $_product->get_max_purchase_quantity(), 'min_value' => 0, 'product_name' => $_product->get_name() ), $_product, false ), $cart_item_key, $cart_item ); ?><button type="button" data-cart-quantity="1" aria-label="<?php echo esc_attr( 'Increase quantity for ' . $_product->get_name() ); ?>">+</button></div><a class="remove auracast-cart-remove" href="<?php echo esc_url( wc_get_cart_remove_url( $cart_item_key ) ); ?>" aria-label="<?php echo esc_attr( 'Remove ' . $_product->get_name() . ' from cart' ); ?>" data-product_id="<?php echo esc_attr( $product_id ); ?>" data-product_sku="<?php echo esc_attr( $_product->get_sku() ); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 6h16M9 6V3h6v3M7 6v15h10V6M10 10v7M14 10v7"/></svg></a></div></td>
<td class="product-subtotal" data-title="Total"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?></td>
</tr>
<?php endforeach; do_action( 'woocommerce_cart_contents' ); ?>
<tr class="auracast-cart-actions-row"><td colspan="3" class="actions"><button type="submit" class="button" name="update_cart" value="Update cart">Update cart</button><?php do_action( 'woocommerce_cart_actions' ); wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?></td></tr>
<?php do_action( 'woocommerce_after_cart_contents' ); ?>
</tbody></table>
<?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>
<div class="cart-collaterals auracast-cart-footer"><div class="cart_totals auracast-cart-estimate"><div class="auracast-estimated-total"><h2>Estimated total</h2><p><?php echo wp_kses_post( wc_price( max( 0, WC()->cart->get_cart_contents_total() + WC()->cart->get_fee_total() ) ) ); ?> <?php echo esc_html( get_woocommerce_currency() ); ?></p></div><p class="auracast-cart-tax-note">Taxes, discounts and <a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>">shipping</a> calculated at checkout.</p><div class="wc-proceed-to-checkout"><a class="checkout-button button alt wc-forward" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">Check out</a></div></div></div>
<?php do_action( 'woocommerce_after_cart' ); ?>
