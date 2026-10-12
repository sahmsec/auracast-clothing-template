<?php
/** Checkout form. @version 9.4.0 */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_before_checkout_form', $checkout );
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
    echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
    return;
}
$billing_fields = $checkout->get_checkout_fields( 'billing' );
uasort( $billing_fields, 'wc_checkout_fields_uasort_comparison' );
?>
<form name="checkout" method="post" class="checkout woocommerce-checkout auracast-checkout-form" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php esc_attr_e( 'Checkout', 'woocommerce' ); ?>">
    <button class="checkout-summary-toggle" type="button" aria-expanded="false" aria-controls="order_review"><span><?php esc_html_e( 'Order summary', 'auracast' ); ?> <?php echo auracast_icon( 'chevron' ); ?></span><span class="checkout-total-value"><?php echo WC()->cart->get_total(); ?></span></button>
    <div class="checkout-layout">
        <div class="checkout-customer-column">
            <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
            <div id="customer_details">
                <section class="checkout-section checkout-contact"><div class="checkout-section-heading"><h2><?php esc_html_e( 'Contact', 'auracast' ); ?></h2><?php if ( ! is_user_logged_in() ) : ?><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Sign in', 'auracast' ); ?></a><?php endif; ?></div>
                    <?php if ( isset( $billing_fields['billing_email'] ) ) { woocommerce_form_field( 'billing_email', $billing_fields['billing_email'], $checkout->get_value( 'billing_email' ) ); } ?>
                    <label class="checkout-checkbox"><input type="checkbox" name="auracast_marketing_opt_in" value="1"> <?php esc_html_e( 'Email me with news and offers', 'auracast' ); ?></label>
                </section>
                <section class="checkout-section checkout-delivery"><h2><?php esc_html_e( 'Delivery', 'auracast' ); ?></h2>
                    <div class="woocommerce-billing-fields"><div class="woocommerce-billing-fields__field-wrapper">
                        <?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); foreach ( $billing_fields as $key => $field ) { if ( 'billing_email' !== $key ) { woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); } } do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
                    </div></div>
                    <label class="checkout-checkbox"><input type="checkbox" id="auracast-save-delivery" name="auracast_save_delivery" value="1"> <?php esc_html_e( 'Save this information for next time', 'auracast' ); ?></label>
                </section>
                <?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
                    <div class="woocommerce-account-fields">
                        <?php if ( ! $checkout->is_registration_required() ) : ?><label class="checkout-checkbox"><input type="checkbox" name="createaccount" id="createaccount" value="1"> <?php esc_html_e( 'Create an account?', 'woocommerce' ); ?></label><?php endif; ?>
                        <?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); if ( $checkout->get_checkout_fields( 'account' ) ) : ?><div class="create-account"><?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) { woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); } ?></div><?php endif; do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php do_action( 'woocommerce_checkout_after_customer_details' ); auracast_checkout_shipping_methods(); woocommerce_checkout_payment(); ?>
            <section class="checkout-section checkout-billing"><h3><?php esc_html_e( 'Billing address', 'auracast' ); ?></h3><div class="checkout-choice-list">
                <label class="checkout-choice is-selected"><input type="radio" name="auracast_different_billing" value="0" checked><span><?php esc_html_e( 'Same as shipping address', 'auracast' ); ?></span></label>
                <label class="checkout-choice"><input type="radio" name="auracast_different_billing" value="1"><span><?php esc_html_e( 'Use a different billing address', 'auracast' ); ?></span></label>
                <div class="checkout-different-billing" hidden><?php foreach ( $billing_fields as $key => $field ) { if ( in_array( $key, array( 'billing_email', 'billing_phone' ), true ) ) { continue; } $field['required'] = false; woocommerce_form_field( 'auracast_' . $key, $field, 'billing_country' === $key ? $checkout->get_value( 'billing_country' ) : '' ); } ?></div>
            </div></section>
            <section class="checkout-section checkout-tip"><h3><?php esc_html_e( 'Add tip', 'auracast' ); ?></h3><div class="checkout-tip-panel"><label class="checkout-checkbox checkout-tip-support"><input type="checkbox" id="checkout-tip-enabled" checked> <?php esc_html_e( 'Show your support for the team at AURACAST', 'auracast' ); ?></label><div class="checkout-tip-content"><div class="checkout-tip-options">
                <?php $tip_choice = WC()->session->get( 'auracast_tip_choice', 'none' ); foreach ( array( '10', '15', '20', 'none' ) as $choice ) : ?><label class="checkout-tip-option"><input type="radio" name="auracast_tip_choice" value="<?php echo esc_attr( $choice ); ?>" <?php checked( $tip_choice, $choice ); ?>><span><?php echo 'none' === $choice ? esc_html__( 'None', 'auracast' ) : esc_html( $choice . '%' ); ?><?php if ( 'none' !== $choice ) : ?><small data-tip-percent="<?php echo esc_attr( $choice ); ?>"><?php echo wp_kses_post( wc_price( WC()->cart->get_cart_contents_total() * (float) $choice / 100 ) ); ?></small><?php endif; ?></span></label><?php endforeach; ?>
            </div><div class="checkout-tip-custom"><div class="checkout-tip-input"><input type="number" name="auracast_tip_custom" min="0" max="100000" step="0.01" placeholder="<?php esc_attr_e( 'Custom tip', 'auracast' ); ?>" aria-label="<?php esc_attr_e( 'Custom tip', 'auracast' ); ?>" value="<?php echo 'custom' === $tip_choice ? esc_attr( WC()->session->get( 'auracast_tip_custom', '' ) ) : ''; ?>"><button type="button" data-tip-step="-1" aria-label="<?php esc_attr_e( 'Decrease Custom tip', 'auracast' ); ?>">−</button><button type="button" data-tip-step="1" aria-label="<?php esc_attr_e( 'Increase Custom tip', 'auracast' ); ?>">+</button></div><input type="radio" class="screen-reader-text" name="auracast_tip_choice" value="custom" <?php checked( $tip_choice, 'custom' ); ?> aria-label="<?php esc_attr_e( 'Use custom tip', 'auracast' ); ?>"><button type="button" class="checkout-add-tip"><?php esc_html_e( 'Add tip', 'auracast' ); ?></button></div><p><?php esc_html_e( 'Thank you, we appreciate it.', 'auracast' ); ?></p></div></div></section>
            <?php if ( $checkout->get_checkout_fields( 'order' ) && get_option( 'woocommerce_enable_order_comments' ) !== 'no' ) : ?><details class="checkout-order-notes"><summary><?php esc_html_e( 'Add order notes (optional)', 'auracast' ); ?></summary><?php foreach ( $checkout->get_checkout_fields( 'order' ) as $key => $field ) { woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); } ?></details><?php endif; ?>
            <div class="checkout-mobile-bottom-summary"><?php if ( wc_coupons_enabled() ) : ?><button type="button" class="checkout-add-discount" aria-expanded="false"><svg class="icon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 3h6l6 6-5 5-7-7V3Z"/><circle cx="5.5" cy="5.5" r=".7"/></svg><?php esc_html_e( 'Add discount', 'auracast' ); ?></button><?php endif; ?><div class="checkout-mobile-summary-expanded" hidden><button type="button" class="checkout-mobile-summary-heading" aria-expanded="true"><span><?php esc_html_e( 'Order summary', 'auracast' ); ?></span><?php echo auracast_icon( 'chevron' ); ?></button><?php wc_get_template( 'checkout/review-order.php', array( 'mobile_summary' => true ) ); ?></div></div>
            <div class="checkout-final-total"><span class="checkout-total-image" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Total', 'auracast' ); ?></strong><small><?php echo esc_html( sprintf( _n( '%d item', '%d items', WC()->cart->get_cart_contents_count(), 'auracast' ), WC()->cart->get_cart_contents_count() ) ); ?></small></span><span class="checkout-total-value"><?php echo WC()->cart->get_total(); ?></span><?php echo auracast_icon( 'chevron' ); ?></div>
            <div class="checkout-submit form-row place-order"><?php wc_get_template( 'checkout/terms.php' ); do_action( 'woocommerce_review_order_before_submit' ); ?>
                <noscript><button type="submit" class="button" name="woocommerce_checkout_update_totals" value="1"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button></noscript>
                <?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr__( 'Complete order', 'auracast' ) . '" data-value="' . esc_attr__( 'Complete order', 'auracast' ) . '">' . esc_html__( 'Complete order', 'auracast' ) . '</button>' ); do_action( 'woocommerce_review_order_after_submit' ); wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
            </div>
            <?php auracast_checkout_policy_links(); ?>
        </div>
        <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); do_action( 'woocommerce_checkout_before_order_review' ); ?>
        <aside id="order_review" class="woocommerce-checkout-review-order checkout-summary" aria-label="<?php esc_attr_e( 'Order summary', 'auracast' ); ?>"><?php woocommerce_order_review(); ?></aside>
        <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
    </div>
</form>
<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
