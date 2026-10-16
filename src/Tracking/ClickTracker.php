<?php
/**
 * Rewrite outgoing email links and record the click plus a later purchase.
 *
 * @package OtterMail
 */

namespace OtterMail\Tracking;

use OtterMail\Core\Settings;

defined( 'ABSPATH' ) || exit;

class ClickTracker {

	const COOKIE = 'ottermail_click';

	/**
	 * @var ClickRepository
	 */
	private $repo;

	public function __construct() {
		$this->repo = new ClickRepository();
	}

	/**
	 * Register the public redirect and the purchase hook.
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'handle' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 30, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_status' ), 30, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_status' ), 30, 2 );
	}

	/**
	 * Replace http(s) links in a message that is about to be sent.
	 *
	 * @param string $html    Email HTML.
	 * @param array  $context email, source_type, source_id.
	 * @return array{html: string, ids: int[]}
	 */
	public function apply( $html, array $context ) {
		$ids  = array();
		$repo = $this->repo;
		$html = preg_replace_callback(
			'/href\s*=\s*(["\'])([^"\']+)\1/i',
			static function ( $matches ) use ( $context, &$ids, $repo ) {
				$absolute = ClickTracker::absolute_url( $matches[2] );
				if ( ! $absolute || false !== strpos( $absolute, 'ottermail_click=' ) ) {
					return $matches[0];
				}

				$tracked = $repo->create(
					array(
						'email'       => isset( $context['email'] ) ? $context['email'] : '',
						'source_type' => isset( $context['source_type'] ) ? $context['source_type'] : '',
						'source_id'   => isset( $context['source_id'] ) ? $context['source_id'] : 0,
						'url'         => $absolute,
					)
				);
				if ( ! $tracked ) {
					return $matches[0];
				}

				$ids[] = (int) $tracked['id'];

				return 'href=' . $matches[1] . esc_url( self::email_href( $tracked['token'], $absolute ) ) . $matches[1];
			},
			(string) $html
		);

		return array(
			'html' => is_string( $html ) ? $html : '',
			'ids'  => $ids,
		);
	}

	/**
	 * Forget links when wp_mail refused the message.
	 *
	 * @param int[] $ids Row IDs returned by apply().
	 */
	public function discard( array $ids ) {
		$this->repo->delete_unclicked( $ids );
	}

	/**
	 * Count the click, remember it for a later order, then continue to the destination.
	 */
	public function handle() {
		if ( empty( $_GET['ottermail_click'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['ottermail_click'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$row   = $this->repo->find_by_token( $token );
		if ( ! $row ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$this->repo->record_click( (int) $row->id );

		if ( ! $this->is_unsubscribe( $row->url ) ) {
			$this->remember( $token );
		}

		if ( $this->is_unsubscribe( $row->url ) || false !== strpos( (string) $row->url, 'ottermail_restore=' ) ) {
			$this->redirect( remove_query_arg( 'ottermail_click', $row->url ) );
		}

		$this->redirect( remove_query_arg( 'ottermail_click', Settings::email_destination_url() ) );
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public function on_payment_complete( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( $order instanceof \WC_Order ) {
			$this->attribute_order( $order );
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
			$this->attribute_order( $order );
		}
	}

	/**
	 * Count the paid order against the last store link clicked in this browser.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function attribute_order( \WC_Order $order ) {
		if ( ! $order->is_paid() || $order->get_meta( '_ottermail_click_id', true ) ) {
			return;
		}

		$token = $this->remembered_token();
		$row   = $token ? $this->repo->find_by_token( $token ) : null;
		if ( ! $row || empty( $row->clicked_at ) || $this->is_unsubscribe( $row->url ) ) {
			return;
		}

		$clicked = strtotime( $row->clicked_at );
		$days    = max( 1, (int) Settings::get( 'cart_restore_ttl_days', 7 ) );
		if ( ! $clicked || ( time() - $clicked ) > ( $days * DAY_IN_SECONDS ) ) {
			return;
		}

		if ( ! $this->repo->attribute_order( (int) $row->id, $order->get_id(), (float) $order->get_total() ) ) {
			return;
		}

		$order->update_meta_data( '_ottermail_click_id', (int) $row->id );
		$order->save_meta_data();
	}

	/**
	 * @param string $token Raw click token.
	 */
	private function remember( $token ) {
		$days    = max( 1, (int) Settings::get( 'cart_restore_ttl_days', 7 ) );
		$expires = time() + ( $days * DAY_IN_SECONDS );
		$secure  = is_ssl();

		setcookie( self::COOKIE, $token, $expires, '/', COOKIE_DOMAIN, $secure, true );
		$_COOKIE[ self::COOKIE ] = $token;

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::COOKIE, $token );
		}
	}

	/**
	 * @return string
	 */
	private function remembered_token() {
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			return sanitize_text_field( (string) WC()->session->get( self::COOKIE ) );
		}

		return '';
	}

	/**
	 * @param string $url Destination stored when the email was sent.
	 */
	private function redirect( $url ) {
		$parts = wp_parse_url( $url );
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Address written into the email. On this store it is the cart, or the saved URL, with the click token.
	 *
	 * @param string $token    Raw click token.
	 * @param string $original Original href, kept for restore and unsubscribe.
	 * @return string
	 */
	public static function email_href( $token, $original ) {
		$target = ( false !== strpos( (string) $original, 'ottermail_unsubscribe' ) )
			? $original
			: Settings::email_destination_url();

		$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host = wp_parse_url( $target, PHP_URL_HOST );
		if ( ! $home || ! $host || strtolower( $home ) !== strtolower( $host ) ) {
			$target = home_url( '/' );
		}

		return add_query_arg( 'ottermail_click', rawurlencode( $token ), $target );
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_unsubscribe( $url ) {
		return false !== strpos( (string) $url, 'ottermail_unsubscribe' );
	}

	/**
	 * http(s) destination, or a site path turned into one. mailto and anchors stay untouched.
	 *
	 * @param string $url Raw href.
	 * @return string
	 */
	public static function absolute_url( $url ) {
		$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES );
		if ( '' === $url || '#' === substr( $url, 0, 1 ) ) {
			return '';
		}
		if ( preg_match( '#^(mailto:|tel:|sms:|javascript:)#i', $url ) ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $url ) ) {
			return esc_url_raw( $url );
		}
		if ( '/' === substr( $url, 0, 1 ) ) {
			return esc_url_raw( home_url( $url ) );
		}

		return '';
	}
}
