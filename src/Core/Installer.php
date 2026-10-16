<?php
/**
 * Database installer and schema upgrades.
 *
 * @package OtterMail
 */

namespace OtterMail\Core;

use OtterMail\Support\Tables;
use OtterMail\Templates\Presets;
use OtterMail\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

class Installer {

	const SCHEMA_VERSION = 7;
	const OPTION_VERSION = 'ottermail_schema_version';

	/**
	 * Install or upgrade schema.
	 */
	public static function install() {
		self::create_tables();
		self::seed_templates();
		update_option( self::OPTION_VERSION, self::SCHEMA_VERSION, true );
	}

	/**
	 * Upgrade when plugin loads and version differs.
	 */
	public static function maybe_upgrade() {
		$current = (int) get_option( self::OPTION_VERSION, 0 );
		if ( $current < self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create custom tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$sql = array();

		$campaigns = Tables::name( Tables::CAMPAIGNS );
		$sql[]     = "CREATE TABLE {$campaigns} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			subject varchar(255) NOT NULL DEFAULT '',
			preheader varchar(255) NOT NULL DEFAULT '',
			from_name varchar(190) NOT NULL DEFAULT '',
			from_email varchar(190) NOT NULL DEFAULT '',
			reply_to varchar(190) NOT NULL DEFAULT '',
			template_id bigint(20) unsigned NULL,
			audience_id bigint(20) unsigned NULL,
			list_id bigint(20) unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			scheduled_at datetime NULL,
			started_at datetime NULL,
			sent_at datetime NULL,
			completed_at datetime NULL,
			recipients_count int(10) unsigned NOT NULL DEFAULT 0,
			sent_count int(10) unsigned NOT NULL DEFAULT 0,
			failed_count int(10) unsigned NOT NULL DEFAULT 0,
			skipped_count int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) {$charset};";

		$recipients = Tables::name( Tables::RECIPIENTS );
		$sql[]      = "CREATE TABLE {$recipients} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NULL,
			email varchar(190) NOT NULL,
			first_name varchar(190) NOT NULL DEFAULT '',
			last_name varchar(190) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'queued',
			queued_at datetime NULL,
			sent_at datetime NULL,
			failed_at datetime NULL,
			error_message text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY campaign_email (campaign_id, email),
			KEY status (status),
			KEY email (email),
			KEY campaign_id (campaign_id)
		) {$charset};";

