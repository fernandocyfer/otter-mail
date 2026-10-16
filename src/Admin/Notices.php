<?php
/**
 * Admin notices.
 *
 * @package OtterMail
 */

namespace OtterMail\Admin;

use OtterMail\Core\Plugin;

defined( 'ABSPATH' ) || exit;

class Notices {

	/**
	 * Register.
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'woocommerce_missing' ) );
		add_action( 'admin_notices', array( $this, 'flash' ) );
	}

	/**
	 * WooCommerce inactive.
	 */
	public function woocommerce_missing() {
		if ( Plugin::instance()->woocommerce_active() ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'OtterMail precisa do WooCommerce ativo. Ative o WooCommerce para usar campanhas, publicos e carrinhos.', 'ottermail' );
		echo '</p></div>';
	}

	/**
	 * Query-flag notices.
	 */
	public function flash() {
		if ( empty( $_GET['ottermail_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$key     = sanitize_key( wp_unslash( $_GET['ottermail_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map     = array(
			'saved'               => array( 'success', __( 'Alteracoes salvas.', 'ottermail' ) ),
			'deleted'             => array( 'success', __( 'Item removido.', 'ottermail' ) ),
			'duplicated'          => array( 'success', __( 'Template duplicado.', 'ottermail' ) ),
			'campaign_sent'       => array( 'success', __( 'Campanha enviada para a fila.', 'ottermail' ) ),
			'campaign_scheduled'  => array( 'success', __( 'Campanha agendada.', 'ottermail' ) ),
			'error_audience'      => array( 'error', __( 'Selecione um publico da loja ou uma lista de newsletter antes de enviar.', 'ottermail' ) ),
			'error_list'          => array( 'error', __( 'Lista nao encontrada.', 'ottermail' ) ),
			'error_contact'       => array( 'error', __( 'Informe um email valido. Duplicados sao ignorados.', 'ottermail' ) ),
			'error_csv'           => array( 'error', __( 'Nao foi possivel ler o arquivo CSV.', 'ottermail' ) ),
			'contact_added'       => array( 'success', __( 'Contato adicionado a lista.', 'ottermail' ) ),
			'csv_imported'        => array( 'success', __( 'CSV importado. Emails duplicados foram ignorados.', 'ottermail' ) ),
			'elementor_synced'    => array( 'success', __( 'Submissoes do Elementor sincronizadas.', 'ottermail' ) ),
			'error_content'       => array( 'error', __( 'Assunto e template sao obrigatorios.', 'ottermail' ) ),
			'error_schedule'      => array( 'error', __( 'Horario de agendamento invalido.', 'ottermail' ) ),
			'error_no_template'   => array( 'error', __( 'Selecione um template para personalizar ou copiar.', 'ottermail' ) ),
			'template_copy_ready' => array( 'success', __( 'Copia criada. Personalize o layout sem alterar o original.', 'ottermail' ) ),
		);

		if ( ! isset( $map[ $key ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $key ][0] ),
			esc_html( $map[ $key ][1] )
		);
	}
}
