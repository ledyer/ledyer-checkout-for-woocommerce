<?php
/**
 * Class file for Ledyer_Checkout_For_WooCommerce class.
 *
 * @package Ledyer
 * @since 1.0.0
 */

namespace Ledyer;

use Ledyer\Admin\Meta_Box;

\defined( 'ABSPATH' ) || die();

/**
 * Ledyer_Checkout_For_WooCommerce class.
 *
 * Init class
 */
class Ledyer_Checkout_For_WooCommerce {
	use Singleton;

	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = [];
	/**
	 * Reference to credentials class.
	 *
	 * @var Credentials $credentials
	 */
	public $credentials;
	/**
	 * Reference to merchant URLs class.
	 *
	 * @var Merchant_URLs $merchant_urls
	 */
	public $merchant_urls;
	/**
	 * Reference to Ledyer API
	 *
	 * @var API $api
	 */
	public $api;
	/**
	 * Reference to Ledyer Checkout
	 *
	 * @var Checkout $checkout
	 */
	public $checkout;

	const VERSION = '1.1.3';
	const SLUG = 'ledyer-checkout-for-woocommerce';
	const SETTINGS = 'ledyer_checkout_for_woocommerce_settings';

	public function actions() {
		\add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
		\add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		add_action( 'rest_api_init', function () {
			register_rest_route( 'ledyer/v1', '/notifications/', array(
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_order_status' ],
				'permission_callback' => '__return_true'
			) );
		} );

		add_action(
			'woocommerce_checkout_fields',
			array(
				$this,
				'modify_checkout_fields',
			),
			20,
			1,
		);

	}

	/**
	 * Handles Order management from notifications endpoint
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function update_order_status( $request ) {
		$request_body = $request->get_body();


		if( ! $this->is_json( $request_body ) ) {
			Logger::log( 'Request body isn\'t valid JSON string.' );

			$data = array(
				'message'         => 'Request body isn\'t valid JSON string.',
				'json' => $request_body,
			);

			$response = new \WP_REST_Response( $data );
			$response->set_status( 404 );

			return $response;
		}

		$request_body = json_decode( $request_body, true );

		if( ! is_array( $request_body ) || empty( $request_body['orderId'] || empty( $request_body['eventType'] ) ) ) {
			Logger::log( 'Request body doesn\'t hold orderId and eventType data.' );

			$data = array(
				'message'         => 'Request body doesn\'t hold orderId and eventType data.',
				'json' => $request_body,
			);

			$response = new \WP_REST_Response( $data );
			$response->set_status( 404 );

			return $response;
		}

		$order_id = $request_body['orderId'];

		$args = array(
			'post_type'   => 'shop_order',
			'post_status' => 'any',
			'meta_key'    => '_wc_ledyer_order_id',
			'meta_value'  => $order_id,
		);

		$orders = new \WP_Query( $args );

		$ledyer_order = ledyer()->api->get_order( $order_id );

		if ( ! $ledyer_order || ( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) || $ledyer_order['id'] !== $order_id ) {
			Logger::log( $order_id . ': Could not get Ledyer order.' );

			$data = array(
				'message'         => $order_id . ': Could not get Ledyer order.',
				'ledyer_order_id' => $order_id,
			);

			$response = new \WP_REST_Response( $data );
			$response->set_status( 404 );

			return $response;
		}

		if ( $orders->have_posts() && in_array( $request_body['eventType'], array('com.ledyer.order.ready_for_capture', 'com.ledyer.order.create') ) ) {
			foreach ( $orders->posts as $order ) {
				if ( 'revision' !== $order->post_status ) {

					$order = wc_get_order( $order->ID );

					if( 'com.ledyer.order.create' === $request_body['eventType'] ) {
						$order->update_status('pending');
						$response = ledyer()->api->acknowledge_order( $order_id );
						if( is_wp_error( $response ) ) {
							\Ledyer\Logger::log( 'Couldn\'t acknowledge order ' . $order_id  );
						}
					}

					if( 'com.ledyer.order.ready_for_capture' === $request_body['eventType'] ) {
						$order->update_status('processing');
					}

					switch ( $ledyer_order['status'][0] ) {
						case 'fullyCaptured':
							$order->update_status( 'completed' );
							$order->add_order_note( sprintf( __( 'Payment Completed in Ledyer with Payment ID %1$s. Payment type - %2$s.', 'ledyer-checkout-for-woocommerce' ), $order_id, $request['paymentMethod']['type'] ) );
							break;
						case 'cancelled':
							$order->update_status( 'canceled' );
							$order->add_order_note( sprintf( __( 'Payment Canceled in Ledyer with Payment ID %1$s. Payment type - %2$s.', 'ledyer-checkout-for-woocommerce' ), $order_id, $request['paymentMethod']['type'] ) );
							break;
						case 'fullyRefunded':
							$order->update_status( 'refunded' );
							$order->add_order_note( sprintf( __( 'Payment Fully Refunded in Ledyer with Payment ID %1$s. Payment type - %2$s.', 'ledyer-checkout-for-woocommerce' ), $order_id, $request['paymentMethod']['type'] ) );
							break;
					}
				}
			}

			$data = array(
				'message' => 'successfully updated order status.',
				'ledyer_order_id' => $order_id,
			);

			$response = new \WP_REST_Response( $data );
			$response->set_status( 201 );

			return $response;
		} else {
			Logger::log( $order_id . ': Could not find Ledyer order in Woo.' );
			$data = array(
				'message' => 'Ledyer order '. $order_id .' doesn\'t exist in Woo.',
				'ledyer_order_id' => $order_id,
			);

			$response = new \WP_REST_Response( $data );
			$response->set_status( 200 );

			return $response;
		}

	}

	/**
	 * Checks if given string is valid JSON string.
	 * @param $string
	 *
	 * @return bool
	 */
	public function is_json( $string ) {
		json_decode( $string );

		return json_last_error() === JSON_ERROR_NONE;
	}


