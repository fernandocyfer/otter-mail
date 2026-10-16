<?php
/**
 * Front and admin assets — loaded only on OtterMail screens.
 *
 * @package OtterMail
 */

namespace OtterMail\Core;

defined( 'ABSPATH' ) || exit;

class Assets {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
	}

	/**
	 * Extra body class so CSS never leaks to other plugins.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( $this->is_ottermail_screen() ) {
			$classes .= ' ottermail-admin';
		}

		return $classes;
	}

	/**
	 * Admin CSS/JS on OtterMail screens only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function admin_assets( $hook ) {
		if ( ! $this->is_ottermail_screen( $hook ) ) {
			return;
		}

		$page = $this->current_page();
		$css  = OTTERMAIL_PATH . 'assets/css/admin.css';
		$js   = OTTERMAIL_PATH . 'assets/js/admin.js';

		wp_enqueue_style(
			'ottermail-admin',
			OTTERMAIL_URL . 'assets/css/admin.css',
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : OTTERMAIL_VERSION
		);

		wp_enqueue_script(
			'ottermail-admin',
			OTTERMAIL_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			file_exists( $js ) ? (string) filemtime( $js ) : OTTERMAIL_VERSION,
			true
		);

		$needs_media  = in_array( $page, array( 'ottermail-template-edit', 'ottermail-settings' ), true );
		$needs_layout = 'ottermail-template-edit' === $page;

		if ( $needs_media ) {
			wp_enqueue_media();
		}

		wp_localize_script(
			'ottermail-admin',
			'ottermailAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ottermail_admin' ),
				'i18n'    => array(
					'confirmSend'  => __( 'Enviar esta campanha agora para os destinatários selecionados?', 'ottermail' ),
					'estimating'   => __( 'Calculando estimativa…', 'ottermail' ),
					'sending'      => __( 'Enviando…', 'ottermail' ),
					'selectLogo'   => __( 'Selecionar imagem', 'ottermail' ),
					'useLogo'      => __( 'Usar esta imagem', 'ottermail' ),
					'previewError' => __( 'Não foi possível gerar o preview.', 'ottermail' ),
					'testEmail'    => __( 'Enviar teste', 'ottermail' ),
				),
			)
		);

		if ( $needs_layout ) {
			$layout_js = OTTERMAIL_PATH . 'assets/js/layout-editor.js';
			wp_enqueue_script(
				'ottermail-layout-editor',
				OTTERMAIL_URL . 'assets/js/layout-editor.js',
				array( 'jquery', 'ottermail-admin' ),
				file_exists( $layout_js ) ? (string) filemtime( $layout_js ) : OTTERMAIL_VERSION,
				true
			);
			wp_localize_script(
				'ottermail-layout-editor',
				'ottermailLayout',
				array(
					'types' => \OtterMail\Templates\Layout::catalog(),
					'i18n'  => array(
						'empty'        => __( 'Adicione uma seção para começar o layout.', 'ottermail' ),
						'drag'         => __( 'Arrastar', 'ottermail' ),
						'remove'       => __( 'Remover', 'ottermail' ),
						'moveUp'       => __( 'Subir', 'ottermail' ),
						'moveDown'     => __( 'Descer', 'ottermail' ),
						'selectImage'  => __( 'Selecionar imagem', 'ottermail' ),
						'removeImage'  => __( 'Remover', 'ottermail' ),
						'alt'          => __( 'Texto alternativo', 'ottermail' ),
						'heading'      => __( 'Título', 'ottermail' ),
						'html'         => __( 'Conteúdo', 'ottermail' ),
						'footer'       => __( 'Rodapé', 'ottermail' ),
						'buttonLabel'  => __( 'Texto do botão', 'ottermail' ),
						'buttonUrl'    => __( 'Link do botão', 'ottermail' ),
						'imageUrl'     => __( 'URL da imagem', 'ottermail' ),
						'productIds'   => __( 'IDs de produtos, separados por vírgula', 'ottermail' ),
						'coupon'       => __( 'Cupom WooCommerce', 'ottermail' ),
						'couponPlaceholder' => __( 'Código do cupom criado no WooCommerce', 'ottermail' ),
						'spacer'       => __( 'Altura (px)', 'ottermail' ),
						'dividerHint'  => __( 'Linha divisória no email.', 'ottermail' ),
						'background'   => __( 'Fundo', 'ottermail' ),
						'padding'      => __( 'Espaçamento (px)', 'ottermail' ),
						'columns'      => __( 'Colunas', 'ottermail' ),
						'color'        => __( 'Cor', 'ottermail' ),
						'fontSize'     => __( 'Tamanho (px)', 'ottermail' ),
						'align'        => __( 'Alinhamento', 'ottermail' ),
						'alignLeft'    => __( 'Esquerda', 'ottermail' ),
						'alignCenter'  => __( 'Centro', 'ottermail' ),
						'alignRight'   => __( 'Direita', 'ottermail' ),
						'imageWidth'   => __( 'Largura (%)', 'ottermail' ),
						'logoWidth'    => __( 'Largura (px)', 'ottermail' ),
						'buttonBg'     => __( 'Fundo do botão', 'ottermail' ),
						'buttonColor'  => __( 'Cor do texto', 'ottermail' ),
						'fullWidth'    => __( 'Largura total', 'ottermail' ),
						'dropContent'  => __( 'Solte conteúdo aqui', 'ottermail' ),
						'dropRow'      => __( 'Solte uma linha aqui', 'ottermail' ),
						'widths'       => array(
							'100'      => __( '1 coluna', 'ottermail' ),
							'50-50'    => __( '50 / 50', 'ottermail' ),
							'33-33-33' => __( '33 / 33 / 33', 'ottermail' ),
							'70-30'    => __( '70 / 30', 'ottermail' ),
							'30-70'    => __( '30 / 70', 'ottermail' ),
							'60-40'    => __( '60 / 40', 'ottermail' ),
							'40-60'    => __( '40 / 60', 'ottermail' ),
						),
					),
				)
			);
		}
	}

	/**
	 * Cart capture script on checkout.
	 */
	public function frontend_assets() {
		if ( ! Settings::get( 'cart_capture_enabled' ) ) {
			return;
		}

		if ( is_admin() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'ottermail-cart-capture',
			OTTERMAIL_URL . 'assets/js/cart-capture.js',
			array( 'jquery' ),
			OTTERMAIL_VERSION,
			true
		);

		wp_localize_script(
			'ottermail-cart-capture',
			'ottermailCart',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ottermail_capture_email' ),
			)
		);
	}

	/**
	 * Whether the current admin request is an OtterMail page.
	 *
	 * @param string $hook Optional hook suffix.
	 * @return bool
	 */
	private function is_ottermail_screen( $hook = '' ) {
		$page = $this->current_page();
		if ( $page && 0 === strpos( $page, 'ottermail' ) ) {
			return true;
		}

		return $hook && false !== strpos( (string) $hook, 'ottermail' );
	}

	/**
	 * @return string
	 */
	private function current_page() {
		if ( empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}

		return sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
