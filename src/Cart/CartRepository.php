<?php
/**
 * Cart persistence.
 *
 * @package OtterMail
 */

namespace OtterMail\Cart;

use OtterMail\Support\Tables;
use OtterMail\Support\Tokens;

defined( 'ABSPATH' ) || exit;

class CartRepository {

	/**
	 * @return string
	 */
	private function table() {
		return Tables::name( Tables::CARTS );
	}

	/**
	 * @param int $id ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Find the cart explicitly associated with an order.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	public function get_by_order_id( $order_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE order_id = %d ORDER BY id DESC LIMIT 1', absint( $order_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * @param string $hash Token hash.
	 * @return object|null
	 */
	public function get_by_token_hash( $hash ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE restore_token_hash = %s', $hash ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Find an open cart for this session or customer.
	 *
	 * @param string $session_key Session.
	 * @param int    $customer_id User ID.
	 * @param string $email       Email.
	 * @return object|null
	 */
	public function find_open( $session_key, $customer_id, $email ) {
		global $wpdb;

		$table = $this->table();

		if ( $customer_id ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE customer_id = %d AND status IN ('active','abandoned','checkout_abandoned','recovered') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$customer_id
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		if ( $email ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE email = %s AND status IN ('active','abandoned','checkout_abandoned','recovered') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					strtolower( sanitize_email( $email ) )
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		if ( $session_key ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE session_key = %s AND status IN ('active','abandoned','checkout_abandoned','recovered') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$session_key
				)
			);
		}

		return null;
	}

	/**
	 * Cart that belongs to this checkout. Session wins, then email, then customer.
	 *
	 * @param string $session_key Session.
	 * @param int    $customer_id User ID.
	 * @param string $email       Billing email.
	 * @return object|null
	 */
	public function find_for_checkout( $session_key, $customer_id, $email ) {
		global $wpdb;

		$table = $this->table();
		$email = strtolower( sanitize_email( $email ) );
		$open  = "status IN ('active','abandoned','checkout_abandoned','recovered')";

		if ( $session_key ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE session_key = %s AND {$open} ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$session_key
				)
			);
			if ( $row && ( ! $row->email || ! $email || strtolower( $row->email ) === $email ) ) {
				return $row;
			}
		}

		if ( $email ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE email = %s AND {$open} ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$email
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		if ( $customer_id ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE customer_id = %d AND {$open} ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					absint( $customer_id )
				)
			);
		}

		return null;
	}

	/**
	 * Carts that may already belong to a paid order but were never converted.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function unconverted_carts( $limit = 100 ) {
		global $wpdb;

		$table = $this->table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('abandoned','checkout_abandoned','recovered','active') AND email <> '' ORDER BY FIELD(status, 'abandoned', 'checkout_abandoned', 'recovered', 'active'), id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $limit )
			)
		);
	}

	/**
	 * Insert a cart. Returns [id, raw_token].
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public function insert( array $data ) {
		global $wpdb;

		$token = Tokens::generate();
		$now   = current_time( 'mysql' );

		$wpdb->insert(
			$this->table(),
			array(
				'session_key'        => isset( $data['session_key'] ) ? sanitize_text_field( $data['session_key'] ) : '',
				'customer_id'        => isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : null,
				'email'              => isset( $data['email'] ) ? strtolower( sanitize_email( $data['email'] ) ) : '',
				'first_name'         => isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '',
				'cart_hash'          => isset( $data['cart_hash'] ) ? sanitize_text_field( $data['cart_hash'] ) : '',
				'cart_contents'      => isset( $data['cart_contents'] ) ? wp_json_encode( $data['cart_contents'] ) : null,
				'cart_total'         => isset( $data['cart_total'] ) ? (float) $data['cart_total'] : 0,
				'currency'           => isset( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : '',
				'status'             => 'active',
				'restore_token_hash' => Tokens::hash( $token ),
				'created_at'         => $now,
				'updated_at'         => $now,
				'last_activity_at'   => $now,
			)
		);

		return array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $token,
		);
	}

	/**
	 * @param int   $id   ID.
	 * @param array $data Data.
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update( $this->table(), $data, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Remove a cart that belongs to a normal store purchase.
	 *
	 * @param int $id Cart ID.
	 */
	public function delete( $id ) {
		global $wpdb;

		$wpdb->delete( $this->table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Sales stored as conversions even though the cart never entered recovery.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function normal_sales( $limit = 100 ) {
		global $wpdb;

		$table = $this->table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'converted' AND abandoned_at IS NULL AND recovered_at IS NULL ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $limit )
			)
		);
	}

	/**
	 * Carts ready to be marked abandoned.
	 *
	 * @param int $minutes Threshold.
	 * @return array
	 */
	public function due_for_abandonment( $minutes ) {
		global $wpdb;

		$table = $this->table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND email <> '' AND last_activity_at <= DATE_SUB(%s, INTERVAL %d MINUTE)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'active',
				current_time( 'mysql' ),
				absint( $minutes )
			)
		);
	}

	/**
	 * Admin listing.
	 *
	 * @param string $status Status filter.
	 * @param int    $limit  Limit.
	 * @return array
	 */
	public function list_carts( $status = '', $limit = 50 ) {
		global $wpdb;

		$table = $this->table();
		$logs  = Tables::name( Tables::LOGS );
		$select = "SELECT {$table}.*,\n\t\t\t( SELECT COUNT(*) FROM {$logs} WHERE object_type = 'cart' AND object_id = {$table}.id AND event_type = 'automation_email_sent' ) AS automation_email_count,\n\t\t\t( SELECT MAX(created_at) FROM {$logs} WHERE object_type = 'cart' AND object_id = {$table}.id AND event_type = 'automation_email_sent' ) AS last_automation_email_at\n\t\t\tFROM {$table}";
		if ( $status ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"{$select} WHERE status = %s ORDER BY last_activity_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					sanitize_key( $status ),
					absint( $limit )
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare( "{$select} ORDER BY last_activity_at DESC LIMIT %d", absint( $limit ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * @param string $status Status.
	 * @return int
	 */
	public function count_status( $status ) {
		return $this->count_statuses( array( $status ) );
	}

	/**
	 * @param string[] $statuses Statuses.
	 * @return int
	 */
	public function count_statuses( array $statuses ) {
		global $wpdb;

		$statuses = array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );
		if ( ! $statuses ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->table() . " WHERE status IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$statuses
			)
		);
	}

	/**
	 * Converted carts with the signals used to attribute a sale to email.
	 *
	 * @return array
	 */
	public function converted_for_report() {
		global $wpdb;

		$table = $this->table();
		$logs  = Tables::name( Tables::LOGS );

		return $wpdb->get_results(
			"SELECT c.id, c.order_id, c.cart_total, c.recovered_at,
				(
					SELECT COUNT(*) FROM {$logs} l
					WHERE l.object_type = 'cart' AND l.object_id = c.id AND l.event_type = 'automation_email_sent'
				) AS automation_email_count
			FROM {$table} c
			WHERE c.status = 'converted'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
