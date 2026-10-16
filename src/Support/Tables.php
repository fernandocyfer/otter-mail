<?php
/**
 * Table name helper.
 *
 * @package OtterMail
 */

namespace OtterMail\Support;

defined( 'ABSPATH' ) || exit;

class Tables {

	const CAMPAIGNS             = 'campaigns';
	const RECIPIENTS            = 'campaign_recipients';
	const AUDIENCES             = 'audiences';
	const TEMPLATES             = 'templates';
	const AUTOMATIONS           = 'automations';
	const AUTOMATION_STEPS      = 'automation_steps';
	const AUTOMATION_ENROLLMENTS = 'automation_enrollments';
	const CARTS                 = 'carts';
	const LISTS                 = 'lists';
	const LIST_CONTACTS         = 'list_contacts';
	const SUPPRESSIONS          = 'suppressions';
	const LOGS                  = 'logs';
	const DELIVERY_RATE         = 'delivery_rate';
	const CLICKS                = 'clicks';

	/**
	 * Full table name with $wpdb prefix.
	 *
	 * @param string $suffix Suffix without prefix.
	 * @return string
	 */
	public static function name( $suffix ) {
		global $wpdb;

		return $wpdb->prefix . 'ottermail_' . $suffix;
	}
}
