<?php
/** Standalone checkout presentation with WooCommerce order processing. */
defined( 'ABSPATH' ) || exit;

add_filter( 'template_include', function ( $template ) {
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        return get_template_directory() . '/page-checkout.php';
    }
    return $template;
}, 50 );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
    wp_enqueue_style( 'auracast-checkout', auracast_asset( 'css/checkout.css' ), array( 'auracast-yokai' ), AURACAST_VERSION );
    if ( ! is_wc_endpoint_url( 'order-received' ) ) {
        wp_enqueue_script( 'auracast-checkout', auracast_asset( 'js/checkout.js' ), array( 'jquery', 'wc-checkout' ), AURACAST_VERSION, true );
        wp_localize_script( 'auracast-checkout', 'auracastCheckout', array( 'currency' => get_woocommerce_currency_symbol(), 'savedKey' => 'auracast-checkout-delivery' ) );
    }
}, 30 );

add_action( 'wp', function () {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
} );

add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
    foreach ( array( 'billing', 'shipping' ) as $group ) {
        $prefix = $group . '_';
        unset( $fields[ $group ][ $prefix . 'company' ] );
        // Bangladesh delivery uses city and phone, without a province selector.
        $country = ( WC()->customer ? WC()->customer->get_billing_country() : '' ) ?: WC()->countries->get_base_country();
        if ( 'BD' === $country ) { unset( $fields[ $group ][ $prefix . 'state' ] ); }
        foreach ( array( 'country' => 10, 'first_name' => 20, 'last_name' => 30, 'address_1' => 40, 'address_2' => 50, 'city' => 60, 'postcode' => 70, 'phone' => 80 ) as $key => $priority ) {
            if ( ! isset( $fields[ $group ][ $prefix . $key ] ) ) { continue; }
            $field =& $fields[ $group ][ $prefix . $key ];
            $field['priority'] = $priority;
            $field['class'] = array( in_array( $key, array( 'first_name', 'city' ), true ) ? 'form-row-first' : ( in_array( $key, array( 'last_name', 'postcode' ), true ) ? 'form-row-last' : 'form-row-wide' ) );
            $labels = array( 'country' => 'Country/Region', 'first_name' => 'First name', 'last_name' => 'Last name', 'address_1' => 'Address', 'address_2' => 'Apartment, suite, etc. (optional)', 'city' => 'City', 'postcode' => 'Postal code (optional)', 'phone' => 'Phone' );
            $field['label'] = $labels[ $key ];
            $field['placeholder'] = $labels[ $key ];
            if ( 'country' === $key ) {
                $field['options'] = 'billing' === $group ? WC()->countries->get_allowed_countries() : WC()->countries->get_shipping_countries();
                $field['class'][] = 'checkout-country-field';
                $field['input_class'] = array( 'country_to_state' );
            }
            if ( in_array( $key, array( 'address_2', 'postcode' ), true ) ) { $field['required'] = false; }
            if ( 'phone' === $key ) { $field['required'] = true; }
            unset( $field );
        }
    }
    if ( isset( $fields['billing']['billing_email'] ) ) {
        $fields['billing']['billing_email']['type'] = 'text';
        $fields['billing']['billing_email']['label'] = 'Email or mobile phone number';
        $fields['billing']['billing_email']['placeholder'] = 'Email or mobile phone number';
        $fields['billing']['billing_email']['required'] = false;
        $fields['billing']['billing_email']['validate'] = array();
        $fields['billing']['billing_email']['class'] = array( 'form-row-wide', 'checkout-contact-field' );
        $fields['billing']['billing_email']['autocomplete'] = 'email';
    }
    unset( $fields['order']['order_comments'] );
    return $fields;
}, 30 );

add_filter( 'woocommerce_form_field_args', function ( $args, $key ) {
    if ( in_array( $key, array( 'billing_country', 'auracast_billing_country' ), true ) ) {
        $args['type'] = 'select';
        $args['options'] = WC()->countries->get_allowed_countries();
    }
    return $args;
}, 30, 2 );

add_filter( 'woocommerce_form_field', function ( $html, $key ) {
    if ( ! in_array( $key, array( 'billing_email', 'billing_phone' ), true ) || ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return $html; }
    $message = 'billing_email' === $key ? __( 'Used for your order confirmation and cart reminders', 'auracast' ) : __( 'In case we need to contact you about your order', 'auracast' );
    return str_replace( '</p>', '<button type="button" class="checkout-field-help" aria-label="' . esc_attr( $message ) . '" title="' . esc_attr( $message ) . '">?</button><span class="checkout-help-popup" hidden>' . esc_html( $message ) . '</span></p>', $html );
}, 20, 2 );

