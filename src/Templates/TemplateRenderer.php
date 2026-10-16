<?php
/**
 * Single HTML renderer for preview, test and live send.
 *
 * @package OtterMail
 */

namespace OtterMail\Templates;

use OtterMail\Core\Settings;

defined( 'ABSPATH' ) || exit;

class TemplateRenderer {

	/**
	 * @var Variables
	 */
	private $variables;

	public function __construct() {
		$this->variables = new Variables();
	}

	/**
	 * Render a template to full HTML.
	 *
	 * @param Template $template Template.
	 * @param array    $context  Context.
	 * @return string
	 */
	public function render( Template $template, array $context = array() ) {
		$width  = (int) Settings::get( 'email_width', 600 );
		$blocks = $template->blocks();

		// The WooCommerce coupon selected in the template is the canonical
		// value for {{coupon.code}}, including previews and test messages.
		if ( $template->coupon_code ) {
			$context['coupon'] = $template->coupon_code;
		}

		if ( empty( $context['cart']['items_html'] ) && ! empty( $context['cart']['items'] ) && is_array( $context['cart']['items'] ) ) {
			$context['cart']['items_html'] = $this->render_cart_items( $context['cart']['items'] );
		}

		$inner = '';
		if ( $blocks ) {
			$inner .= $this->render_list( $blocks, $template, $context, $width, false );
		} else {
			$logo   = $template->logo_url ? $template->logo_url : (string) Settings::get( 'logo_url', '' );
			$footer = $template->footer ? $template->footer : (string) Settings::get( 'footer_text', '' );
			$inner .= $this->block_logo( $logo, array() );
			$inner .= $this->block_heading( $template->heading, array() );
			$inner .= $this->block_text( $template->body, array() );
			if ( $template->show_products ) {
				$ids = $template->normalized_product_ids();
				if ( empty( $ids ) && ! empty( $context['product_ids'] ) ) {
					$ids = Template::parse_product_ids( $context['product_ids'] );
				}
				$inner .= $this->render_products( $ids );
			}
			$inner .= $this->block_cta( $template->cta_label, $template->cta_url, array() );
			$inner .= $this->block_text( $footer, array() );
		}
		$inner .= $this->block_unsubscribe();

		$html = $this->wrap( $inner, $width, $template->preheader );

		return $this->variables->resolve( $html, $context );
	}

	/**
	 * @param array    $blocks    Blocks.
	 * @param Template $template  Template.
	 * @param array    $context   Context.
	 * @param int      $width     Width.
	 * @param bool     $in_column Nested in a column.
	 * @return string
	 */
	private function render_list( array $blocks, Template $template, array $context, $width, $in_column ) {
		$html = '';
		foreach ( $blocks as $block ) {
			$html .= $this->render_block( $block, $template, $context, $width, $in_column );
		}

		return $html;
	}

