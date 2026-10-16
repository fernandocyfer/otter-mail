<?php
/**
 * Custom capabilities.
 *
 * @package OtterMail
 */

namespace OtterMail\Core;

defined( 'ABSPATH' ) || exit;

class Capabilities {

	const MANAGE     = 'manage_ottermail';
	const SEND       = 'send_ottermail_campaigns';
	const TEMPLATES  = 'manage_ottermail_templates';

	/**
	 * Grant caps to administrators.
	 */
	public static function add() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		$role->add_cap( self::MANAGE );
		$role->add_cap( self::SEND );
		$role->add_cap( self::TEMPLATES );
	}

	/**
	 * Remove caps from administrators.
	 */
	public static function remove() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		$role->remove_cap( self::MANAGE );
		$role->remove_cap( self::SEND );
		$role->remove_cap( self::TEMPLATES );
	}

	/**
	 * Current user can manage the plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( self::MANAGE ) || current_user_can( 'manage_options' );
	}

	/**
	 * Current user can send campaigns.
	 *
	 * @return bool
	 */
	public static function current_user_can_send() {
		return current_user_can( self::SEND ) || current_user_can( 'manage_options' );
	}

	/**
	 * Current user can manage templates.
	 *
	 * @return bool
	 */
	public static function current_user_can_templates() {
		return current_user_can( self::TEMPLATES ) || current_user_can( 'manage_options' );
	}
}
