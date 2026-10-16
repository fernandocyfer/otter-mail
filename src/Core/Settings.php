<?php
/**
 * Plugin settings.
 *
 * @package OtterMail
 */

namespace OtterMail\Core;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'ottermail_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'from_name'                 => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'from_email'                => get_option( 'admin_email' ),
			'reply_to'                  => get_option( 'admin_email' ),
			'batch_size'                => 50,
			'batch_interval_seconds'    => 60,
			'emails_per_hour'           => 500,
			'cart_capture_enabled'      => 1,
			'cart_abandon_minutes'      => 60,
			'cart_restore_ttl_days'     => 7,
			'cart_restore_redirect'     => 'cart',
			'email_link_url'            => '',
			'email_width'               => 600,
			'logo_url'                  => '',
			'footer_text'               => '',
			'delete_data_on_uninstall'  => 0,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $default;
	}

	/**
	 * Where an email link sends the customer.
	 *
	 * A saved address wins. Otherwise the store cart, or the checkout when that
	 * destination is selected.
	 *
	 * @return string
	 */
	public static function email_destination_url() {
		$custom = self::get( 'email_link_url', '' );
		if ( is_string( $custom ) && $custom && wp_http_validate_url( $custom ) ) {
			return $custom;
		}

		if ( 'checkout' === self::get( 'cart_restore_redirect', 'cart' ) && function_exists( 'wc_get_checkout_url' ) ) {
			return wc_get_checkout_url();
		}

		if ( function_exists( 'wc_get_cart_url' ) ) {
			return wc_get_cart_url();
		}

		return home_url( '/' );
	}

	/**
	 * Persist settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function save( array $input ) {
		$clean = self::all();

		if ( isset( $input['from_name'] ) ) {
			$clean['from_name'] = sanitize_text_field( $input['from_name'] );
		}
		if ( isset( $input['from_email'] ) ) {
			$clean['from_email'] = sanitize_email( $input['from_email'] );
		}
		if ( isset( $input['reply_to'] ) ) {
			$clean['reply_to'] = sanitize_email( $input['reply_to'] );
		}
		if ( isset( $input['batch_size'] ) ) {
			$clean['batch_size'] = max( 1, min( 200, absint( $input['batch_size'] ) ) );
		}
		if ( isset( $input['batch_interval_seconds'] ) ) {
			$clean['batch_interval_seconds'] = max( 10, min( HOUR_IN_SECONDS, absint( $input['batch_interval_seconds'] ) ) );
		}
		if ( isset( $input['emails_per_hour'] ) ) {
			$clean['emails_per_hour'] = max( 1, min( 100000, absint( $input['emails_per_hour'] ) ) );
		}

		$clean['cart_capture_enabled'] = empty( $input['cart_capture_enabled'] ) ? 0 : 1;

		if ( isset( $input['cart_abandon_minutes'] ) ) {
			$clean['cart_abandon_minutes'] = max( 5, absint( $input['cart_abandon_minutes'] ) );
		}
		if ( isset( $input['cart_restore_ttl_days'] ) ) {
			$clean['cart_restore_ttl_days'] = max( 1, absint( $input['cart_restore_ttl_days'] ) );
		}
		if ( isset( $input['cart_restore_redirect'] ) && in_array( $input['cart_restore_redirect'], array( 'cart', 'checkout' ), true ) ) {
			$clean['cart_restore_redirect'] = $input['cart_restore_redirect'];
		}
		if ( isset( $input['email_link_url'] ) ) {
			$clean['email_link_url'] = esc_url_raw( $input['email_link_url'] );
		}
		if ( isset( $input['email_width'] ) ) {
			$clean['email_width'] = max( 320, min( 800, absint( $input['email_width'] ) ) );
		}
		if ( isset( $input['logo_url'] ) ) {
			$clean['logo_url'] = esc_url_raw( $input['logo_url'] );
		}
		if ( isset( $input['footer_text'] ) ) {
			$clean['footer_text'] = wp_kses_post( $input['footer_text'] );
		}

		$clean['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		update_option( self::OPTION, $clean, false );

		return $clean;
	}
}
