<?php
/**
 * Deactivation — does not delete data.
 *
 * @package OtterMail
 */

namespace OtterMail\Core;

use OtterMail\Support\Scheduler;

defined( 'ABSPATH' ) || exit;

class Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		Scheduler::unschedule_all();
		flush_rewrite_rules( false );
	}
}
