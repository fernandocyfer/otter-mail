<?php
/**
 * Plugin bootstrap.
 *
 * @package OtterMail
 */

namespace OtterMail\Core;

use OtterMail\Admin\Admin;
use OtterMail\Admin\Notices;
use OtterMail\Audiences\AudienceController;
use OtterMail\Automations\AutomationController;
use OtterMail\Automations\AutomationService;
use OtterMail\Campaigns\CampaignController;
use OtterMail\Campaigns\CampaignService;
use OtterMail\Cart\CartCapture;
use OtterMail\Cart\CartRecovery;
use OtterMail\Cart\CartRestore;
use OtterMail\Orders\PostPurchase;
use OtterMail\Lists\ListController;
use OtterMail\Support\Scheduler;
use OtterMail\Templates\TemplateController;
use OtterMail\Tracking\ClickTracker;
use OtterMail\Unsubscribe\UnsubscribeService;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register early hooks (always).
	 */
	public function boot() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		add_action( 'init', array( $this, 'register_query_vars' ) );
	}

	/**
	 * Declare High-Performance Order Storage compatibility.
	 */
	public function declare_hpos() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OTTERMAIL_FILE, true );
		}
	}

	/**
	 * Boot modules after plugins are loaded.
	 */
	public function init() {
		load_plugin_textdomain( 'ottermail', false, dirname( plugin_basename( OTTERMAIL_FILE ) ) . '/languages' );

		Installer::maybe_upgrade();
		( new Notices() )->register();

		if ( ! $this->woocommerce_active() ) {
			return;
		}

		Capabilities::add();

		( new Assets() )->register();
		( new Admin() )->register();
		( new CampaignController() )->register();
		( new CampaignService() )->register();
		( new AudienceController() )->register();
		( new ListController() )->register();
		( new TemplateController() )->register();
		( new AutomationController() )->register();
		( new CartCapture() )->register();
		( new CartRestore() )->register();
		( new CartRecovery() )->register();
		( new PostPurchase() )->register();
		( new AutomationService() )->register();
		( new UnsubscribeService() )->register();
		( new ClickTracker() )->register();
		( new Privacy() )->register();

		add_action( 'init', array( $this, 'ensure_recurring' ), 30 );
	}

	/**
	 * WooCommerce is available.
	 *
	 * @return bool
	 */
	public function woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Public query vars for restore / unsubscribe.
	 */
	public function register_query_vars() {
		add_rewrite_tag( '%ottermail_restore%', '([^&]+)' );
		add_rewrite_tag( '%ottermail_unsubscribe%', '([^&]+)' );
		add_rewrite_tag( '%ottermail_click%', '([^&]+)' );
	}

	/**
	 * Ensure the recurring cart check exists while the plugin is active.
	 */
	public function ensure_recurring() {
		Scheduler::schedule_recurring_cart_check();
	}
}
