<?php
defined( 'ABSPATH' ) || exit;
$title      = __( 'Públicos', 'ottermail' );
$subtitle   = __( 'Segmentos dinâmicos de clientes WooCommerce.', 'ottermail' );
$actions    = '<a class="ottermail-btn ottermail-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-audience-edit' ) ) . '">' . esc_html__( 'Criar público', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';
?>
<?php if ( empty( $audiences ) ) : ?>
	<div class="ottermail-empty-state">
		<h2><?php esc_html_e( 'Nenhum público ainda', 'ottermail' ); ?></h2>
		<p><?php esc_html_e( 'Crie um segmento dinâmico de clientes para usar em campanhas.', 'ottermail' ); ?></p>
		<a class="ottermail-btn ottermail-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-audience-edit' ) ); ?>"><?php esc_html_e( 'Criar público', 'ottermail' ); ?></a>
	</div>
<?php else : ?>
	<table class="widefat striped ottermail-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Nome', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Correspondência', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Estimativa', 'ottermail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $audiences as $audience ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-audience-edit&id=' . (int) $audience->id ) ); ?>"><?php echo esc_html( $audience->name ); ?></a></td>
					<td><?php echo esc_html( 'any' === $audience->match_type ? __( 'Qualquer', 'ottermail' ) : __( 'Todas', 'ottermail' ) ); ?></td>
					<td><?php echo null !== $audience->last_estimate ? esc_html( number_format_i18n( $audience->last_estimate ) ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>
