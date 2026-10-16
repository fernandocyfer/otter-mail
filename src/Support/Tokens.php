<?php
/**
 * Cryptographic tokens (restore / unsubscribe).
 *
 * @package OtterMail
 */

namespace OtterMail\Support;

defined( 'ABSPATH' ) || exit;

class Tokens {

	/**
	 * Generate a URL-safe random token.
	 *
	 * @param int $bytes Entropy bytes.
	 * @return string
	 */
	public static function generate( $bytes = 32 ) {
		try {
			return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
		} catch ( \Exception $e ) {
			return wp_generate_password( 64, false, false );
		}
	}

	/**
	 * SHA-256 hash of a token (for storage).
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function hash( $token ) {
		return hash( 'sha256', (string) $token );
	}

	/**
	 * Signed restore token (id + HMAC). ID alone is never accepted.
	 *
	 * @param int $cart_id Cart ID.
	 * @return string
	 */
	public static function cart_token( $cart_id ) {
		$cart_id = absint( $cart_id );

		return $cart_id . '.' . hash_hmac( 'sha256', 'cart-restore|' . $cart_id, wp_salt( 'auth' ) );
	}

	/**
	 * Validate restore token and return cart ID.
	 *
	 * @param string $token Token.
	 * @return int
	 */
	public static function cart_id_from_token( $token ) {
		$parts = explode( '.', (string) $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return 0;
		}

		$cart_id = absint( $parts[0] );
		if ( ! $cart_id ) {
			return 0;
		}

		$expected = self::cart_token( $cart_id );

		return hash_equals( $expected, (string) $token ) ? $cart_id : 0;
	}

	/**
	 * HMAC for unsubscribe links (deterministic, no DB token).
	 *
	 * @param string $email Email.
	 * @param string $scope Scope.
	 * @return string
	 */
	public static function unsubscribe_hmac( $email, $scope = 'marketing' ) {
		$key = wp_salt( 'auth' );
		$data = strtolower( trim( $email ) ) . '|' . $scope;

		return hash_hmac( 'sha256', $data, $key );
	}

	/**
	 * Verify unsubscribe HMAC.
	 *
	 * @param string $email Email.
	 * @param string $token Token.
	 * @param string $scope Scope.
	 * @return bool
	 */
	public static function unsubscribe_valid( $email, $token, $scope = 'marketing' ) {
		$expected = self::unsubscribe_hmac( $email, $scope );

		return hash_equals( $expected, (string) $token );
	}
}
