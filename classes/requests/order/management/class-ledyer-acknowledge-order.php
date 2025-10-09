<?php
/**
 * Acknowledge Order Request
 *
 * @package Ledyer\Requests\Order
 */
namespace Ledyer\Requests\Order\Management;

use Ledyer\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Acknowledge_Order
 *
 * @package Ledyer\Requests\Order\Management
 */
class Acknowledge_Order extends Request_Order {
	/**
	 * Request method
	 *
	 * @var string
	 */
	protected $method = 'POST';

	/**
	 * The log title to use for the debug log.
	 *
	 * @var string
	 */
	protected $log_title = 'Acknowledge order';

	/**
	 * Set entrypoint
	 */
	protected function set_url() {
		$this->url = sprintf( 'v1/orders/%s/acknowledge', $this->arguments['orderId'] );

		parent::get_request_url();
	}
}
