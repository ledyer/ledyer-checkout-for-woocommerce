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
		Logger::set_context( array( 'source' => 'confirmation' ) );
		$order_id = wc_get_order_id_by_order_key( $order_key );

		if ( empty( $order_id ) ) {
			Logger::error( 'Could not get the WooCommerce order ID from the order key', array( 'order_key' => $order_key ) );
			return;
		}

		Logger::set_context( array( 'wc_order_id' => $order_id ) );
		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			Logger::error( 'Could not get the WooCommerce order' );
			return;
		}

		Logger::add_order_context( $order );
		Logger::info( 'Customer returned to the confirmation page, confirming the order' );
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
			Logger::info( 'Order is already paid or on-hold, skipping confirmation', array( 'order_status' => $order->get_status() ) );
			return;
		}

		$payment_id = $order->get_meta( '_wc_ledyer_order_id' );
		if ( empty( $payment_id ) ) {
			Logger::error( 'Could not get the Ledyer order ID from the WooCommerce order' );
			return;
		}

		$ledyer_order = ledyer()->api->get_order( $payment_id );
		// If we could not get the Ledyer order, set it to an empty array and continue with the confirmation.
		if ( is_wp_error( $ledyer_order ) ) {
			Logger::warning( 'Could not get the order from Ledyer, continuing the confirmation', array( 'error' => $ledyer_order->get_error_message() ) );
			$ledyer_order = array();
		}

		$order->set_transaction_id( $payment_id );

		$ledyer_update_order_reference = ledyer()->api->update_order_reference( $payment_id, array( 'reference' => $order->get_order_number() ) );
		if ( is_wp_error( $ledyer_update_order_reference ) ) {
			Logger::error(
				'Could not set the merchant reference in Ledyer',
				array(
					'reference' => $order->get_order_number(),
					'error'     => $ledyer_update_order_reference->get_error_message(),
				)
			);
		} else {
			$order->update_meta_data( '_ledyer_merchant_reference', $order->get_order_number() );
		}

		$ledyer_note           = '';
		$ledyer_payment_method = $ledyer_order['paymentMethod'] ?? array( 'provider' => '', 'type' => '' );
		$ledyer_payment_status = ledyer()->api->get_payment_status( $payment_id );
		if ( ! is_wp_error( $ledyer_payment_status ) ) {
			$ledyer_payment_method = $ledyer_payment_status['paymentMethod'];
			$ledyer_note           = sanitize_text_field( $ledyer_payment_status['note'] ) ?? '';
		} else {
			Logger::error( 'Could not get the payment status from Ledyer', array( 'error' => $ledyer_payment_status->get_error_message() ) );
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

		Logger::info(
			'Payment method set on the order',
			array(
				'payment_type'     => $ledyer_payment_type,
				'payment_provider' => $ledyer_payment_provider,
			)
		);

		self::process_order_status( $ledyer_payment_status, $order, $payment_id );

		$order->update_meta_data( '_ledyer_date_paid', gmdate( 'Y-m-d H:i:s' ) );
		$order->save();

		do_action( 'ledyer_process_payment', $order->get_id(), $ledyer_order );

		$response = ledyer()->api->acknowledge_order( $payment_id );
		if ( is_wp_error( $response ) ) {
			Logger::error( 'Could not acknowledge the order in Ledyer', array( 'error' => $response->get_error_message() ) );
		} else {
			Logger::info( 'Order confirmed and acknowledged in Ledyer', array( 'order_status' => $order->get_status() ) );
		}
	}

	/**
	 * Process the order status based on the Ledyer payment status.
	 *
	 * @param array $ledyer_payment_status The Ledyer payment status response.
	 * @param \WC_Order $order The WooCommerce order object.
	 * @param string $ledyer_order_id The Ledyer order ID.
	 *
	 * @return void
	 */
	public static function process_order_status( $ledyer_payment_status, $order, $ledyer_order_id ) {
		$ack_order  = false;
		$old_status = $order->get_status();
		$status     = is_array( $ledyer_payment_status ) && isset( $ledyer_payment_status['status'] ) ? $ledyer_payment_status['status'] : '';

		Logger::add_order_context( $order );
		Logger::info(
			'Processing order status from Ledyer',
			array(
				'ledyer_status' => $status,
				'order_status'  => $old_status,
			)
		);

		switch ( $status ) {
			case \LedyerPaymentStatus::ORDER_PENDING:
				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
					$note = sprintf(
						__(
							'New session created in Ledyer with Payment ID %1$s. %2$s',
							'ledyer-checkout-for-woocommerce'
						),
						$ledyer_order_id,
						$ledyer_payment_status['note']
					);
					$order->update_status( 'on-hold', $note );
				}
				break;
			case \LedyerPaymentStatus::PAYMENT_PENDING:
				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
					$note = sprintf(
						__(
							'New payment pending payment created in Ledyer with Payment ID %1$s. %2$s',
							'ledyer-checkout-for-woocommerce'
						),
						$ledyer_order_id,
						$ledyer_payment_status['note']
					);
					$order->update_status( 'on-hold', $note );
					$ack_order = true;
				}
				break;
			case \LedyerPaymentStatus::PAYMENT_CONFIRMED:
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$note = sprintf(
						__(
							'Payment successfully confirmed in Ledyer with Payment ID %1$s. %2$s',
							'ledyer-checkout-for-woocommerce'
						),
						$ledyer_order_id,
						$ledyer_payment_status['note']
					);


					$order->add_order_note( $note );
					$ack_order = true;

					// If the order does not need payment, we need to ensure the "ready-for-capture" event has been triggered.
					$ready_for_capture = $order->get_meta( '_ledyer_ready_for_capture', true );
					if ( ! $order->needs_payment() && empty( $ready_for_capture ) ) {
						// Set the metadata to indicate the order is still waiting for the ready for capture event.
						$order->update_meta_data( '_ledyer_waiting_on_ready_for_capture', true );
						$order->save();
						Logger::info( 'Order is paid but waiting for the ready_for_capture event' );
						break;
					}
					$order->payment_complete( $ledyer_order_id );
					Logger::info( 'Payment completed on the order' );
				}
				break;
			case \LedyerPaymentStatus::ORDER_CAPTURED:
				$new_status = 'completed';

				$settings = get_option( 'woocommerce_lco_settings' );

				// Check if we should keep card payments in processing status.
				if (
					isset( $settings['keep_cards_processing'] )
					&& 'yes' === $settings['keep_cards_processing']
					&& isset( $ledyer_payment_status['paymentMethod'] )
					&& isset( $ledyer_payment_status['paymentMethod']['type'] )
					&& 'card' === $ledyer_payment_status['paymentMethod']['type']
				) {
					$new_status = 'processing';
				}

				$new_status = apply_filters( 'lco_captured_update_status', $new_status, $ledyer_payment_status );
				$order->update_status( $new_status );
				break;
			case \LedyerPaymentStatus::ORDER_REFUNDED:
				$order->update_status( 'refunded' );
				break;
			case \LedyerPaymentStatus::ORDER_CANCELLED:
				$order->update_status( 'cancelled' );
				break;
			case \LedyerPaymentStatus::ORDER_INITIATED:
				Logger::info( 'Order is initiated in Ledyer but not yet placed, no status change' );
				break;
			default:
				Logger::warning( 'Unhandled payment status from Ledyer', array( 'ledyer_status' => $status ) );
				break;
		}

		if ( $order->get_status() !== $old_status ) {
			Logger::info(
				'Order status changed',
				array(
					'from' => $old_status,
					'to'   => $order->get_status(),
				)
			);
		}

		// If we need to acknowledge the order, do it now.
		if ( $ack_order ) {
			$response = ledyer()->api->acknowledge_order( $ledyer_order_id );
			if ( is_wp_error( $response ) ) {
				Logger::error( 'Could not acknowledge the order in Ledyer', array( 'error' => $response->get_error_message() ) );
				return;
			}
			Logger::info( 'Order acknowledged in Ledyer' );

			// If the merchant reference was not already set, set it now.
			$merchant_reference = $order->get_meta( '_ledyer_merchant_reference', true );
			if ( empty( $merchant_reference ) ) {
				$ledyer_update_order = ledyer()->api->update_order_reference( $ledyer_order_id, array( 'reference' => $order->get_order_number() ) );
				if ( is_wp_error( $ledyer_update_order ) ) {
					Logger::error(
						'Could not set the merchant reference in Ledyer',
						array(
							'reference' => $order->get_order_number(),
							'error'     => $ledyer_update_order->get_error_message(),
						)
					);
					return;
				}
				Logger::info( 'Merchant reference set in Ledyer', array( 'reference' => $order->get_order_number() ) );
			}
		}
	}
}
