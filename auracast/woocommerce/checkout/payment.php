<?php
/** Checkout payment methods. Submit controls remain outside this AJAX fragment. @version 10.9.0 */
defined( 'ABSPATH' ) || exit;
if ( ! wp_doing_ajax() ) { do_action( 'woocommerce_review_order_before_payment' ); }
?>
<section id="payment" class="woocommerce-checkout-payment checkout-section"><h2><?php esc_html_e( 'Payment', 'auracast' ); ?></h2><p class="checkout-payment-assurance"><?php esc_html_e( 'All transactions are secure and encrypted.', 'auracast' ); ?></p>
<?php if ( WC()->cart && WC()->cart->needs_payment() ) : ?><ul class="wc_payment_methods payment_methods methods" aria-label="<?php esc_attr_e( 'Payment methods', 'woocommerce' ); ?>">
<?php if ( ! empty( $available_gateways ) ) { foreach ( $available_gateways as $gateway ) { wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) ); } } else { echo '<li>'; wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', __( 'Please fill in your delivery details to see available payment methods.', 'auracast' ) ), 'notice' ); echo '</li>'; } ?>
</ul><?php endif; ?></section>
<?php if ( ! wp_doing_ajax() ) { do_action( 'woocommerce_review_order_after_payment' ); } ?>
