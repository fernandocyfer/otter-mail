<?php
/**
 * Mark carts abandoned and detect conversion.
 *
 * @package OtterMail
 */

namespace OtterMail\Cart;

use OtterMail\Automations\AutomationRepository;
use OtterMail\Automations\AutomationService;
use OtterMail\Support\Logger;
use OtterMail\Support\Scheduler;

defined( 'ABSPATH' ) || exit;

class CartRecovery {

	/**
	 * @var CartRepository
	 */
	private $repo;

	public function __construct() {
		$this->repo = new CartRepository();
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( Scheduler::HOOK_CHECK_CARTS, array( $this, 'check_abandoned' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_created' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_store_api_checkout' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_status' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_status' ), 20, 2 );
	}

	/**
	 * Recurring job: active carts past the inactivity threshold.
	 */
	public function check_abandoned() {
		$this->reconcile_paid_carts();

		$automations       = new AutomationRepository();
		$cart_minutes      = $automations->abandon_delay_minutes( 'cart_abandoned' );
		$checkout_minutes  = $automations->abandon_delay_minutes( 'checkout_abandoned' );
		$due               = $this->repo->due_for_abandonment( min( $cart_minutes, $checkout_minutes ) );
		$auto              = new AutomationService();

		foreach ( $due as $cart ) {
			$paid = $this->matching_paid_order( $cart );
			if ( $paid ) {
				$this->settle_paid_cart( $cart, $paid );
				continue;
			}

			$is_checkout = ! empty( $cart->checkout_started_at );
			$needed      = $is_checkout ? $checkout_minutes : $cart_minutes;
			if ( $this->idle_minutes( $cart ) < $needed ) {
				continue;
			}
			$this->repo->update(
				$cart->id,
				array(
					'status'       => $is_checkout ? 'checkout_abandoned' : 'abandoned',
					'abandoned_at' => current_time( 'mysql' ),
				)
			);

			Logger::log( $is_checkout ? 'checkout_abandoned' : 'cart_abandoned', $is_checkout ? __( 'Checkout marcado como abandonado.', 'ottermail' ) : __( 'Carrinho marcado como abandonado.', 'ottermail' ), 'cart', (int) $cart->id );
			do_action( $is_checkout ? 'ottermail_checkout_marked_abandoned' : 'ottermail_cart_marked_abandoned', $cart );

			$fresh = $this->repo->get( $cart->id );
			if ( $fresh ) {
				if ( $is_checkout ) {
					$auto->cancel_for_cart( (int) $fresh->id );
					$auto->enroll_checkout( $fresh );
				} else {
					$auto->enroll_cart( $fresh );
				}
			}
		}
	}

	/**
	 * @param int       $order_id Order ID.
	 * @param array     $data     Posted data.
	 * @param \WC_Order $order    Order.
	 */
	public function on_order_created( $order_id, $data, $order ) {
		unset( $data );
		if ( ! $order instanceof \WC_Order && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof \WC_Order ) {
			$this->associate_order( $order );

			// Some gateways complete payment before this checkout hook fires.
			if ( $order->is_paid() ) {
				$this->convert_from_order( $order );
			}
		}
	}

	/**
	 * Checkout block / Store API creates the order without the classic checkout hook.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function on_store_api_checkout( $order ) {
		if ( $order instanceof \WC_Order ) {
			$this->on_order_created( $order->get_id(), array(), $order );
		}
	}

	/**
	 * Payment webhooks often have no cart session. The order id saved at checkout is enough.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_payment_complete( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( $order instanceof \WC_Order ) {
			$this->associate_order( $order );
			$this->convert_from_order( $order );
		}
	}

	/**
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order    Order.
	 */
	public function on_order_status( $order_id, $order = null ) {
		if ( ! $order instanceof \WC_Order && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof \WC_Order && $order->is_paid() ) {
			$this->convert_from_order( $order );
		}
	}

