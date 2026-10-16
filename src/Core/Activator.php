<?php
/**
 * Activation.
 *
 * @package OtterMail
 */

namespace OtterMail\Core;

use OtterMail\Support\Scheduler;

defined( 'ABSPATH' ) || exit;

class Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		Installer::install();
		Capabilities::add();

		if ( ! get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', false );
		}

		if ( class_exists( 'WooCommerce' ) ) {
			Scheduler::schedule_recurring_cart_check();
		}

		flush_rewrite_rules( false );
	}
}
