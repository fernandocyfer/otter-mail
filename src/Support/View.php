<?php
/**
 * Admin template loader.
 *
 * @package OtterMail
 */

namespace OtterMail\Support;

defined( 'ABSPATH' ) || exit;

class View {

	/**
	 * Render a PHP template from templates/.
	 *
	 * Isolated so extract() cannot collide with this method's $template path.
	 *
	 * @param string $view Relative path without .php.
	 * @param array  $data Extracted as variables.
	 */
	public static function render( $view, array $data = array() ) {
		$file = OTTERMAIL_PATH . 'templates/' . ltrim( $view, '/' ) . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		$render = static function ( $__file, array $__data ) {
			extract( $__data, EXTR_OVERWRITE ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $__file;
		};

		$render( $file, $data );
	}

	/**
	 * Capture template output.
	 *
	 * @param string $view Relative path.
	 * @param array  $data Data.
	 * @return string
	 */
	public static function capture( $view, array $data = array() ) {
		ob_start();
		self::render( $view, $data );
		return (string) ob_get_clean();
	}
}
