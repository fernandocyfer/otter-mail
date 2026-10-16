<?php
/**
 * Newsletter contact list entity.
 *
 * @package OtterMail
 */

namespace OtterMail\Lists;

defined( 'ABSPATH' ) || exit;

class ContactList {

	const SOURCES = array( 'manual', 'csv', 'elementor' );

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
	public $source = 'manual';

	/**
	 * @var int
	 */
	public $elementor_post_id = 0;

	/**
	 * @var string
	 */
	public $elementor_form_id = '';

	/**
	 * @var int
	 */
	public $contact_count = 0;

	/**
	 * @param object $row Row.
	 * @return ContactList
	 */
	public static function from_row( $row ) {
		$list                     = new self();
		$list->id                 = (int) $row->id;
		$list->name               = (string) $row->name;
		$list->source             = (string) $row->source;
		$list->elementor_post_id  = isset( $row->elementor_post_id ) ? (int) $row->elementor_post_id : 0;
		$list->elementor_form_id  = isset( $row->elementor_form_id ) ? (string) $row->elementor_form_id : '';
		$list->contact_count      = isset( $row->contact_count ) ? (int) $row->contact_count : 0;

		return $list;
	}

	/**
	 * Human-readable source label.
	 *
	 * @return string
	 */
	public function source_label() {
		$labels = array(
			'manual'    => __( 'Manual', 'ottermail' ),
			'csv'       => __( 'CSV', 'ottermail' ),
			'elementor' => __( 'Formulário Elementor', 'ottermail' ),
		);

		return isset( $labels[ $this->source ] ) ? $labels[ $this->source ] : $this->source;
	}
}