	/**
	 * @param array    $block     Block.
	 * @param Template $template  Template.
	 * @param array    $context   Context.
	 * @param int      $width     Width.
	 * @param bool     $in_column Nested.
	 * @return string
	 */
	private function render_block( array $block, Template $template, array $context, $width, $in_column ) {
		$type = isset( $block['type'] ) ? $block['type'] : '';
		$data = isset( $block['data'] ) && is_array( $block['data'] ) ? $block['data'] : array();

		switch ( $type ) {
			case 'section':
				return $this->block_section( $block, $template, $context, $width );
			case 'row':
				return $this->block_row( $block, $template, $context, $width );
			case 'logo':
				$url = ! empty( $data['url'] ) ? $data['url'] : ( $template->logo_url ? $template->logo_url : (string) Settings::get( 'logo_url', '' ) );
				return $this->block_logo( $url, $data, $in_column );
			case 'heading':
				return $this->block_heading( isset( $data['text'] ) ? $data['text'] : '', $data, $in_column );
			case 'text':
				return $this->block_text( isset( $data['html'] ) ? $data['html'] : '', $data, $in_column );
			case 'image':
				return $this->block_image( $data, $width, $in_column );
			case 'button':
				return $this->block_cta( isset( $data['label'] ) ? $data['label'] : '', isset( $data['url'] ) ? $data['url'] : '', $data, $in_column );
			case 'products':
				$ids = Template::parse_product_ids( isset( $data['product_ids'] ) ? $data['product_ids'] : '' );
				if ( empty( $ids ) && ! empty( $context['product_ids'] ) ) {
					$ids = Template::parse_product_ids( $context['product_ids'] );
				}
				return $this->render_products( $ids, $in_column );
			case 'divider':
				$color = Layout::hex( isset( $data['color'] ) ? $data['color'] : '', '#e5e7eb' );
				$pad   = $in_column ? '8px 0' : '8px 32px';
				return '<tr><td class="om-pad" style="padding:' . $pad . ';"><hr style="border:0;border-top:1px solid ' . esc_attr( $color ) . ';margin:0;"></td></tr>';
			case 'spacer':
				$height = Layout::int_range( isset( $data['height'] ) ? $data['height'] : 24, 8, 160, 24 );
				return '<tr><td style="height:' . $height . 'px;line-height:' . $height . 'px;font-size:1px;">&nbsp;</td></tr>';
			case 'coupon':
				// Legacy visual coupon blocks are intentionally not rendered.
				// The coupon is shown only through {{coupon.code}} in text content.
				return '';
			case 'footer':
				$html = ! empty( $data['html'] ) ? $data['html'] : (string) Settings::get( 'footer_text', '' );
				return $this->block_text( $html, $data, $in_column );
			default:
				return '';
		}
	}

	/**
	 * @param array    $block    Section.
	 * @param Template $template Template.
	 * @param array    $context  Context.
	 * @param int      $width    Width.
	 * @return string
	 */
	private function block_section( array $block, Template $template, array $context, $width ) {
		$data   = isset( $block['data'] ) && is_array( $block['data'] ) ? $block['data'] : array();
		$bg     = Layout::hex( isset( $data['background'] ) ? $data['background'] : '', '#ffffff' );
		$pad    = Layout::int_range( isset( $data['padding'] ) ? $data['padding'] : 24, 0, 80, 24 );
		$kids   = isset( $block['children'] ) && is_array( $block['children'] ) ? $block['children'] : array();
		$inner  = $this->render_list( $kids, $template, $context, $width, false );

		return '<tr><td class="om-section" style="padding:' . $pad . 'px;background:' . esc_attr( $bg ) . ';">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
			. $inner
			. '</table></td></tr>';
	}

	/**
	 * @param array    $block    Row.
	 * @param Template $template Template.
	 * @param array    $context  Context.
	 * @param int      $width    Width.
	 * @return string
	 */
	private function block_row( array $block, Template $template, array $context, $width ) {
		$data    = isset( $block['data'] ) && is_array( $block['data'] ) ? $block['data'] : array();
		$bg      = Layout::hex( isset( $data['background'] ) ? $data['background'] : '', '' );
		$pad     = Layout::int_range( isset( $data['padding'] ) ? $data['padding'] : 8, 0, 48, 8 );
		$widths  = isset( $data['widths'] ) ? $data['widths'] : '100';
		$pcts    = Layout::percents( $widths );
		$columns = isset( $block['columns'] ) && is_array( $block['columns'] ) ? $block['columns'] : array();
		$bg_css  = $bg ? 'background:' . esc_attr( $bg ) . ';' : '';

		$html = '<tr><td style="padding:0;' . $bg_css . '"><table role="presentation" class="om-row" width="100%" cellpadding="0" cellspacing="0"><tr>';

		foreach ( $pcts as $i => $pct ) {
			$kids  = isset( $columns[ $i ]['children'] ) && is_array( $columns[ $i ]['children'] ) ? $columns[ $i ]['children'] : array();
			$inner = $this->render_list( $kids, $template, $context, (int) round( $width * $pct / 100 ), true );
			$html .= '<td class="om-col" width="' . absint( $pct ) . '%" valign="top" style="width:' . absint( $pct ) . '%;max-width:' . absint( $pct ) . '%;padding:' . $pad . 'px;vertical-align:top;">'
				. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
				. $inner
				. '</table></td>';
		}

		$html .= '</tr></table></td></tr>';

		return $html;
	}

