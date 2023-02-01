<?php
/**
 * Class file for LCO_Gateway class.
 *
 * @package Ledyer
 * @since 1.0.0
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

if ( class_exists( 'WC_Payment_Gateway' ) ) {
	/**
	 * LCO_Gateway class.
	 *
	 * @extends WC_Payment_Gateway
	 */
	class LCO_Gateway extends \WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'lco';
			$this->method_title       = __( 'Ledyer Checkout', 'ledyer-checkout-for-woocommerce' );
			$this->method_description = __( 'The current Ledyer Checkout replaces standard WooCommerce checkout page.', 'ledyer-checkout-for-woocommerce' );
			$this->has_fields         = false;
			$this->supports           = apply_filters(
				'lco_wc_supports',
				array(
					'products',
					'subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
					'multiple_subscriptions',
					'subscription_payment_method_change_customer',
					'subscription_payment_method_change_admin',
				)
			);

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			$this->enabled  = $this->get_option( 'enabled' );
			$this->testmode = 'yes' === $this->get_option( 'testmode' );
			$this->logging  = 'yes' === $this->get_option( 'logging' );

			\add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array(
					$this,
					'process_admin_options',
				)
			);
			\add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			\add_filter( 'script_loader_tag', array( $this, 'add_data_attributes' ), 10, 2 );

			\add_action( 'woocommerce_checkout_order_processed', array( $this, 'wc_order_created' ), 10, 3 );
			\add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
			\add_action( 'woocommerce_thankyou', 'lco_unset_sessions', 100, 1 );

			\add_action('woocommerce_admin_order_data_after_billing_address', array( $this, 'ledyer_order_billing_fileds' ), 10, 1);
			\add_action('woocommerce_admin_order_data_after_shipping_address', array( $this, 'ledyer_order_shipping_fileds' ), 10, 1);

			\add_filter('woocommerce_formatted_address_replacements', array( $this, 'change_order_fields' ), 10, 2);
		}

		/**
		 * Get gateway icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			$icon_src  = 'https://static.ledyer.com/images/logos/ledyer-darkgray.svg';
			$icon_html = '<img src="' . $icon_src . '" alt="Ledyer Checkout" style="width: 100px;border-radius:0px"/>';

			return apply_filters( 'wc_ledyer_checkout_icon_html', $icon_html );
		}

		/**
		 * Initialise settings fields.
		 */
		public function init_form_fields() {
			$this->form_fields = Fields::fields();
		}

		/**
		 * Enqueue payment scripts.
		 *
		 * @hook wp_enqueue_scripts
		 */
		public function enqueue_scripts() {
			if ( 'yes' !== ledyer()->get_setting( 'enabled' ) ) {
				return;
			}

			if ( ! is_checkout() ) {
				return;
			}


			$scriptSrcUrl = 'https://checkout.live.ledyer.com/bootstrap.js';

			if ( $this->testmode ) {
				switch (ledyer()->get_setting( 'development_test_environment' )) {
					case 'local':
					case 'local-fe':
						$scriptSrcUrl = 'http://localhost:1337/bootstrap.iife.js';
						break;
					case 'development':
						$scriptSrcUrl = 'https://checkout.dev.ledyer.com/bootstrap.js';
						break;
					default: 
						$scriptSrcUrl = 'https://checkout.sandbox.ledyer.com/bootstrap.js';
						break;
				}
			}

			//Register iframe script
			wp_register_script(
				'lco-iframe',
				$scriptSrcUrl,
				array( 'jquery', 'wc-cart', 'jquery-blockui' ),
				LCO_WC_VERSION,
				true
			);


			if ( is_order_received_page() ) {
				wp_enqueue_script( 'lco-iframe' );
				return;
			}

			$pay_for_order = false;

			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				$pay_for_order = true;
			}

			wp_register_script(
				'lco',
				plugins_url( 'assets/js/ledyer-checkout-for-woocommerce.js', LCO_WC_MAIN_FILE ),
				array( 'jquery', 'wc-cart', 'jquery-blockui' ),
				'73823712837218321',
				true
			);

			wp_register_style(
				'lco',
				plugins_url(  'build/ledyer-checkout-for-woocommerce.css', LCO_WC_MAIN_FILE ),
				array(),
				filemtime( plugin_dir_path( LCO_WC_MAIN_FILE ) . ( 'build/ledyer-checkout-for-woocommerce.css' ) )
			);

			$email_exists = 'no';

			if ( null !== WC()->customer && method_exists( WC()->customer, 'get_billing_email' ) && ! empty( WC()->customer->get_billing_email() ) ) {
				if ( email_exists( WC()->customer->get_billing_email() ) ) {
					// Email exist in a user account.
					$email_exists = 'yes';
				}
			}

			$standard_woo_checkout_fields = apply_filters( 'lco_ignored_checkout_fields', array(
				'billing_first_name',
				'billing_last_name',
				'billing_address_1',
				'billing_address_2',
				'billing_postcode',
				'billing_city',
				'billing_phone',
				'billing_email',
				'billing_state',
				'billing_country',
				'billing_company',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_postcode',
				'shipping_city',
				'shipping_state',
				'shipping_country',
				'shipping_company',
				'terms',
				'terms-field',
				'_wp_http_referer',
				'ship_to_different_address'
			) );

			$checkout_localize_params = array(
				'update_cart_url'               => \WC_AJAX::get_endpoint( 'lco_wc_update_cart' ),
				'update_cart_nonce'             => wp_create_nonce( 'lco_wc_update_cart' ),
				'change_payment_method_url'     => \WC_AJAX::get_endpoint( 'lco_wc_change_payment_method' ),
				'change_payment_method_nonce'   => wp_create_nonce( 'lco_wc_change_payment_method' ),
				'get_ledyer_order_url'          => \WC_AJAX::get_endpoint( 'lco_wc_get_ledyer_order' ),
				'get_ledyer_order_nonce'        => wp_create_nonce( 'lco_wc_get_ledyer_order' ),
				'log_to_file_url'               => \WC_AJAX::get_endpoint( 'lco_wc_log_js' ),
				'log_to_file_nonce'             => wp_create_nonce( 'lco_wc_log_js' ),
				'submit_order'                  => \WC_AJAX::get_endpoint( 'checkout' ),
				'logging'                       => $this->logging,
				'standard_woo_checkout_fields'  => $standard_woo_checkout_fields,
				'is_confirmation_page'          => ( is_lco_confirmation() ) ? 'yes' : 'no',
				'required_fields_text'          => __( 'Please fill in all required checkout fields.', 'ledyer-checkout-for-woocommerce' ),
				'email_exists'                  => $email_exists,
				'must_login_message'            => apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ) ),
				'timeout_message'               => __( 'Please try again, something went wrong with processing your order.', 'ledyer-checkout-for-woocommerce' ),
				'timeout_time'                  => apply_filters( 'lco_checkout_timeout_duration', 20 ),
				'pay_for_order'                 => $pay_for_order,
				'no_shipping_message'           => apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ),
			);

			if ( version_compare( WC_VERSION, '3.9', '>=' ) ) {
				$checkout_localize_params['force_update'] = true;
			}
			wp_localize_script( 'lco', 'lco_params', $checkout_localize_params );

			wp_enqueue_script( 'lco-iframe' );

			wp_enqueue_script( 'lco' );

			if ( ! $pay_for_order ) {
				wp_enqueue_style( 'lco' );
			}
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id WooCommerce order ID.
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$order                 = wc_get_order( $order_id );

			// Regular purchase.
			// 1. Process the payment.
			// 2. Redirect to order received page.
			if ( $this->process_payment_handler( $order_id ) ) {
				// Base64 encoded timestamp to always have a fresh URL for on hash change event.
				return array(
					'result'   => 'success',
					'redirect' => add_query_arg(
						array(
							'lco_confirm'  => 'yes',
						),
						$order->get_checkout_order_received_url()
					),
				);
			} else {
				return array(
					'result' => 'error',
				);
			}

		}

		/**
		 * Process the payment with information from Ledyer and return the result.
		 *
		 * @param int $order_id WooCommerce order ID.
		 *
		 * @return mixed
		 */
		public function process_payment_handler( $order_id ) {
			// Get the Ledyer order ID.
			$order = wc_get_order( $order_id );

			$ledyer_order_id = WC()->session->get( 'lco_wc_order_id' );

			$ledyer_order = ledyer()->api->get_order_session( $ledyer_order_id );

			if ( ! $ledyer_order || is_wp_error( $ledyer_order ) ) {
				return false;
			}

			if ( $order_id && $ledyer_order ) {
				$customer_billing = isset( $ledyer_order['customer']['billingAddress'] ) ? $ledyer_order['customer']['billingAddress'] : false;
				$customer_shipping = isset( $ledyer_order['customer']['shippingAddress'] ) ? $ledyer_order['customer']['shippingAddress'] : false;

				$company_name = ! empty( $customer_billing['companyName'] ) ? $customer_billing['companyName'] : ( ! empty( $customer_shipping['companyName'] ) ? $customer_shipping['companyName'] : '' );
				// Set WC order transaction ID.
				update_post_meta( $order_id, '_wc_ledyer_order_id', $ledyer_order['orderId'] );

				update_post_meta( $order_id, '_wc_ledyer_session_id', $ledyer_order['id'] );

				update_post_meta( $order_id, '_transaction_id', $ledyer_order['orderId'] );

				update_post_meta( $order_id, '_ledyer_company_id', $ledyer_order['customer']['companyId'] );

				update_post_meta( $order_id, '_ledyer_company_name', $company_name );

				$environment = $this->testmode ? 'sandbox' : 'production';
				update_post_meta( $order_id, '_wc_ledyer_environment', $environment );

				$ledyer_country = wc_get_base_location()['country'];
				update_post_meta( $order_id, '_wc_ledyer_country', $ledyer_country );

				// Set shipping phone and email.
				update_post_meta( $order_id, '_shipping_phone', sanitize_text_field( $ledyer_order['customer']['phone'] ) );
				update_post_meta( $order_id, '_shipping_email', sanitize_text_field( $ledyer_order['customer']['email'] ) );

				// Set shipping meta
				if( isset( $ledyer_order['customer']['shippingAddress'] ) ) {
					update_post_meta( $order_id, '_shipping_attention_name', sanitize_text_field( $ledyer_order['customer']['shippingAddress']['attentionName'] ) );
					update_post_meta( $order_id, '_shipping_care_of', sanitize_text_field( $ledyer_order['customer']['shippingAddress']['careOf'] ) );
				}

				// Set billing meta
				if( isset( $ledyer_order['customer']['billingAddress'] ) ) {
					update_post_meta( $order_id, '_billing_attention_name', sanitize_text_field( $ledyer_order['customer']['billingAddress']['attentionName'] ) );
					update_post_meta( $order_id, '_billing_care_of', sanitize_text_field( $ledyer_order['customer']['billingAddress']['careOf'] ) );
				}


				$order->save();

				// Let other plugins hook into this sequence.
				do_action( 'lco_wc_process_payment', $order_id, $ledyer_order );

				// Check that the transaction id got set correctly.
				if ( get_post_meta( $order_id, '_transaction_id', true ) === $ledyer_order_id ) {
					return true;
				}
			}

			// Return false if we get here. Something went wrong.
			return false;
		}

		/**
		 * This plugin doesn't handle order management, but it allows Ledyer Order Management plugin to process refunds
		 * and then return true or false.
		 *
		 * @param int      $order_id WooCommerce order ID.
		 * @param null|int $amount Refund amount.
		 * @param string   $reason Reason for refund.
		 *
		 * @return bool
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			return apply_filters( 'wc_ledyer_checkout_process_refund', false, $order_id, $amount, $reason );
		}

		/**
		 * Displays Ledyer Checkout thank you iframe and clears Ledyer order ID value from WC session.
		 *
		 * @param int $order_id WooCommerce order ID.
		 */
		public function show_thank_you_snippet( $order_id = null ) {
			if ( $order_id ) {
				$order = wc_get_order( $order_id );

				if ( is_object( $order ) && $order->get_transaction_id() ) {
					?>
                    <div id="lco-iframe">
						<?php do_action( 'lco_wc_thankyou_before_snippet' ); ?>
						<?php do_action( 'lco_wc_thankyou_after_snippet' ); ?>
                    </div>

					<?php
				}
			}
		}

		public function add_data_attributes( $tag, $handle ) {
			if ( $handle == 'lco-iframe' ) {

				$env = 'production';

				if ( $this->testmode ) {
					switch (ledyer()->get_setting( 'development_test_environment' )) {
						case 'local':
						case 'local-fe':
							$env = 'localhost';
							break;
						case 'development':
							$env = 'dev';
							break;
						default:
							$env = 'sandbox';
							break;
					}
				}

				$buy_button_color = ledyer()->get_setting( 'color_button' );
				$no_padding       = 'yes' === ledyer()->get_setting( 'iframe_padding' ) ? 'true' : 'false';
				$lco_order_id     = WC()->session->get( 'lco_wc_session_id' );

				if ( is_order_received_page() ) {
					$order_key    = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );

					if ( empty( $order_key ) ) {
						return;
					}
					$order_id = wc_get_order_id_by_order_key( $order_key );

					$lco_order_id     = get_post_meta( $order_id, '_wc_ledyer_session_id', true );
				}

				return str_replace( '<script', '<script data-env="' . $env . '"  data-session-id="' . $lco_order_id . '" data-container-id="lco-iframe" data-buy-button-color="' . $buy_button_color . '" data-no-padding="' . $no_padding . '" ', $tag );
			}

			return $tag;
		}

		/**
		 * @param $order_id
		 * @param $posted_data
		 * @param $order
		 */
		public function wc_order_created( $order_id, $posted_data, $order ) {
		}

		/**
		 * Add additional billing fields on Edit Order Screen
		 * @param $order
		 *
		 * @return void
		 */
		public function ledyer_order_billing_fileds( $order ) {

			if ( 'Automattic\WooCommerce\Admin\Overrides\Order' === get_class( $order ) ) {
				$order_id = $order->get_id();
			} else {
				$order_id = $order->id;
			}

			$attention_name = get_post_meta($order_id, '_billing_attention_name', true);
			$care_of = get_post_meta($order_id, '_billing_care_of', true);

			if( ! empty( $attention_name ) ) {
				echo '<p><strong>' . __('Billing Attention Name') . ':</strong><br> ' . esc_html($attention_name) . '</p>';
			}

			if( ! empty( $care_of ) ) {
				echo '<p><strong>' . __('Billing Care Of') . ':</strong><br> ' . esc_html($care_of) . '</p>';
			}
		}
		/**
		 * Add additional shipping fields on Edit Order Screen
		 * @param $order
		 *
		 * @return void
		 */
		public function ledyer_order_shipping_fileds( $order ) {

			if ( 'Automattic\WooCommerce\Admin\Overrides\Order' === get_class( $order ) ) {
				$order_id = $order->get_id();
			} else {
				$order_id = $order->id;
			}

			$attention_name = get_post_meta( $order_id, '_shipping_attention_name', true );
			$care_of        = get_post_meta( $order_id, '_shipping_care_of', true );

			if ( ! empty( $attention_name ) ) {
				echo '<p><strong>' . __( 'Shipping Attention Name' ) . ':</strong><br> ' . esc_html($attention_name) . '</p>';
			}

			if ( ! empty( $care_of ) ) {
				echo '<p><strong>' . __( 'Shipping Care Of' ) . ':</strong><br> ' . esc_html($care_of) . '</p>';
			}
		}

		/**
		 * Hide name fields on Edit Order Screen
		 * @param $fields
		 * @param $args
		 *
		 * @return mixed
		 */
		public function change_order_fields( $fields, $args ) {
			if( is_admin() && ! wp_doing_ajax() ) {
				//Hide billing and shipping Name
				$fields['{name}'] = '';
			}
			return $fields;
		}

	}
}
