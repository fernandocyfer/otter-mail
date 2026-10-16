<?php
/**
 * Audience persistence.
 *
 * @package OtterMail
 */

namespace OtterMail\Audiences;

use OtterMail\Support\Tables;

defined( 'ABSPATH' ) || exit;

class AudienceRepository {

	/**
	 * @return string
	 */
	private function table() {
		return Tables::name( Tables::AUDIENCES );
	}

	/**
	 * @param int $id ID.
	 * @return Audience|null
	 */
	public function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $row ? Audience::from_row( $row ) : null;
	}

	/**
	 * @return Audience[]
	 */
	public function all() {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT * FROM ' . $this->table() . ' ORDER BY name ASC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( array( Audience::class, 'from_row' ), $rows ? $rows : array() );
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
				'name'         => sanitize_text_field( $data['name'] ),
				'match_type'   => ( isset( $data['match_type'] ) && 'any' === $data['match_type'] ) ? 'any' : 'all',
				'conditions'   => wp_json_encode( $this->sanitize_conditions( isset( $data['conditions'] ) ? $data['conditions'] : array() ) ),
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int   $id   ID.
	 * @param array $data Data.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		return false !== $wpdb->update(
			$this->table(),
			array(
				'name'       => sanitize_text_field( $data['name'] ),
				'match_type' => ( isset( $data['match_type'] ) && 'any' === $data['match_type'] ) ? 'any' : 'all',
				'conditions' => wp_json_encode( $this->sanitize_conditions( isset( $data['conditions'] ) ? $data['conditions'] : array() ) ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * @param int $id ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( $this->table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Store last estimate.
	 *
	 * @param int $id    ID.
	 * @param int $count Count.
	 */
	public function save_estimate( $id, $count ) {
		global $wpdb;

		$wpdb->update(
			$this->table(),
			array(
				'last_estimate'     => absint( $count ),
				'last_estimated_at' => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * @param array $conditions Conditions.
	 * @return array
	 */
	private function sanitize_conditions( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			return array();
		}

		$clean = array();
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) || empty( $condition['field'] ) ) {
				continue;
			}
			$clean[] = array(
				'field'    => sanitize_key( $condition['field'] ),
				'operator' => isset( $condition['operator'] ) ? sanitize_key( $condition['operator'] ) : 'eq',
				'value'    => isset( $condition['value'] ) ? sanitize_text_field( (string) $condition['value'] ) : '',
			);
		}

		return $clean;
	}
}
