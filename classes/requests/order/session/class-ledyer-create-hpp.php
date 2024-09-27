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
	 * Creates the HPP URL.
	 *
	 * @param string $session_id The Ledyer Checkout session to use for the HPP request.
	 * @return string The HPP URL.
	 */
	public function create_hpp_url( $session_id ) {
		$mode = 'yes' === ledyer()->get_setting( 'testmode' ) ? 'sandbox' : 'live';
		return "https://pos.{$mode}.ledyer.com/?sessionId={$session_id}";
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
