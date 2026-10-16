<?php
/**
 * V1 trigger: abandoned cart.
 *
 * @package OtterMail
 */

namespace OtterMail\Automations\Triggers;

defined( 'ABSPATH' ) || exit;

class CartAbandonedTrigger implements TriggerInterface {

	/**
	 * {@inheritdoc}
	 */
	public function key() {
		return 'cart_abandoned';
	}
}
