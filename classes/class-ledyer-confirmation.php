<?php
/**
 * Confirmation Class file.
 *
 * @package Ledyer
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * Confirmation class.
 *
 * @since 1.0.0
 *
 * Class that handles confirmation of order and redirect to Thank you page.
 */
class Confirmation {

	use Singleton;

	/**
	 * Confirmation constructor.
	 */
	public function actions() {
		add_action( 'init', array( $this, 'confirm_order' ), 10, 2 );
		add_action( 'init', array( $this, 'check_if_external_payment' ) );
	}

	/**
	 * Confirm the order in Woo
	 */
	public function confirm_order() {
		$ledyer_confirm = filter_input( INPUT_GET, 'lco_confirm', FILTER_SANITIZE_URL );
		$order_key      = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );

		if ( empty( $ledyer_confirm ) || empty( $order_key ) ) {
			return;
		}
		$order_id = wc_get_order_id_by_order_key( $order_key );
		if ( empty( $order_id ) ) {
			return;
		}

		Logger::log( $order_id . ': Confirm the Ledyer order from the confirmation page.' );
		wc_ledyer_confirm_ledyer_order( $order_id );
		lco_unset_sessions();
	}

	/**
	 * Checks if we have an external payment method on page load.
	 *
	 * @return void
	 */
	public function check_if_external_payment() {
		$epm      = filter_input( INPUT_GET, 'kco-external-payment', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $epm ) ) {
			$this->run_kepm( $epm, $order_id );
		}
	}

	/**
	 * Initiates a External Payment Method payment.
	 *
	 * @param string $epm The name of the external payment method.
	 * @param string $order_id The WooCommerce order id.
	 * @return void
	 */
	public function run_kepm( $epm, $order_id ) {
		$order = wc_get_order( $order_id );

		// Check if we have a order.
		if ( empty( $order ) ) {
			wc_print_notice( __( 'Failed getting the order for the external payment.', 'ledyer-checkout-for-woocommerce' ), 'error' );
			return;
		}

		$payment_methods = WC()->payment_gateways->get_available_payment_gateways();
		// Check if the payment method is available.
		if ( ! isset( $payment_methods[ $epm ] ) ) {
			wc_print_notice( __( 'Failed to find the payment method for the external payment.', 'ledyer-checkout-for-woocommerce' ), 'error' );
			return;
		}

		// Everything is fine, redirect to the URL specified by the gateway.
		WC()->session->set( 'chosen_payment_method', $epm );
		$order->set_payment_method( $payment_methods[ $epm ] );
		$order->save();
		$result = $payment_methods[ $epm ]->process_payment( $order_id );
		// Check if the result is good.
		if ( ! isset( $result['result'] ) || 'success' !== $result['result'] ) {
			wc_print_notice( __( 'Something went wrong with the external payment. Please try again', 'ledyer-checkout-for-woocommerce' ), 'error' );
			return;
		}
		wp_redirect( $result['redirect'] );
	}
}
