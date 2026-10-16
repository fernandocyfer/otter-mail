<?php
/**
 * Plugin Name:       OtterMail - Email Marketing para WooCommerce
 * Plugin URI:        https://softjunky.com/products/ottermail/
 * Description:       Email marketing inteligente para WooCommerce — campanhas segmentadas e recuperação de carrinhos abandonados.
 * Version:           1.0.0
 * Author:            SoftJunky
 * Author URI:        https://softjunky.com
 * Contributors:      cyferweb
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ottermail
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   10.6
 *
 * @package OtterMail
 */

defined( 'ABSPATH' ) || exit;

define( 'OTTERMAIL_VERSION', '1.0.0' );
define( 'OTTERMAIL_FILE', __FILE__ );
define( 'OTTERMAIL_PATH', plugin_dir_path( __FILE__ ) );
define( 'OTTERMAIL_URL', plugin_dir_url( __FILE__ ) );

require_once OTTERMAIL_PATH . 'src/Autoloader.php';

OtterMail\Autoloader::register();

register_activation_hook( OTTERMAIL_FILE, array( 'OtterMail\Core\Activator', 'activate' ) );
register_deactivation_hook( OTTERMAIL_FILE, array( 'OtterMail\Core\Deactivator', 'deactivate' ) );

OtterMail\Core\Plugin::instance()->boot();
