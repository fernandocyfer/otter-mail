<?php
defined( 'ABSPATH' ) || exit;
$id         = $automation ? (int) $automation->id : 0;
$title      = $id ? __( 'Editar automação', 'ottermail' ) : __( 'Nova automação', 'ottermail' );
$subtitle   = __( 'Sequência de até 5 emails após o abandono do carrinho.', 'ottermail' );
$actions    = '<a class="ottermail-btn ottermail-btn-secondary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-automations' ) ) . '">' . esc_html__( 'Voltar', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';

if ( empty( $steps ) ) {
	$steps = array(
		(object) array(
			'delay_value' => 1,
			'delay_unit'  => 'hours',
			'template_id' => 0,
		),
	);
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form">
	<?php wp_nonce_field( 'ottermail_save_automation' ); ?>
	<input type="hidden" name="action" value="ottermail_save_automation">
	<input type="hidden" name="automation_id" value="<?php echo esc_attr( $id ); ?>">

	<section class="ottermail-panel">
		<div class="ottermail-section-head">
			<h2><?php esc_html_e( 'Identificação', 'ottermail' ); ?></h2>
		</div>
		<table class="form-table">
			<tr>
				<th><label><?php esc_html_e( 'Nome', 'ottermail' ); ?></label></th>
				<td><input class="regular-text" type="text" name="name" value="<?php echo esc_attr( $automation ? $automation->name : '' ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="ottermail-trigger-type"><?php esc_html_e( 'Gatilho', 'ottermail' ); ?></label></th>
				<td>
					<select id="ottermail-trigger-type" name="trigger_type">
						<option value="cart_abandoned" <?php selected( $automation ? $automation->trigger_type : 'cart_abandoned', 'cart_abandoned' ); ?>><?php esc_html_e( 'Carrinho abandonado', 'ottermail' ); ?></option>
						<option value="checkout_abandoned" <?php selected( $automation ? $automation->trigger_type : '', 'checkout_abandoned' ); ?>><?php esc_html_e( 'Checkout abandonado', 'ottermail' ); ?></option>
						<option value="post_purchase" <?php selected( $automation ? $automation->trigger_type : '', 'post_purchase' ); ?>><?php esc_html_e( 'Pós-compra', 'ottermail' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Status', 'ottermail' ); ?></label></th>
				<td>
					<select name="status">
						<option value="draft" <?php selected( $automation ? $automation->status : 'draft', 'draft' ); ?>><?php esc_html_e( 'Rascunho', 'ottermail' ); ?></option>
						<option value="active" <?php selected( $automation ? $automation->status : '', 'active' ); ?>><?php esc_html_e( 'Ativa', 'ottermail' ); ?></option>
						<option value="paused" <?php selected( $automation ? $automation->status : '', 'paused' ); ?>><?php esc_html_e( 'Pausada', 'ottermail' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
	</section>

	<section class="ottermail-panel">
		<div class="ottermail-section-head">
			<h2><?php esc_html_e( 'Sequência (até 5 emails)', 'ottermail' ); ?></h2>
		</div>
		<table class="ottermail-conditions" id="ottermail-steps">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Esperar', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Unidade', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Template', 'ottermail' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $steps as $step ) : ?>
					<tr>
						<td><input type="number" min="0" name="step_delay[]" value="<?php echo esc_attr( $step->delay_value ); ?>"></td>
						<td>
							<select name="step_unit[]">
								<option value="minutes" <?php selected( $step->delay_unit, 'minutes' ); ?>><?php esc_html_e( 'minutos', 'ottermail' ); ?></option>
								<option value="hours" <?php selected( $step->delay_unit, 'hours' ); ?>><?php esc_html_e( 'horas', 'ottermail' ); ?></option>
								<option value="days" <?php selected( $step->delay_unit, 'days' ); ?>><?php esc_html_e( 'dias', 'ottermail' ); ?></option>
							</select>
						</td>
						<td>
							<select name="step_template[]">
								<option value="0"><?php esc_html_e( 'Selecionar', 'ottermail' ); ?></option>
								<?php foreach ( $templates as $tpl ) : ?>
									<option value="<?php echo esc_attr( $tpl->id ); ?>" <?php selected( (int) $step->template_id, (int) $tpl->id ); ?>><?php echo esc_html( $tpl->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="ottermail-panel-actions">
			<button type="button" class="ottermail-btn ottermail-btn-secondary" id="ottermail-add-step"><?php esc_html_e( 'Adicionar etapa', 'ottermail' ); ?></button>
		</p>
	</section>

	<div class="ottermail-form-actions">
		<button type="submit" class="ottermail-btn ottermail-btn-primary"><?php esc_html_e( 'Salvar automação', 'ottermail' ); ?></button>
	</div>
</form>
</div>
