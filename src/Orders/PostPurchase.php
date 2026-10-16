<?php
/**
 * Start post-purchase automations for paid WooCommerce orders.
 *
 * @package OtterMail
 */

namespace OtterMail\Orders;

use OtterMail\Automations\AutomationService;

defined( 'ABSPATH' ) || exit;

class PostPurchase {

	/**
	 * Register paid-order hooks. Processing covers the usual paid physical
	 * order, while completed covers stores that mark payment and fulfillment at
	 * the same time. The enrollment uniqueness makes the two hooks idempotent.
	 */
	public function register() {
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_paid_order' ), 30, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_paid_order' ), 30, 2 );
	}

	/**
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order WooCommerce order.
	 */
	public function on_paid_order( $order_id, $order = null ) {
		if ( ! $order instanceof \WC_Order && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( $order instanceof \WC_Order ) {
			( new AutomationService() )->enroll_order( $order );
		}
	}
}
