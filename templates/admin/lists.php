<?php
defined( 'ABSPATH' ) || exit;

$title      = __( 'Listas de newsletter', 'ottermail' );
$subtitle   = __( 'Contatos manuais, CSV ou formulários do Elementor para disparar campanhas.', 'ottermail' );
$actions    = '<a class="ottermail-btn ottermail-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-list-edit' ) ) . '">' . esc_html__( 'Criar lista', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';
?>
<?php if ( empty( $lists ) ) : ?>
	<div class="ottermail-empty-state">
		<h2><?php esc_html_e( 'Nenhuma lista ainda', 'ottermail' ); ?></h2>
		<p><?php esc_html_e( 'Crie uma lista, importe um CSV ou ligue um formulário do Elementor para disparar newsletters.', 'ottermail' ); ?></p>
		<a class="ottermail-btn ottermail-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-list-edit' ) ); ?>"><?php esc_html_e( 'Criar lista', 'ottermail' ); ?></a>
	</div>
<?php else : ?>
	<table class="widefat striped ottermail-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Nome', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Origem', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Contatos', 'ottermail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $lists as $list ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-list-edit&id=' . (int) $list->id ) ); ?>">
							<?php echo esc_html( $list->name ); ?>
						</a>
					</td>
					<td><?php echo esc_html( $list->source_label() ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $list->contact_count ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>
