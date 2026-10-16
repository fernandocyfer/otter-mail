<?php
defined( 'ABSPATH' ) || exit;
$title      = __( 'Campanhas', 'ottermail' );
$subtitle   = __( 'Envios pontuais para um público da loja ou uma lista de newsletter.', 'ottermail' );
$actions    = '<a class="ottermail-btn ottermail-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-campaign-edit' ) ) . '">' . esc_html__( 'Criar campanha', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';

$status_labels = array(
	'draft'      => __( 'Rascunho', 'ottermail' ),
	'processing' => __( 'Enviando', 'ottermail' ),
	'sent'       => __( 'Enviada', 'ottermail' ),
	'failed'     => __( 'Falhou', 'ottermail' ),
	'cancelled'  => __( 'Cancelada', 'ottermail' ),
	'scheduled'  => __( 'Agendada', 'ottermail' ),
);
?>
<?php if ( empty( $campaigns ) ) : ?>
	<div class="ottermail-empty-state">
		<h2><?php esc_html_e( 'Nenhuma campanha ainda', 'ottermail' ); ?></h2>
		<p><?php esc_html_e( 'Crie sua primeira campanha para alcançar clientes da sua loja.', 'ottermail' ); ?></p>
		<a class="ottermail-btn ottermail-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-campaign-edit' ) ); ?>"><?php esc_html_e( 'Criar campanha', 'ottermail' ); ?></a>
	</div>
<?php else : ?>
	<table class="widefat striped ottermail-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Nome', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Assunto', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Status', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Destinatários', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Enviados', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Falhas', 'ottermail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $campaigns as $campaign ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-campaign-edit&id=' . (int) $campaign->id ) ); ?>">
							<?php echo esc_html( $campaign->name ); ?>
						</a>
					</td>
					<td><?php echo esc_html( $campaign->subject ); ?></td>
					<td>
						<span class="ottermail-badge ottermail-badge-<?php echo esc_attr( $campaign->status ); ?>">
							<?php echo esc_html( isset( $status_labels[ $campaign->status ] ) ? $status_labels[ $campaign->status ] : $campaign->status ); ?>
						</span>
					</td>
					<td><?php echo esc_html( number_format_i18n( $campaign->recipients_count ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $campaign->sent_count ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $campaign->failed_count ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>
