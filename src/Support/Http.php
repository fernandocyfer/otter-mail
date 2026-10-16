<?php
/**
 * HTTP / request helpers.
 *
 * @package OtterMail
 */

namespace OtterMail\Support;

defined( 'ABSPATH' ) || exit;

class Http {

	/**
	 * Redirect with an admin notice query flag.
	 *
	 * @param string $url  URL.
	 * @param string $flag Notice key.
	 */
	public static function redirect( $url, $flag = '' ) {
		if ( $flag ) {
			$url = add_query_arg( 'ottermail_notice', rawurlencode( $flag ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Current admin page URL.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function admin_url( $page, array $args = array() ) {
		$args['page'] = $page;

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Positive integer from request.
	 *
	 * @param string $key     Key.
	 * @param string $method  get|post|request.
	 * @return int
	 */
	public static function int( $key, $method = 'request' ) {
		$src = self::source( $method );
		if ( ! isset( $src[ $key ] ) ) {
			return 0;
		}

		return absint( wp_unslash( $src[ $key ] ) );
	}

	/**
	 * Sanitized text from request.
	 *
	 * @param string $key    Key.
	 * @param string $method Method.
	 * @return string
	 */
	public static function text( $key, $method = 'request' ) {
		$src = self::source( $method );
		if ( ! isset( $src[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $src[ $key ] ) );
	}

	/**
	 * @param string $method Method.
	 * @return array
	 */
	private static function source( $method ) {
		switch ( $method ) {
			case 'get':
				return $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			case 'post':
				return $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			default:
				return $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
}
