<?php
/**
 * Email layout blocks for the drag-and-drop editor.
 *
 * @package OtterMail
 */

namespace OtterMail\Templates;

defined( 'ABSPATH' ) || exit;

class Layout {

	const TYPES = array( 'section', 'row', 'logo', 'heading', 'text', 'image', 'button', 'products', 'divider', 'spacer', 'coupon', 'footer' );

	const WIDTHS = array( '100', '50-50', '33-33-33', '70-30', '30-70', '60-40', '40-60' );

	/**
	 * Catalog for the editor palette.
	 *
	 * @return array
	 */
	public static function catalog() {
		return array(
			'section'  => array(
				'label'    => __( 'Seção', 'ottermail' ),
				'group'    => 'layout',
				'defaults' => array(
					'background' => '#ffffff',
					'padding'    => 24,
				),
			),
			'row'      => array(
				'label'    => __( 'Linha', 'ottermail' ),
				'group'    => 'layout',
				'defaults' => array(
					'columns'    => 2,
					'widths'     => '50-50',
					'padding'    => 8,
					'background' => '',
				),
			),
			'logo'     => array(
				'label'    => __( 'Logo', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array(
					'url'   => '',
					'width' => 160,
					'align' => 'center',
				),
			),
			'heading'  => array(
				'label'    => __( 'Título', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array(
					'text'      => '',
					'color'     => '#1d2327',
					'font_size' => 24,
					'align'     => 'center',
				),
			),
			'text'     => array(
				'label'    => __( 'Texto', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array(
					'html'      => '',
					'color'     => '#1d2327',
					'font_size' => 16,
					'align'     => 'left',
				),
			),
			'image'    => array(
				'label'    => __( 'Imagem', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array(
					'url'   => '',
					'alt'   => '',
					'width' => 100,
					'align' => 'center',
				),
			),
			'button'   => array(
				'label'    => __( 'Botão', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array(
					'label'      => __( 'Ver loja', 'ottermail' ),
					'url'        => '{{store.url}}',
					'background' => '#1d4ed8',
					'color'      => '#ffffff',
					'font_size'  => 16,
					'align'      => 'center',
					'full_width' => 0,
				),
			),
			'products' => array(
				'label'    => __( 'Produtos', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array( 'product_ids' => '' ),
			),
			'divider'  => array(
				'label'    => __( 'Divisor', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array( 'color' => '#e5e7eb' ),
			),
			'spacer'   => array(
				'label'    => __( 'Espaço', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array( 'height' => 24 ),
			),
			'footer'   => array(
				'label'    => __( 'Rodapé', 'ottermail' ),
				'group'    => 'content',
				'defaults' => array(
					'html'      => '',
					'color'     => '#646970',
					'font_size' => 12,
					'align'     => 'center',
				),
			),
		);
	}

	/**
	 * Starting blocks for a blank template.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			self::section_with(
				array(
					array(
						'id'   => self::uid(),
						'type' => 'heading',
						'data' => array(
							'text'      => '',
							'color'     => '#1d2327',
							'font_size' => 24,
							'align'     => 'center',
						),
					),
					array(
						'id'   => self::uid(),
						'type' => 'text',
						'data' => array(
							'html'      => '',
							'color'     => '#1d2327',
							'font_size' => 16,
							'align'     => 'left',
						),
					),
					array(
						'id'   => self::uid(),
						'type' => 'button',
						'data' => array(
							'label'      => __( 'Ver loja', 'ottermail' ),
							'url'        => '{{store.url}}',
							'background' => '#1d4ed8',
							'color'      => '#ffffff',
							'font_size'  => 16,
							'align'      => 'center',
							'full_width' => 0,
						),
					),
				)
			),
		);
	}

	/**
	 * Hydrate blocks from the previous single-field template.
	 *
	 * @param Template $template Template.
	 * @return array
	 */
	public static function from_legacy( Template $template ) {
		$blocks = array();

		if ( $template->logo_url ) {
			$blocks[] = array(
				'id'   => self::uid(),
				'type' => 'logo',
				'data' => array(
					'url'   => $template->logo_url,
					'width' => 160,
					'align' => 'center',
				),
			);
		}
		if ( $template->heading ) {
			$blocks[] = array(
				'id'   => self::uid(),
				'type' => 'heading',
				'data' => array(
					'text'      => $template->heading,
					'color'     => '#1d2327',
					'font_size' => 24,
					'align'     => 'center',
				),
			);
		}
		if ( $template->body ) {
			$blocks[] = array(
				'id'   => self::uid(),
				'type' => 'text',
				'data' => array(
					'html'      => $template->body,
					'color'     => '#1d2327',
					'font_size' => 16,
					'align'     => 'left',
				),
			);
		}
		if ( $template->show_products ) {
			$blocks[] = array(
				'id'   => self::uid(),
				'type' => 'products',
				'data' => array( 'product_ids' => $template->product_ids_list() ),
			);
		}
		if ( $template->cta_label ) {
			$blocks[] = array(
				'id'   => self::uid(),
				'type' => 'button',
				'data' => array(
					'label'      => $template->cta_label,
					'url'        => $template->cta_url ? $template->cta_url : '{{store.url}}',
					'background' => '#1d4ed8',
					'color'      => '#ffffff',
					'font_size'  => 16,
					'align'      => 'center',
					'full_width' => 0,
				),
			);
		}
		if ( $template->coupon_code ) {
			$blocks[] = array(
				'id'   => self::uid(),
				'type' => 'coupon',
				'data' => array(
					'code'  => $template->coupon_code,
					'color' => '#1d4ed8',
				),
			);
		}
		if ( $template->footer ) {
			$blocks[] = array(
				'id'   => self::uid(),
				'type' => 'footer',
				'data' => array(
					'html'      => $template->footer,
					'color'     => '#646970',
					'font_size' => 12,
					'align'     => 'center',
				),
			);
		}

		if ( ! $blocks ) {
			return self::defaults();
		}

		return array( self::section_with( $blocks ) );
	}

	/**
	 * Keep legacy columns in sync with the first matching block.
	 *
	 * @param array $blocks Blocks.
	 * @return array
	 */
	public static function legacy_fields( array $blocks ) {
		$fields = array(
			'logo_url'      => '',
			'heading'       => '',
			'body'          => '',
			'cta_label'     => '',
			'cta_url'       => '',
			'show_products' => 0,
			'product_ids'   => '',
			'coupon_code'   => '',
			'footer'        => '',
		);

		self::walk(
			$blocks,
			function ( $block ) use ( &$fields ) {
				$type = isset( $block['type'] ) ? $block['type'] : '';
				$data = isset( $block['data'] ) && is_array( $block['data'] ) ? $block['data'] : array();

				if ( 'logo' === $type && ! $fields['logo_url'] && ! empty( $data['url'] ) ) {
					$fields['logo_url'] = $data['url'];
				}
				if ( 'heading' === $type && ! $fields['heading'] && ! empty( $data['text'] ) ) {
					$fields['heading'] = $data['text'];
				}
				if ( 'text' === $type && ! $fields['body'] && ! empty( $data['html'] ) ) {
					$fields['body'] = $data['html'];
				}
				if ( 'button' === $type && ! $fields['cta_label'] && ! empty( $data['label'] ) ) {
					$fields['cta_label'] = $data['label'];
					$fields['cta_url']   = isset( $data['url'] ) ? $data['url'] : '';
				}
				if ( 'products' === $type && ! $fields['show_products'] ) {
					$fields['show_products'] = 1;
					$fields['product_ids']   = isset( $data['product_ids'] ) ? $data['product_ids'] : '';
				}
				if ( 'coupon' === $type && ! $fields['coupon_code'] && ! empty( $data['code'] ) ) {
					$fields['coupon_code'] = $data['code'];
				}
				if ( 'footer' === $type && ! $fields['footer'] && ! empty( $data['html'] ) ) {
					$fields['footer'] = $data['html'];
				}
			}
		);

		return $fields;
	}

	/**
	 * Walk every block in the tree.
	 *
	 * @param array    $blocks Blocks.
	 * @param callable $cb     Callback.
	 */
	public static function walk( array $blocks, $cb ) {
		foreach ( $blocks as $block ) {
			call_user_func( $cb, $block );
			if ( ! empty( $block['children'] ) && is_array( $block['children'] ) ) {
				self::walk( $block['children'], $cb );
			}
			if ( ! empty( $block['columns'] ) && is_array( $block['columns'] ) ) {
				foreach ( $block['columns'] as $column ) {
					if ( ! empty( $column['children'] ) && is_array( $column['children'] ) ) {
						self::walk( $column['children'], $cb );
					}
				}
			}
		}
	}

	/**
	 * Sanitize raw JSON or array of blocks.
	 *
	 * @param mixed $raw Raw payload.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$blocks = array();
		foreach ( $raw as $item ) {
			$block = self::sanitize_block( $item );
			if ( $block ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Column percentages for a widths key.
	 *
	 * @param string $widths Widths key.
	 * @return int[]
	 */
	public static function percents( $widths ) {
		$map = array(
			'100'       => array( 100 ),
			'50-50'     => array( 50, 50 ),
			'33-33-33'  => array( 33, 33, 34 ),
			'70-30'     => array( 70, 30 ),
			'30-70'     => array( 30, 70 ),
			'60-40'     => array( 60, 40 ),
			'40-60'     => array( 40, 60 ),
		);

		return isset( $map[ $widths ] ) ? $map[ $widths ] : array( 100 );
	}

	/**
	 * @param string $value    Raw color.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	public static function hex( $value, $fallback = '' ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value && function_exists( 'sanitize_hex_color' ) ) {
			$ok = sanitize_hex_color( $value );
			if ( $ok ) {
				return $ok;
			}
		}

		return $fallback;
	}

	/**
	 * @param mixed $value   Raw.
	 * @param int   $min     Min.
	 * @param int   $max     Max.
	 * @param int   $default Default.
	 * @return int
	 */
	public static function int_range( $value, $min, $max, $default ) {
		if ( '' === $value || null === $value ) {
			return (int) $default;
		}

		$n = (int) $value;

		return (int) min( (int) $max, max( (int) $min, $n ) );
	}

	/**
	 * @param mixed $value Raw align.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	public static function align( $value, $fallback = 'left' ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		if ( in_array( $value, array( 'left', 'center', 'right' ), true ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * @return string
	 */
	public static function uid() {
		return 'b' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 10 );
	}

	/**
	 * @param array $inner Inner content blocks.
	 * @return array
	 */
	/**
	 * Wrap a flat list of blocks in a section > row tree.
	 *
	 * @param array $blocks Blocks.
	 * @return array
	 */
	public static function ensure_structure( array $blocks ) {
		if ( empty( $blocks ) ) {
			return self::defaults();
		}

		$has_section = false;
		foreach ( $blocks as $block ) {
			if ( isset( $block['type'] ) && 'section' === $block['type'] ) {
				$has_section = true;
				break;
			}
		}

		if ( $has_section ) {
			return $blocks;
		}

		$rows  = array();
		$loose = array();
		foreach ( $blocks as $block ) {
			if ( isset( $block['type'] ) && 'row' === $block['type'] ) {
				if ( $loose ) {
					$rows[] = self::row_with( $loose );
					$loose  = array();
				}
				$rows[] = $block;
			} else {
				$loose[] = $block;
			}
		}
		if ( $loose ) {
			$rows[] = self::row_with( $loose );
		}

		$section             = self::section_with( array() );
		$section['children'] = $rows;

		return array( $section );
	}

	/**
	 * @param array $inner Inner content blocks.
	 * @return array
	 */
	private static function row_with( array $inner ) {
		return array(
			'id'      => self::uid(),
			'type'    => 'row',
			'data'    => array(
				'columns'    => 1,
				'widths'     => '100',
				'padding'    => 0,
				'background' => '',
			),
			'columns' => array(
				array( 'children' => $inner ),
			),
		);
	}

	/**
	 * @param array $inner Inner content blocks.
	 * @return array
	 */
	private static function section_with( array $inner ) {
		return array(
			'id'       => self::uid(),
			'type'     => 'section',
			'data'     => array(
				'background' => '#ffffff',
				'padding'    => 24,
			),
			'children' => array(
				self::row_with( $inner ),
			),
		);
	}

	/**
	 * @param mixed $item Raw block.
	 * @return array|null
	 */
	private static function sanitize_block( $item ) {
		if ( ! is_array( $item ) ) {
			return null;
		}

		$type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : '';
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return null;
		}

		$id   = isset( $item['id'] ) ? sanitize_key( $item['id'] ) : '';
		$data = isset( $item['data'] ) && is_array( $item['data'] ) ? $item['data'] : array();
		$block = array(
			'id'   => $id ? $id : self::uid(),
			'type' => $type,
			'data' => self::sanitize_data( $type, $data ),
		);

		if ( 'section' === $type ) {
			$kids = isset( $item['children'] ) ? $item['children'] : array();
			$block['children'] = self::sanitize_rows( $kids );
		}

		if ( 'row' === $type ) {
			$n          = isset( $block['data']['columns'] ) ? (int) $block['data']['columns'] : 1;
			$raw_cols   = isset( $item['columns'] ) && is_array( $item['columns'] ) ? $item['columns'] : array();
			$columns    = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$kids = array();
				if ( isset( $raw_cols[ $i ]['children'] ) ) {
					$kids = $raw_cols[ $i ]['children'];
				}
				$columns[] = array(
					'children' => self::sanitize_content( $kids ),
				);
			}
			$block['columns'] = $columns;
		}

		return $block;
	}

	/**
	 * Only rows inside a section.
	 *
	 * @param mixed $raw Raw.
	 * @return array
	 */
	private static function sanitize_rows( $raw ) {
		$blocks = self::sanitize( $raw );
		$rows   = array();
		$loose  = array();
		foreach ( $blocks as $block ) {
			if ( 'row' === $block['type'] ) {
				if ( $loose ) {
					$rows[] = self::row_with( $loose );
					$loose  = array();
				}
				$rows[] = $block;
			} elseif ( 'section' !== $block['type'] ) {
				$loose[] = $block;
			}
		}
		if ( $loose ) {
			$rows[] = self::row_with( $loose );
		}

		return $rows;
	}

	/**
	 * Content blocks only (no nested sections/rows).
	 *
	 * @param mixed $raw Raw.
	 * @return array
	 */
	private static function sanitize_content( $raw ) {
		$blocks  = self::sanitize( $raw );
		$content = array();
		foreach ( $blocks as $block ) {
			if ( in_array( $block['type'], array( 'section', 'row', 'coupon' ), true ) ) {
				continue;
			}
			unset( $block['children'], $block['columns'] );
			$content[] = $block;
		}

		return $content;
	}

	/**
	 * @param string $type Type.
	 * @param array  $data Data.
	 * @return array
	 */
	private static function sanitize_data( $type, array $data ) {
		switch ( $type ) {
			case 'section':
				return array(
					'background' => self::hex( isset( $data['background'] ) ? $data['background'] : '', '#ffffff' ),
					'padding'    => self::int_range( isset( $data['padding'] ) ? $data['padding'] : 24, 0, 80, 24 ),
				);
			case 'row':
				$widths = isset( $data['widths'] ) ? (string) $data['widths'] : '';
				if ( ! in_array( $widths, self::WIDTHS, true ) ) {
					$columns = self::int_range( isset( $data['columns'] ) ? $data['columns'] : 1, 1, 3, 1 );
					$widths  = self::normalize_widths( $columns, $widths );
				}
				return array(
					'columns'    => count( self::percents( $widths ) ),
					'widths'     => $widths,
					'padding'    => self::int_range( isset( $data['padding'] ) ? $data['padding'] : 8, 0, 48, 8 ),
					'background' => self::hex( isset( $data['background'] ) ? $data['background'] : '', '' ),
				);
			case 'logo':
				return array(
					'url'   => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
					'width' => self::int_range( isset( $data['width'] ) ? $data['width'] : 160, 40, 320, 160 ),
					'align' => self::align( isset( $data['align'] ) ? $data['align'] : 'center', 'center' ),
				);
			case 'image':
				return array(
					'url'   => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
					'alt'   => isset( $data['alt'] ) ? sanitize_text_field( $data['alt'] ) : '',
					'width' => self::int_range( isset( $data['width'] ) ? $data['width'] : 100, 20, 100, 100 ),
					'align' => self::align( isset( $data['align'] ) ? $data['align'] : 'center', 'center' ),
				);
			case 'heading':
				return array(
					'text'      => isset( $data['text'] ) ? sanitize_text_field( $data['text'] ) : '',
					'color'     => self::hex( isset( $data['color'] ) ? $data['color'] : '', '#1d2327' ),
					'font_size' => self::int_range( isset( $data['font_size'] ) ? $data['font_size'] : 24, 14, 48, 24 ),
					'align'     => self::align( isset( $data['align'] ) ? $data['align'] : 'center', 'center' ),
				);
			case 'text':
			case 'footer':
				$fallback_size = 'footer' === $type ? 12 : 16;
				$fallback_align = 'footer' === $type ? 'center' : 'left';
				$fallback_color = 'footer' === $type ? '#646970' : '#1d2327';
				return array(
					'html'      => isset( $data['html'] ) ? wp_kses_post( $data['html'] ) : '',
					'color'     => self::hex( isset( $data['color'] ) ? $data['color'] : '', $fallback_color ),
					'font_size' => self::int_range( isset( $data['font_size'] ) ? $data['font_size'] : $fallback_size, 10, 24, $fallback_size ),
					'align'     => self::align( isset( $data['align'] ) ? $data['align'] : $fallback_align, $fallback_align ),
				);
			case 'button':
				$url = isset( $data['url'] ) ? trim( (string) $data['url'] ) : '';
				if ( $url && ! preg_match( '/^\{\{\s*[a-z0-9_.]+\s*\}\}$/i', $url ) ) {
					$url = esc_url_raw( $url, array( 'http', 'https', 'mailto' ) );
				}
				return array(
					'label'      => isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '',
					'url'        => $url,
					'background' => self::hex( isset( $data['background'] ) ? $data['background'] : '', '#1d4ed8' ),
					'color'      => self::hex( isset( $data['color'] ) ? $data['color'] : '', '#ffffff' ),
					'font_size'  => self::int_range( isset( $data['font_size'] ) ? $data['font_size'] : 16, 12, 24, 16 ),
					'align'      => self::align( isset( $data['align'] ) ? $data['align'] : 'center', 'center' ),
					'full_width' => empty( $data['full_width'] ) || '0' === (string) $data['full_width'] ? 0 : 1,
				);
			case 'products':
				$ids = isset( $data['product_ids'] ) ? $data['product_ids'] : '';
				return array(
					'product_ids' => implode( ',', Template::parse_product_ids( $ids ) ),
				);
			case 'spacer':
				return array(
					'height' => self::int_range( isset( $data['height'] ) ? $data['height'] : 24, 8, 160, 24 ),
				);
			case 'coupon':
				return array(
					'code'  => isset( $data['code'] ) ? sanitize_text_field( $data['code'] ) : '',
					'color' => self::hex( isset( $data['color'] ) ? $data['color'] : '', '#1d4ed8' ),
				);
			case 'divider':
				return array(
					'color' => self::hex( isset( $data['color'] ) ? $data['color'] : '', '#e5e7eb' ),
				);
			default:
				return array();
		}
	}

	/**
	 * @param int    $columns Column count.
	 * @param string $widths  Widths key.
	 * @return string
	 */
	private static function normalize_widths( $columns, $widths ) {
		$widths = is_string( $widths ) ? $widths : '';
		if ( 1 === (int) $columns ) {
			return '100';
		}
		if ( 3 === (int) $columns ) {
			return '33-33-33';
		}
		$two = array( '50-50', '70-30', '30-70', '60-40', '40-60' );
		if ( in_array( $widths, $two, true ) ) {
			return $widths;
		}

		return '50-50';
	}
}
