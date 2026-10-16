<?php
/**
 * Campaign entity.
 *
 * @package OtterMail
 */

namespace OtterMail\Campaigns;

defined( 'ABSPATH' ) || exit;

class Campaign {

	const STATUSES = array( 'draft', 'scheduled', 'processing', 'sent', 'paused', 'cancelled' );

	/**
	 * @var int
	 */
	public $id = 0;

	/**
	 * @var string
	 */
	public $name = '';

	/**
	 * @var string
	 */
	public $subject = '';

	/**
	 * @var string
	 */
	public $preheader = '';

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
	 * @var int
	 */
	public $template_id = 0;

	/**
	 * @var int
	 */
	public $audience_id = 0;

	/**
	 * @var int
	 */
	public $list_id = 0;

	/**
	 * @var string
	 */
	public $status = 'draft';

	/**
	 * @var string|null
	 */
	public $scheduled_at = null;

	/**
	 * @var int
	 */
	public $recipients_count = 0;

	/**
	 * @var int
	 */
	public $sent_count = 0;

	/**
	 * @var int
	 */
	public $failed_count = 0;

	/**
	 * @var int
	 */
	public $skipped_count = 0;

	/**
	 * @param object $row Row.
	 * @return Campaign
	 */
	public static function from_row( $row ) {
		$c                    = new self();
		$c->id                = (int) $row->id;
		$c->name              = (string) $row->name;
		$c->subject           = (string) $row->subject;
		$c->preheader         = (string) $row->preheader;
		$c->from_name         = (string) $row->from_name;
		$c->from_email        = (string) $row->from_email;
		$c->reply_to          = (string) $row->reply_to;
		$c->template_id       = (int) $row->template_id;
		$c->audience_id       = (int) $row->audience_id;
		$c->list_id           = isset( $row->list_id ) ? (int) $row->list_id : 0;
		$c->status            = (string) $row->status;
		$c->scheduled_at      = $row->scheduled_at;
		$c->recipients_count  = (int) $row->recipients_count;
		$c->sent_count        = (int) $row->sent_count;
		$c->failed_count      = (int) $row->failed_count;
		$c->skipped_count     = (int) $row->skipped_count;

		return $c;
	}
}
