<?php
/**
 * Future-proof automation trigger contract.
 *
 * @package OtterMail
 */

namespace OtterMail\Automations\Triggers;

defined( 'ABSPATH' ) || exit;

interface TriggerInterface {

	/**
	 * Trigger key stored on the automation.
	 *
	 * @return string
	 */
	public function key();
}
