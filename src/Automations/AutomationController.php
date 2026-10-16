<?php
/**
 * Automation admin actions.
 *
 * @package OtterMail
 */

namespace OtterMail\Automations;

use OtterMail\Core\Capabilities;
use OtterMail\Support\Http;

defined( 'ABSPATH' ) || exit;

class AutomationController {

	/**
	 * @var AutomationRepository
	 */
	private $repo;

	public function __construct() {
		$this->repo = new AutomationRepository();
	}

	/**
	 * Register handlers.
	 */
	public function register() {
		add_action( 'admin_post_ottermail_save_automation', array( $this, 'save' ) );
	}

	/**
	 * Save automation + steps.
	 */
	public function save() {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Sem permissao.', 'ottermail' ) );
		}
		check_admin_referer( 'ottermail_save_automation' );

		$id   = Http::int( 'automation_id', 'post' );
		$data = array(
			'name'         => Http::text( 'name', 'post' ),
			'status'       => Http::text( 'status', 'post' ),
			'trigger_type' => Http::text( 'trigger_type', 'post' ),
		);
		if ( ! in_array( $data['trigger_type'], array( 'cart_abandoned', 'checkout_abandoned', 'post_purchase' ), true ) ) {
			$data['trigger_type'] = 'cart_abandoned';
		}

		if ( $id ) {
			$this->repo->update( $id, $data );
		} else {
			$id = $this->repo->insert( $data );
		}

		$delays     = isset( $_POST['step_delay'] ) ? wp_unslash( $_POST['step_delay'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$units      = isset( $_POST['step_unit'] ) ? wp_unslash( $_POST['step_unit'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$templates  = isset( $_POST['step_template'] ) ? wp_unslash( $_POST['step_template'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$steps      = array();

		foreach ( (array) $templates as $i => $template_id ) {
			$template_id = absint( $template_id );
			if ( ! $template_id ) {
				continue;
			}
			$steps[] = array(
				'delay_value' => isset( $delays[ $i ] ) ? absint( $delays[ $i ] ) : 1,
				'delay_unit'  => isset( $units[ $i ] ) ? sanitize_key( $units[ $i ] ) : 'hours',
				'template_id' => $template_id,
			);
		}

		$this->repo->save_steps( $id, $steps );
		Http::redirect( Http::admin_url( 'ottermail-automation-edit', array( 'id' => $id ) ), 'saved' );
	}
}
