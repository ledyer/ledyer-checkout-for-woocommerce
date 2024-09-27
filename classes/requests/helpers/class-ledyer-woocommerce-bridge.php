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

		$data = array_merge( $cart->get_customer(), $data );

		return apply_filters( 'lco_' . __FUNCTION__, $data );
	}

	/**
	 * Get formatted order data for Ledyer API Requests
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return array[]
	 */
	public static function get_order_data( $order ) {
		$is_test = ledyer()->get_setting( 'testmode' );

		// Initialize variables to store totals.
		$order_lines            = array();
		$total_order_amount     = 0;
		$total_order_vat_amount = 0;

		/**
		 * Loop order items and convert to Ledyer.
		 *
		 * @var \WC_Order_Item_Product $item
		 */
		foreach ( $order->get_items() as $item_id => $item ) {
			$product   = $item->get_product();
			$quantity  = $item->get_quantity();
			$total     = round( $item->get_total() * 100 );
			$total_tax = round( $item->get_total_tax() * 100 );

			// Add order line similar to cart data (without explicit rounding).
			$order_lines[] = array(
				'reference'      => $product->get_sku() ? $product->get_sku() : $product->get_id(),
				'description'    => $item->get_name(),
				'quantity'       => $quantity,
				'vat'            => self::get_order_line_tax_rate( $order, $item ),
				'totalAmount'    => $total + $total_tax,
				'totalVatAmount' => $total_tax,
				'type'           => $product->is_virtual() ? 'digital' : 'physical',
			);

			// Accumulate totals.
			$total_order_amount     += $total;
			$total_order_vat_amount += $total_tax;
		}

		/**
		 * Loop order items and convert to Ledyer.
		 *
		 * @var \WC_Order_Item_Shipping $shipping_item
		 */
		foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
			$shipping_total     = round( $shipping_item->get_total() * 100 );
			$shipping_total_tax = round( $shipping_item->get_total_tax() * 100 );

			$order_lines[] = array(
				'type'           => 'shippingFee',
				'reference'      => $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id(),
				'description'    => $shipping_item->get_name(),
				'quantity'       => 1,
				'vat'            => self::get_order_line_tax_rate( $order, $shipping_item ),
				'totalAmount'    => $shipping_total + $shipping_total_tax,
				'totalVatAmount' => $shipping_total_tax,
			);

			// Add shipping totals.
			$total_order_amount     += $shipping_total;
			$total_order_vat_amount += $shipping_total_tax;
		}
		$merchant_urls    = ledyer()->merchant_urls->get_urls( $order->get_id() );
		$confirmation_url = $merchant_urls ? $merchant_urls['confirmation'] : '';
		// Build the data array similar to the cart data.
		$data = array(
			'country'                 => $order->get_billing_country(),
			'currency'                => $order->get_currency(),
			'locale'                  => get_locale(),
			'metadata'                => null,
			'orderLines'              => $order_lines,
			'reference'               => null,
			'settings'                => array(
				'security' => array(
					'level'                   => 100,
					'requireClientValidation' => true,
				),
				'customer' => array(
					'showNameFields'             => false,
					'allowShippingAddress'       => false,
					'showShippingAddressContact' => false,
				),
				'urls'     => array(
					'terms'        => 'https://krokedil.anya.ngrok.io/?page_id=3',
					'privacy'      => '',
					'confirmation' => $confirmation_url,
					'validate'     => '',
				),
			),
			'storeId'                 => ledyer()->get_setting( ( 'yes' === $is_test ? 'test_' : '' ) . 'store_id' ),
			'totalOrderAmount'        => $total_order_amount + $total_order_vat_amount,
			'totalOrderAmountExclVat' => $total_order_amount,
			'totalOrderVatAmount'     => $total_order_vat_amount,
		);

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
				'level' => intval( ledyer()->get_setting('security_level') ),
				'requireClientValidation'  => true,
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
					'showShippingAddressContact' => 'yes' === ledyer()->get_setting('show_shipping_address_contact'),
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

	/**
	 * Gets the order line tax rate.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param mixed    $order_item If not false the WooCommerce order item WC_Order_Item.
	 * @return int
	 */
	public static function get_order_line_tax_rate( $order, $order_item = false ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			foreach ( $order_item->get_taxes()['total'] as $key => $value ) {
				if ( '' !== $value ) {
					if ( $rate_id === $key ) {
						return round( \WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] * 100 );
					}
				}
			}
		}
		// If we get here, there is no tax set for the order item. Return zero.
		return 0;
	}
}
