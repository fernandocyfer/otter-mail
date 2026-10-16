<?php
defined( 'ABSPATH' ) || exit;

$audience   = isset( $audience ) ? $audience : null;
$id         = $audience ? (int) $audience->id : 0;
$name       = $audience ? $audience->name : '';
$match      = $audience ? $audience->match_type : 'all';
$conditions = $audience ? $audience->conditions : array();
if ( empty( $conditions ) ) {
	$conditions = array(
		array(
			'field'    => 'order_status',
			'operator' => 'eq',
			'value'    => 'completed',
		),
	);
}

$field_groups = array(
	__( 'Cliente', 'ottermail' )   => array(
		'customer_email'      => __( 'Email do cliente', 'ottermail' ),
		'customer_name'       => __( 'Nome do cliente', 'ottermail' ),
		'customer_registered' => __( 'Usuário registrado (yes/no)', 'ottermail' ),
		'has_order'           => __( 'Possui pedido (yes/no)', 'ottermail' ),
	),
	__( 'Pedidos', 'ottermail' )   => array(
		'order_status'          => __( 'Status do pedido', 'ottermail' ),
		'order_count'           => __( 'Número de pedidos', 'ottermail' ),
		'total_spent'           => __( 'Valor total gasto', 'ottermail' ),
		'purchased_last_days'   => __( 'Comprou nos últimos X dias', 'ottermail' ),
		'not_purchased_days'    => __( 'Não compra há X dias', 'ottermail' ),
		'purchased_product'     => __( 'Comprou produto (ID)', 'ottermail' ),
		'not_purchased_product' => __( 'Não comprou produto (ID)', 'ottermail' ),
		'purchased_category'    => __( 'Comprou categoria (ID)', 'ottermail' ),
	),
	__( 'Carrinho', 'ottermail' )  => array(
		'has_abandoned_cart'    => __( 'Possui carrinho abandonado (sim/não)', 'ottermail' ),
		'abandoned_minutes'     => __( 'Carrinho parado há mais de X minutos', 'ottermail' ),
		'cart_total'            => __( 'Valor do carrinho', 'ottermail' ),
		'cart_contains_product' => __( 'Carrinho contém produto (ID)', 'ottermail' ),
	),
);

$operators = array(
	'eq'       => __( 'igual a', 'ottermail' ),
	'gte'      => __( 'maior ou igual', 'ottermail' ),
	'lte'      => __( 'menor ou igual', 'ottermail' ),
	'gt'       => __( 'maior que', 'ottermail' ),
	'lt'       => __( 'menor que', 'ottermail' ),
	'contains' => __( 'contém', 'ottermail' ),
);

$title      = $id ? __( 'Editar público', 'ottermail' ) : __( 'Novo público', 'ottermail' );
$subtitle   = __( 'Segmente clientes da loja com regras dinâmicas. O snapshot só é criado no disparo da campanha.', 'ottermail' );
$actions    = '<a class="ottermail-btn ottermail-btn-secondary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-audiences' ) ) . '">' . esc_html__( 'Voltar', 'ottermail' ) . '</a>';
$page_class = 'ottermail-is-audience-edit';
include __DIR__ . '/partials-header.php';

