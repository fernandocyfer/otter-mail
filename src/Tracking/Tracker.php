<?php
/**
 * Tracking stub — no open pixel in V1.
 *
 * @package OtterMail
 */

namespace OtterMail\Tracking;

defined( 'ABSPATH' ) || exit;

class Tracker {

	/**
	 * Record a domain event for a future tracking pipeline.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Payload.
	 */
	public function record( $event, array $data = array() ) {
		do_action( 'ottermail_track', sanitize_key( $event ), $data );
	}
}
