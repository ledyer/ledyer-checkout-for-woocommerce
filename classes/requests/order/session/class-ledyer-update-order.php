<?php
/**
 * Update Order Request
 *
 * @package Ledyer\Requests\Order\Session
 */
namespace Ledyer\Requests\Order\Session;

use Ledyer\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Update_Order
 *
 * @package Ledyer\Requests\Order\Session
 */
class Update_Order extends Request_Order {
	/*
	 * Set entrypoint
	 */
	protected $method = 'POST';

	/**
	 * The log title to use for the debug log.
	 *
	 * @var string
	 */
	protected $log_title = 'Update session';

	/*
	 * Request method
	 */
	protected function set_url() {
		$this->url = sprintf( 'v1/sessions/%s', $this->arguments['orderId'] );

		parent::get_request_url();
	}
}
