<?php
/**
 * Admin entry point.
 *
 * @package OtterMail
 */

namespace OtterMail\Admin;

use OtterMail\Core\Settings;
use OtterMail\Core\Capabilities;
use OtterMail\Support\Http;

defined( 'ABSPATH' ) || exit;

class Admin {

	/**
	 * Register admin.
	 */
	public function register() {
		( new Menu() )->register();
		add_action( 'admin_post_ottermail_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Persist settings.
	 */
	public function save_settings() {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Sem permissao.', 'ottermail' ) );
		}
		check_admin_referer( 'ottermail_save_settings' );

		Settings::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		Http::redirect( Http::admin_url( 'ottermail-settings' ), 'saved' );
	}
}
