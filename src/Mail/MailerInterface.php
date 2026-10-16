<?php
/**
 * Mailer contract.
 *
 * @package OtterMail
 */

namespace OtterMail\Mail;

defined( 'ABSPATH' ) || exit;

interface MailerInterface {

	/**
	 * Send a message.
	 *
	 * @param Message $message Message.
	 * @return bool True if the local transport accepted the message (not delivery).
	 */
	public function send( Message $message );
}
