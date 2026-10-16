<?php
/**
 * Capture WooCommerce cart contents.
 *
 * @package OtterMail
 */

namespace OtterMail\Cart;

use OtterMail\Core\Settings;
use OtterMail\Automations\AutomationService;

defined( 'ABSPATH' ) || exit;

class CartCapture {

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
		add_action( 'woocommerce_cart_updated', array( $this, 'sync' ) );
		add_action( 'wp_ajax_ottermail_capture_email', array( $this, 'ajax_email' ) );
		add_action( 'wp_ajax_nopriv_ottermail_capture_email', array( $this, 'ajax_email' ) );
	}

	/**
	 * Persist current cart if capture is enabled.
	 */
	public function sync() {
		if ( ! Settings::get( 'cart_capture_enabled' ) ) {
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$cart = WC()->cart;
		if ( $cart->is_empty() ) {
			return;
		}

		$payload = $this->snapshot();
		if ( empty( $payload['items'] ) ) {
			return;
		}

		$session = $this->session_key();
		$user_id = get_current_user_id();
		$email   = $this->current_email();
		$name    = $this->current_first_name();

		$existing = $this->repo->find_open( $session, $user_id, $email );
		$data     = array(
			'session_key'   => $session,
			'customer_id'   => $user_id ? $user_id : null,
			'email'         => $email,
			'first_name'    => $name,
			'cart_hash'     => md5( wp_json_encode( $payload ) ),
			'cart_contents' => array( 'items' => $payload['items'] ),
			'cart_total'    => $payload['total'],
			'currency'      => $payload['currency'],
		);

		if ( $existing ) {
			$was_abandoned = in_array( $existing->status, array( 'abandoned', 'checkout_abandoned' ), true );
			$update = array(
				'session_key'      => $session,
				'cart_hash'        => $data['cart_hash'],
				'cart_contents'    => wp_json_encode( $data['cart_contents'] ),
				'cart_total'       => $payload['total'],
				'currency'         => $payload['currency'],
				'last_activity_at' => current_time( 'mysql' ),
				'status'           => in_array( $existing->status, array( 'converted', 'expired' ), true ) ? $existing->status : 'active',
			);
			if ( $email ) {
				$update['email'] = strtolower( sanitize_email( $email ) );
			}
			if ( $name ) {
				$update['first_name'] = sanitize_text_field( $name );
			}
			if ( $user_id ) {
				$update['customer_id'] = $user_id;
			}
			$this->repo->update( $existing->id, $update );
			if ( $was_abandoned ) {
				( new AutomationService() )->cancel_for_cart( (int) $existing->id );
			}
			return;
		}

		$this->repo->insert( $data );
	}

	/**
	 * Associate a guest email with the current cart.
	 */
	public function ajax_email() {
		check_ajax_referer( 'ottermail_capture_email', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name  = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			wp_send_json_success( array( 'ok' => true ) );
		}

		$session  = $this->session_key();
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => 'session_required' ), 400 );
		}

		$customer_email = WC()->customer ? strtolower( sanitize_email( WC()->customer->get_billing_email() ) ) : '';
		if ( $customer_email && ! hash_equals( $customer_email, strtolower( $email ) ) ) {
			wp_send_json_error( array( 'message' => 'email_mismatch' ), 400 );
		}

		$user_id  = get_current_user_id();
		$existing = $this->repo->find_open( $session, $user_id, $email );
		$email_changed = ! $existing || strtolower( (string) $existing->email ) !== strtolower( $email );
		if ( $email_changed && ! $this->capture_change_allowed( $session ) ) {
			wp_send_json_error( array( 'message' => 'rate_limited' ), 429 );
		}

		if ( $existing ) {
			$update = array(
					'email'            => strtolower( $email ),
					'first_name'       => $name,
					'last_activity_at' => current_time( 'mysql' ),
				);
			if ( empty( $existing->checkout_started_at ) ) {
				$update['checkout_started_at'] = current_time( 'mysql' );
			}
			$this->repo->update( $existing->id, $update );
		} else {
			$this->sync();
			$existing = $this->repo->find_open( $session, $user_id, '' );
			if ( $existing ) {
				$this->repo->update(
					$existing->id,
					array(
						'email'               => strtolower( $email ),
						'first_name'          => $name,
						'checkout_started_at' => current_time( 'mysql' ),
					)
				);
			}
		}

		wp_send_json_success( array( 'ok' => true ) );
	}

	/**
	 * Limit distinct email changes per checkout session to reduce abuse of the
	 * public capture endpoint without affecting normal checkout refreshes.
	 *
	 * @param string $session Session key.
	 * @return bool
	 */
	private function capture_change_allowed( $session ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$key = 'ottermail_capture_' . substr(
			hash_hmac( 'sha256', $session . '|' . $ip, wp_salt( 'nonce' ) ),
			0,
			32
		);
		$count = (int) get_transient( $key );
		if ( $count >= 5 ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * @return array
	 */
	private function snapshot() {
		$items = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product ) {
				continue;
			}
			$image = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
			$items[] = array(
				'product_id'   => isset( $item['product_id'] ) ? (int) $item['product_id'] : 0,
				'variation_id' => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
				'quantity'     => isset( $item['quantity'] ) ? (int) $item['quantity'] : 1,
				'name'         => $product->get_name(),
				'line_total'   => html_entity_decode( wp_strip_all_tags( wc_price( $item['line_total'] ) ) ),
				'image'        => $image ? $image : '',
			);
		}

		return array(
			'items'    => $items,
			'total'    => (float) WC()->cart->get_total( 'edit' ),
			'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
		);
	}

	/**
	 * @return string
	 */
	private function session_key() {
		if ( WC()->session && method_exists( WC()->session, 'get_customer_id' ) ) {
			return (string) WC()->session->get_customer_id();
		}

		return '';
	}

	/**
	 * @return string
	 */
	private function current_email() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			return $user && $user->user_email ? $user->user_email : '';
		}

		if ( WC()->customer && WC()->customer->get_billing_email() ) {
			return WC()->customer->get_billing_email();
		}

		return '';
	}

	/**
	 * @return string
	 */
	private function current_first_name() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			return $user ? $user->first_name : '';
		}

		if ( WC()->customer ) {
			return (string) WC()->customer->get_billing_first_name();
		}

		return '';
	}
}
