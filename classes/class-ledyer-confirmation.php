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
	}


	/**
	 * Confirm the order in Woo before redirecting the customer to thank you page.
	 */
	public function confirm_order() {

		$ledyer_purchase_complete = filter_input( INPUT_GET, 'lco_purchase_complete', FILTER_SANITIZE_STRING );
		$ledyer_confirm = filter_input( INPUT_GET, 'lco_confirm', FILTER_SANITIZE_STRING );
		$order_key    = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
		$ledyer_pending    = filter_input( INPUT_GET, 'lco_pending', FILTER_SANITIZE_STRING );

		if ( empty( $ledyer_confirm ) || empty( $order_key ) || empty( $ledyer_purchase_complete) ) {
			return;
		}
		$order_id = wc_get_order_id_by_order_key( $order_key );
		$order    = wc_get_order( $order_id );

		$order_pending_order = ( 'yes' === $ledyer_pending ) ? true : false;
		$should_confirm_order = ( 'yes' === $ledyer_purchase_complete ) ? true : false;

		Logger::log( $order_id . ': Confirm the Ledyer order from the confirmation page.' );

		if ( $should_confirm_order ) {
			// Confirm the order.
			wc_ledyer_confirm_ledyer_order( $order_id, $order_pending_order );
			lco_unset_sessions();
		}
	}
}