	/**
	 * @param array $data      Data.
	 * @param int   $max_width Parent width.
	 * @param bool  $in_column Nested.
	 * @return string
	 */
	private function block_image( array $data, $max_width, $in_column ) {
		$url = isset( $data['url'] ) ? $data['url'] : '';
		if ( ! $url ) {
			return '';
		}

		$pct   = Layout::int_range( isset( $data['width'] ) ? $data['width'] : 100, 20, 100, 100 );
		$align = Layout::align( isset( $data['align'] ) ? $data['align'] : 'center', 'center' );
		$alt   = isset( $data['alt'] ) ? $data['alt'] : '';
		$px    = max( 40, (int) round( $max_width * $pct / 100 ) );
		$pad   = $in_column ? '8px 0' : '8px 32px 16px';

		return '<tr><td class="om-pad" style="padding:' . $pad . ';text-align:' . esc_attr( $align ) . ';">'
			. '<img class="om-img" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" width="' . $px . '" style="width:' . $pct . '%;max-width:100%;height:auto;border:0;display:inline-block;">'
			. '</td></tr>';
	}

	/**
	 * @param string $code       Code.
	 * @param array  $data       Data.
	 * @param bool   $in_column  Nested.
	 * @return string
	 */
	private function block_coupon( $code, array $data, $in_column ) {
		if ( ! $code ) {
			return '';
		}

		$color = Layout::hex( isset( $data['color'] ) ? $data['color'] : '', '#1d4ed8' );
		$pad   = $in_column ? '8px 0' : '8px 32px 16px';

		return '<tr><td class="om-pad" style="padding:' . $pad . ';text-align:center;">'
			. '<div class="om-coupon" style="display:inline-block;max-width:100%;box-sizing:border-box;border:1px dashed ' . esc_attr( $color ) . ';border-radius:8px;padding:12px 20px;font-size:18px;font-weight:bold;letter-spacing:0.08em;color:' . esc_attr( $color ) . ';overflow-wrap:anywhere;">'
			. esc_html( $code )
			. '</div></td></tr>';
	}

	/**
	 * Resolve a subject line.
	 *
	 * @param string $subject Subject.
	 * @param array  $context Context.
	 * @return string
	 */
	public function subject( $subject, array $context = array() ) {
		return wp_strip_all_tags( $this->variables->resolve( $subject, $context ) );
	}

