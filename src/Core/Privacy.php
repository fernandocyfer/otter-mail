<?php
/**
 * WordPress privacy exporter/eraser hooks (prepared for V2 expansion).
 *
 * @package OtterMail
 */

namespace OtterMail\Core;

defined( 'ABSPATH' ) || exit;

class Privacy {

	/**
	 * Register WP privacy hooks.
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['ottermail'] = array(
			'exporter_friendly_name' => __( 'OtterMail', 'ottermail' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['ottermail'] = array(
			'eraser_friendly_name' => __( 'OtterMail', 'ottermail' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export personal data for an email.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public function export( $email ) {
		global $wpdb;

		$email = sanitize_email( $email );
		$items = array();

		$carts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, status, cart_total, created_at FROM ' . \OtterMail\Support\Tables::name( \OtterMail\Support\Tables::CARTS ) . ' WHERE email = %s',
				$email
			)
		);

		foreach ( $carts as $cart ) {
			$items[] = array(
				'group_id'    => 'ottermail-carts',
				'group_label' => __( 'Carrinhos OtterMail', 'ottermail' ),
				'item_id'     => 'cart-' . $cart->id,
				'data'        => array(
					array(
						'name'  => __( 'Status', 'ottermail' ),
						'value' => $cart->status,
					),
					array(
						'name'  => __( 'Total', 'ottermail' ),
						'value' => $cart->cart_total,
					),
				),
			);
		}

		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Anonymize carts for an email (does not delete campaign history).
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public function erase( $email ) {
		global $wpdb;

		$email = strtolower( sanitize_email( $email ) );
		if ( ! $email ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array( __( 'Email inválido.', 'ottermail' ) ),
				'done'           => true,
			);
		}

		$anonymous   = 'deleted+' . substr( hash_hmac( 'sha256', $email, wp_salt( 'auth' ) ), 0, 24 ) . '@example.invalid';
		$carts       = \OtterMail\Support\Tables::name( \OtterMail\Support\Tables::CARTS );
		$recipients  = \OtterMail\Support\Tables::name( \OtterMail\Support\Tables::RECIPIENTS );
		$contacts    = \OtterMail\Support\Tables::name( \OtterMail\Support\Tables::LIST_CONTACTS );
		$enrollments = \OtterMail\Support\Tables::name( \OtterMail\Support\Tables::AUTOMATION_ENROLLMENTS );
		$logs        = \OtterMail\Support\Tables::name( \OtterMail\Support\Tables::LOGS );
		$clicks      = \OtterMail\Support\Tables::name( \OtterMail\Support\Tables::CLICKS );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$carts} SET email = %s, first_name = '', cart_contents = NULL WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$anonymous,
				$email
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$recipients} SET email = %s, first_name = '', last_name = '', error_message = NULL WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$anonymous,
				$email
			)
		);
		$wpdb->delete( $contacts, array( 'email' => $email ), array( '%s' ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$enrollments} SET email = %s, cancelled_at = IF(status = 'active', %s, cancelled_at), status = IF(status = 'active', 'cancelled', status), last_error = NULL, locked_at = NULL WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$anonymous,
				current_time( 'mysql' ),
				$email
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$clicks} SET email = %s WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$anonymous,
				$email
			)
		);

		$like = '%' . $wpdb->esc_like( $email ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$logs} WHERE message LIKE %s OR context LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$like,
				$like
			)
		);

		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array(
				__( 'Registros de supressão foram preservados para impedir novos envios ao endereço descadastrado.', 'ottermail' ),
			),
			'done'           => true,
		);
	}
}
