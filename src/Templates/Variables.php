<?php
/**
 * Central template variable registry.
 *
 * @package OtterMail
 */

namespace OtterMail\Templates;

use OtterMail\Unsubscribe\UnsubscribeService;

defined( 'ABSPATH' ) || exit;

class Variables {

	/**
	 * Build a flat map of {{key}} => value for a context.
	 *
	 * @param array $context Context keys: customer, cart, order, coupon, campaign, template.
	 * @return array
	 */
	public function map( array $context ) {
		$customer = isset( $context['customer'] ) && is_array( $context['customer'] ) ? $context['customer'] : array();
		$cart     = isset( $context['cart'] ) && is_array( $context['cart'] ) ? $context['cart'] : array();
		$order    = isset( $context['order'] ) && is_array( $context['order'] ) ? $context['order'] : array();
		$coupon   = isset( $context['coupon'] ) ? (string) $context['coupon'] : '';

		$email = isset( $customer['email'] ) ? sanitize_email( $customer['email'] ) : '';
		$first = isset( $customer['first_name'] ) ? $customer['first_name'] : '';
		$last  = isset( $customer['last_name'] ) ? $customer['last_name'] : '';

		$unsub = new UnsubscribeService();

		$map = array(
			'customer.first_name' => $first ? esc_html( $first ) : __( 'cliente', 'ottermail' ),
			'customer.last_name'  => esc_html( $last ),
			'customer.email'      => esc_html( $email ),
			'store.name'          => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'store.url'           => esc_url( home_url( '/' ) ),
			'cart.total'          => isset( $cart['total_html'] ) ? $cart['total_html'] : '',
			'cart.items'          => isset( $cart['items_html'] ) ? $cart['items_html'] : '',
			'cart.restore_url'    => isset( $cart['restore_url'] ) ? esc_url( $cart['restore_url'] ) : '',
			'order.number'        => isset( $order['number'] ) ? esc_html( $order['number'] ) : '',
			'order.total'         => isset( $order['total_html'] ) ? $order['total_html'] : '',
			'order.items'         => isset( $order['items_html'] ) ? $order['items_html'] : '',
			'order.cross_sell'    => isset( $order['cross_sell_html'] ) ? $order['cross_sell_html'] : '',
			'coupon.code'         => esc_html( $coupon ),
			'unsubscribe.url'     => $email ? esc_url( $unsub->url_for( $email, 'marketing' ) ) : '',
		);

		/**
		 * Filter registered template variables.
		 *
		 * @param array $map     Flat key => value.
		 * @param array $context Original context.
		 */
		return apply_filters( 'ottermail_template_variables', $map, $context );
	}

	/**
	 * Replace {{tokens}} in a string.
	 *
	 * @param string $html    HTML.
	 * @param array  $context Context.
	 * @return string
	 */
	public function resolve( $html, array $context ) {
		$map = $this->map( $context );

		return (string) preg_replace_callback(
			'/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
			function ( $matches ) use ( $map ) {
				$key = strtolower( $matches[1] );
				return array_key_exists( $key, $map ) ? (string) $map[ $key ] : '';
			},
			(string) $html
		);
	}

	/**
	 * Sample context for tests and previews.
	 *
	 * @return array
	 */
	public static function sample_context() {
		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'R$';

		return array(
			'customer' => array(
				'first_name' => 'Ana',
				'last_name'  => 'Silva',
				'email'      => get_option( 'admin_email' ),
			),
			'cart'     => array(
				'total_html'  => esc_html( $currency . ' 349,90' ),
				'items_html'  => '<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;"><tr><td style="border-bottom:1px solid #eee;">Produto de exemplo</td><td align="right" style="border-bottom:1px solid #eee;">' . esc_html( $currency . ' 349,90' ) . '</td></tr></table>',
				'restore_url' => home_url( '/?ottermail_restore=preview' ),
			),
			'coupon'   => 'VOLTA10',
		);
	}
}