	/**
	 * @param string $inner Inner HTML.
	 * @param int    $width Width.
	 * @param string $preheader Preheader.
	 * @return string
	 */
	private function wrap( $inner, $width, $preheader ) {
		$width = absint( $width );
		$pre   = $preheader
			? '<div style="display:none;font-size:1px;color:#fff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . esc_html( $preheader ) . '</div>'
			: '';

		$css = '@media only screen and (max-width:620px){'
			. 'html,body{width:100%!important;margin:0!important;padding:0!important;}'
			. '.om-wrap{width:100%!important;max-width:100%!important;border-radius:0!important;}'
			. '.om-row,.om-row tbody,.om-row tr{display:block!important;width:100%!important;}'
			. '.om-row .om-col{display:block!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important;}'
			. '.om-img,.om-logo,img{width:100%!important;max-width:100%!important;height:auto!important;}'
			. '.om-btn{display:block!important;width:100%!important;box-sizing:border-box!important;text-align:center!important;}'
			. '.om-pad{padding-left:16px!important;padding-right:16px!important;}'
			. '.om-coupon{max-width:100%!important;overflow-wrap:anywhere!important;}'
			. '}';

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<style type="text/css">' . $css . '</style></head>'
			. '<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1d2327;width:100%;">'
			. $pre
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;width:100%;"><tr><td class="om-outer-pad" align="center" style="padding:16px 8px;">'
			. '<table role="presentation" class="om-wrap" width="' . $width . '" cellpadding="0" cellspacing="0" style="width:100%;max-width:' . $width . 'px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">'
			. $inner
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * @param string $logo Logo URL.
	 * @param array  $data Data.
	 * @param bool   $in_column Nested.
	 * @return string
	 */
	private function block_logo( $logo, array $data, $in_column = false ) {
		$align = Layout::align( isset( $data['align'] ) ? $data['align'] : 'center', 'center' );
		$w     = Layout::int_range( isset( $data['width'] ) ? $data['width'] : 160, 40, 320, 160 );
		$pad   = $in_column ? '8px 0' : '32px 32px 8px';

		if ( ! $logo ) {
			$name = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
			return '<tr><td class="om-pad" style="padding:' . $pad . ';text-align:' . esc_attr( $align ) . ';font-size:20px;font-weight:bold;">' . $name . '</td></tr>';
		}

		return '<tr><td class="om-pad" style="padding:' . $pad . ';text-align:' . esc_attr( $align ) . ';">'
			. '<img class="om-logo" src="' . esc_url( $logo ) . '" alt="" width="' . $w . '" style="width:' . $w . 'px;max-width:100%;height:auto;border:0;display:inline-block;">'
			. '</td></tr>';
	}

	/**
	 * @param string $heading Heading.
	 * @param array  $data    Data.
	 * @param bool   $in_column Nested.
	 * @return string
	 */
	private function block_heading( $heading, array $data, $in_column = false ) {
		if ( ! $heading ) {
			return '';
		}

		$size  = Layout::int_range( isset( $data['font_size'] ) ? $data['font_size'] : 24, 14, 48, 24 );
		$color = Layout::hex( isset( $data['color'] ) ? $data['color'] : '', '#1d2327' );
		$align = Layout::align( isset( $data['align'] ) ? $data['align'] : 'center', 'center' );
		$pad   = $in_column ? '8px 0 12px' : '8px 32px 16px';

		return '<tr><td class="om-pad" style="padding:' . $pad . ';text-align:' . esc_attr( $align ) . ';font-size:' . $size . 'px;font-weight:bold;line-height:1.3;color:' . esc_attr( $color ) . ';">'
			. esc_html( $heading )
			. '</td></tr>';
	}

	/**
	 * @param string $html HTML.
	 * @param array  $data Data.
	 * @param bool   $in_column Nested.
	 * @return string
	 */
	private function block_text( $html, array $data, $in_column = false ) {
		if ( ! $html ) {
			return '';
		}

		$size  = Layout::int_range( isset( $data['font_size'] ) ? $data['font_size'] : 16, 10, 24, 16 );
		$color = Layout::hex( isset( $data['color'] ) ? $data['color'] : '', '#1d2327' );
		$align = Layout::align( isset( $data['align'] ) ? $data['align'] : 'left', 'left' );
		$pad   = $in_column ? '4px 0 12px' : '8px 32px 16px';

		return '<tr><td class="om-pad" style="padding:' . $pad . ';text-align:' . esc_attr( $align ) . ';font-size:' . $size . 'px;line-height:1.6;color:' . esc_attr( $color ) . ';">'
			. $html
			. '</td></tr>';
	}

	/**
	 * @param string $label Label.
	 * @param string $url   URL.
	 * @param array  $data  Data.
	 * @param bool   $in_column Nested.
	 * @return string
	 */
	private function block_cta( $label, $url, array $data, $in_column = false ) {
		if ( ! $label ) {
			return '';
		}

		$href   = $url ? $url : '{{store.url}}';
		$bg     = Layout::hex( isset( $data['background'] ) ? $data['background'] : '', '#1d4ed8' );
		$color  = Layout::hex( isset( $data['color'] ) ? $data['color'] : '', '#ffffff' );
		$size   = Layout::int_range( isset( $data['font_size'] ) ? $data['font_size'] : 16, 12, 24, 16 );
		$align  = Layout::align( isset( $data['align'] ) ? $data['align'] : 'center', 'center' );
		$full   = ! empty( $data['full_width'] );
		$pad    = $in_column ? '8px 0 16px' : '8px 32px 24px';
		$width  = $full ? 'width:100%;box-sizing:border-box;' : '';
		$display = $full ? 'display:block;' : 'display:inline-block;';
		$safe_href = preg_match( '/^\{\{\s*[a-z0-9_.]+\s*\}\}$/i', $href )
			? esc_attr( $href )
			: esc_url( $href, array( 'http', 'https', 'mailto' ) );

		return '<tr><td class="om-pad" style="padding:' . $pad . ';text-align:' . esc_attr( $align ) . ';">'
			. '<a class="om-btn" href="' . $safe_href . '" style="' . $display . $width . 'background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;font-size:' . $size . 'px;">'
			. esc_html( $label )
			. '</a></td></tr>';
	}

	/**
	 * @return string
	 */
	private function block_unsubscribe() {
		$text = __( 'Não quer mais receber estes emails?', 'ottermail' );

		return '<tr><td class="om-pad" style="padding:16px 32px 32px;text-align:center;font-size:12px;color:#646970;">'
			. esc_html( $text )
			. ' <a href="{{unsubscribe.url}}" style="color:#646970;">' . esc_html__( 'Descadastrar', 'ottermail' ) . '</a>'
			. '</td></tr>';
	}

	/**
	 * Product cards.
	 *
	 * @param array $ids Product IDs.
	 * @param bool  $in_column Nested.
	 * @return string
	 */
	public function render_products( array $ids, $in_column = false ) {
		if ( empty( $ids ) || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$pad  = $in_column ? '8px 0' : '8px 32px 16px';
		$html = '<tr><td class="om-pad" style="padding:' . $pad . ';">';

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}

			$image = wp_get_attachment_image_url( $product->get_image_id(), 'medium' );
			$price = $product->get_price_html();
			$name  = $product->get_name();
			$url   = $product->get_permalink();

			$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;border:1px solid #eee;border-radius:6px;"><tr>';
			if ( $image ) {
				$html .= '<td class="om-col" width="96" valign="top" style="padding:12px;"><img class="om-img" src="' . esc_url( $image ) . '" width="80" alt="" style="display:block;border:0;max-width:100%;height:auto;"></td>';
			}
			$html .= '<td valign="top" style="padding:12px;"><div style="font-weight:bold;margin-bottom:6px;">' . esc_html( $name ) . '</div>';
			$html .= '<div style="margin-bottom:8px;">' . wp_kses_post( $price ) . '</div>';
			$html .= '<a href="' . esc_url( $url ) . '" style="color:#1d4ed8;font-weight:bold;text-decoration:none;">' . esc_html__( 'Ver produto', 'ottermail' ) . '</a></td></tr></table>';
		}

		$html .= '</td></tr>';

		return $html;
	}

	/**
	 * Cart line items table.
	 *
	 * @param array $items Items.
	 * @return string
	 */
	public function render_cart_items( array $items ) {
		if ( empty( $items ) ) {
			return '';
		}

		$html = '<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin:12px 0;">';

		foreach ( $items as $item ) {
			$name = isset( $item['name'] ) ? $item['name'] : '';
			$qty  = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			$line = isset( $item['line_total'] ) ? $item['line_total'] : '';
			$img  = isset( $item['image'] ) ? $item['image'] : '';

			$html .= '<tr>';
			if ( $img ) {
				$html .= '<td width="64" style="border-bottom:1px solid #eee;"><img class="om-img" src="' . esc_url( $img ) . '" width="48" alt="" style="display:block;border:0;max-width:100%;height:auto;"></td>';
			}
			$html .= '<td style="border-bottom:1px solid #eee;">' . esc_html( $name ) . ' × ' . $qty . '</td>';
			$html .= '<td align="right" style="border-bottom:1px solid #eee;">' . esc_html( $line ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</table>';

		return $html;
	}
}
