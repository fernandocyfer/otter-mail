<?php
/**
 * Template persistence.
 *
 * @package OtterMail
 */

namespace OtterMail\Templates;

use OtterMail\Support\Tables;

defined( 'ABSPATH' ) || exit;

class TemplateRepository {

	/**
	 * @return string
	 */
	private function table() {
		return Tables::name( Tables::TEMPLATES );
	}

	/**
	 * @param int $id ID.
	 * @return Template|null
	 */
	public function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $row ? Template::from_row( $row ) : null;
	}

	/**
	 * @param string $slug Slug.
	 * @return Template|null
	 */
	public function get_by_slug( $slug ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE slug = %s', $slug ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $row ? Template::from_row( $row ) : null;
	}

	/**
	 * @return Template[]
	 */
	public function all() {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT * FROM ' . $this->table() . ' ORDER BY is_system DESC, name ASC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( array( Template::class, 'from_row' ), $rows ? $rows : array() );
	}

	/**
	 * @param array $data Data.
	 * @return int
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$data = $this->sanitize( $data );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$wpdb->insert( $this->table(), $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int   $id   ID.
	 * @param array $data Data.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$existing = $this->get( $id );
		if ( ! $existing ) {
			return false;
		}

		$data = $this->sanitize( $data );
		if ( $existing->is_system ) {
			unset( $data['is_system'], $data['slug'] );
		}
		$data['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update( $this->table(), $data, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Duplicate a template as a user copy.
	 *
	 * @param int $id ID.
	 * @return int
	 */
	public function duplicate( $id ) {
		$src = $this->get( $id );
		if ( ! $src ) {
			return 0;
		}

		$row              = $src->to_row();
		$row['name']      = sprintf(
			/* translators: %s original name */
			__( 'Cópia de %s', 'ottermail' ),
			$src->name
		);
		$row['slug']      = sanitize_title( $row['name'] . '-' . wp_generate_password( 6, false, false ) );
		$row['is_system'] = 0;

		return $this->insert( $row );
	}

	/**
	 * Delete a non-system template.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$existing = $this->get( $id );
		if ( ! $existing || $existing->is_system ) {
			return false;
		}

		return (bool) $wpdb->delete( $this->table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * @param array $data Data.
	 * @return array
	 */
	private function sanitize( array $data ) {
		$ids = array();
		if ( ! empty( $data['product_ids'] ) ) {
			if ( is_array( $data['product_ids'] ) ) {
				$ids = $data['product_ids'];
			} else {
				$ids = explode( ',', (string) $data['product_ids'] );
			}
		}

		$blocks = Layout::sanitize( isset( $data['layout_json'] ) ? $data['layout_json'] : '' );
		if ( empty( $blocks ) ) {
			$tmp               = new Template();
			$tmp->logo_url     = isset( $data['logo_url'] ) ? esc_url_raw( $data['logo_url'] ) : '';
			$tmp->heading      = isset( $data['heading'] ) ? sanitize_text_field( $data['heading'] ) : '';
			$tmp->body         = isset( $data['body'] ) ? wp_kses_post( $data['body'] ) : '';
			$tmp->cta_label    = isset( $data['cta_label'] ) ? sanitize_text_field( $data['cta_label'] ) : '';
			$tmp->cta_url      = isset( $data['cta_url'] ) ? esc_url_raw( $data['cta_url'] ) : '';
			$tmp->show_products = empty( $data['show_products'] ) ? 0 : 1;
			$tmp->product_ids  = implode( ',', array_filter( array_map( 'absint', $ids ) ) );
			$tmp->coupon_code  = isset( $data['coupon_code'] ) ? sanitize_text_field( $data['coupon_code'] ) : '';
			$tmp->footer       = isset( $data['footer'] ) ? wp_kses_post( $data['footer'] ) : '';
			$blocks            = Layout::from_legacy( $tmp );
		} else {
			$blocks = Layout::ensure_structure( $blocks );
		}

		$legacy = Layout::legacy_fields( $blocks );

		return array(
			'name'          => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'slug'          => isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '',
			'category'      => isset( $data['category'] ) ? sanitize_key( $data['category'] ) : 'marketing',
			'subject'       => isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : '',
			'preheader'     => isset( $data['preheader'] ) ? sanitize_text_field( $data['preheader'] ) : '',
			'logo_url'      => $legacy['logo_url'],
			'heading'       => $legacy['heading'],
			'body'          => $legacy['body'],
			'cta_label'     => $legacy['cta_label'],
			'cta_url'       => $legacy['cta_url'],
			'show_products' => $legacy['show_products'] ? 1 : 0,
			'product_ids'   => $legacy['product_ids'],
			'coupon_code'   => isset( $data['coupon_code'] ) ? sanitize_text_field( $data['coupon_code'] ) : $legacy['coupon_code'],
			'footer'        => $legacy['footer'],
			'layout_json'   => wp_json_encode( $blocks ),
			'is_system'     => empty( $data['is_system'] ) ? 0 : 1,
		);
	}
}
