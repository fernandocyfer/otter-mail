<?php
/**
 * Global delivery rate limiter.
 *
 * @package OtterMail
 */

namespace OtterMail\Mail;

use OtterMail\Core\Settings;
use OtterMail\Support\Tables;

defined( 'ABSPATH' ) || exit;

class RateLimiter {

	/**
	 * Atomically reserve capacity in the current UTC hour.
	 *
	 * Reservations are intentionally not returned after a local transport
	 * failure, keeping the limiter conservative while the provider is unstable.
	 *
	 * @param int $count Number of messages.
	 * @return bool
	 */
	public function reserve( $count = 1 ) {
		global $wpdb;

		$count = max( 1, absint( $count ) );
		$limit = max( 1, (int) Settings::get( 'emails_per_hour', 500 ) );
		if ( $count > $limit ) {
			return false;
		}

		$table  = Tables::name( Tables::DELIVERY_RATE );
		$bucket = gmdate( 'YmdH' );

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (bucket, reserved_count, updated_at) VALUES (%s, %d, %s)
				ON DUPLICATE KEY UPDATE reserved_count = IF(reserved_count + VALUES(reserved_count) <= %d, reserved_count + VALUES(reserved_count), reserved_count)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$bucket,
				$count,
				current_time( 'mysql', true ),
				$limit
			)
		);

		return false !== $result && (int) $result > 0;
	}

	/**
	 * Delay until the next UTC hourly bucket, with a small safety margin.
	 *
	 * @return int Seconds.
	 */
	public function retry_after() {
		return ( HOUR_IN_SECONDS - ( time() % HOUR_IN_SECONDS ) ) + 5;
	}
}
