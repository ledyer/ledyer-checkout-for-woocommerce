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
		add_action( 'init', array( $this, 'handle_redirect' ), 10, 2 );
	}

	/**
	 * Confirm the order in Woo
	 */
	public function handle_redirect() {
		$ledyer_confirm = filter_input( INPUT_GET, 'lco_confirm', FILTER_SANITIZE_URL );
		$order_key      = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $ledyer_confirm ) || empty( $order_key ) ) {
			return;
		}
		$order_id = wc_get_order_id_by_order_key( $order_key );

		if ( empty( $order_id ) ) {
			\Ledyer\Logger::log( "Could not get the WooCommerce order id from order key {$order_key}" );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			\Ledyer\Logger::log( "Could not get the WooCommerce order with the id {$order_id}" );
			return;
		}

		Logger::log( "{$order_id}: Confirm the Ledyer order from the confirmation page." );
		$this->confirm_order( $order );
		lco_unset_sessions();
	}

	/**
	 * Confirm the order in WooCommerce.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return void
	 */
	private function confirm_order( $order ) {
		// Advanced invoice must be set to on-hold regardless of needs_processing().
		// For all other payment methods, if the order needs processing, set to on-hold, and handle it through callback.
		// Otherwise, process the order through payment_complete().

		// If the order is already completed or on-hold, return.
		if ( ! empty( $order->get_date_paid() ) || $order->has_status( array( 'on-hold' ) ) ) {
			return;
		}

		$payment_id = $order->get_meta( '_wc_ledyer_order_id' );
		if ( empty( $payment_id ) ) {
			\Ledyer\Logger::log( "Could not get the Ledyer payment id from the order {$order->get_id()}" );
			return;
		}

		$ledyer_order = ledyer()->api->get_order( $payment_id );
		$order->set_transaction_id( $payment_id );

		$ledyer_update_order_reference = ledyer()->api->update_order_reference( $payment_id, array( 'reference' => $order->get_order_number() ) );
		if ( is_wp_error( $ledyer_update_order_reference ) ) {
			\Ledyer\Logger::log( "Couldn't set merchant reference {$order->get_order_number()}" );
		} else {
			$order->update_meta_data( '_ledyer_merchant_reference', $order->get_order_number() );
		}

		$ledyer_note           = '';
		$ledyer_payment_method = $ledyer_order['paymentMethod'];
		$ledyer_payment_status = ledyer()->api->get_payment_status( $payment_id );
		if ( ! is_wp_error( $ledyer_payment_status ) ) {
			$ledyer_payment_method = $ledyer_payment_status['paymentMethod'];
			$ledyer_note           = sanitize_text_field( $ledyer_payment_status['note'] ) ?? '';
		}
		$ledyer_payment_provider = sanitize_text_field( $ledyer_payment_method['provider'] );
		$ledyer_payment_type     = sanitize_text_field( $ledyer_payment_method['type'] );

		$order->update_meta_data( 'ledyer_payment_type', $ledyer_payment_type );
		$order->update_meta_data( 'ledyer_payment_method', $ledyer_payment_provider );

		switch ( $ledyer_payment_type ) {
			case 'invoice':
				$method_title = __( 'Invoice', 'ledyer-checkout-for-woocommerce' );
				break;
			case 'advanceInvoice':
				$method_title = __( 'Advance Invoice', 'ledyer-checkout-for-woocommerce' );
				break;
			case 'card':
				$method_title = __( 'Card', 'ledyer-checkout-for-woocommerce' );
				break;
			case 'bankTransfer':
				$method_title = __( 'Direct Debit', 'ledyer-checkout-for-woocommerce' );
				break;
			case 'partPayment':
				$method_title = __( 'Part Payment', 'ledyer-checkout-for-woocommerce' );
				break;
		}

		$order->set_payment_method_title( sprintf( '%s (Ledyer)', $method_title ) );

		$note = sprintf(
			// translators: 1: Payment ID, 2: Note from Ledyer.
			__(
				'New payment created in Ledyer with Payment ID %1$s. %2$s',
				'ledyer-checkout-for-woocommerce'
			),
			$payment_id,
			$ledyer_note
		);
		if ( 'advanceInvoice' === $ledyer_payment_type ) {
			$order->update_status( 'on-hold', $note );
		} elseif ( $order->needs_processing() ) {
			$order->add_order_note( $note );
			$order->payment_complete( $payment_id );
		} else {
			$order->update_status( 'on-hold', $note );
		}

		$order->update_meta_data( '_ledyer_date_paid', gmdate( 'Y-m-d H:i:s' ) );
		$order->save();

		do_action( 'ledyer_process_payment', $order->get_id(), $ledyer_order );

		$response = ledyer()->api->acknowledge_order( $payment_id );
		if ( is_wp_error( $response ) ) {
			\Ledyer\Logger::log( "Couldn't acknowledge order {$payment_id}|{$order->get_id()}" );
		}
	}
}
