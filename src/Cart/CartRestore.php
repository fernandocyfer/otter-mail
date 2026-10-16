<?php
/**
 * Restore a captured cart from a secure token.
 *
 * @package OtterMail
 */

namespace OtterMail\Cart;

use OtterMail\Core\Settings;
use OtterMail\Support\Logger;
use OtterMail\Support\Tokens;

defined( 'ABSPATH' ) || exit;

class CartRestore {

	/**
	 * @var CartRepository
	 */
	private $repo;

	public function __construct() {
		$this->repo = new CartRepository();
	}

	/**
	 * Register public restore handler.
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Restore URL for a cart row (requires raw token stored only in the link we send).
	 * We look up by hash, so callers that still have the raw token build the URL.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function url_from_token( $token ) {
		return add_query_arg( 'ottermail_restore', $token, home_url( '/' ) );
	}

	/**
	 * Restore URL for a persisted cart.
	 *
	 * @param int $cart_id Cart ID.
	 * @return string
	 */
	public static function url_for_cart( $cart_id ) {
		return self::url_from_token( Tokens::cart_token( $cart_id ) );
	}

	/**
	 * Handle ?ottermail_restore=TOKEN
	 */
	public function handle() {
		$token = isset( $_GET['ottermail_restore'] ) ? sanitize_text_field( wp_unslash( $_GET['ottermail_restore'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $token || 'preview' === $token ) {
			return;
		}

		$cart_id = Tokens::cart_id_from_token( $token );
		$cart    = $cart_id ? $this->repo->get( $cart_id ) : null;
		if ( ! $cart ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Link de recuperacao invalido.', 'ottermail' ), 'error' );
			}
			wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) );
			exit;
		}

		$ttl_days = (int) Settings::get( 'cart_restore_ttl_days', 7 );
		$base     = $cart->last_activity_at ? strtotime( $cart->last_activity_at ) : strtotime( $cart->created_at );
		if ( $base && ( time() - $base ) > ( $ttl_days * DAY_IN_SECONDS ) ) {
			$this->repo->update( $cart->id, array( 'status' => 'expired' ) );
			wc_add_notice( __( 'Este link de recuperacao expirou.', 'ottermail' ), 'error' );
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		if ( in_array( $cart->status, array( 'converted', 'expired' ), true ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		WC()->cart->empty_cart();
		$contents = json_decode( (string) $cart->cart_contents, true );
		$items    = ( isset( $contents['items'] ) && is_array( $contents['items'] ) ) ? $contents['items'] : array();

		foreach ( $items as $item ) {
			$product_id   = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
			$qty          = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;
			if ( ! $product_id ) {
				continue;
			}
			$product = wc_get_product( $variation_id ? $variation_id : $product_id );
			if ( ! $product ) {
				continue;
			}
			WC()->cart->add_to_cart( $product_id, $qty, $variation_id );
		}

		if ( WC()->session ) {
			WC()->session->set( 'ottermail_restore_cart_id', (int) $cart->id );
		}

		$this->repo->update(
			$cart->id,
			array(
				'status'       => 'recovered',
				'recovered_at' => current_time( 'mysql' ),
			)
		);

		Logger::log( 'cart_recovered', __( 'Carrinho restaurado pelo cliente.', 'ottermail' ), 'cart', (int) $cart->id );
		do_action( 'ottermail_cart_recovered', $cart );

		$url    = remove_query_arg( 'ottermail_click', Settings::email_destination_url() );
		$parts  = wp_parse_url( $url );
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( in_array( $scheme, array( 'http', 'https' ), true ) ) {
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) );
		exit;
	}
}