	/**
	 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		if ( ! defined( 'WC_VERSION' ) ) {
			return;
		}

		$this->include_files();
		$this->set_settings();

		AJAX::init();
		Templates::instance();
		Confirmation::instance();
		Checkout::instance();
		// Set class variables.
		$this->credentials   = Credentials::instance();
		$this->merchant_urls = new Merchant_URLs();
		$this->api           = new API();

		load_plugin_textdomain( 'ledyer-checkout-for-woocommerce', false, LCO_WC_PLUGIN_NAME . '/languages' );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LCO_WC_MAIN_FILE ), array(
			$this,
			'plugin_action_links'
		) );
	}

	/**
	 * Autoload classes
	 */
	public function include_files() {
		include_once LCO_WC_PLUGIN_PATH . '/includes/lco-functions.php';
        include_once LCO_WC_PLUGIN_PATH . '/includes/lco-functions.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-singleton.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-logger.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/admin/class-ledyer-meta-box.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/class-ledyer-request.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-request-order.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/session/class-ledyer-create-order.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/session/class-ledyer-get-order.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/session/class-ledyer-update-order.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/management/class-ledyer-acknowledge-order.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/management/class-ledyer-get-order.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/management/class-ledyer-update-order-reference.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-ledyer-cart.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-ledyer-woocommerce-bridge.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-ajax.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-api.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-checkout.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-confirmation.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-credentials.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-fields.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-lco-gateway.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-merchant-urls.php';
        include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-templates.php';
	}

	/**
	 * Get setting link.
	 *
	 * @since 1.0.0
	 *
	 * @return string Setting link
	 */
	public function get_setting_link() {
		$section_slug = 'lco';

		$params = array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => $section_slug,
		);

		$admin_url = add_query_arg( $params, 'admin.php' );
		return $admin_url;
	}

	/**
	 * Set LCO settings.
	 */
	public function set_settings() {
		self::$settings = get_option( 'woocommerce_lco_settings' );
	}

	/**
	 * Get LCO setting by name
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get_setting( $key ) {
		return self::$settings[ $key ];
	}

	/**
	 * Add the gateways to WooCommerce
	 *
	 * @param  array $methods Payment methods.
	 *
	 * @return array $methods Payment methods.
	 * @since  1.0.0
	 */
	public function add_gateways( $methods ) {
		$methods[] = 'Ledyer\LCO_Gateway';

		return $methods;
	}

	/**
	 * Adds plugin action links
	 *
	 * @param array $links Plugin action link before filtering.
	 *
	 * @return array Filtered links.
	 */
	public function plugin_action_links( $links ) {
		$setting_link = $this->get_setting_link();
		$plugin_links = array(
			'<a href="' . $setting_link . '">' . __( 'Settings', 'ledyer-checkout-for-woocommerce' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init meta box on admin hook.
	 */
	public function on_admin_init() {
		new Meta_Box();
	}

	/**
	 * Modify checkout fields
	 *
	 * @access public
	 *
	 * @param array $checkout_fields
	 *
	 * @return array $checkout_fields
	 */
	public function modify_checkout_fields( $checkout_fields ) {

		if( ! is_checkout() ) {
			return $checkout_fields;
		}

		if( ! isset( WC()->session ) || empty( WC()->session->get('chosen_payment_method') ) ) {
			return $checkout_fields;
		}

		if( 'lco' === WC()->session->get('chosen_payment_method') ) {
			foreach ( $checkout_fields['billing'] as $key => $field ) {
				if( false !== stripos( $key, 'first_name' ) || false !== stripos( $key, 'last_name' ) ) {
					$checkout_fields['billing'][ $key ]['required'] = false;
				}
			}

			foreach ( $checkout_fields['shipping'] as $key => $field ) {
				if( false !== stripos( $key, 'first_name' ) || false !== stripos( $key, 'last_name' ) ) {
					$checkout_fields['shipping'][ $key ]['required'] = false;
				}
			}
		}

		return $checkout_fields;
	}
}