/** Keep Bangladesh's postcode optional when country locale updates the form. */
function auracast_checkout_address_locale( $fields ) {
    foreach ( array( 'country' => 'Country/Region', 'first_name' => 'First name', 'last_name' => 'Last name', 'address_1' => 'Address', 'address_2' => 'Apartment, suite, etc. (optional)', 'city' => 'City', 'postcode' => 'Postal code (optional)', 'phone' => 'Phone' ) as $key => $label ) {
        $priorities = array( 'country' => 10, 'first_name' => 20, 'last_name' => 30, 'address_1' => 40, 'address_2' => 50, 'city' => 60, 'postcode' => 70, 'phone' => 80 );
        $fields[ $key ] = array_merge( $fields[ $key ] ?? array(), array( 'label' => $label, 'placeholder' => $label, 'priority' => $priorities[ $key ], 'class' => array( in_array( $key, array( 'first_name', 'city' ), true ) ? 'form-row-first' : ( in_array( $key, array( 'last_name', 'postcode' ), true ) ? 'form-row-last' : 'form-row-wide' ) ) ) );
    }
    $fields['postcode']['required'] = false;
    $fields['phone']['required'] = true;
    return $fields;
}
add_filter( 'woocommerce_get_country_locale_default', 'auracast_checkout_address_locale' );
add_filter( 'woocommerce_get_country_locale', function ( $locale ) {
    $locale['BD'] = auracast_checkout_address_locale( $locale['BD'] ?? array() );
    $locale['BD']['state']['required'] = false;
    $locale['BD']['state']['hidden'] = true;
    return $locale;
} );

/** Primary address is delivery; a separate billing address maps to native fields. */
add_filter( 'woocommerce_checkout_posted_data', function ( $data ) {
    $contact = sanitize_text_field( wp_unslash( $_POST['billing_email'] ?? '' ) );
    if ( $contact && ! is_email( $contact ) ) {
        $data['billing_email'] = '';
        if ( empty( $data['billing_phone'] ) ) { $data['billing_phone'] = $contact; }
    }
    if ( ! empty( $_POST['auracast_different_billing'] ) ) {
        foreach ( array( 'first_name', 'last_name', 'country', 'state', 'address_1', 'address_2', 'city', 'postcode' ) as $key ) {
            $data[ 'shipping_' . $key ] = $data[ 'billing_' . $key ] ?? '';
            $data[ 'billing_' . $key ] = sanitize_text_field( wp_unslash( $_POST[ 'auracast_billing_' . $key ] ?? '' ) );
        }
        $data['ship_to_different_address'] = 1;
    }
    return $data;
}, 30 );

add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
    $contact = sanitize_text_field( wp_unslash( $_POST['billing_email'] ?? '' ) );
    if ( ! $contact || ( ! is_email( $contact ) && ! preg_match( '/^\+?[0-9 ()-]{7,20}$/', $contact ) ) ) {
        $errors->add( 'auracast_contact', __( 'Enter a valid email or mobile phone number.', 'auracast' ), array( 'id' => 'billing_email' ) );
    }
}, 30, 2 );

add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
    $order->update_meta_data( '_auracast_contact', sanitize_text_field( wp_unslash( $_POST['billing_email'] ?? '' ) ) );
    $order->update_meta_data( '_auracast_marketing_opt_in', ! empty( $_POST['auracast_marketing_opt_in'] ) ? 'yes' : 'no' );
    $order->update_meta_data( '_auracast_save_delivery', ! empty( $_POST['auracast_save_delivery'] ) ? 'yes' : 'no' );
}, 20, 2 );

