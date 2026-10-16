<?php
/**
 * Newsletter list persistence.
 *
 * @package OtterMail
 */

namespace OtterMail\Lists;

use OtterMail\Support\Tables;

defined( 'ABSPATH' ) || exit;

class ContactListRepository {

	/**
	 * @return string
	 */
	private function table() {
		return Tables::name( Tables::LISTS );
	}

	/**
	 * @return string
	 */
	private function contacts() {
		return Tables::name( Tables::LIST_CONTACTS );
	}

	/**
	 * @param int $id ID.
	 * @return ContactList|null
	 */
	public function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $row ? ContactList::from_row( $row ) : null;
	}

	/**
	 * @return ContactList[]
	 */
	public function all() {
		global $wpdb;

		$lists    = $this->table();
		$contacts = $this->contacts();
		$rows     = $wpdb->get_results(
			"SELECT l.*, (SELECT COUNT(*) FROM {$contacts} c WHERE c.list_id = l.id) AS contact_count FROM {$lists} l ORDER BY l.name ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return array_map( array( ContactList::class, 'from_row' ), $rows ? $rows : array() );
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

		$row               = $this->sanitize( $data );
		$row['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update( $this->table(), $row, array( 'id' => absint( $id ) ) );
	}

	/**
	 * @param int $id ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$id = absint( $id );
		$wpdb->delete( $this->contacts(), array( 'list_id' => $id ), array( '%d' ) );

		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Lists linked to an Elementor form widget.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $element_id Widget ID.
	 * @return ContactList[]
	 */
	public function by_elementor_form( $post_id, $element_id ) {
		global $wpdb;

		$element_id = sanitize_text_field( $element_id );
		if ( ! $element_id ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE source = %s AND elementor_form_id = %s AND (elementor_post_id = %d OR elementor_post_id = 0)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'elementor',
				$element_id,
				absint( $post_id )
			)
		);

		return array_map( array( ContactList::class, 'from_row' ), $rows ? $rows : array() );
	}

	/**
	 * @param int $list_id List ID.
	 * @return int
	 */
	public function count_contacts( $list_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->contacts() . ' WHERE list_id = %d', absint( $list_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * @param int $list_id List ID.
	 * @param int $limit   Limit.
	 * @param int $offset  Offset.
	 * @return array
	 */
	public function get_contacts( $list_id, $limit = 200, $offset = 0 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->contacts() . ' WHERE list_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $list_id ),
				absint( $limit ),
				absint( $offset )
			)
		);
	}

	/**
	 * Recipients payload for a campaign snapshot.
	 *
	 * @param int $list_id List ID.
	 * @return array
	 */
	public function emails_for_send( $list_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT email, first_name, last_name FROM ' . $this->contacts() . ' WHERE list_id = %d ORDER BY id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $list_id )
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'email'       => $row->email,
				'first_name'  => $row->first_name,
				'last_name'   => $row->last_name,
				'customer_id' => 0,
			);
		}

		return $out;
	}

	/**
	 * Insert or ignore a contact.
	 *
	 * @param int    $list_id    List ID.
	 * @param string $email      Email.
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param string $source     Source.
	 * @return bool True when a new row was inserted.
	 */
	public function add_contact( $list_id, $email, $first_name = '', $last_name = '', $source = 'manual' ) {
		global $wpdb;

		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return false;
		}

		$now    = current_time( 'mysql' );
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . $this->contacts() . ' (list_id, email, first_name, last_name, source, created_at) VALUES (%d, %s, %s, %s, %s, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $list_id ),
				$email,
				sanitize_text_field( $first_name ),
				sanitize_text_field( $last_name ),
				sanitize_key( $source ),
				$now
			)
		);

		return (bool) $result;
	}

	/**
	 * @param int $contact_id Contact ID.
	 * @param int $list_id    List ID.
	 * @return bool
	 */
	public function delete_contact( $contact_id, $list_id ) {
		global $wpdb;

		return (bool) $wpdb->delete(
			$this->contacts(),
			array(
				'id'      => absint( $contact_id ),
				'list_id' => absint( $list_id ),
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * @param array $data Data.
	 * @return array
	 */
	private function sanitize( array $data ) {
		$source = isset( $data['source'] ) ? sanitize_key( $data['source'] ) : 'manual';
		if ( ! in_array( $source, ContactList::SOURCES, true ) ) {
			$source = 'manual';
		}

		return array(
			'name'               => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'source'             => $source,
			'elementor_post_id'  => isset( $data['elementor_post_id'] ) ? absint( $data['elementor_post_id'] ) : 0,
			'elementor_form_id'  => isset( $data['elementor_form_id'] ) ? sanitize_text_field( $data['elementor_form_id'] ) : '',
		);
	}
}
