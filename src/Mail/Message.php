<?php
/**
 * Outgoing email message.
 *
 * @package OtterMail
 */

namespace OtterMail\Mail;

defined( 'ABSPATH' ) || exit;

class Message {

	/**
	 * @var string
	 */
	public $to = '';

	/**
	 * @var string
	 */
	public $subject = '';

	/**
	 * @var string
	 */
	public $html = '';

	/**
	 * @var string
	 */
	public $from_name = '';

	/**
	 * @var string
	 */
	public $from_email = '';

	/**
	 * @var string
	 */
	public $reply_to = '';

	/**
	 * @var array
	 */
	public $headers = array();
}