/** Tips are real cart fees and are recalculated by the native checkout AJAX. */
add_action( 'woocommerce_checkout_update_order_review', function ( $posted ) {
    parse_str( $posted, $data );
    $choice = sanitize_key( $data['auracast_tip_choice'] ?? 'none' );
    if ( ! in_array( $choice, array( 'none', '10', '15', '20', 'custom' ), true ) ) { $choice = 'none'; }
    WC()->session->set( 'auracast_tip_choice', $choice );
    WC()->session->set( 'auracast_tip_custom', min( 100000, max( 0, (float) ( $data['auracast_tip_custom'] ?? 0 ) ) ) );
} );
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) { return; }
    if ( ! WC()->session ) { return; }
    $choice = WC()->session->get( 'auracast_tip_choice', 'none' );
    $amount = 'custom' === $choice ? (float) WC()->session->get( 'auracast_tip_custom', 0 ) : ( in_array( $choice, array( '10', '15', '20' ), true ) ? $cart->get_cart_contents_total() * (float) $choice / 100 : 0 );
    if ( $amount > 0 ) { $cart->add_fee( __( 'Tip', 'auracast' ), round( $amount, wc_get_price_decimals() ), false ); }
}, 30 );
add_action( 'woocommerce_thankyou', function () {
    if ( WC()->session ) { WC()->session->__unset( 'auracast_tip_choice' ); WC()->session->__unset( 'auracast_tip_custom' ); }
} );

function auracast_checkout_shipping_methods() {
    echo '<section class="auracast-shipping-methods checkout-section" aria-labelledby="checkout-shipping-title"><h3 id="checkout-shipping-title">' . esc_html__( 'Shipping method', 'auracast' ) . '</h3>';
    if ( WC()->cart->needs_shipping() ) {
        $packages = WC()->shipping()->get_packages();
        $chosen = WC()->session->get( 'chosen_shipping_methods', array() );
        foreach ( $packages as $index => $package ) {
            if ( empty( $package['rates'] ) ) { echo '<p class="shipping-unavailable">' . esc_html__( 'Enter your delivery address to see available shipping methods.', 'auracast' ) . '</p>'; continue; }
            echo '<div class="checkout-choice-list">';
            foreach ( $package['rates'] as $rate ) {
                $id = 'shipping_method_' . $index . '_' . sanitize_title( $rate->id );
                $selected = isset( $chosen[ $index ] ) && $chosen[ $index ] === $rate->id;
                $tax = WC()->cart->display_prices_including_tax() ? array_sum( $rate->taxes ) : 0;
                echo '<label class="checkout-choice' . ( $selected ? ' is-selected' : '' ) . '" for="' . esc_attr( $id ) . '"><input type="radio" name="shipping_method[' . (int) $index . ']" data-index="' . (int) $index . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $rate->id ) . '" class="shipping_method" ' . checked( $selected, true, false ) . '><span><strong>' . esc_html( $rate->get_label() ) . '</strong><small>' . esc_html__( 'Tracking number provided', 'auracast' ) . '</small></span><span class="shipping-rate">' . wp_kses_post( wc_price( $rate->cost + $tax ) ) . '</span></label>';
            }
            echo '</div>';
        }
    } else { echo '<p>' . esc_html__( 'No shipping required.', 'auracast' ) . '</p>'; }
    echo '</section>';
}
add_filter( 'woocommerce_update_order_review_fragments', function ( $fragments ) {
    ob_start(); auracast_checkout_shipping_methods(); $fragments['.auracast-shipping-methods'] = ob_get_clean();
    ob_start(); wc_get_template( 'checkout/review-order.php', array( 'mobile_summary' => true ) ); $fragments['.checkout-mobile-review-order-table'] = ob_get_clean();
    $fragments['.checkout-total-value'] = '<span class="checkout-total-value">' . WC()->cart->get_total() . '</span>';
    return $fragments;
} );

function auracast_checkout_policy_links() {
    $links = array( 'Refund policy' => home_url( '/refund-policy/' ), 'Shipping' => home_url( '/shipping-policy/' ), 'Privacy policy' => get_privacy_policy_url() ?: home_url( '/privacy-policy/' ), 'Terms of service' => function_exists( 'wc_get_page_permalink' ) && wc_get_page_id( 'terms' ) > 0 ? wc_get_page_permalink( 'terms' ) : home_url( '/terms-of-service/' ), 'Contact' => home_url( '/contact-us/' ) );
    echo '<nav class="checkout-policy-links" aria-label="' . esc_attr__( 'Store policies', 'auracast' ) . '">';
    foreach ( $links as $label => $url ) { echo '<a href="' . esc_url( $url ) . '" data-checkout-policy="' . esc_attr( $label ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>'; }
    echo '</nav>';
}

add_filter( 'woocommerce_order_button_text', function ( $text ) { return __( 'Complete order', 'auracast' ); } );
