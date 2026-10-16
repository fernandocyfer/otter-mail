<?php
/**
 * Email template entity.
 *
 * @package OtterMail
 */

namespace OtterMail\Templates;

defined( 'ABSPATH' ) || exit;

class Template {

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
	public $slug = '';

	/**
	 * @var string
	 */
	public $category = 'marketing';

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
	public $logo_url = '';

	/**
	 * @var string
	 */
	public $heading = '';

	/**
	 * @var string
	 */
	public $body = '';

	/**
	 * @var string
	 */
	public $cta_label = '';

	/**
	 * @var string
	 */
	public $cta_url = '';

	/**
	 * @var int
	 */
	public $show_products = 0;

	/**
	 * @var array
	 */
	public $product_ids = array();

	/**
	 * @var string
	 */
	public $coupon_code = '';

	/**
	 * @var string
	 */
	public $footer = '';

	/**
	 * @var string
	 */
	public $layout_json = '';

	/**
	 * @var int
	 */
	public $is_system = 0;

	/**
	 * Hydrate from a database row.
	 *
	 * @param object $row Row.
	 * @return Template
	 */
	public static function from_row( $row ) {
		$t                 = new self();
		$t->id             = (int) $row->id;
		$t->name           = (string) $row->name;
		$t->slug           = (string) $row->slug;
		$t->category       = (string) $row->category;
		$t->subject        = (string) $row->subject;
		$t->preheader      = (string) $row->preheader;
		$t->logo_url       = (string) $row->logo_url;
		$t->heading        = (string) $row->heading;
		$t->body           = (string) $row->body;
		$t->cta_label      = (string) $row->cta_label;
		$t->cta_url        = (string) $row->cta_url;
		$t->show_products  = (int) $row->show_products;
		$t->product_ids    = self::parse_product_ids( isset( $row->product_ids ) ? $row->product_ids : '' );
		$t->coupon_code    = (string) $row->coupon_code;
		$t->footer         = (string) $row->footer;
		$t->layout_json    = isset( $row->layout_json ) ? (string) $row->layout_json : '';
		$t->is_system      = (int) $row->is_system;

		return $t;
	}

	/**
	 * Row payload for insert/update.
	 *
	 * @return array
	 */
	public function to_row() {
		return array(
			'name'          => $this->name,
			'slug'          => $this->slug,
			'category'      => $this->category,
			'subject'       => $this->subject,
			'preheader'     => $this->preheader,
			'logo_url'      => $this->logo_url,
			'heading'       => $this->heading,
			'body'          => $this->body,
			'cta_label'     => $this->cta_label,
			'cta_url'       => $this->cta_url,
			'show_products' => $this->show_products ? 1 : 0,
			'product_ids'   => implode( ',', $this->normalized_product_ids() ),
			'coupon_code'   => $this->coupon_code,
			'footer'        => $this->footer,
			'layout_json'   => $this->layout_json,
			'is_system'     => $this->is_system ? 1 : 0,
		);
	}

	/**
	 * Product IDs as a comma-separated string for admin fields.
	 *
	 * @return string
	 */
	public function product_ids_list() {
		return implode( ',', $this->normalized_product_ids() );
	}

	/**
	 * @return int[]
	 */
	public function normalized_product_ids() {
		return self::parse_product_ids( $this->product_ids );
	}

	/**
	 * @param mixed $value Raw value from DB or form.
	 * @return int[]
	 */
	public static function parse_product_ids( $value ) {
		if ( is_array( $value ) ) {
			$parts = $value;
		} elseif ( is_string( $value ) && '' !== $value ) {
			$parts = explode( ',', $value );
		} else {
			return array();
		}

		return array_values( array_filter( array_map( 'absint', $parts ) ) );
	}

	/**
	 * Layout blocks, hydrating from legacy fields when JSON is empty.
	 *
	 * @return array
	 */
	public function blocks() {
		$decoded = array();
		if ( $this->layout_json ) {
			$decoded = json_decode( $this->layout_json, true );
		}
		if ( is_array( $decoded ) && $decoded ) {
			$blocks = Layout::ensure_structure( Layout::sanitize( $decoded ) );
			if ( $blocks ) {
				return $blocks;
			}
		}

		return Layout::from_legacy( $this );
	}
}
