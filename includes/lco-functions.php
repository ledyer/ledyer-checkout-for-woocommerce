<?php
/**
 * Functions file for the plugin.
 *
 * @package Ledyer
 */

\defined( 'ABSPATH' ) || die();

/**
 * Gets a Ledyer order. Either creates or updates existing order.
 *
 * @return array
 */
function lco_create_or_update_order() {
	// Need to calculate these here, because WooCommerce hasn't done it yet.
	WC()->cart->calculate_fees();
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();

	$old_ledyer_settings = WC()->session->get( 'lco_wc_settings' );

	if ( WC()->session->get( 'lco_wc_order_id' )
	     && $old_ledyer_settings['allow_custom_shipping'] === ledyer()->get_setting( 'allow_custom_shipping' )
	     && $old_ledyer_settings['customer_show_name_fields'] === ledyer()->get_setting( 'customer_show_name_fields' )
	     && $old_ledyer_settings['terms_url'] === ledyer()->get_setting( 'terms_url' )
	     && $old_ledyer_settings['privacy_url'] === ledyer()->get_setting( 'privacy_url' ) ) {


		$ledyer_order_id   = WC()->session->get( 'lco_wc_order_id' );
		$ledyer_session_id = WC()->session->get( 'lco_wc_session_id' );
		$data              = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_updated_cart_data();
		$ledyer_order      = ledyer()->api->update_order_session( $ledyer_order_id, $data );

		if ( ! $ledyer_order || ( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) || $ledyer_order['orderId'] !== $ledyer_order_id || $ledyer_order['sessionId'] !== $ledyer_session_id ) {
			// If update order failed try to create new order.
			$data         = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_cart_data();
			$ledyer_order = ledyer()->api->create_order_session( $data );
			if ( ! $ledyer_order || ( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) ) {
				// If failed then bail.
				if( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) {
					$errors = $ledyer_order->errors;
				} else {
					$errors = $ledyer_order;
				}

				\Ledyer\Logger::log( $errors );
				return false;
			}
			WC()->session->set( 'lco_wc_session_id', $ledyer_order['sessionId'] );
			WC()->session->set( 'lco_wc_order_id', $ledyer_order['orderId'] );
			WC()->session->set( 'lco_wc_settings', array(
				'allow_custom_shipping'     => ledyer()->get_setting( 'allow_custom_shipping' ),
				'customer_show_name_fields' => ledyer()->get_setting( 'customer_show_name_fields' ),
				'terms_url'                 => ledyer()->get_setting( 'terms_url' ),
				'privacy_url'               => ledyer()->get_setting( 'privacy_url' ),
			) );

			return $ledyer_order;
		} else {
			//If sessions somehow change??
			if ( ( $ledyer_order_id !== $ledyer_order['orderId'] ) || ( $ledyer_session_id !== $ledyer_order['sessionId'] ) ) {
				WC()->session->set( 'lco_wc_session_id', $ledyer_order['sessionId'] );
				WC()->session->set( 'lco_wc_order_id', $ledyer_order['orderId'] );
				WC()->session->set( 'lco_wc_settings', array(
					'allow_custom_shipping'     => ledyer()->get_setting( 'allow_custom_shipping' ),
					'customer_show_name_fields' => ledyer()->get_setting( 'customer_show_name_fields' ),
					'terms_url'                 => ledyer()->get_setting( 'terms_url' ),
					'privacy_url'               => ledyer()->get_setting( 'privacy_url' ),
				) );
			}
		}
		return $ledyer_order;
	} else {
		// Create new order, since we dont have one.
		$data         = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_cart_data();
		$ledyer_order = ledyer()->api->create_order_session( $data );

		if ( ! $ledyer_order || ( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) ) {
			if( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) {
				$errors = $ledyer_order->errors;
			} else {
				$errors = $ledyer_order;
			}

			\Ledyer\Logger::log( $errors );
			return false;
		}

		WC()->session->set( 'lco_wc_session_id', $ledyer_order['sessionId'] );
		WC()->session->set( 'lco_wc_order_id', $ledyer_order['orderId'] );
		WC()->session->set( 'lco_wc_settings', array(
			'allow_custom_shipping'     => ledyer()->get_setting( 'allow_custom_shipping' ),
			'customer_show_name_fields' => ledyer()->get_setting( 'customer_show_name_fields' ),
			'terms_url'                 => ledyer()->get_setting( 'terms_url' ),
			'privacy_url'               => ledyer()->get_setting( 'privacy_url' ),
		) );

		return $ledyer_order;
	}
}
/**
 * Checks if the current page is the confirmation page.
 *
 * @return boolean
 */