		$audiences = Tables::name( Tables::AUDIENCES );
		$sql[]     = "CREATE TABLE {$audiences} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			match_type varchar(10) NOT NULL DEFAULT 'all',
			conditions longtext NOT NULL,
			last_estimate int(10) unsigned NULL,
			last_estimated_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) {$charset};";

		$templates = Tables::name( Tables::TEMPLATES );
		$sql[]     = "CREATE TABLE {$templates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			slug varchar(190) NOT NULL DEFAULT '',
			category varchar(50) NOT NULL DEFAULT 'marketing',
			subject varchar(255) NOT NULL DEFAULT '',
			preheader varchar(255) NOT NULL DEFAULT '',
			logo_url text NULL,
			heading varchar(255) NOT NULL DEFAULT '',
			body longtext NOT NULL,
			cta_label varchar(190) NOT NULL DEFAULT '',
			cta_url text NULL,
			show_products tinyint(1) NOT NULL DEFAULT 0,
			product_ids text NULL,
			coupon_code varchar(190) NOT NULL DEFAULT '',
			footer text NULL,
			layout_json longtext NULL,
			is_system tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY slug (slug),
			KEY is_system (is_system)
		) {$charset};";

		$automations = Tables::name( Tables::AUTOMATIONS );
		$sql[]       = "CREATE TABLE {$automations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'draft',
			trigger_type varchar(50) NOT NULL DEFAULT 'cart_abandoned',
			trigger_config longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY trigger_type (trigger_type)
		) {$charset};";

		$steps = Tables::name( Tables::AUTOMATION_STEPS );
		$sql[] = "CREATE TABLE {$steps} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) unsigned NOT NULL,
			position tinyint(3) unsigned NOT NULL DEFAULT 1,
			delay_value int(10) unsigned NOT NULL DEFAULT 1,
			delay_unit varchar(20) NOT NULL DEFAULT 'hours',
			template_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY automation_id (automation_id)
		) {$charset};";

		$enrollments = Tables::name( Tables::AUTOMATION_ENROLLMENTS );
		$sql[]       = "CREATE TABLE {$enrollments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) unsigned NOT NULL,
			cart_id bigint(20) unsigned NOT NULL,
			email varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			current_step tinyint(3) unsigned NOT NULL DEFAULT 0,
			retry_count tinyint(3) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			last_attempt_at datetime NULL,
			locked_at datetime NULL,
			enrolled_at datetime NOT NULL,
			cancelled_at datetime NULL,
			completed_at datetime NULL,
			failed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY automation_cart (automation_id, cart_id),
			KEY email (email),
			KEY status (status),
			KEY cart_id (cart_id)
		) {$charset};";

		$lists = Tables::name( Tables::LISTS );
		$sql[] = "CREATE TABLE {$lists} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT 'manual',
			elementor_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			elementor_form_id varchar(40) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY source (source),
			KEY elementor_form (elementor_post_id, elementor_form_id)
		) {$charset};";

		$contacts = Tables::name( Tables::LIST_CONTACTS );
		$sql[]    = "CREATE TABLE {$contacts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			list_id bigint(20) unsigned NOT NULL,
			email varchar(190) NOT NULL,
			first_name varchar(190) NOT NULL DEFAULT '',
			last_name varchar(190) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT 'manual',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY list_email (list_id, email),
			KEY email (email),
			KEY list_id (list_id)
		) {$charset};";

		$carts = Tables::name( Tables::CARTS );
		$sql[] = "CREATE TABLE {$carts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(191) NOT NULL DEFAULT '',
			customer_id bigint(20) unsigned NULL,
			email varchar(190) NOT NULL DEFAULT '',
			first_name varchar(190) NOT NULL DEFAULT '',
			cart_hash varchar(64) NOT NULL DEFAULT '',
			cart_contents longtext NULL,
			cart_total decimal(18,2) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			restore_token_hash varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_activity_at datetime NOT NULL,
			checkout_started_at datetime NULL,
			abandoned_at datetime NULL,
			recovered_at datetime NULL,
			converted_at datetime NULL,
			order_id bigint(20) unsigned NULL,
			PRIMARY KEY  (id),
			KEY session_key (session_key),
			KEY email (email),
			KEY customer_id (customer_id),
			KEY status (status),
			KEY restore_token_hash (restore_token_hash),
			KEY last_activity_at (last_activity_at),
			KEY order_id (order_id)
		) {$charset};";

		$suppressions = Tables::name( Tables::SUPPRESSIONS );
		$sql[]        = "CREATE TABLE {$suppressions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			scope varchar(30) NOT NULL DEFAULT 'marketing',
			token_hash varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email_scope (email, scope),
			KEY token_hash (token_hash),
			KEY email (email)
		) {$charset};";

		$logs = Tables::name( Tables::LOGS );
		$sql[] = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL DEFAULT '',
			object_type varchar(50) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NULL,
			message text NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY object_lookup (object_type, object_id),
			KEY created_at (created_at)
		) {$charset};";

		$delivery_rate = Tables::name( Tables::DELIVERY_RATE );
		$sql[]         = "CREATE TABLE {$delivery_rate} (
			bucket varchar(12) NOT NULL,
			reserved_count int(10) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (bucket)
		) {$charset};";

		$clicks = Tables::name( Tables::CLICKS );
		$sql[]  = "CREATE TABLE {$clicks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash varchar(64) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			source_type varchar(20) NOT NULL DEFAULT '',
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			url text NOT NULL,
			click_count int(10) unsigned NOT NULL DEFAULT 0,
			clicked_at datetime NULL,
			order_id bigint(20) unsigned NULL,
			order_total decimal(18,2) NULL,
			purchased_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY source_lookup (source_type, source_id),
			KEY email (email),
			KEY order_id (order_id)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Insert system templates once.
	 */
	public static function seed_templates() {
		$repo    = new TemplateRepository();
		$presets = Presets::all();

		foreach ( $presets as $preset ) {
			if ( $repo->get_by_slug( $preset['slug'] ) ) {
				continue;
			}
			$repo->insert( $preset );
		}
	}

	/**
	 * Drop all plugin tables (uninstall).
	 */
	public static function drop_tables() {
		global $wpdb;

		$suffixes = array(
			Tables::CAMPAIGNS,
			Tables::RECIPIENTS,
			Tables::AUDIENCES,
			Tables::TEMPLATES,
			Tables::AUTOMATIONS,
			Tables::AUTOMATION_STEPS,
			Tables::AUTOMATION_ENROLLMENTS,
			Tables::CARTS,
			Tables::LISTS,
			Tables::LIST_CONTACTS,
			Tables::SUPPRESSIONS,
			Tables::LOGS,
			Tables::DELIVERY_RATE,
			Tables::CLICKS,
		);

		foreach ( $suffixes as $suffix ) {
			$table = Tables::name( $suffix );
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
