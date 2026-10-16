<?php
/**
 * PSR-4 autoloader for OtterMail\.
 *
 * @package OtterMail
 */

namespace OtterMail;

defined( 'ABSPATH' ) || exit;

class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, 'OtterMail\\' ) ) {
			return;
		}

		$relative = str_replace( 'OtterMail\\', '', $class );
		$path     = OTTERMAIL_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
}
