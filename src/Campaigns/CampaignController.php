<?php
/**
 * Campaign admin actions.
 *
 * @package OtterMail
 */

namespace OtterMail\Campaigns;

use OtterMail\Core\Capabilities;
use OtterMail\Core\Settings;
use OtterMail\Support\Http;
use OtterMail\Support\Logger;
use OtterMail\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

class CampaignController {

	/**
	 * @var CampaignRepository
	 */
	private $repo;

	/**
	 * @var CampaignService
	 */
	private $service;

	public function __construct() {
		$this->repo    = new CampaignRepository();
		$this->service = new CampaignService();
	}

	/**
	 * Register handlers.
	 */
	public function register() {
		add_action( 'admin_post_ottermail_save_campaign', array( $this, 'save' ) );
		add_action( 'admin_post_ottermail_campaign_action', array( $this, 'action' ) );
		add_action( 'wp_ajax_ottermail_send_test', array( $this, 'ajax_test' ) );
	}

	/**
	 * Save campaign from wizard.
	 */
	public function save() {
		$this->guard();
		check_admin_referer( 'ottermail_save_campaign' );

		$id   = Http::int( 'campaign_id', 'post' );
		$data = array(
			'name'        => Http::text( 'name', 'post' ),
			'subject'     => Http::text( 'subject', 'post' ),
			'preheader'   => Http::text( 'preheader', 'post' ),
			'from_name'   => Http::text( 'from_name', 'post' ),
			'from_email'  => Http::text( 'from_email', 'post' ),
			'reply_to'    => Http::text( 'reply_to', 'post' ),
			'template_id' => Http::int( 'template_id', 'post' ),
			'audience_id' => Http::int( 'audience_id', 'post' ),
			'list_id'     => Http::int( 'list_id', 'post' ),
		);

		$source = Http::text( 'recipient_source', 'post' );
		if ( 'list' === $source ) {
			$data['audience_id'] = 0;
		} elseif ( 'audience' === $source ) {
			$data['list_id'] = 0;
		}

		if ( ! $data['from_name'] ) {
			$data['from_name'] = Settings::get( 'from_name' );
		}
		if ( ! $data['from_email'] ) {
			$data['from_email'] = Settings::get( 'from_email' );
		}

		if ( $id ) {
			$this->repo->update( $id, $data );
		} else {
			$id = $this->repo->insert( $data );
			Logger::log( 'campaign_created', __( 'Campanha criada.', 'ottermail' ), 'campaign', $id );
		}

		$step = Http::text( 'wizard_step', 'post' );
		$do   = Http::text( 'ottermail_do', 'post' );

		if ( in_array( $do, array( 'customize_template', 'copy_template', 'new_template' ), true ) ) {
			$this->open_template_editor( $id, $do, (int) $data['template_id'] );
		}

		if ( 'send_now' === $do ) {
			if ( ! Capabilities::current_user_can_send() ) {
				wp_die( esc_html__( 'Sem permissao para enviar.', 'ottermail' ) );
			}
			$result = $this->service->send_now( $id );
			if ( is_wp_error( $result ) ) {
				Http::redirect( Http::admin_url( 'ottermail-campaign-edit', array( 'id' => $id, 'step' => 4 ) ), 'error_' . $result->get_error_code() );
			}
			Http::redirect( Http::admin_url( 'ottermail-campaigns' ), 'campaign_sent' );
		}

		if ( 'schedule' === $do ) {
			if ( ! Capabilities::current_user_can_send() ) {
				wp_die( esc_html__( 'Sem permissao para agendar envios.', 'ottermail' ) );
			}
			$when = Http::text( 'scheduled_at', 'post' );
			$result = $this->service->schedule( $id, $when );
			if ( is_wp_error( $result ) ) {
				Http::redirect( Http::admin_url( 'ottermail-campaign-edit', array( 'id' => $id, 'step' => 4 ) ), 'error_schedule' );
			}
			Http::redirect( Http::admin_url( 'ottermail-campaigns' ), 'campaign_scheduled' );
		}

		$args = array( 'id' => $id );
		if ( $step ) {
			$args['step'] = $step;
		}

		Http::redirect( Http::admin_url( 'ottermail-campaign-edit', $args ), 'saved' );
	}

	/**
	 * Pause / cancel / delete.
	 */
	public function action() {
		$this->guard();
		check_admin_referer( 'ottermail_campaign_action' );

		$id     = Http::int( 'campaign_id', 'post' );
		$action = Http::text( 'do', 'post' );

		if ( 'pause' === $action ) {
			$this->service->pause( $id );
		} elseif ( 'cancel' === $action ) {
			$this->service->cancel( $id );
		} elseif ( 'delete' === $action ) {
			$this->repo->delete( $id );
			Http::redirect( Http::admin_url( 'ottermail-campaigns' ), 'deleted' );
		}

		Http::redirect( Http::admin_url( 'ottermail-campaigns' ), 'saved' );
	}

	/**
	 * AJAX test send.
	 */
	public function ajax_test() {
		check_ajax_referer( 'ottermail_admin', 'nonce' );
		if ( ! Capabilities::current_user_can_send() ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissao.', 'ottermail' ) ), 403 );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$to          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$subject     = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';

		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => __( 'Selecione um template antes de enviar o teste.', 'ottermail' ) ) );
		}

		$result = $this->service->send_test( $template_id, $to, $subject );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Falha no envio de teste. Veja a atividade recente no dashboard.', 'ottermail' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Email de teste enviado. Isso só confirma que o transporte local aceitou a mensagem.', 'ottermail' ) ) );
	}

	/**
	 * Open the template editor from the campaign wizard.
	 *
	 * System layouts are copied before editing so originals stay intact.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $do          Action.
	 * @param int    $template_id Selected template ID.
	 */
	private function open_template_editor( $campaign_id, $do, $template_id ) {
		$templates = new TemplateRepository();

		if ( 'new_template' === $do ) {
			Http::redirect(
				Http::admin_url(
					'ottermail-template-edit',
					array( 'return_campaign' => $campaign_id )
				)
			);
		}

		if ( $template_id < 1 ) {
			Http::redirect(
				Http::admin_url( 'ottermail-campaign-edit', array( 'id' => $campaign_id, 'step' => 2 ) ),
				'error_no_template'
			);
		}

		$tpl = $templates->get( $template_id );
		if ( ! $tpl ) {
			Http::redirect(
				Http::admin_url( 'ottermail-campaign-edit', array( 'id' => $campaign_id, 'step' => 2 ) ),
				'error_no_template'
			);
		}

		$needs_copy = ( 'copy_template' === $do ) || ( 'customize_template' === $do && $tpl->is_system );
		if ( $needs_copy ) {
			$template_id = $templates->duplicate( $template_id );
			if ( ! $template_id ) {
				Http::redirect(
					Http::admin_url( 'ottermail-campaign-edit', array( 'id' => $campaign_id, 'step' => 2 ) ),
					'error_no_template'
				);
			}
			$this->repo->patch( $campaign_id, array( 'template_id' => $template_id ) );
			Http::redirect(
				Http::admin_url(
					'ottermail-template-edit',
					array(
						'id'              => $template_id,
						'return_campaign' => $campaign_id,
					)
				),
				'template_copy_ready'
			);
		}

		Http::redirect(
			Http::admin_url(
				'ottermail-template-edit',
				array(
					'id'              => $template_id,
					'return_campaign' => $campaign_id,
				)
			)
		);
	}

	/**
	 * Capability gate.
	 */
	private function guard() {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Sem permissao.', 'ottermail' ) );
		}
	}
}
