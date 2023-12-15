<?php
/**
 * AJAX class file.
 *
 * @package Ledyer
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * AJAX class.
 *
 * Registers AJAX actions for Ledyer Checkout for WooCommerce.
 *
 * @extends WC_AJAX
 */
class AJAX extends \WC_AJAX {

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'lco_wc_update_cart'                    => true,
			'lco_wc_change_payment_method'          => true,
			'lco_wc_get_ledyer_order'               => true,
			'lco_wc_log_js'                         => true,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Cart quantity update function.
	 */
	public static function lco_wc_update_cart() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lco_wc_update_cart' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

		$values = array();
		if ( isset( $_POST['checkout'] ) ) {
			$response = sanitize_url(wp_unslash( $_POST['checkout'] ));
			parse_str( html_entity_decode($response), $values );
			$values=filter_var_array($values, FILTER_SANITIZE_ENCODED);
		}

		$cart = $values['cart'];

		foreach ( $cart as $cart_key => $cart_value ) {
			$new_quantity = (int) $cart_value['qty'];
			WC()->cart->set_quantity( $cart_key, $new_quantity, false );
		}
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$ledyer_order_id = WC()->session->get( 'lco_wc_order_id' );
		$data              = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_updated_cart_data();
		$ledyer_order = ledyer()->api->update_order_session( $ledyer_order_id, $data );

		// If the update failed return error.
		if ( is_wp_error( $ledyer_order ) ) {
			wp_send_json_error();
			wp_die();
		}
		wp_die();
	}

	/**
	 * Refresh checkout fragment.
	 */
	public static function lco_wc_change_payment_method() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lco_wc_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$switch_to_ledyer   = isset( $_POST['lco'] ) ? sanitize_text_field( wp_unslash( $_POST['lco'] ) ) : '';

		if ( 'false' === $switch_to_ledyer ) {
			// Set chosen payment method to first gateway that is not Ledyer Checkout for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'lco' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'lco' );
		}

		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);

		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function lco_wc_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lco_wc_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$posted_message  = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$ledyer_order_id = WC()->session->get( 'lco_wc_order_id' );
		$message         = "Frontend JS $ledyer_order_id: $posted_message";
		Logger::log( $message );
		wp_send_json_success();
		wp_die();
	}
	/**
	 * Gets Ledyer order
	 *
	 * @return void
	 */
	public static function lco_wc_get_ledyer_order() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lco_wc_get_ledyer_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$ledyer_order = ledyer()->api->get_order_session( WC()->session->get( 'lco_wc_order_id' ) );

		if ( ! $ledyer_order ) {
			wp_send_json_error( $ledyer_order );
			wp_die();
		}

		$customer_fields = self::set_customer_data( $ledyer_order );

		if( ! $customer_fields ) {
			wp_send_json_error( 'customer data not set' );
			exit;
		}

		wp_send_json_success( $customer_fields );
		wp_die();

	}
	/**
	 * Sets hidden customer fields using info from Ledyer order.
	 *
	 * @return array
	 */
    public static function set_customer_data( $ledyer_order ) {
      echo 'kuken';
      if ( WC()->checkout() && ! empty( WC()->checkout()->checkout_fields ) ) {
          $billing_address = [];
          $shipping_address = [];

          // Helper function to safely get nested array values
          $safe_get = function ($array, $keys, $default = '') {
              foreach (explode('.', $keys) as $key) {
                  if (!isset($array[$key])) {
                      return $default;
                  }
                  $array = $array[$key];
              }
              return $array;
          };

          // Populate billing address fields
          $billing_fields = ['first_name', 'last_name', 'company', 'country', 'address_1', 'postcode', 'city', 'phone', 'email'];
          foreach ($billing_fields as $field) {
              $key = 'billing_' . $field;
              $billing_address[$key] = $safe_get($ledyer_order, 'customer.' . $field);
          }

          // Populate shipping address fields
          $shipping_fields = ['first_name', 'last_name', 'company', 'country', 'address_1', 'postcode', 'city', 'phone', 'email'];
          foreach ($shipping_fields as $field) {
              $key = 'shipping_' . $field;
              $shipping_address[$key] = $safe_get($ledyer_order, 'customer.shippingAddress.contact.' . $field, $safe_get($ledyer_order, 'customer.shippingAddress.' . $field));
          }

          // Special handling for address_1 field
          $billing_address['billing_address_1'] = empty($billing_address['billing_address_1']) ? $billing_address['billing_company'] : $billing_address['billing_address_1'];
          $shipping_address['shipping_address_1'] = empty($shipping_address['shipping_address_1']) ? $shipping_address['shipping_company'] : $shipping_address['shipping_address_1'];

          return array_merge($billing_address, $shipping_address);
      }

      return null;
  }
}
