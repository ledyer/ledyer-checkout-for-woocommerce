<?php
/**
 * Data processor fro Ledyer Order Requests.
 *
 * @package Ledyer\Requests\Helpers
 */
namespace Ledyer\Requests\Helpers;

defined( 'ABSPATH' ) || exit();

/**
 * Class Woocommerce_Bridge
 *
 * Class that formats WooCommerce data for Ledyer API.
 *
 * @package Ledyer\Requests\Helpers
 */
class Woocommerce_Bridge {

	/**
	 * Formatted Ledyer settings.
	 *
	 * @var array $ledyer_settings
	 */
	private static $ledyer_settings;

	/**
	 * Get formatted cart data for Ledyer API Requests
	 *
	 * @return array[]
	 */
	public static function get_cart_data() {

		$is_test = ledyer()->get_setting('testmode');
		$cart = new Cart();
		$cart->process_data();

		$data = array(
			'locale'    => get_locale(),
			'metadata'  => null,
			'orderLines' => $cart->get_order_lines(),
			'reference' => null,
			'settings'  => self::get_order_settings( true ),
			'storeId' => ledyer()->get_setting( ( 'yes' === $is_test ? 'test_' : '' ) . 'store_id' ),
			'totalOrderAmount' => $cart->get_order_amount(),
			'totalOrderAmountExclVat' => $cart->get_order_amount_ex_tax( $cart->get_order_lines() ),
			'totalOrderVatAmount' => $cart->get_order_tax_amount( $cart->get_order_lines() ),
		);

		$data =  array_merge( $cart->get_customer(), $data );

		return apply_filters( 'lco_' . __FUNCTION__, $data );
	}
	/**
	 * Get formatted cart data for Ledyer API Requests -- update
	 *
	 * @return array[]
	 */
	public static function get_updated_cart_data() {
		$cart = new Cart();
		$cart->process_data();

		$data = array(
			'locale'    => get_locale(),
			'orderLines' => $cart->get_order_lines(),
			'settings'  => self::get_order_settings( false ),
			'totalOrderAmount' => $cart->get_order_amount(),
			'totalOrderAmountExclVat' => $cart->get_order_amount_ex_tax( $cart->get_order_lines() ),
			'totalOrderVatAmount' => $cart->get_order_tax_amount( $cart->get_order_lines() ),
		);

		$customer_cart_data =  $cart->get_customer();

		$data =  array_merge( array( 'country' => $customer_cart_data['country'], 'currency' => $customer_cart_data['currency'] ), $data );

		return apply_filters( 'lco_' . __FUNCTION__, $data );
	}

	/**
	 * Creates formatted Ledyer settings for Ledyer API
	 */
	private static function set_order_settings( $full = true ) {
		$merchant_urls = ledyer()->merchant_urls->get_urls();

		self::$ledyer_settings = array(
			'security' => array(
				'level' => intval( ledyer()->get_setting('security_level') )
			),
		);

		if( $full ) {
			self::$ledyer_settings = array(
				'security' => array(
					'level' => intval( ledyer()->get_setting('security_level') ),
					'requireClientValidation'  => true,
				),
				'customer' => array(
					'showNameFields' => 'yes' === ledyer()->get_setting('customer_show_name_fields'),
					'allowShippingAddress' => 'yes' === ledyer()->get_setting('allow_custom_shipping'),
				),
				'urls' => array(
					'terms' => $merchant_urls['terms'],
					'privacy' => $merchant_urls['privacy'],
					'confirmation' => '',
					'validate' => null,
				)
			);
		}
	}

	/**
	 * Get formatted Ledyer settings for Ledyer API
	 *
	 * @return array
	 */
	public static function get_order_settings( $full = true ) {
		self::set_order_settings( $full );
		return self::$ledyer_settings;
	}

}
