<?php
/**
 * Template admin actions.
 *
 * @package OtterMail
 */

namespace OtterMail\Templates;

use OtterMail\Campaigns\CampaignRepository;
use OtterMail\Core\Capabilities;
use OtterMail\Support\Http;

defined( 'ABSPATH' ) || exit;

class TemplateController {

	/**
	 * @var TemplateRepository
	 */
	private $repo;

	public function __construct() {
		$this->repo = new TemplateRepository();
	}

	/**
	 * Register handlers.
	 */
	public function register() {
		add_action( 'admin_post_ottermail_save_template', array( $this, 'save' ) );
		add_action( 'admin_post_ottermail_template_action', array( $this, 'action' ) );
		add_action( 'wp_ajax_ottermail_preview_template', array( $this, 'ajax_preview' ) );
	}

	/**
	 * Save template.
	 */
	public function save() {
		if ( ! Capabilities::current_user_can_templates() ) {
			wp_die( esc_html__( 'Sem permissao.', 'ottermail' ) );
		}
		check_admin_referer( 'ottermail_save_template' );

		$id   = Http::int( 'template_id', 'post' );
		$data = array(
			'name'          => Http::text( 'name', 'post' ),
			'category'      => Http::text( 'category', 'post' ),
			'subject'       => Http::text( 'subject', 'post' ),
			'preheader'     => Http::text( 'preheader', 'post' ),
			'logo_url'      => Http::text( 'logo_url', 'post' ),
			'heading'       => Http::text( 'heading', 'post' ),
			'body'          => isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '',
			'cta_label'     => Http::text( 'cta_label', 'post' ),
			'cta_url'       => Http::text( 'cta_url', 'post' ),
			'show_products' => empty( $_POST['show_products'] ) || '0' === (string) $_POST['show_products'] ? 0 : 1,
			'product_ids'   => Http::text( 'product_ids', 'post' ),
			'coupon_code'   => Http::text( 'coupon_code', 'post' ),
			'footer'        => isset( $_POST['footer'] ) ? wp_kses_post( wp_unslash( $_POST['footer'] ) ) : '',
			'layout_json'   => isset( $_POST['layout_json'] ) ? wp_unslash( $_POST['layout_json'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);

		if ( $id ) {
			$this->repo->update( $id, $data );
		} else {
			$data['slug']      = sanitize_title( $data['name'] . '-' . wp_generate_password( 4, false, false ) );
			$data['is_system'] = 0;
			$id                = $this->repo->insert( $data );
		}

		$return = Http::int( 'return_campaign', 'post' );
		if ( $return && $id ) {
			$campaigns = new CampaignRepository();
			if ( $campaigns->get( $return ) ) {
				$campaigns->patch( $return, array( 'template_id' => $id ) );
			}
			Http::redirect(
				Http::admin_url(
					'ottermail-campaign-edit',
					array(
						'id'   => $return,
						'step' => 2,
					)
				),
				'saved'
			);
		}

		Http::redirect( Http::admin_url( 'ottermail-template-edit', array( 'id' => $id ) ), 'saved' );
	}

	/**
	 * Duplicate / delete.
	 */
	public function action() {
		if ( ! Capabilities::current_user_can_templates() ) {
			wp_die( esc_html__( 'Sem permissao.', 'ottermail' ) );
		}
		check_admin_referer( 'ottermail_template_action' );

		$id     = Http::int( 'template_id', 'post' );
		$action = Http::text( 'do', 'post' );
		$return = Http::int( 'return_campaign', 'post' );

		if ( 'duplicate' === $action ) {
			$new  = $this->repo->duplicate( $id );
			$args = array( 'id' => $new );
			if ( $return && $new ) {
				$args['return_campaign'] = $return;
				$campaigns               = new CampaignRepository();
				if ( $campaigns->get( $return ) ) {
					$campaigns->patch( $return, array( 'template_id' => $new ) );
				}
			}
			Http::redirect( Http::admin_url( 'ottermail-template-edit', $args ), 'duplicated' );
		}

		if ( 'delete' === $action ) {
			$this->repo->delete( $id );
			if ( $return ) {
				$campaigns = new CampaignRepository();
				$campaign  = $campaigns->get( $return );
				if ( $campaign && (int) $campaign->template_id === $id ) {
					$campaigns->patch( $return, array( 'template_id' => 0 ) );
				}
				Http::redirect(
					Http::admin_url(
						'ottermail-campaign-edit',
						array(
							'id'   => $return,
							'step' => 2,
						)
					),
					'deleted'
				);
			}
		}

		Http::redirect( Http::admin_url( 'ottermail-templates' ), 'saved' );
	}

	/**
	 * Render HTML preview from saved template and/or current form fields.
	 */
	public function ajax_preview() {
		check_ajax_referer( 'ottermail_admin', 'nonce' );
		if ( ! Capabilities::current_user_can_templates() ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissao.', 'ottermail' ) ), 403 );
		}

		$template = $this->hydrate_from_request();
		$renderer = new TemplateRenderer();
		$html     = $renderer->render( $template, Variables::sample_context() );

		wp_send_json_success(
			array(
				'html'    => $html,
				'subject' => $renderer->subject( $template->subject, Variables::sample_context() ),
			)
		);
	}

	/**
	 * Build a Template from POST (live form) with DB fallback.
	 *
	 * @return Template
	 */
	private function hydrate_from_request() {
		$id       = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$template = $id ? $this->repo->get( $id ) : null;
		if ( ! $template ) {
			$template = new Template();
		}

		$map = array(
			'name'       => 'sanitize_text_field',
			'subject'    => 'sanitize_text_field',
			'preheader'  => 'sanitize_text_field',
			'logo_url'   => 'esc_url_raw',
			'heading'    => 'sanitize_text_field',
			'cta_label'  => 'sanitize_text_field',
			'cta_url'    => 'esc_url_raw',
			'coupon_code'=> 'sanitize_text_field',
		);

		foreach ( $map as $field => $callback ) {
			if ( isset( $_POST[ $field ] ) ) {
				$template->{$field} = $callback( wp_unslash( $_POST[ $field ] ) );
			}
		}

		if ( isset( $_POST['body'] ) ) {
			$template->body = wp_kses_post( wp_unslash( $_POST['body'] ) );
		}
		if ( isset( $_POST['footer'] ) ) {
			$template->footer = wp_kses_post( wp_unslash( $_POST['footer'] ) );
		}
		if ( isset( $_POST['show_products'] ) ) {
			$template->show_products = empty( $_POST['show_products'] ) || '0' === (string) $_POST['show_products'] ? 0 : 1;
		}
		if ( isset( $_POST['product_ids'] ) ) {
			$template->product_ids = Template::parse_product_ids( wp_unslash( $_POST['product_ids'] ) );
		}
		$coupon_code = $template->coupon_code;
		if ( isset( $_POST['layout_json'] ) ) {
			$template->layout_json = wp_json_encode( Layout::ensure_structure( Layout::sanitize( wp_unslash( $_POST['layout_json'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$legacy                = Layout::legacy_fields( $template->blocks() );
			$template->logo_url    = $legacy['logo_url'];
			$template->heading     = $legacy['heading'];
			$template->body        = $legacy['body'];
			$template->cta_label   = $legacy['cta_label'];
			$template->cta_url     = $legacy['cta_url'];
			$template->show_products = $legacy['show_products'] ? 1 : 0;
			$template->product_ids = Template::parse_product_ids( $legacy['product_ids'] );
			$template->coupon_code = $coupon_code;
			$template->footer      = $legacy['footer'];
		}

		return $template;
	}
}
