<?php
/**
 * Suppression list.
 *
 * @package OtterMail
 */

namespace OtterMail\Unsubscribe;

use OtterMail\Support\Tables;

defined( 'ABSPATH' ) || exit;

class SuppressionRepository {

	/**
	 * @return string
	 */
	private function table() {
		return Tables::name( Tables::SUPPRESSIONS );
	}

	/**
	 * Whether an email is suppressed for a send type.
	 *
	 * Marketing sends are blocked by marketing|all.
	 * Cart recovery is blocked by cart|all only.
	 *
	 * @param string $email Email.
	 * @param string $kind  marketing|cart.
	 * @return bool
	 */
	public function is_suppressed( $email, $kind = 'marketing' ) {
		global $wpdb;

		$email = $this->normalize( $email );
		if ( ! $email ) {
			return true;
		}

		$scopes = ( 'cart' === $kind ) ? array( 'cart', 'all' ) : array( 'marketing', 'all' );
		$in     = "'" . implode( "','", array_map( 'esc_sql', $scopes ) ) . "'";

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->table() . " WHERE email = %s AND scope IN ({$in})", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email
			)
		);

		return $count > 0;
	}

	/**
	 * Add a suppression.
	 *
	 * @param string $email Email.
	 * @param string $scope Scope.
	 * @param string $hash  Optional token hash.
	 * @return bool
	 */
	public function add( $email, $scope = 'marketing', $hash = '' ) {
		global $wpdb;

		$email = $this->normalize( $email );
		if ( ! $email ) {
			return false;
		}

		$scope = in_array( $scope, array( 'marketing', 'cart', 'all' ), true ) ? $scope : 'marketing';

		return false !== $wpdb->replace(
			$this->table(),
			array(
				'email'      => $email,
				'scope'      => $scope,
				'token_hash' => $hash ? sanitize_text_field( $hash ) : '',
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * @param string $email Email.
	 * @return string
	 */
	private function normalize( $email ) {
		return strtolower( sanitize_email( $email ) );
	}
}
