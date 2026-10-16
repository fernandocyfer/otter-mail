<?php
/**
 * Stored links sent in emails, plus their clicks and purchases.
 *
 * @package OtterMail
 */

namespace OtterMail\Tracking;

use OtterMail\Support\Tables;
use OtterMail\Support\Tokens;

defined( 'ABSPATH' ) || exit;

class ClickRepository {

	/**
	 * @return string
	 */
	private function table() {
		return Tables::name( Tables::CLICKS );
	}

	/**
	 * Store one sent link and return the public tracking URL.
	 *
	 * @param array $data email, source_type, source_id, url.
	 * @return array|null {id, url}
	 */
	public function create( array $data ) {
		global $wpdb;

		$url = isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '';
		if ( ! $url ) {
			return null;
		}

		$token = Tokens::generate();
		$now   = current_time( 'mysql' );
		$ok    = $wpdb->insert(
			$this->table(),
			array(
				'token_hash'  => Tokens::hash( $token ),
				'email'       => isset( $data['email'] ) ? strtolower( sanitize_email( $data['email'] ) ) : '',
				'source_type' => isset( $data['source_type'] ) ? sanitize_key( $data['source_type'] ) : '',
				'source_id'   => isset( $data['source_id'] ) ? absint( $data['source_id'] ) : 0,
				'url'         => $url,
				'click_count' => 0,
				'created_at'  => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( ! $ok ) {
			return null;
		}

		return array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $token,
		);
	}

	/**
	 * Drop links from a message the transport refused.
	 *
	 * @param int[] $ids Row IDs.
	 */
	public function delete_unclicked( array $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return;
		}

		$table = $this->table();
		$in    = implode( ',', $ids );
		$wpdb->query( "DELETE FROM {$table} WHERE click_count = 0 AND id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param string $token Raw token from the email link.
	 * @return object|null
	 */
	public function find_by_token( $token ) {
		global $wpdb;

		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE token_hash = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				Tokens::hash( $token )
			)
		);
	}

	/**
	 * @param int $id Click ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $id )
			)
		);
	}

	/**
	 * Count one visit to a sent link.
	 *
	 * @param int $id Click ID.
	 */
	public function record_click( $id ) {
		global $wpdb;

		$table = $this->table();
		$now   = current_time( 'mysql' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET click_count = click_count + 1, clicked_at = IF(clicked_at IS NULL, %s, clicked_at) WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				absint( $id )
			)
		);
	}

	/**
	 * Attach the first paid order that followed this click.
	 *
	 * @param int   $id    Click ID.
	 * @param int   $order_id Order ID.
	 * @param float $total Order total.
	 * @return bool
	 */
	public function attribute_order( $id, $order_id, $total ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->table() . ' SET order_id = %d, order_total = %f, purchased_at = %s WHERE id = %d AND order_id IS NULL AND click_count > 0', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $order_id ),
				(float) $total,
				current_time( 'mysql' ),
				absint( $id )
			)
		);

		return (bool) $updated;
	}

	/**
	 * Sent links, clicks and purchases, excluding test messages.
	 *
	 * @return array
	 */
	public function totals() {
		global $wpdb;

		$table = $this->table();
		$row   = $wpdb->get_row(
			"SELECT COUNT(*) AS links_sent, COALESCE(SUM(click_count), 0) AS clicks, COUNT(DISTINCT order_id) AS purchases, COALESCE(SUM(order_total), 0) AS revenue FROM {$table} WHERE source_type <> 'test'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return array(
			'links_sent' => $row ? (int) $row->links_sent : 0,
			'clicks'     => $row ? (int) $row->clicks : 0,
			'purchases'  => $row ? (int) $row->purchases : 0,
			'revenue'    => $row ? (float) $row->revenue : 0,
		);
	}

	/**
	 * Per-source click and purchase counts.
	 *
	 * @param string $source_type campaign|automation.
	 * @return array<int, object>
	 */
	public function grouped( $source_type ) {
		global $wpdb;

		$table = $this->table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source_id, COUNT(*) AS links_sent, COALESCE(SUM(click_count), 0) AS clicks, COUNT(DISTINCT order_id) AS purchases, COALESCE(SUM(order_total), 0) AS revenue FROM {$table} WHERE source_type = %s GROUP BY source_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key( $source_type )
			)
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->source_id ] = $row;
		}

		return $map;
	}

	/**
	 * Replace a personal email when the store erases that customer.
	 *
	 * @param string $email     Current email.
	 * @param string $anonymous Replacement.
	 */
	public function anonymize_email( $email, $anonymous ) {
		global $wpdb;

		$wpdb->update(
			$this->table(),
			array( 'email' => $anonymous ),
			array( 'email' => strtolower( sanitize_email( $email ) ) ),
			array( '%s' ),
			array( '%s' )
		);
	}
}
