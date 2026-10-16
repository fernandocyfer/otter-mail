<?php
defined( 'ABSPATH' ) || exit;
$title      = __( 'Automações', 'ottermail' );
$subtitle   = __( 'Sequências disparadas por carrinho ou checkout abandonado.', 'ottermail' );
$actions    = '<a class="ottermail-btn ottermail-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-automation-edit' ) ) . '">' . esc_html__( 'Criar automação', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';

$trigger_labels = array(
	'cart_abandoned' => __( 'Carrinho abandonado', 'ottermail' ),
	'checkout_abandoned' => __( 'Checkout abandonado', 'ottermail' ),
);
$status_labels  = array(
	'draft'  => __( 'Rascunho', 'ottermail' ),
	'active' => __( 'Ativa', 'ottermail' ),
	'paused' => __( 'Pausada', 'ottermail' ),
);
?>
<?php if ( empty( $automations ) ) : ?>
	<div class="ottermail-empty-state">
		<h2><?php esc_html_e( 'Nenhuma automação ainda', 'ottermail' ); ?></h2>
		<p><?php esc_html_e( 'Crie uma sequência para recuperar carrinhos abandonados.', 'ottermail' ); ?></p>
		<a class="ottermail-btn ottermail-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-automation-edit' ) ); ?>"><?php esc_html_e( 'Criar automação', 'ottermail' ); ?></a>
	</div>
<?php else : ?>
	<table class="widefat striped ottermail-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Nome', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Gatilho', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Status', 'ottermail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $automations as $automation ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-automation-edit&id=' . (int) $automation->id ) ); ?>"><?php echo esc_html( $automation->name ); ?></a></td>
					<td><?php echo esc_html( isset( $trigger_labels[ $automation->trigger_type ] ) ? $trigger_labels[ $automation->trigger_type ] : $automation->trigger_type ); ?></td>
					<td>
						<span class="ottermail-badge ottermail-badge-<?php echo esc_attr( $automation->status ); ?>">
							<?php echo esc_html( isset( $status_labels[ $automation->status ] ) ? $status_labels[ $automation->status ] : $automation->status ); ?>
						</span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>
