<?php
/**
 * API Class file.
 *
 * @package Ledyer
 */
namespace Ledyer;

\defined( 'ABSPATH' ) || die();

use Ledyer\Requests\Order\Management\Update_Order_Reference;
use Ledyer\Requests\Order\Session\Create_Order;
use Ledyer\Requests\Order\Session\Update_Order;
use Ledyer\Requests\Order\Session\Get_Order;

/**
 * API class.
 *
 * Class that has functions for the ledyer communication.
 */
class API {

	/**
	 * @param $order_id
	 *
	 * @return mixed|\WP_Error
	 */
	public function get_order_session( $order_id ) {
		return ( new Get_Order( array( 'orderId' => $order_id ) ) )->request();
	}

	/**
	 * @param $data
	 *
	 * @return mixed|\WP_Error
	 */
	public function create_order_session( $data, $order_id = false ) {

		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			$confirmation_url = $order->get_checkout_order_received_url();

			if ( $confirmation_url ) {
				$data['settings']['urls']['confirmation'] = $confirmation_url;
			}
		}

		return ( new Create_Order( compact( 'data' ) ) )->request();
	}

	/**
	 * Creates a Ledyer HPP URL.
	 *
	 * @param string $session_id The Ledyer Checkout session to use for the HPP request.
	 * @return mixed
	 */
	public function create_ledyer_hpp_url( $session_id ) {
		return 'https://pos.sandbox.ledyer.com/?sessionId=' . $session_id;
	}

	/**
	 * @param $order_id
	 * @param $data
	 *
	 * @return mixed|\WP_Error
	 */
	public function update_order_session( $order_id, $data ) {
		return ( new Update_Order(
			array(
				'orderId' => $order_id,
				'data'    => $data,
			)
		) )->request();
	}

	/**
	 * @param $order_id
	 *
	 * @return mixed|\WP_Error
	 */
	public function get_order( $order_id ) {
		return ( new \Ledyer\Requests\Order\Management\Get_Order( array( 'orderId' => $order_id ) ) )->request();
	}

	/**
	 * @param $order_id
	 * @param $data
	 *
	 * @return mixed|\WP_Error
	 */
	public function update_order_reference( $order_id, $data ) {
		return ( new Update_Order_Reference(
			array(
				'orderId' => $order_id,
				'data'    => $data,
			)
		) )->request();
	}

	/**
	 * @param $order_id
	 *
	 * @return mixed|\WP_Error
	 */
	public function acknowledge_order( $order_id ) {
		return ( new \Ledyer\Requests\Order\Management\Acknowledge_Order(
			array(
				'orderId' => $order_id,
				'data'    => array(),
			)
		) )->request();
	}

	/**
	 * @param $order_id
	 *
	 * @return mixed|\WP_Error
	 */
	public function get_payment_status( $order_id ) {
		return ( new \Ledyer\Requests\Order\Management\Get_Payment_Status( array( 'orderId' => $order_id ) ) )->request();
	}
}
