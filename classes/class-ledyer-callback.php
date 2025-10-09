<?php
/**
 * Class Callback.
 *
 * Handles callbacks (also known as "notifications") from Ledyer.
 */

namespace Ledyer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Callback.
 */
class Callback {

	/**
	 * REST API namespace and endpoint for Ledyer callbacks.
	 *
	 * This is used to register the REST API route for handling Ledyer notifications.
	 */
	public const REST_API_NAMESPACE = 'ledyer/v1';
	public const REST_API_ENDPOINT  = '/notifications';
	public const API_ENDPOINT       = 'wp-json/' . self::REST_API_NAMESPACE . self::REST_API_ENDPOINT;

	/**
	 * Singleton instance.
	 *
	 * @var Callback|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the Callback class.
	 *
	 * @return Callback
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Callback constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'schedule_process_notification', array( $this, 'process_notification' ), 10, 2 );
	}

	/**
	 * Register the REST API route(s).
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_API_NAMESPACE,
			self::REST_API_ENDPOINT,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_notification' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handles notification callbacks
	 *
	 * @param \WP_REST_Request $request The incoming request object.
	 * @return \WP_REST_Response
	 */
	public function handle_notification( \WP_REST_Request $request ) {
		$request_body = $request->get_json_params();
		$response     = new \WP_REST_Response( null, 400 );

		if ( empty( $request_body ) ) {
			Logger::log( "[CALLBACK]: Request body isn't valid JSON string. Received: " . wp_json_encode( $request_body ) );
			return $response;
		}

		$ledyer_event_type = $request_body['eventType'];
		$ledyer_order_id   = $request_body['orderId'];

		if ( ! isset( $ledyer_event_type, $ledyer_order_id ) ) {
			Logger::log( "[CALLBACK]: Request body doesn't hold orderId and eventType data." );
			return $response;
		}

		$schedule_id = as_schedule_single_action( time() + 60, 'schedule_process_notification', array( $ledyer_order_id, $ledyer_event_type ) );

		if ( 0 === $schedule_id ) {
			Logger::log( "[CALLBACK]: Couldn't schedule process_notification for order: $ledyer_order_id and type: $ledyer_event_type" );
			$response->set_status( 500 );
			return $response;
		}

		Logger::log( "[CALLBACK]: Enqueued notification: $ledyer_event_type, schedule-id: $schedule_id" );
		$response->set_status( 200 );
		return $response;
	}

	/**
	 * Processes the notification from Ledyer.
	 *
	 * This function is called by the scheduled action and processes the notification
	 * by updating the WooCommerce order based on the Ledyer event type and order ID.
	 *
	 * @param string $ledyer_order_id The Ledyer order ID.
	 * @param string $ledyer_event_type The type of event from Ledyer.
	 */
	public function process_notification( $ledyer_order_id, $ledyer_event_type ) {
		Logger::log( "[SCHEDULER]: process notification: $ledyer_order_id" );

		$orders = wc_get_orders(
			array(
				'meta_key'     => '_wc_ledyer_order_id',
				'meta_value'   => $ledyer_order_id,
				'meta_compare' => '=',
				'date_created' => '>' . ( time() - MONTH_IN_SECONDS ),
			),
		);

		$order = reset( $orders );
		if ( ! $order ) {
			Logger::log( "[SCHEDULER]: No WooCommerce order found for Ledyer order ID: $ledyer_order_id" );
			return;
		}

		$order_id = $order->get_id();
		$order    = wc_get_order( $order_id );

		$wc_order_ledyer_order_id = $order->get_meta( '_wc_ledyer_order_id' );
		if ( $ledyer_order_id !== $wc_order_ledyer_order_id ) {
			Logger::log( "[SCHEDULER]: Order {$order->get_order_number()} has Ledyer order ID $wc_order_ledyer_order_id. Expected $ledyer_order_id" );
			return;
		}

		Logger::log( "[SCHEDULER]: Order to process: $order_id" );

		$ledyer_payment_status = ledyer()->api->get_payment_status( $ledyer_order_id );
		if ( is_wp_error( $ledyer_payment_status ) ) {
			Logger::log( "[SCHEDULER]: Could not get ledyer payment status $ledyer_order_id" );
			return;
		}

		// Process the ready for capture event.
		if ( 'com.ledyer.order.ready_for_capture' === $ledyer_event_type ) {
			$this->process_ready_for_capture( $ledyer_payment_status, $order, $ledyer_order_id );
			return;
		}

		$ledyer_payment_method = $ledyer_payment_status['paymentMethod'];
		if ( ! empty( $ledyer_payment_status['paymentMethod'] ) ) {
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
			$order->save();
		}

		$this->process_order_status( $ledyer_payment_status, $order, $ledyer_order_id );
	}

	/**
	 * Process the ready for capture event.
	*
	 * @param array $ledyer_payment_status The Ledyer payment status response.
	 * @param \WC_Order $order The WooCommerce order object.
	 * @param string $ledyer_order_id The Ledyer order ID.
	 *
	 * @return void
	 */
	private function process_ready_for_capture( $ledyer_payment_status, $order, $ledyer_order_id ) {
	    $order->update_meta_data( '_ledyer_ready_for_capture', true );

	    // If we were waiting for the ready for capture event, we can now complete the order.
	    $waiting_on_ready_for_capture = $order->get_meta( '_ledyer_waiting_on_ready_for_capture', true );
	    if ( ! empty( $waiting_on_ready_for_capture ) ) {
	    	$order->delete_meta_data( '_ledyer_waiting_on_ready_for_capture' );
	    	Logger::log( "[SCHEDULER]: Order {$order->get_order_number()} was waiting for ready_for_capture event. Now processing the order." );
	    	$this->process_order_status( $ledyer_payment_status, $order, $ledyer_order_id );
	    }

	    // Save the order and return.
	    $order->save();
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
	private function process_order_status( $ledyer_payment_status, $order, $ledyer_order_id ) {
		$ack_order = false;

	    switch ( $ledyer_payment_status['status'] ) {
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
	    				Logger::log( "[SCHEDULER]: Order {$order->get_order_number()} is paid but waiting for ready_for_capture event." );
	    				break;
	    			}
	    			$order->payment_complete( $ledyer_order_id );
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
	    }

		// If we need to acknowledge the order, do it now.
	    if ( $ack_order ) {
	    	$response = ledyer()->api->acknowledge_order( $ledyer_order_id );
	    	if ( is_wp_error( $response ) ) {
	    		Logger::log( "[SCHEDULER]: Couldn't acknowledge order $ledyer_order_id" );
	    		return;
	    	}

			// If the merchant reference was not already set, set it now.
			$merchant_reference = $order->get_meta( '_ledyer_merchant_reference', true );
	    	if ( empty( $merchant_reference ) ) {
				$ledyer_update_order = ledyer()->api->update_order_reference( $ledyer_order_id, array( 'reference' => $order->get_order_number() ) );
				if ( is_wp_error( $ledyer_update_order ) ) {
					Logger::log( "[SCHEDULER]: Couldn't set merchant reference {$order->get_order_number()}" );
					return;
				}
			}
	    }
	}
}
