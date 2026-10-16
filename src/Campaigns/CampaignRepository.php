<?php
/**
 * Campaign persistence.
 *
 * @package OtterMail
 */

namespace OtterMail\Campaigns;

use OtterMail\Support\Tables;

defined( 'ABSPATH' ) || exit;

class CampaignRepository {

	/**
	 * @return string
	 */
	private function table() {
		return Tables::name( Tables::CAMPAIGNS );
	}

	/**
	 * @return string
	 */
	private function recipients() {
		return Tables::name( Tables::RECIPIENTS );
	}

	/**
	 * @param int $id ID.
	 * @return Campaign|null
	 */
	public function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $row ? Campaign::from_row( $row ) : null;
	}

	/**
	 * @param int $limit Limit.
	 * @return Campaign[]
	 */
	public function latest( $limit = 50 ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' ORDER BY id DESC LIMIT %d', absint( $limit ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return array_map( array( Campaign::class, 'from_row' ), $rows ? $rows : array() );
	}

	/**
	 * @param array $data Data.
	 * @return int
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$row  = $this->sanitize( $data );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		$row['status']     = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft';

		$wpdb->insert( $this->table(), $row );

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int   $id   ID.
	 * @param array $data Data.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$row                = $this->sanitize( $data );
		$row['updated_at']  = current_time( 'mysql' );

		if ( isset( $data['status'] ) && in_array( $data['status'], Campaign::STATUSES, true ) ) {
			$row['status'] = $data['status'];
		}
		if ( array_key_exists( 'scheduled_at', $data ) ) {
			$row['scheduled_at'] = $data['scheduled_at'] ? sanitize_text_field( $data['scheduled_at'] ) : null;
		}

		return false !== $wpdb->update( $this->table(), $row, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Patch counters / timestamps.
	 *
	 * @param int   $id   ID.
	 * @param array $data Data.
	 */
	public function patch( $id, array $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update( $this->table(), $data, array( 'id' => absint( $id ) ) );
	}

	/**
	 * @param int $id ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$wpdb->delete( $this->recipients(), array( 'campaign_id' => absint( $id ) ), array( '%d' ) );

		return (bool) $wpdb->delete( $this->table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Insert recipient snapshot rows. Skips duplicate emails via UNIQUE key.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $recipients  Recipients.
	 * @return int Inserted count.
	 */
	public function insert_recipients( $campaign_id, array $recipients ) {
		global $wpdb;

		$inserted = 0;
		$now      = current_time( 'mysql' );

		foreach ( $recipients as $recipient ) {
			$email = strtolower( sanitize_email( $recipient['email'] ) );
			if ( ! is_email( $email ) ) {
				continue;
			}

			$result = $wpdb->query(
				$wpdb->prepare(
					'INSERT IGNORE INTO ' . $this->recipients() . ' (campaign_id, customer_id, email, first_name, last_name, status, queued_at, created_at) VALUES (%d, %d, %s, %s, %s, %s, %s, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					absint( $campaign_id ),
					isset( $recipient['customer_id'] ) ? absint( $recipient['customer_id'] ) : 0,
					$email,
					isset( $recipient['first_name'] ) ? sanitize_text_field( $recipient['first_name'] ) : '',
					isset( $recipient['last_name'] ) ? sanitize_text_field( $recipient['last_name'] ) : '',
					'queued',
					$now,
					$now
				)
			);

			if ( $result ) {
				++$inserted;
			}
		}

		return $inserted;
	}

	/**
	 * Claim a batch of queued recipients.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $limit       Limit.
	 * @return array
	 */
	public function claim_batch( $campaign_id, $limit ) {
		global $wpdb;

		$table = $this->recipients();
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE campaign_id = %d AND status = %s ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $campaign_id ),
				'queued',
				absint( $limit )
			)
		);

		if ( empty( $ids ) ) {
			return array();
		}

		$id_list = implode( ',', array_map( 'absint', $ids ) );
		$wpdb->query( "UPDATE {$table} SET status = 'processing' WHERE id IN ({$id_list}) AND status = 'queued'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->get_results( "SELECT * FROM {$table} WHERE id IN ({$id_list}) AND status = 'processing'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Return an unprocessed claimed batch to the queue.
	 *
	 * @param array $recipient_ids Recipient IDs.
	 */
	public function requeue_batch( array $recipient_ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $recipient_ids ) );
		if ( empty( $ids ) ) {
			return;
		}

		$id_list = implode( ',', $ids );
		$table   = $this->recipients();
		$wpdb->query( "UPDATE {$table} SET status = 'queued' WHERE id IN ({$id_list}) AND status = 'processing'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param int    $id     Recipient ID.
	 * @param string $status Status.
	 * @param string $error  Error.
	 */
	public function mark_recipient( $id, $status, $error = '' ) {
		global $wpdb;

		$data = array( 'status' => $status );
		$now  = current_time( 'mysql' );

		if ( 'sent' === $status ) {
			$data['sent_at'] = $now;
		}
		if ( 'failed' === $status ) {
			$data['failed_at']     = $now;
			$data['error_message'] = sanitize_textarea_field( $error );
		}

		$wpdb->update( $this->recipients(), $data, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Recalculate campaign counters from recipients.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function refresh_counters( $campaign_id ) {
		global $wpdb;

		$table = $this->recipients();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(status IN ('queued','processing','sent','failed','skipped')) AS total,
					SUM(status = 'sent') AS sent,
					SUM(status = 'failed') AS failed,
					SUM(status = 'skipped') AS skipped,
					SUM(status = 'queued') AS queued
				FROM {$table} WHERE campaign_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $campaign_id )
			)
		);

		$this->patch(
			$campaign_id,
			array(
				'recipients_count' => (int) $row->total,
				'sent_count'       => (int) $row->sent,
				'failed_count'     => (int) $row->failed,
				'skipped_count'    => (int) $row->skipped,
			)
		);

		return $row;
	}

	/**
	 * Count campaigns by status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	public function count_by_status( $status ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE status = %s', $status ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Total sent recipients.
	 *
	 * @return int
	 */
	public function count_sent_emails() {
		return $this->count_recipient_status( 'sent' );
	}

	/**
	 * Recipients that the local transport rejected.
	 *
	 * @return int
	 */
	public function count_failed_emails() {
		return $this->count_recipient_status( 'failed' );
	}

	/**
	 * @param string $status Recipient status.
	 * @return int
	 */
	public function count_recipient_status( $status ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->recipients() . ' WHERE status = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				sanitize_key( $status )
			)
		);
	}

	/**
	 * Distinct emails that received a campaign.
	 *
	 * @return int
	 */
	public function count_unique_reached() {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT email) FROM ' . $this->recipients() . " WHERE status = 'sent'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @param array $data Data.
	 * @return array
	 */
	private function sanitize( array $data ) {
		return array(
			'name'        => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'subject'     => isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : '',
			'preheader'   => isset( $data['preheader'] ) ? sanitize_text_field( $data['preheader'] ) : '',
			'from_name'   => isset( $data['from_name'] ) ? sanitize_text_field( $data['from_name'] ) : '',
			'from_email'  => isset( $data['from_email'] ) ? sanitize_email( $data['from_email'] ) : '',
			'reply_to'    => isset( $data['reply_to'] ) ? sanitize_email( $data['reply_to'] ) : '',
			'template_id' => isset( $data['template_id'] ) ? absint( $data['template_id'] ) : 0,
			'audience_id' => isset( $data['audience_id'] ) ? absint( $data['audience_id'] ) : 0,
			'list_id'     => isset( $data['list_id'] ) ? absint( $data['list_id'] ) : 0,
		);
	}
}
