<?php
/**
 * Get Order Request
 *
 * @package Ledyer\Requests\Order\Session
 */
namespace Ledyer\Requests\Order\Session;

use Ledyer\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Get_Order
 *
 * @package Ledyer\Requests\Order\Session
 */
class Get_Order extends Request_Order {
	/**
	 * Request method
	 *
	 * @var string
	 */
	protected $method = 'GET';

	/**
	 * The log title to use for the debug log.
	 *
	 * @var string
	 */
	protected $log_title = 'Get session';

	/**
	 * Set entrypoint
	 */
	protected function set_url(): void {
		$this->url = sprintf( 'v1/sessions/%s', $this->arguments['orderId'] );

		parent::get_request_url();
	}
}
