<?php
/**
 * Admin shell wrapper. All plugin UI lives inside .ottermail-wrap.
 *
 * @var string $title
 * @var string $subtitle
 * @var string $actions
 * @var string $page_class
 *
 * @package OtterMail
 */

defined( 'ABSPATH' ) || exit;

$page_class = isset( $page_class ) ? $page_class : '';
?>
<div class="wrap ottermail-wrap<?php echo $page_class ? ' ' . esc_attr( $page_class ) : ''; ?>">
	<hr class="wp-header-end">
	<div class="ottermail-header">
		<div class="ottermail-brand">
			<span class="ottermail-brand-mark" aria-hidden="true">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
					<path d="M4 7.5 12 13l8-5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>
			<div class="ottermail-brand-text">
				<p class="ottermail-brand-name">OtterMail</p>
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( ! empty( $subtitle ) ) : ?>
					<p class="ottermail-subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="ottermail-header-actions">
			<?php if ( ! empty( $actions ) ) : ?>
				<?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
	</div>
