<?php
/**
 * Ledyer Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package ledyer-checkout-for-woocommerce
 */

do_action( 'woocommerce_before_checkout_form', WC()->checkout() );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

$settings = get_option( 'woocommerce_lco_settings' );
?>

<form name="checkout" class="checkout woocommerce-checkout">
	<?php do_action( 'lco_wc_before_wrapper' ); ?>
	<div id="lco-wrapper">
		<div id="lco-order-review">
			<?php do_action( 'lco_wc_before_order_review' ); ?>
			<?php
			if ( ! isset( $settings['show_subtotal_detail'] ) || in_array( $settings['show_subtotal_detail'], array( 'woo', 'both' ), true ) ) {
				woocommerce_order_review();
				WC()->checkout()->checkout_form_billing();
				WC()->checkout()->checkout_form_shipping();
                wc_get_template( 'checkout/terms.php' );
			}
			?>
			<?php do_action( 'lco_wc_after_order_review' ); ?>
		</div>

		<div id="lco-iframe">
			<?php do_action( 'lco_wc_before_snippet' ); ?>
			<?php $ledyer_order = lco_create_or_update_order(); ?>

            <?php if( false === $ledyer_order ) wc_ledyer_cart_redirect(); ?>

			<?php do_action( 'lco_wc_after_snippet' ); ?>
		</div>
	</div>
	<?php do_action( 'lco_wc_after_wrapper' ); ?>
</form>

<?php do_action( 'woocommerce_after_checkout_form', WC()->checkout() ); ?>
