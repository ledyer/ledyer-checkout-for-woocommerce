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
	 * Confirm the order in Woo
	 */
	public function confirm_order() {
		$ledyer_confirm = filter_input( INPUT_GET, 'lco_confirm', FILTER_SANITIZE_URL);
		$order_key    = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING);

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
}