	/**
	 * Persist the captured cart ID on the order while the checkout session is
	 * still available. Creating an unpaid order does not count as conversion.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function associate_order( \WC_Order $order ) {
		$linked = $this->repo->get_by_order_id( $order->get_id() );
		if ( $linked && 'converted' === $linked->status ) {
			return;
		}

		$cart_id = 0;
		if ( function_exists( 'WC' ) && WC()->session ) {
			$cart_id = (int) WC()->session->get( 'ottermail_restore_cart_id' );
		}

		$cart = $cart_id ? $this->repo->get( $cart_id ) : null;
		if ( ! $cart || 'converted' === $cart->status ) {
			$cart = $linked;
		}
		if ( ! $cart || 'converted' === $cart->status ) {
			$cart = $this->cart_for_order( $order );
		}

		if ( ! $cart || 'converted' === $cart->status ) {
			return;
		}

		$update = array(
			'order_id'         => $order->get_id(),
			'last_activity_at' => current_time( 'mysql' ),
		);
		if ( $order->get_billing_email() && empty( $cart->email ) ) {
			$update['email'] = strtolower( sanitize_email( $order->get_billing_email() ) );
		}
		$this->repo->update( $cart->id, $update );

		$order->update_meta_data( '_ottermail_cart_id', (int) $cart->id );
		$order->save_meta_data();
	}

	/**
	 * Link an order to a captured cart.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function convert_from_order( \WC_Order $order ) {
		if ( ! $order->is_paid() ) {
			return;
		}

		$linked = $this->repo->get_by_order_id( $order->get_id() );
		if ( $linked && 'converted' === $linked->status && $this->in_plugin_flow( $linked ) ) {
			return;
		}

		$cart_id = (int) $order->get_meta( '_ottermail_cart_id', true );
		if ( function_exists( 'WC' ) && WC()->session ) {
			$cart_id = $cart_id ? $cart_id : (int) WC()->session->get( 'ottermail_restore_cart_id' );
		}

		$cart = $cart_id ? $this->repo->get( $cart_id ) : null;
		if ( ! $cart || 'converted' === $cart->status ) {
			$cart = $linked;
		}
		if ( ! $cart || 'converted' === $cart->status ) {
			$cart = $this->cart_for_order( $order );
		}

		$this->settle_paid_cart( $cart, $order );
	}

	/**
	 * Mark a captured cart as a completed sale and stop recovery emails.
	 *
	 * @param object|null $cart  Cart row.
	 * @param \WC_Order   $order Paid order.
	 */
	private function convert_cart( $cart, \WC_Order $order ) {
		if ( ! $cart || 'converted' === $cart->status || ! $order->is_paid() ) {
			return;
		}

		$claimed = (int) $order->get_meta( '_ottermail_cart_id', true );
		if ( $claimed && $claimed !== (int) $cart->id ) {
			return;
		}

		$order->update_meta_data( '_ottermail_cart_id', (int) $cart->id );
		$order->save_meta_data();

		$this->repo->update(
			$cart->id,
			array(
				'status'       => 'converted',
				'converted_at' => current_time( 'mysql' ),
				'order_id'     => $order->get_id(),
			)
		);

		Logger::log( 'cart_converted', __( 'Carrinho convertido em pedido.', 'ottermail' ), 'cart', (int) $cart->id, array( 'order_id' => $order->get_id() ) );
		do_action( 'ottermail_cart_converted', $cart, $order );

		( new AutomationService() )->cancel_for_cart( (int) $cart->id );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'ottermail_restore_cart_id', null );
		}
	}

	/**
	 * A paid order only becomes a conversion after the cart entered recovery.
	 * A normal store purchase removes the captured cart instead.
	 *
	 * @param object|null $cart  Cart row.
	 * @param \WC_Order   $order Paid order.
	 */
	private function settle_paid_cart( $cart, \WC_Order $order ) {
		if ( ! $cart || ! $order->is_paid() ) {
			return;
		}

		if ( $this->in_plugin_flow( $cart ) ) {
			$this->convert_cart( $cart, $order );
			return;
		}

		$this->discard_cart( $cart, $order );
	}

	/**
	 * The customer bought through the store, before any recovery email or link.
	 *
	 * @param object    $cart  Cart row.
	 * @param \WC_Order $order Paid order.
	 */
	private function discard_cart( $cart, \WC_Order $order ) {
		$cart_id = (int) $cart->id;
		$auto    = new AutomationService();
		$auto->cancel_for_cart( $cart_id );
		( new AutomationRepository() )->delete_enrollments_for_cart( $cart_id );

		Logger::delete_for_object( 'cart', $cart_id );
		$this->repo->delete( $cart_id );

		$claimed = (int) $order->get_meta( '_ottermail_cart_id', true );
		if ( $claimed === $cart_id ) {
			$order->delete_meta_data( '_ottermail_cart_id' );
			$order->save();
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'ottermail_restore_cart_id', null );
		}
	}

	/**
	 * Recovery already started: the cart was abandoned or restored by the plugin.
	 *
	 * @param object $cart Cart row.
	 * @return bool
	 */
	private function in_plugin_flow( $cart ) {
		if ( in_array( $cart->status, array( 'abandoned', 'checkout_abandoned', 'recovered' ), true ) ) {
			return true;
		}

		return ! empty( $cart->abandoned_at ) || ! empty( $cart->recovered_at );
	}

	/**
	 * Recover carts the plugin already emailed, and drop normal store sales.
	 */
	private function reconcile_paid_carts() {
		foreach ( $this->repo->unconverted_carts( 100 ) as $cart ) {
			$order = $this->matching_paid_order( $cart );
			if ( $order ) {
				$this->settle_paid_cart( $cart, $order );
			}
		}

		foreach ( $this->repo->normal_sales( 100 ) as $cart ) {
			$order = ! empty( $cart->order_id ) && function_exists( 'wc_get_order' ) ? wc_get_order( $cart->order_id ) : null;
			if ( $order instanceof \WC_Order ) {
				$this->discard_cart( $cart, $order );
			} else {
				$this->repo->delete( $cart->id );
				Logger::delete_for_object( 'cart', (int) $cart->id );
			}
		}
	}

	/**
	 * Paid order that belongs to this cart, including sales the checkout hook missed.
	 *
	 * @param object $cart Cart row.
	 * @return \WC_Order|null
	 */
	private function matching_paid_order( $cart ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		if ( ! empty( $cart->order_id ) ) {
			$linked = wc_get_order( $cart->order_id );
			if ( $linked instanceof \WC_Order && $linked->is_paid() ) {
				return $linked;
			}
		}

		if ( empty( $cart->email ) || ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		$created = ! empty( $cart->created_at ) ? strtotime( $cart->created_at ) : 0;
		$orders  = wc_get_orders(
			array(
				'billing_email' => $cart->email,
				'status'        => function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' ),
				'limit'         => 10,
				'orderby'       => 'date',
				'order'         => 'DESC',
			)
		);

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order || ! $order->is_paid() ) {
				continue;
			}
			$claimed = (int) $order->get_meta( '_ottermail_cart_id', true );
			if ( $claimed && $claimed !== (int) $cart->id ) {
				continue;
			}
			$stamp = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
			if ( $created && $stamp && $stamp + HOUR_IN_SECONDS < $created ) {
				continue;
			}

			return $order;
		}

		return null;
	}

	/**
	 * Minutes since the latest cart or checkout activity.
	 *
	 * @param object $cart Cart row.
	 * @return int
	 */
	private function idle_minutes( $cart ) {
		$stamps = array();
		if ( ! empty( $cart->last_activity_at ) ) {
			$stamps[] = strtotime( $cart->last_activity_at );
		}
		if ( ! empty( $cart->checkout_started_at ) ) {
			$stamps[] = strtotime( $cart->checkout_started_at );
		}
		if ( ! $stamps ) {
			return 0;
		}

		return (int) floor( max( 0, current_time( 'timestamp' ) - max( $stamps ) ) / 60 );
	}

	/**
	 * Open cart for the customer who placed this order.
	 *
	 * @param \WC_Order $order Order.
	 * @return object|null
	 */
	private function cart_for_order( \WC_Order $order ) {
		$session_key = '';
		if ( function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'get_customer_id' ) ) {
			$session_key = (string) WC()->session->get_customer_id();
		}

		return $this->repo->find_for_checkout(
			$session_key,
			(int) $order->get_customer_id(),
			(string) $order->get_billing_email()
		);
	}
}
