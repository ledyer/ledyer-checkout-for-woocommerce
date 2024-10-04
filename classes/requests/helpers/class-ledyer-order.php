<?php
/**
 * Order lines processor.
 *
 * @package Ledyer\Requests\Helpers
 */

namespace Ledyer\Requests\Helpers;

use WC_Tax;

defined( 'ABSPATH' ) || exit();

/**
 * Order class.
 *
 * Class that formats WooCommerce order contents for Ledyer API.
 */
class Order {

	/**
	 * Get order data for Ledyer API Request
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
		$merchant_urls = ledyer()->merchant_urls->get_urls( $order->get_id() );
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
					'terms'        => $merchant_urls['terms'],
					'privacy'      => '',
					'confirmation' => $merchant_urls['confirmation'],
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
