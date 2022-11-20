<?php
/**
 * Abstract Request_Order
 *
 * @package Ledyer\Requests\Order
 */
namespace Ledyer\Requests\Order;

use Ledyer\Requests\Request;

defined( 'ABSPATH' ) || exit();

/**
 * Class Request_Order
 *
 * @package Ledyer\Requests\Order
 */
abstract class Request_Order extends Request {
	/*
	 * Set request url for all Request_Order child classes
	 */
	protected function set_request_url() {
		// $this->request_url  = parent::is_test() ? 'https://api.sandbox.ledyer.com/' : 'https://api.live.ledyer.com/';
		$this->request_url  = parent::is_test() ? 'https://api.dev.ledyer.com/' : 'https://api.live.ledyer.com/';
		$this->set_url();
	}
	/*
	 * Set entrypoint in all Request_Order child classes
	 */
	abstract protected function set_url();
}