function is_lco_confirmation() {
	if ( isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] && isset( $_GET['lco_wc_session_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- No nonce possible on this page.
		return true;
	}

	return false;
}

/**
 * Get the selected, or the first, payment method.
 */
function lco_wc_get_selected_payment_method() {
	$selected_payment_method = '';
	if ( null !== WC()->session && method_exists( WC()->session, 'get' ) && WC()->session->get( 'chosen_payment_method' ) ) {
		$selected_payment_method = WC()->session->get( 'chosen_payment_method' );
	} else {
		$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
		reset( $available_payment_gateways );
		$selected_payment_method = key( $available_payment_gateways );
	}

	return $selected_payment_method;
}

/**
 * Unsets the sessions used by the plguin.
 *
 * @return void
 */
function lco_unset_sessions() {
	WC()->session->__unset( 'lco_wc_order_id' );
	WC()->session->__unset( 'lco_wc_session_id' );
	WC()->session->__unset( 'lco_wc_settings' );
}


/**
 * Shows select another payment method button in Ledyer Checkout page.
 */
function lco_wc_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_lco_settings' );
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'ledyer-checkout-for-woocommerce' );

		?>
        <p class="ledyer-checkout-select-other-wrapper">
            <a class="checkout-button button" href="#" id="ledyer-checkout-select-other">
				<?php echo esc_html( $select_another_method_text ); ?>
            </a>
        </p>
		<?php
	}
}

/**
 * Confirm the order in WooCommerce.
 *
 * @param int $order_id Woocommerce order id.
 *
 * @return void
 */
function wc_ledyer_confirm_ledyer_order( $order_id ) {
	$order = wc_get_order( $order_id );
	// If the order is already completed or on-hold, return.
	if ( !empty($order->get_date_paid()) || $order->has_status(array('on-hold')) ) {
		return;
	}

	$payment_id    = get_post_meta( $order_id, '_wc_ledyer_order_id', true );
	$session_id    = get_post_meta( $order_id, '_wc_ledyer_session_id', true );

	if ( null === $payment_id ) {
		$payment_id = WC()->session->get( 'lco_wc_order_id' );
	}

	if( null === $session_id ) {
		$session_id = WC()->session->get( 'lco_wc_session_id' );
    }

	
	$pending = false;
	
	$ledyer_order = ledyer()->api->get_order( $payment_id );
	if ( is_wp_error($ledyer_order) ) {
		$errcode = $ledyer_order->get_error_code();
		if ( $errcode === 404 ) {
			$pending = true;
		} else {
			return;
		}
	}

	if ( $pending ) {
		$ledyer_session = ledyer()->api->get_order_session($payment_id);
		if ( is_wp_error($ledyer_session) ) {
			\Ledyer\Logger::log( 'Could not get ledyer session ' . $payment_id  );
			return;
		}
		if ($ledyer_session['state'] === 'pendingSca') {
			update_post_meta( $order_id, 'ledyerpayment_type', $ledyer_session['paymentMethod']['type'] );
			update_post_meta( $order_id, 'ledyer_payment_method', $ledyer_session['paymentMethod']['provider'] );
			$note = sprintf( __( 'New session created in Ledyer with Payment ID %1$s. Payment type - %2$s. Awaiting signature.', 
				'ledyer-checkout-for-woocommerce' ), $session_id, $ledyer_session['paymentMethod']['type'] );
			$order->update_status('on-hold', $note);
		} else {
			\Ledyer\Logger::log( 'Unknown ledyer pending state ' . $payment_id );
			return;
		}
	} else {
		do_action( 'ledyer_process_payment', $order_id, $ledyer_order );
		update_post_meta( $order_id, 'ledyerpayment_type', $ledyer_order['paymentMethod']['type'] );
		update_post_meta( $order_id, 'ledyer_payment_method', $ledyer_order['paymentMethod']['provider'] );
		update_post_meta( $order_id, '_ledyer_date_paid', gmdate( 'Y-m-d H:i:s' ) );
		$order->add_order_note( sprintf( __( 'New payment created in Ledyer with Payment ID %1$s. Payment type - %2$s.', 
			'ledyer-checkout-for-woocommerce' ), $payment_id, $ledyer_order['paymentMethod']['type'] ) );

		$ledyer_order_status = $ledyer_order['status'] ?? [];
		$ledyer_order_events = $ledyer_order['events'] ?? [];
		$ledyer_order_event_types = array_column($ledyer_order_events, 'type');

		$awaitingPayment = in_array(LedyerStatus::uncaptured, $ledyer_order_status) && 
			!in_array(LedyerEventType::readyForCapture, $ledyer_order_event_types);
		$paymentComplete = in_array(LedyerStatus::uncaptured, $ledyer_order_status) && 
			in_array(LedyerEventType::readyForCapture, $ledyer_order_event_types);

		if ($awaitingPayment && !$order->has_status(array('on-hold'))) {
			$order->update_status('on-hold', 'Awaiting payment.');
		} else if ($paymentComplete) {
			$order->payment_complete($payment_id);
		}

		$response = ledyer()->api->acknowledge_order( $payment_id );
		if( is_wp_error( $response ) ) {
			\Ledyer\Logger::log( 'Couldn\'t acknowledge order ' . $payment_id  );
		}
		$ledyer_update_order = ledyer()->api->update_order_reference( $payment_id, array( 'reference' => strval( $order->ID ) ) );
		if ( is_wp_error( $ledyer_update_order ) ) {
			\Ledyer\Logger::log( 'Couldn\'t set merchant reference ' . $order->ID  );
		}
	}

}


function wc_ledyer_cart_redirect() {
	$url = add_query_arg(
		array(
			'lco-order' => 'error',
			'reason'    => base64_encode( __( 'Failed to load Ledyer Checkout template file.', 'ledyer-checkout-for-woocommerce' ) ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		),
		wc_get_cart_url()
	);


	wp_safe_redirect( $url );
	exit;
}
