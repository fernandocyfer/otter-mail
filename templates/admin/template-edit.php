<?php
defined( 'ABSPATH' ) || exit;

$t               = ( isset( $template ) && $template instanceof \OtterMail\Templates\Template ) ? $template : null;
$id              = $t ? (int) $t->id : 0;
$return_campaign = isset( $return_campaign ) ? absint( $return_campaign ) : 0;
$blocks          = $t ? $t->blocks() : \OtterMail\Templates\Layout::defaults();
$catalog         = \OtterMail\Templates\Layout::catalog();

$title    = $id ? __( 'Editar template', 'ottermail' ) : __( 'Novo template', 'ottermail' );
$subtitle = __( 'Monte o email arrastando blocos. O preview usa o mesmo HTML do envio.', 'ottermail' );
if ( $return_campaign ) {
	$actions  = '<a class="ottermail-btn ottermail-btn-secondary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-campaign-edit&id=' . $return_campaign . '&step=2' ) ) . '">' . esc_html__( 'Voltar à campanha', 'ottermail' ) . '</a>';
	$subtitle = __( 'Personalize o layout em blocos e salve para voltar à campanha.', 'ottermail' );
} else {
	$actions = '<a class="ottermail-btn ottermail-btn-secondary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-templates' ) ) . '">' . esc_html__( 'Voltar', 'ottermail' ) . '</a>';
}
$page_class = 'ottermail-is-template-edit';
include __DIR__ . '/partials-header.php';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form" id="ottermail-template-form">
	<?php wp_nonce_field( 'ottermail_save_template' ); ?>
	<input type="hidden" name="action" value="ottermail_save_template">
	<input type="hidden" name="template_id" id="template_id" value="<?php echo esc_attr( $id ); ?>">
	<textarea name="layout_json" id="ottermail_layout_json" class="ottermail-layout-json" aria-hidden="true"><?php echo esc_textarea( wp_json_encode( $blocks ) ); ?></textarea>
	<input type="hidden" name="logo_url" value="<?php echo esc_attr( $t ? $t->logo_url : '' ); ?>">
			<input type="hidden" name="heading" value="<?php echo esc_attr( $t ? $t->heading : '' ); ?>">
			<textarea name="body" hidden><?php echo esc_textarea( $t ? $t->body : '' ); ?></textarea>
			<input type="hidden" name="cta_label" value="<?php echo esc_attr( $t ? $t->cta_label : '' ); ?>">
			<input type="hidden" name="cta_url" value="<?php echo esc_attr( $t ? $t->cta_url : '' ); ?>">
			<textarea name="footer" hidden><?php echo esc_textarea( $t ? $t->footer : '' ); ?></textarea>
	<input type="hidden" name="product_ids" value="<?php echo esc_attr( $t ? $t->product_ids_list() : '' ); ?>">
	<input type="hidden" name="show_products" value="<?php echo $t && $t->show_products ? '1' : '0'; ?>">
	<?php if ( $return_campaign ) : ?>
		<input type="hidden" name="return_campaign" value="<?php echo esc_attr( $return_campaign ); ?>">
	<?php endif; ?>

	<section class="ottermail-panel">
		<div class="ottermail-section-head">
			<h2><?php esc_html_e( 'Identificação', 'ottermail' ); ?></h2>
		</div>
		<div class="ottermail-grid">
			<div class="ottermail-field">
				<label for="template_name"><?php esc_html_e( 'Nome', 'ottermail' ); ?></label>
				<input class="regular-text" type="text" name="name" id="template_name" value="<?php echo esc_attr( $t ? $t->name : '' ); ?>" required>
			</div>
			<div class="ottermail-field">
				<label for="template_category"><?php esc_html_e( 'Categoria', 'ottermail' ); ?></label>
				<select name="category" id="template_category">
					<option value="marketing" <?php selected( $t ? $t->category : '', 'marketing' ); ?>><?php esc_html_e( 'Marketing', 'ottermail' ); ?></option>
					<option value="cart" <?php selected( $t ? $t->category : '', 'cart' ); ?>><?php esc_html_e( 'Carrinho', 'ottermail' ); ?></option>
				</select>
			</div>
			<div class="ottermail-field">
				<label for="subject"><?php esc_html_e( 'Assunto padrão', 'ottermail' ); ?></label>
				<input class="regular-text" type="text" name="subject" id="subject" value="<?php echo esc_attr( $t ? $t->subject : '' ); ?>">
			</div>
			<div class="ottermail-field">
				<label for="preheader"><?php esc_html_e( 'Preheader', 'ottermail' ); ?></label>
				<input class="regular-text" type="text" name="preheader" id="preheader" value="<?php echo esc_attr( $t ? $t->preheader : '' ); ?>">
			</div>
			<div class="ottermail-field">
				<label for="coupon_code"><?php esc_html_e( 'Cupom WooCommerce', 'ottermail' ); ?></label>
				<input class="regular-text" type="text" name="coupon_code" id="coupon_code" value="<?php echo esc_attr( $t ? $t->coupon_code : '' ); ?>" placeholder="<?php esc_attr_e( 'Código do cupom criado no WooCommerce', 'ottermail' ); ?>">
			</div>
		</div>
	</section>

	<section class="ottermail-panel" id="ottermail-builder">
		<div class="ottermail-section-head">
			<h2><?php esc_html_e( 'Layout', 'ottermail' ); ?></h2>
		</div>
		<p class="ottermail-panel-lead"><?php esc_html_e( 'Clique em uma seção ou linha para estruturar o email, depois adicione conteúdo na coluna destacada. Use ⋮⋮ ou as setas para reordenar. Variáveis: {{customer.first_name}}, {{cart.items}}, {{cart.total}}, {{cart.restore_url}}, {{coupon.code}}, {{unsubscribe.url}}.', 'ottermail' ); ?></p>
		<div class="ottermail-builder">
			<aside class="ottermail-builder-palette" aria-label="<?php esc_attr_e( 'Blocos', 'ottermail' ); ?>">
				<?php
				$groups = array(
					'layout'  => __( 'Estrutura', 'ottermail' ),
					'content' => __( 'Conteúdo', 'ottermail' ),
				);
				foreach ( $groups as $group => $group_label ) :
					?>
					<p class="ottermail-field-label"><?php echo esc_html( $group_label ); ?></p>
					<?php foreach ( $catalog as $type => $meta ) : ?>
						<?php if ( ( isset( $meta['group'] ) ? $meta['group'] : 'content' ) !== $group ) { continue; } ?>
						<button type="button" class="ottermail-palette-item ottermail-palette-item--<?php echo esc_attr( $group ); ?>" draggable="true" data-type="<?php echo esc_attr( $type ); ?>">
							<?php echo esc_html( $meta['label'] ); ?>
						</button>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</aside>
			<div class="ottermail-builder-stage">
				<div id="ottermail-builder-canvas" class="ottermail-builder-canvas"></div>
			</div>
		</div>
	</section>

	<div class="ottermail-form-actions">
		<button type="submit" class="ottermail-btn ottermail-btn-primary"><?php echo $return_campaign ? esc_html__( 'Salvar e voltar à campanha', 'ottermail' ) : esc_html__( 'Salvar template', 'ottermail' ); ?></button>
		<?php if ( $id ) : ?>
			<button type="submit" class="ottermail-btn ottermail-btn-secondary" form="ottermail-template-action" name="do" value="duplicate"><?php esc_html_e( 'Duplicar', 'ottermail' ); ?></button>
			<?php if ( ! $t->is_system ) : ?>
				<button type="submit" class="ottermail-btn ottermail-btn-danger" form="ottermail-template-action" name="do" value="delete" onclick="return confirm('<?php echo esc_js( __( 'Excluir este template?', 'ottermail' ) ); ?>');"><?php esc_html_e( 'Excluir', 'ottermail' ); ?></button>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</form>
