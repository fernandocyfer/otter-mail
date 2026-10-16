<?php
/**
 * Audience admin actions.
 *
 * @package OtterMail
 */

namespace OtterMail\Audiences;

use OtterMail\Core\Capabilities;
use OtterMail\Support\Http;

defined( 'ABSPATH' ) || exit;

class AudienceController {

	/**
	 * @var AudienceRepository
	 */
	private $repo;

	/**
	 * @var AudienceBuilder
	 */
	private $builder;

	public function __construct() {
		$this->repo    = new AudienceRepository();
		$this->builder = new AudienceBuilder();
	}

	/**
	 * Register handlers.
	 */
	public function register() {
		add_action( 'admin_post_ottermail_save_audience', array( $this, 'save' ) );
		add_action( 'admin_post_ottermail_delete_audience', array( $this, 'delete' ) );
		add_action( 'wp_ajax_ottermail_estimate_audience', array( $this, 'ajax_estimate' ) );
	}

	/**
	 * Save audience.
	 */
	public function save() {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Sem permissao.', 'ottermail' ) );
		}
		check_admin_referer( 'ottermail_save_audience' );

		$id   = Http::int( 'audience_id', 'post' );
		$data = array(
			'name'       => Http::text( 'name', 'post' ),
			'match_type' => Http::text( 'match_type', 'post' ),
			'conditions' => $this->read_conditions(),
		);

		if ( $id ) {
			$this->repo->update( $id, $data );
		} else {
			$id = $this->repo->insert( $data );
		}

		$audience = $this->repo->get( $id );
		if ( $audience ) {
			$count = $this->builder->estimate( $audience );
			$this->repo->save_estimate( $id, $count );
		}

		Http::redirect( Http::admin_url( 'ottermail-audience-edit', array( 'id' => $id ) ), 'saved' );
	}

	/**
	 * Delete audience.
	 */
	public function delete() {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Sem permissao.', 'ottermail' ) );
		}
		check_admin_referer( 'ottermail_delete_audience' );
		$this->repo->delete( Http::int( 'audience_id', 'post' ) );
		Http::redirect( Http::admin_url( 'ottermail-audiences' ), 'deleted' );
	}

	/**
	 * AJAX estimate.
	 */
	public function ajax_estimate() {
		check_ajax_referer( 'ottermail_admin', 'nonce' );
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_send_json_error( array(), 403 );
		}

		$id       = isset( $_POST['audience_id'] ) ? absint( $_POST['audience_id'] ) : 0;
		$audience = $id ? $this->repo->get( $id ) : new Audience();
		if ( ! $audience ) {
			$audience = new Audience();
		}

		$audience->match_type = isset( $_POST['match_type'] ) ? sanitize_key( wp_unslash( $_POST['match_type'] ) ) : $audience->match_type;
		$posted               = $this->read_conditions();
		if ( ! empty( $posted ) ) {
			$audience->conditions = $posted;
		}

		$count = $this->builder->estimate( $audience );
		if ( $id ) {
			$this->repo->save_estimate( $id, $count );
		}

		wp_send_json_success( array( 'count' => $count ) );
	}

	/**
	 * Conditions from POST arrays.
	 *
	 * @return array
	 */
	private function read_conditions() {
		$fields    = isset( $_POST['cond_field'] ) ? wp_unslash( $_POST['cond_field'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$operators = isset( $_POST['cond_operator'] ) ? wp_unslash( $_POST['cond_operator'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$values    = isset( $_POST['cond_value'] ) ? wp_unslash( $_POST['cond_value'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$out = array();
		foreach ( (array) $fields as $i => $field ) {
			$out[] = array(
				'field'    => sanitize_key( $field ),
				'operator' => isset( $operators[ $i ] ) ? sanitize_key( $operators[ $i ] ) : 'eq',
				'value'    => isset( $values[ $i ] ) ? sanitize_text_field( $values[ $i ] ) : '',
			);
		}

		return $out;
	}
}
