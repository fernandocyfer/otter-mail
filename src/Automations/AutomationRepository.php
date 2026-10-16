<?php
/**
 * Automation persistence.
 *
 * @package OtterMail
 */

namespace OtterMail\Automations;

use OtterMail\Core\Settings;
use OtterMail\Support\Tables;

defined( 'ABSPATH' ) || exit;

class AutomationRepository {

	/**
	 * @return string
	 */
	private function table() {
		return Tables::name( Tables::AUTOMATIONS );
	}

	/**
	 * @return string
	 */
	private function steps() {
		return Tables::name( Tables::AUTOMATION_STEPS );
	}

	/**
	 * @return string
	 */
	private function enrollments() {
		return Tables::name( Tables::AUTOMATION_ENROLLMENTS );
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
	 * @return array
	 */
	public function all() {
		global $wpdb;

		return $wpdb->get_results( 'SELECT * FROM ' . $this->table() . ' ORDER BY id DESC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Active automations for a trigger.
	 *
	 * @param string $trigger Trigger.
	 * @return array
	 */
	public function active_by_trigger( $trigger ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE status = %s AND trigger_type = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'active',
				sanitize_key( $trigger )
			)
		);
	}

	/**
	 * Inactivity required before a cart is abandoned.
	 *
	 * The first step of an active automation is the grace period: adding a
	 * product and then paying inside that window is a sale. The global setting
	 * applies only when neither cart nor checkout automation is active.
	 *
	 * @param string $trigger cart_abandoned|checkout_abandoned.
	 * @return int Minutes.
	 */
	public function abandon_delay_minutes( $trigger ) {
		$own = $this->shortest_first_step_minutes( $trigger );
		if ( null !== $own ) {
			return $own;
		}

		$other    = 'checkout_abandoned' === $trigger ? 'cart_abandoned' : 'checkout_abandoned';
		$fallback = $this->shortest_first_step_minutes( $other );
		if ( null !== $fallback ) {
			return $fallback;
		}

		return max( 5, (int) Settings::get( 'cart_abandon_minutes', 60 ) );
	}

	/**
	 * Shortest first-step wait among active automations, in minutes.
	 *
	 * @param string $trigger Trigger.
	 * @return int|null Null when no active automation has a step.
	 */
	public function shortest_first_step_minutes( $trigger ) {
		$shortest = null;

		foreach ( $this->active_by_trigger( $trigger ) as $automation ) {
			$steps = $this->get_steps( $automation->id );
			if ( empty( $steps ) ) {
				continue;
			}

			$minutes = self::step_delay_minutes( $steps[0]->delay_value, $steps[0]->delay_unit );
			if ( null === $shortest || $minutes < $shortest ) {
				$shortest = $minutes;
			}
		}

		return $shortest;
	}

	/**
	 * @param int    $value Delay value.
	 * @param string $unit  minutes|hours|days.
	 * @return int
	 */
	public static function step_delay_minutes( $value, $unit ) {
		$value = max( 0, (int) $value );
		if ( 'days' === $unit ) {
			return $value * 1440;
		}
		if ( 'hours' === $unit ) {
			return $value * 60;
		}

		return $value;
	}

	/**
	 * @param array $data Data.
	 * @return int
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table(),
			array(
				'name'           => sanitize_text_field( $data['name'] ),
				'status'         => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft',
				'trigger_type'   => isset( $data['trigger_type'] ) ? sanitize_key( $data['trigger_type'] ) : 'cart_abandoned',
				'trigger_config' => isset( $data['trigger_config'] ) ? wp_json_encode( $data['trigger_config'] ) : null,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int   $id   ID.
	 * @param array $data Data.
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$row = array(
			'updated_at' => current_time( 'mysql' ),
		);
		if ( isset( $data['name'] ) ) {
			$row['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'draft', 'active', 'paused' ), true ) ) {
			$row['status'] = $data['status'];
		}
		if ( isset( $data['trigger_type'] ) ) {
			$row['trigger_type'] = sanitize_key( $data['trigger_type'] );
		}

		$wpdb->update( $this->table(), $row, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Replace steps (max 5).
	 *
	 * @param int   $automation_id Automation ID.
	 * @param array $steps         Steps.
	 */
	public function save_steps( $automation_id, array $steps ) {
		global $wpdb;

		$wpdb->delete( $this->steps(), array( 'automation_id' => absint( $automation_id ) ), array( '%d' ) );

		$position = 1;
		foreach ( array_slice( $steps, 0, 5 ) as $step ) {
			$wpdb->insert(
				$this->steps(),
				array(
					'automation_id' => absint( $automation_id ),
					'position'      => $position,
					'delay_value'   => isset( $step['delay_value'] ) ? max( 0, absint( $step['delay_value'] ) ) : 1,
					'delay_unit'    => in_array( $step['delay_unit'], array( 'minutes', 'hours', 'days' ), true ) ? $step['delay_unit'] : 'hours',
					'template_id'   => isset( $step['template_id'] ) ? absint( $step['template_id'] ) : 0,
					'created_at'    => current_time( 'mysql' ),
				)
			);
			++$position;
		}
	}

	/**
	 * @param int $automation_id Automation ID.
	 * @return array
	 */
	public function get_steps( $automation_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->steps() . ' WHERE automation_id = %d ORDER BY position ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $automation_id )
			)
		);
	}

	/**
	 * @param int $id Step ID.
	 * @return object|null
	 */
	public function get_step( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->steps() . ' WHERE id = %d', absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * @param int $automation_id Automation ID.
	 * @param int $cart_id       Cart ID.
	 * @param string $email      Email.
	 * @return int Enrollment ID.
	 */
	public function enroll( $automation_id, $cart_id, $email ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . $this->enrollments() . ' (automation_id, cart_id, email, status, current_step, enrolled_at) VALUES (%d, %d, %s, %s, %d, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $automation_id ),
				absint( $cart_id ),
				strtolower( sanitize_email( $email ) ),
				'active',
				0,
				current_time( 'mysql' )
			)
		);

		if ( $wpdb->insert_id ) {
			return (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->enrollments() . ' WHERE automation_id = %d AND cart_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $automation_id ),
				absint( $cart_id )
			)
		);

		return $row ? (int) $row->id : 0;
	}

	/**
	 * Enroll a paid order. The legacy cart_id column is the source object ID for
	 * an enrollment, so it safely stores the order ID for post-purchase flows.
	 *
	 * @param int    $automation_id Automation ID.
	 * @param int    $order_id Order ID.
	 * @param string $email Recipient email.
	 * @return int
	 */
	public function enroll_order( $automation_id, $order_id, $email ) {
		return $this->enroll( $automation_id, $order_id, $email );
	}

	/**
	 * @param int $id ID.
	 * @return object|null
	 */
	public function get_enrollment( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->enrollments() . ' WHERE id = %d', absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Atomically lock the current step so concurrent actions cannot send it
	 * twice. Stale locks are recoverable after fifteen minutes.
	 *
	 * @param int $id                   Enrollment ID.
	 * @param int $expected_current_step Current completed step.
	 * @return bool
	 */
	public function claim_enrollment( $id, $expected_current_step ) {
		global $wpdb;

		$now    = current_time( 'mysql' );
		$cutoff = wp_date( 'Y-m-d H:i:s', current_datetime()->getTimestamp() - ( 15 * MINUTE_IN_SECONDS ) );

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->enrollments() . ' SET locked_at = %s, last_attempt_at = %s WHERE id = %d AND status = %s AND current_step = %d AND (locked_at IS NULL OR locked_at < %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$now,
				$now,
				absint( $id ),
				'active',
				absint( $expected_current_step ),
				$cutoff
			)
		);

		return 1 === (int) $updated;
	}

	/**
	 * @param int    $id     ID.
	 * @param array  $data   Data.
	 */
	public function update_enrollment( $id, array $data ) {
		global $wpdb;

		$wpdb->update( $this->enrollments(), $data, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Active enrollments for a cart.
	 *
	 * @param int $cart_id Cart ID.
	 * @return array
	 */
	public function enrollments_for_cart( $cart_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->enrollments() . ' WHERE cart_id = %d AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $cart_id ),
				'active'
			)
		);
	}

	/**
	 * Remove every enrollment tied to a cart that will not stay in the plugin.
	 *
	 * @param int $cart_id Cart ID.
	 */
	public function delete_enrollments_for_cart( $cart_id ) {
		global $wpdb;

		$enrollments = $this->enrollments();
		$automations = $this->table();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE e FROM {$enrollments} e INNER JOIN {$automations} a ON a.id = e.automation_id WHERE e.cart_id = %d AND a.trigger_type <> %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $cart_id ),
				'post_purchase'
			)
		);
	}

	/**
	 * Active enrollments for an email and automation trigger.
	 *
	 * @param string $email Email.
	 * @param string $trigger Trigger type.
	 * @return array
	 */
	public function enrollments_for_email_by_trigger( $email, $trigger ) {
		global $wpdb;

		$enrollments = $this->enrollments();
		$automations = $this->table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.* FROM {$enrollments} e INNER JOIN {$automations} a ON a.id = e.automation_id WHERE e.email = %s AND e.status = %s AND a.trigger_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				strtolower( sanitize_email( $email ) ),
				'active',
				sanitize_key( $trigger )
			)
		);
	}

	/**
	 * One row per automation with enrollment totals and emails accepted by wp_mail.
	 *
	 * @return array
	 */
	public function report_rows() {
		global $wpdb;

		$automations = $this->table();
		$enrollments = $this->enrollments();
		$rows        = $wpdb->get_results(
			"SELECT a.id, a.name, a.status, a.trigger_type,
				COUNT(e.id) AS enrolled,
				COALESCE(SUM(e.status = 'active'), 0) AS active_count,
				COALESCE(SUM(e.status = 'completed'), 0) AS completed_count,
				COALESCE(SUM(e.status = 'cancelled'), 0) AS cancelled_count,
				COALESCE(SUM(e.status = 'failed'), 0) AS failed_count
			FROM {$automations} a
			LEFT JOIN {$enrollments} e ON e.automation_id = a.id
			GROUP BY a.id
			ORDER BY a.id DESC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$sent = $this->sent_email_counts();
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$id = (int) $row->id;
			$out[] = (object) array(
				'id'              => $id,
				'name'            => (string) $row->name,
				'status'          => (string) $row->status,
				'trigger_type'    => (string) $row->trigger_type,
				'enrolled'        => (int) $row->enrolled,
				'active_count'    => (int) $row->active_count,
				'completed_count' => (int) $row->completed_count,
				'cancelled_count' => (int) $row->cancelled_count,
				'failed_count'    => (int) $row->failed_count,
				'sent_count'      => isset( $sent[ $id ] ) ? $sent[ $id ] : 0,
			);
		}

		return $out;
	}

	/**
	 * Emails sent, grouped by the automation id stored in the log context.
	 *
	 * @return array<int, int>
	 */
	private function sent_email_counts() {
		global $wpdb;

		$logs = Tables::name( Tables::LOGS );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(context, '$.automation_id')) AS UNSIGNED) AS automation_id, COUNT(*) AS sent_count
				FROM {$logs}
				WHERE event_type = %s AND context IS NOT NULL
				GROUP BY automation_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'automation_email_sent'
			)
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$id = (int) $row->automation_id;
			if ( $id > 0 ) {
				$counts[ $id ] = (int) $row->sent_count;
			}
		}

		return $counts;
	}
}