$estimate = $audience && null !== $audience->last_estimate ? number_format_i18n( $audience->last_estimate ) : '—';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form" id="ottermail-audience-form">
	<?php wp_nonce_field( 'ottermail_save_audience' ); ?>
	<input type="hidden" name="action" value="ottermail_save_audience">
	<input type="hidden" name="audience_id" value="<?php echo esc_attr( $id ); ?>">

	<div class="ottermail-layout">
		<div class="ottermail-layout-main">
			<section class="ottermail-panel">
				<div class="ottermail-section-head">
					<h2><?php esc_html_e( 'Identificação', 'ottermail' ); ?></h2>
				</div>
				<div class="ottermail-field">
					<label for="name"><?php esc_html_e( 'Nome do público', 'ottermail' ); ?></label>
					<input class="regular-text" type="text" name="name" id="name" value="<?php echo esc_attr( $name ); ?>" required placeholder="<?php esc_attr_e( 'Ex.: Clientes com pedido pago', 'ottermail' ); ?>">
				</div>
				<div class="ottermail-field">
					<span class="ottermail-field-label"><?php esc_html_e( 'Correspondência', 'ottermail' ); ?></span>
					<div class="ottermail-choice">
						<label class="ottermail-choice-card">
							<input type="radio" name="match_type" value="all" <?php checked( $match, 'all' ); ?>>
							<span class="ottermail-choice-title"><?php esc_html_e( 'Todas as condições', 'ottermail' ); ?></span>
							<span class="ottermail-choice-desc"><?php esc_html_e( 'O cliente precisa atender a cada regra.', 'ottermail' ); ?></span>
						</label>
						<label class="ottermail-choice-card">
							<input type="radio" name="match_type" value="any" <?php checked( $match, 'any' ); ?>>
							<span class="ottermail-choice-title"><?php esc_html_e( 'Qualquer condição', 'ottermail' ); ?></span>
							<span class="ottermail-choice-desc"><?php esc_html_e( 'Basta uma das regras ser verdadeira.', 'ottermail' ); ?></span>
						</label>
					</div>
				</div>
			</section>

			<section class="ottermail-panel">
				<div class="ottermail-section-head">
					<h2><?php esc_html_e( 'Condições', 'ottermail' ); ?></h2>
				</div>
				<p class="ottermail-panel-lead"><?php esc_html_e( 'O padrão é exigir todas as regras. A primeira linha vem como “status do pedido = completed”: apague-a se quiser filtrar só por carrinho. Em valor do carrinho use “maior ou igual”; em produto use o ID numérico.', 'ottermail' ); ?></p>
				<table class="ottermail-conditions" id="ottermail-conditions">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campo', 'ottermail' ); ?></th>
							<th><?php esc_html_e( 'Operador', 'ottermail' ); ?></th>
							<th><?php esc_html_e( 'Valor', 'ottermail' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Ações', 'ottermail' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $conditions as $cond ) : ?>
							<tr>
								<td>
									<select name="cond_field[]">
										<?php foreach ( $field_groups as $group => $fields ) : ?>
											<optgroup label="<?php echo esc_attr( $group ); ?>">
												<?php foreach ( $fields as $key => $label ) : ?>
													<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cond['field'], $key ); ?>><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</optgroup>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<select name="cond_operator[]">
										<?php foreach ( $operators as $op => $op_label ) : ?>
											<option value="<?php echo esc_attr( $op ); ?>" <?php selected( $cond['operator'], $op ); ?>><?php echo esc_html( $op_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="text" name="cond_value[]" value="<?php echo esc_attr( $cond['value'] ); ?>" placeholder="<?php esc_attr_e( 'Valor', 'ottermail' ); ?>"></td>
								<td>
									<button type="button" class="ottermail-icon-btn ottermail-remove-condition" aria-label="<?php esc_attr_e( 'Remover condição', 'ottermail' ); ?>">
										<span aria-hidden="true">&times;</span>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="ottermail-panel-actions">
					<button type="button" class="ottermail-btn ottermail-btn-secondary" id="ottermail-add-condition"><?php esc_html_e( 'Adicionar condição', 'ottermail' ); ?></button>
				</p>
			</section>

			<div class="ottermail-form-actions">
				<button type="submit" class="ottermail-btn ottermail-btn-primary"><?php esc_html_e( 'Salvar público', 'ottermail' ); ?></button>
			</div>
		</div>

		<aside class="ottermail-layout-side">
			<section class="ottermail-panel ottermail-estimate-card">
				<div class="ottermail-card-label"><?php esc_html_e( 'Clientes neste público', 'ottermail' ); ?></div>
				<div class="ottermail-card-value" id="ottermail-estimate-value"><?php echo esc_html( $estimate ); ?></div>
				<p class="ottermail-card-hint"><?php esc_html_e( 'Estimativa ao vivo. O envio usa um snapshot fixo.', 'ottermail' ); ?></p>
				<button type="button" class="ottermail-btn ottermail-btn-secondary" id="ottermail-estimate-btn"><?php esc_html_e( 'Atualizar estimativa', 'ottermail' ); ?></button>
			</section>
		</aside>
	</div>
</form>
</div>
