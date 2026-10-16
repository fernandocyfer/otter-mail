<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package OtterMail
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'ottermail_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$tables = array(
	'campaigns',
	'campaign_recipients',
	'audiences',
	'templates',
	'automations',
	'automation_steps',
	'automation_enrollments',
	'carts',
	'lists',
	'list_contacts',
	'suppressions',
		'logs',
		'delivery_rate',
		'clicks',
);

foreach ( $tables as $suffix ) {
	$table = $wpdb->prefix . 'ottermail_' . $suffix;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'ottermail_settings' );
delete_option( 'ottermail_schema_version' );

$role = get_role( 'administrator' );
if ( $role ) {
	$role->remove_cap( 'manage_ottermail' );
	$role->remove_cap( 'send_ottermail_campaigns' );
	$role->remove_cap( 'manage_ottermail_templates' );
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	foreach ( array(
		'ottermail_process_campaign',
		'ottermail_send_campaign_batch',
		'ottermail_check_abandoned_carts',
		'ottermail_process_automation_step',
		'ottermail_send_test_email',
	) as $hook ) {
		as_unschedule_all_actions( $hook, null, 'ottermail' );
	}
}
