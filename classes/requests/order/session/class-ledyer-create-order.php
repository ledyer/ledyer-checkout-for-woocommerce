<?php
/**
 * Create Order Request
 *
 * @package Ledyer\Requests\Order\Session
 */
namespace Ledyer\Requests\Order\Session;

use Ledyer\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Create_Order
 *
 * @package Ledyer\Requests\Order\Session
 */
class Create_Order extends Request_Order {
	/**
	 * Request method
	 */

	protected $method = 'POST';

	/**
	 * The log title to use for the debug log.
	 *
	 * @var string
	 */
	protected $log_title = 'Create session';

	/**
	 * Set entrypoint
	 */
	protected function set_url() {
		$this->url = trim( 'v1/sessions/' );
	}
}
