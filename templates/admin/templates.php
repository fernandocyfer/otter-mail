<?php
defined( 'ABSPATH' ) || exit;
$title      = __( 'Templates', 'ottermail' );
$subtitle   = __( 'Layouts reutilizáveis para campanhas e automações.', 'ottermail' );
$actions    = '<a class="ottermail-btn ottermail-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-template-edit' ) ) . '">' . esc_html__( 'Criar template', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';

$category_labels = array(
	'marketing' => __( 'Marketing', 'ottermail' ),
	'cart'      => __( 'Carrinho', 'ottermail' ),
);
?>
<?php if ( empty( $templates ) ) : ?>
	<div class="ottermail-empty-state">
		<h2><?php esc_html_e( 'Nenhum template ainda', 'ottermail' ); ?></h2>
		<p><?php esc_html_e( 'Crie o layout do email com preview antes de disparar.', 'ottermail' ); ?></p>
		<a class="ottermail-btn ottermail-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-template-edit' ) ); ?>"><?php esc_html_e( 'Criar template', 'ottermail' ); ?></a>
	</div>
<?php else : ?>
	<table class="widefat striped ottermail-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Nome', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Categoria', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Tipo', 'ottermail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $templates as $template ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-template-edit&id=' . (int) $template->id ) ); ?>"><?php echo esc_html( $template->name ); ?></a></td>
					<td><?php echo esc_html( isset( $category_labels[ $template->category ] ) ? $category_labels[ $template->category ] : $template->category ); ?></td>
					<td><?php echo $template->is_system ? esc_html__( 'Sistema', 'ottermail' ) : esc_html__( 'Personalizado', 'ottermail' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>
