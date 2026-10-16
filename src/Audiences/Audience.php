<?php
/**
 * Audience entity.
 *
 * @package OtterMail
 */

namespace OtterMail\Audiences;

defined( 'ABSPATH' ) || exit;

class Audience {

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
	public $match_type = 'all';

	/**
	 * @var array
	 */
	public $conditions = array();

	/**
	 * @var int|null
	 */
	public $last_estimate = null;

	/**
	 * @param object $row Row.
	 * @return Audience
	 */
	public static function from_row( $row ) {
		$a                 = new self();
		$a->id             = (int) $row->id;
		$a->name           = (string) $row->name;
		$a->match_type     = 'any' === $row->match_type ? 'any' : 'all';
		$decoded           = json_decode( (string) $row->conditions, true );
		$a->conditions     = is_array( $decoded ) ? $decoded : array();
		$a->last_estimate  = null !== $row->last_estimate ? (int) $row->last_estimate : null;

		return $a;
	}
}
