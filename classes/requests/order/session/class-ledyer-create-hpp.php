<?php
/**
 * Create HPP
 *
 * @package Ledyer\Requests\Order\Session
 */

namespace Ledyer\Requests\Order\Session;

use Ledyer\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Create_HPP
 *
 * @package Ledyer\Requests\Order\Session
 */
class Create_HPP extends Request_Order {
	/**
	 * Adds a confirmation URL to the cart data.
	 *
	 * @param array $data The WooCommerce cart data.
	 * @param int   $order_id The WooCommerce order id.
	 * @return array The modified cart data.
	 */
	public function set_confirmation_url( $data, $order_id ) {
		$merchant_urls = ledyer()->merchant_urls->get_urls( $order_id );
		if ( $merchant_urls ) {
			$data['settings']['urls']['confirmation'] = $merchant_urls['confirmation'];
		}
		return $data;
	}

	/**
	 * Creates the HPP URL.
	 *
	 * @param string $session_id The Ledyer Checkout session to use for the HPP request.
	 * @return string The HPP URL.
	 */
	public function create_hpp_url( $session_id ) {
		// Todo: remove hardcoding here.
		return 'https://pos.sandbox.ledyer.com/?sessionId=' . $session_id;
	}

	/**
	 *
	 * Request method
	 */
	protected function set_url() {
		$this->url = sprintf( 'v1/sessions/%s', $this->arguments['orderId'] );

		parent::get_request_url();
	}
}
