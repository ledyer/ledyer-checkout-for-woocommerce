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
 * @return array|false
 */
function lco_create_or_update_order() {
	// Need to calculate these here, because WooCommerce hasn't done it yet.
	WC()->cart->calculate_fees();
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();

	$settings_match = lco_session_settings_match( WC()->session->get( 'lco_wc_settings' ) );
	if ( WC()->session->get( 'lco_wc_order_id' ) && ! $settings_match ) {
		\Ledyer\Logger::info( 'Ledyer settings changed since the session was created, creating a new session' );
	}

	if ( WC()->session->get( 'lco_wc_order_id' ) && $settings_match ) {

		$ledyer_order_id   = WC()->session->get( 'lco_wc_order_id' );
		$ledyer_session_id = WC()->session->get( 'lco_wc_session_id' );
		$data              = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_updated_cart_data();
		$ledyer_order      = ledyer()->api->update_order_session( $ledyer_order_id, $data );

		if ( ! $ledyer_order || ( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) || $ledyer_order['orderId'] !== $ledyer_order_id || $ledyer_order['sessionId'] !== $ledyer_session_id ) {
			if ( is_array( $ledyer_order ) ) {
				\Ledyer\Logger::warning(
					'Ledyer session IDs changed after update, creating a new session',
					array(
						'returned_session_id' => $ledyer_order['sessionId'] ?? '',
						'returned_order_id'   => $ledyer_order['orderId'] ?? '',
					)
				);
			} else {
				\Ledyer\Logger::warning( 'Could not update the Ledyer session, creating a new session', array( 'error' => lco_get_log_error( $ledyer_order ) ) );
			}

			// If update order failed try to create new order.
			$data         = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_cart_data();
			$ledyer_order = ledyer()->api->create_order_session( $data );
			if ( ! $ledyer_order || ( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) ) {
				// If failed then bail.
				\Ledyer\Logger::error( 'Could not create a new Ledyer session', array( 'error' => lco_get_log_error( $ledyer_order ) ) );
				return false;
			}
			WC()->session->set( 'lco_wc_session_id', $ledyer_order['sessionId'] );
			WC()->session->set( 'lco_wc_order_id', $ledyer_order['orderId'] );
			lco_log_session_created( $ledyer_order, $ledyer_session_id );
			WC()->session->set(
				'lco_wc_settings',
				array(
					'allow_custom_shipping'         => ledyer()->get_setting( 'allow_custom_shipping' ),
					'show_shipping_address_contact' => ledyer()->get_setting( 'show_shipping_address_contact' ),
					'terms_url'                     => ledyer()->get_setting( 'terms_url' ),
					'privacy_url'                   => ledyer()->get_setting( 'privacy_url' ),
				)
			);

			return $ledyer_order;
		}
		return $ledyer_order;
	} else {
		// Create new order, since we don't have one.
		$data         = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_cart_data();
		$ledyer_order = ledyer()->api->create_order_session( $data );

		if ( ! $ledyer_order || ( is_object( $ledyer_order ) && is_wp_error( $ledyer_order ) ) ) {
			\Ledyer\Logger::error( 'Could not create a Ledyer session', array( 'error' => lco_get_log_error( $ledyer_order ) ) );
			return false;
		}

		WC()->session->set( 'lco_wc_session_id', $ledyer_order['sessionId'] );
		WC()->session->set( 'lco_wc_order_id', $ledyer_order['orderId'] );
		lco_log_session_created( $ledyer_order );
		WC()->session->set(
			'lco_wc_settings',
			array(
				'allow_custom_shipping'         => ledyer()->get_setting( 'allow_custom_shipping' ),
				'show_shipping_address_contact' => ledyer()->get_setting( 'show_shipping_address_contact' ),
				'terms_url'                     => ledyer()->get_setting( 'terms_url' ),
				'privacy_url'                   => ledyer()->get_setting( 'privacy_url' ),
			)
		);

		return $ledyer_order;
	}
}
/**
 * Checks if the Ledyer settings stored with the session still match the current settings.
 *
 * @param array|null $old_ledyer_settings The settings stored in the WooCommerce session.
 *
 * @return bool
 */
function lco_session_settings_match( $old_ledyer_settings ) {
	$old_ledyer_settings = is_array( $old_ledyer_settings ) ? $old_ledyer_settings : array();

	foreach ( array( 'allow_custom_shipping', 'show_shipping_address_contact', 'terms_url', 'privacy_url' ) as $key ) {
		if ( ledyer()->get_setting( $key ) !== ( $old_ledyer_settings[ $key ] ?? null ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Gets a loggable error message from a failed API response.
 *
 * @param mixed $response The API response.
 *
 * @return string
 */
function lco_get_log_error( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response->get_error_code() . ': ' . $response->get_error_message();
	}

	return empty( $response ) ? 'Empty response' : 'Unexpected response';
}

/**
 * Logs that a new Ledyer session was created and adds its IDs to the log context.
 *
 * @param array  $ledyer_order        The Ledyer session response.
 * @param string $previous_session_id The previous Ledyer session ID, if any.
 *
 * @return void
 */
function lco_log_session_created( $ledyer_order, $previous_session_id = '' ) {
	\Ledyer\Logger::set_context(
		array(
			'ledyer_session_id' => $ledyer_order['sessionId'],
			'ledyer_order_id'   => $ledyer_order['orderId'],
		)
	);

	$data = array();
	if ( ! empty( $previous_session_id ) ) {
		$data['previous_session_id'] = $previous_session_id;
	}

	\Ledyer\Logger::info( 'Ledyer session created', $data );
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
 * Unsets the sessions used by the plugin.
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
 * Adds the extra checkout field div to the checkout page.
 *
 * @return void
 */
function lco_wc_add_extra_checkout_fields() {
	do_action( 'lco_wc_before_extra_fields' );
	?>
	<div id="lco-extra-checkout-fields">
	</div>
	<?php
	do_action( 'lco_wc_after_extra_fields' );
}

/**
 * Redirects to cart with error message if checkout template fails to load.
 *
 * @return void
 */
function wc_ledyer_cart_redirect() {
	\Ledyer\Logger::error( 'Failed to load the Ledyer checkout, redirecting the customer to the cart' );
	$url = add_query_arg(
		array(
			'lco-order' => 'error',
			'reason'    => base64_encode( __( 'Failed to load Ledyer Checkout template file.', 'ledyer-checkout-for-woocommerce' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		),
		wc_get_cart_url()
	);

	wp_safe_redirect( $url );
	exit;
}
