<?php
/**
 * Public unsubscribe flow.
 *
 * @package OtterMail
 */

namespace OtterMail\Unsubscribe;

use OtterMail\Support\Logger;
use OtterMail\Support\Tokens;
use OtterMail\Support\View;

defined( 'ABSPATH' ) || exit;

class UnsubscribeService {

	/**
	 * @var SuppressionRepository
	 */
	private $repo;

	public function __construct() {
		$this->repo = new SuppressionRepository();
	}

	/**
	 * Register public hooks.
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Build unsubscribe URL.
	 *
	 * @param string $email Email.
	 * @param string $scope Scope.
	 * @return string
	 */
	public function url_for( $email, $scope = 'marketing' ) {
		return add_query_arg(
			array(
				'ottermail_unsubscribe' => Tokens::unsubscribe_hmac( $email, $scope ),
				'email'                 => rawurlencode( strtolower( sanitize_email( $email ) ) ),
				'scope'                 => $scope,
			),
			home_url( '/' )
		);
	}

	/**
	 * Handle public unsubscribe request.
	 */
	public function handle() {
		$token = isset( $_GET['ottermail_unsubscribe'] ) ? sanitize_text_field( wp_unslash( $_GET['ottermail_unsubscribe'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $token ) {
			return;
		}

		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'marketing'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $scope, array( 'marketing', 'cart', 'all' ), true ) ) {
			$scope = 'marketing';
		}

		$valid = $email && Tokens::unsubscribe_valid( $email, $token, $scope );

		if ( $valid ) {
			$this->repo->add( $email, $scope, Tokens::hash( $token ) );
			Logger::log( 'unsubscribe', __( 'Email descadastrado de marketing.', 'ottermail' ), 'suppression', 0, array( 'scope' => $scope ) );
		}

		status_header( 200 );
		nocache_headers();

		View::render(
			'public/unsubscribe',
			array(
				'success' => $valid,
			)
		);
		exit;
	}

	/**
	 * @param string $email Email.
	 * @param string $kind  marketing|cart.
	 * @return bool
	 */
	public function is_suppressed( $email, $kind = 'marketing' ) {
		return $this->repo->is_suppressed( $email, $kind );
	}
}
