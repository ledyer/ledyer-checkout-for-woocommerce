<?php
/**
 * Meta box
 *
 * Handles the functionality for the LCO meta box.
 *
 * @package Ledyer
 * @since   1.0.0
 */
namespace Ledyer\Admin;

\defined( 'ABSPATH' ) || die();

/**
 * Meta_Box class.
 *
 * Handles the meta box for LCO
 */
class Meta_Box {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Adds meta box to the side of a LCO order.
	 *
	 * @param string $post_type The WordPress post type.
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {
		if ( 'shop_order' === $post_type ) {
			$order_id = get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( in_array( $order->get_payment_method(), array( 'lco', 'ledyer_payments' ), true ) ) {
				add_meta_box( 'lco_meta_box', __( 'Ledyer Order Info', 'ledyer-checkout-for-woocommerce' ), array( $this, 'meta_box_content' ), 'shop_order', 'side', 'core' );
			}
		}
	}

	/**
	 * Adds content for the Ledyer meta box.
	 *
	 * @return void
	 */
	public function meta_box_content() {
		$order_id = get_the_ID();
		$order    = wc_get_order( $order_id );

		// False if automatic settings are enabled, true if not. If true then show the option.
		if ( ! empty( get_post_meta( $order_id, '_transaction_id', true ) ) && ! empty( get_post_meta( $order_id, '_wc_ledyer_order_id', true ) ) ) {

			// TODO: if ledyer_pending or status on-hold: get info from session
			// Then: print_standard_content but with session info
			// Below request will fail if no order has been created yet (fetch from get_order_session instead)
			$ledyer_order = ledyer()->api->get_order( get_post_meta( $order_id, '_wc_ledyer_order_id', true ) );

			if ( is_wp_error( $ledyer_order ) ) {
				$this->print_error_content( __( 'Failed to retrieve the order from Ledyer. The customer may not have been able to complete the checkout flow', 'ledyer-checkout-for-woocommerce' ) );
				return;
			}
		}
		$this->print_standard_content( $ledyer_order );
	}

	/**
	 * Prints the standard content for the Metabox
	 *
	 * @param object $ledyer_order The Ledyer order object.
	 * @return void
	 */
	public function print_standard_content( $ledyer_order ) {
		// Show ledyer order information.
		?>
        <div class="lco-meta-box-content">
			<?php if ( $ledyer_order ) { ?>
				<?php
				if ( '' !== ledyer()->get_setting('testmode') ) {
					$environment = 'yes' === ledyer()->get_setting('testmode') ? 'Sandbox' : 'Production';
					?>
                    <strong><?php esc_html_e( 'Ledyer Environment: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong><?php echo esc_html( $environment ); ?><br/>
				<?php } ?>
                <strong><?php esc_html_e( 'Company id: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['companyId'] ); ?><br/>
                <strong><?php esc_html_e( 'Order id: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['id'] ); ?><br/>
                <strong><?php esc_html_e( 'Ledyer id: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['orderReference'] ); ?><br/>
				<strong><?php esc_html_e( 'Payment method: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['paymentMethod']['type'] ); ?><br/>
				<strong><?php esc_html_e( 'Ledyer status: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( implode(', ', $ledyer_order['status']) ); ?><br/>


				<?php if( ! empty( $ledyer_order['customer']['firstName'] ) || ! empty( $ledyer_order['customer']['lastName'] ) ) : ?>
                    <strong><?php esc_html_e( 'Order setter: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['firstName'] . ' ' . $ledyer_order['customer']['lastName'] ); ?><br/>
				<?php endif; ?>
				<?php if( $ledyer_order['customer']['reference1'] ) : ?>
                    <strong><?php esc_html_e( 'Customer referens 1: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['reference1'] ); ?><br/>
				<?php endif; ?>
				<?php if( $ledyer_order['customer']['reference2'] ) : ?>
                    <strong><?php esc_html_e( 'Customer referens 2: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['reference2'] ); ?><br/>
				<?php endif; ?>
			<?php } ?>
        </div>
		<?php
	}

	/**
	 * Prints an error message for the Ledyer Metabox
	 *
	 * @param string $message The error message.
	 * @return void
	 */
	public function print_error_content( $message ) {
		?>
        <div class="lco-meta-box-content">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
		<?php
	}
}
