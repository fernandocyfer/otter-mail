<?php
/**
 * Domain event logger.
 *
 * @package OtterMail
 */

namespace OtterMail\Support;

defined( 'ABSPATH' ) || exit;

class Logger {

	/**
	 * Insert a log row.
	 *
	 * @param string $event_type  Event key.
	 * @param string $message     Human message.
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param array  $context     Extra context (no secrets).
	 */
	public static function log( $event_type, $message, $object_type = '', $object_id = 0, array $context = array() ) {
		global $wpdb;

		$wpdb->insert(
			Tables::name( Tables::LOGS ),
			array(
				'event_type'  => sanitize_key( $event_type ),
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => $object_id ? absint( $object_id ) : null,
				'message'     => sanitize_textarea_field( $message ),
				'context'     => $context ? wp_json_encode( $context ) : null,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recent logs.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function recent( $limit = 10 ) {
		global $wpdb;

		$table = Tables::name( Tables::LOGS );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $limit )
			)
		);
	}

	/**
	 * Activity for one domain object, oldest first for timelines.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param int    $limit       Limit.
	 * @return array
	 */
	public static function for_object( $object_type, $object_id, $limit = 30 ) {
		global $wpdb;

		$table = Tables::name( Tables::LOGS );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_type = %s AND object_id = %d ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				sanitize_key( $object_type ),
				absint( $object_id ),
				absint( $limit )
			)
		);
	}

	/**
	 * Remove activity recorded for one domain object.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 */
	public static function delete_for_object( $object_type, $object_id ) {
		global $wpdb;

		$wpdb->delete(
			Tables::name( Tables::LOGS ),
			array(
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => absint( $object_id ),
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Human label for a stored event type.
	 *
	 * @param string $event_type Event key.
	 * @return string
	 */
	public static function event_label( $event_type ) {
		$labels = array(
			'campaign_created'       => __( 'Campanha criada', 'ottermail' ),
			'campaign_started'       => __( 'Campanha iniciada', 'ottermail' ),
			'campaign_queued'        => __( 'Campanha na fila', 'ottermail' ),
			'campaign_completed'     => __( 'Campanha concluída', 'ottermail' ),
			'email_failed'           => __( 'Falha de envio', 'ottermail' ),
			'test_email_sent'        => __( 'Email de teste enviado', 'ottermail' ),
			'unsubscribe'            => __( 'Descadastro', 'ottermail' ),
			'cart_abandoned'         => __( 'Carrinho abandonado', 'ottermail' ),
			'checkout_abandoned'     => __( 'Checkout abandonado', 'ottermail' ),
			'cart_recovered'         => __( 'Carrinho restaurado', 'ottermail' ),
			'cart_converted'         => __( 'Carrinho convertido', 'ottermail' ),
			'automation_enrolled'    => __( 'Automação disparada', 'ottermail' ),
			'automation_email_sent'  => __( 'Email de automação', 'ottermail' ),
			'automation_email_retry_scheduled' => __( 'Nova tentativa de automação', 'ottermail' ),
			'automation_enrollment_failed' => __( 'Automação falhou', 'ottermail' ),
			'automation_rate_limited' => __( 'Automação limitada', 'ottermail' ),
			'campaign_rate_limited'  => __( 'Campanha limitada', 'ottermail' ),
			'post_purchase_enrolled' => __( 'Pós-compra iniciada', 'ottermail' ),
			'list_created'           => __( 'Lista criada', 'ottermail' ),
			'list_imported'          => __( 'CSV importado', 'ottermail' ),
		);

		$key = sanitize_key( $event_type );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $event_type;
	}

	/**
	 * Visual tone for an event (dashboard only).
	 *
	 * @param string $event_type Event key.
	 * @return string
	 */
	public static function event_tone( $event_type ) {
		switch ( sanitize_key( $event_type ) ) {
			case 'campaign_completed':
			case 'cart_converted':
			case 'cart_recovered':
			case 'test_email_sent':
				return 'success';
			case 'email_failed':
			case 'automation_enrollment_failed':
				return 'danger';
			case 'cart_abandoned':
			case 'unsubscribe':
				return 'warning';
			default:
				return 'neutral';
		}
	}
}
