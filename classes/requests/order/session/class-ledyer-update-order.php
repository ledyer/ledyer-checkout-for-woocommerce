<?php
/**
 * Update Order Request
 *
 * @package Ledyer\Requests\Order\Session
 */
namespace Ledyer\Requests\Order\Session;

use Ledyer\Requests\Order\Request_Order;

defined("ABSPATH") || exit();

/**
 * Class Update_Order
 *
 * @package Ledyer\Requests\Order\Session
 */
class Update_Order extends Request_Order
{
	/*
	 * Set entrypoint
	 */
	protected $method = "POST";
	/*
	 * Request method
	 */
	protected function set_url()
	{
		$this->url = sprintf("v1/sessions/%s", $this->arguments["orderId"]);

		parent::get_request_url();
	}
}