<?php if ( $id ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-inline-form" id="ottermail-template-action">
		<?php wp_nonce_field( 'ottermail_template_action' ); ?>
		<input type="hidden" name="action" value="ottermail_template_action">
		<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>">
		<?php if ( $return_campaign ) : ?>
			<input type="hidden" name="return_campaign" value="<?php echo esc_attr( $return_campaign ); ?>">
		<?php endif; ?>
	</form>
<?php endif; ?>

<div class="ottermail-preview-panel" id="ottermail-preview-panel">
	<div class="ottermail-preview-toolbar">
		<strong><?php esc_html_e( 'Preview do email', 'ottermail' ); ?></strong>
		<button type="button" class="ottermail-btn ottermail-btn-primary ottermail-preview-btn" data-template="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Atualizar preview', 'ottermail' ); ?></button>
		<button type="button" class="ottermail-btn ottermail-btn-secondary ottermail-preview-mode is-active" data-mode="desktop"><?php esc_html_e( 'Desktop', 'ottermail' ); ?></button>
		<button type="button" class="ottermail-btn ottermail-btn-secondary ottermail-preview-mode" data-mode="mobile"><?php esc_html_e( 'Mobile', 'ottermail' ); ?></button>
		<?php if ( $id ) : ?>
			<button type="button" class="ottermail-btn ottermail-btn-secondary ottermail-test-btn" data-template="<?php echo esc_attr( $id ); ?>" data-subject="<?php echo esc_attr( $t ? $t->subject : '' ); ?>"><?php esc_html_e( 'Enviar teste', 'ottermail' ); ?></button>
		<?php endif; ?>
	</div>
	<p class="ottermail-preview-subject" hidden>
		<?php esc_html_e( 'Assunto:', 'ottermail' ); ?> <span></span>
	</p>
	<div class="ottermail-preview-frame is-desktop" id="ottermail-email-preview">
		<iframe title="<?php esc_attr_e( 'Preview do email', 'ottermail' ); ?>"></iframe>
	</div>
</div>
</div>
