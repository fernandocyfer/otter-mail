<?php
defined( 'ABSPATH' ) || exit;
$title    = __( 'Carrinho', 'ottermail' );
$subtitle = __( 'Detalhe do checkout capturado.', 'ottermail' );
$actions  = '<a class="ottermail-btn ottermail-btn-secondary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-carts' ) ) . '">' . esc_html__( 'Voltar', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';

if ( ! $cart ) {
	echo '<div class="ottermail-empty-state"><p>' . esc_html__( 'Carrinho não encontrado.', 'ottermail' ) . '</p></div></div>';
	return;
}

$contents = json_decode( (string) $cart->cart_contents, true );
$items    = isset( $contents['items'] ) && is_array( $contents['items'] ) ? $contents['items'] : array();

$status_labels = array(
	'active'     => __( 'Ativo', 'ottermail' ),
	'abandoned'  => __( 'Abandonado', 'ottermail' ),
	'checkout_abandoned' => __( 'Checkout abandonado', 'ottermail' ),
	'recovered'  => __( 'Recuperado', 'ottermail' ),
	'converted'  => __( 'Convertido', 'ottermail' ),
	'expired'    => __( 'Expirado', 'ottermail' ),
);
?>
<section class="ottermail-panel">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Resumo', 'ottermail' ); ?></h2>
	</div>
	<ul class="ottermail-review-list">
		<li>
			<strong><?php esc_html_e( 'Email', 'ottermail' ); ?></strong>
			<?php echo esc_html( $cart->email ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Status', 'ottermail' ); ?></strong>
			<span class="ottermail-badge ottermail-badge-<?php echo esc_attr( $cart->status ); ?>">
				<?php echo esc_html( isset( $status_labels[ $cart->status ] ) ? $status_labels[ $cart->status ] : $cart->status ); ?>
			</span>
		</li>
		<li>
			<strong><?php esc_html_e( 'Total', 'ottermail' ); ?></strong>
			<?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $cart->cart_total ) ) : esc_html( $cart->cart_total ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Pedido', 'ottermail' ); ?></strong>
			<?php echo $cart->order_id ? (int) $cart->order_id : '—'; ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Origem da recuperação', 'ottermail' ); ?></strong>
			<?php
			if ( 'converted' === $cart->status && ! empty( $cart->recovered_at ) ) {
				esc_html_e( 'Link do e-mail restaurou o carrinho', 'ottermail' );
			} elseif ( 'converted' === $cart->status ) {
				esc_html_e( 'Sem atribuição confirmada de e-mail', 'ottermail' );
			} else {
				esc_html_e( 'Ainda não convertido', 'ottermail' );
			}
			?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Criado', 'ottermail' ); ?></strong>
			<?php echo esc_html( $cart->created_at ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Última atividade', 'ottermail' ); ?></strong>
			<?php echo esc_html( $cart->last_activity_at ); ?>
		</li>
	</ul>
</section>

<section class="ottermail-panel">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Linha do tempo da automação', 'ottermail' ); ?></h2>
	</div>
	<?php if ( empty( $logs ) ) : ?>
		<p class="ottermail-empty"><?php esc_html_e( 'Nenhum evento de automação registrado para este carrinho.', 'ottermail' ); ?></p>
	<?php else : ?>
		<ul class="ottermail-review-list">
			<?php foreach ( $logs as $log ) : ?>
				<li><strong><?php echo esc_html( \OtterMail\Support\Logger::event_label( $log->event_type ) ); ?></strong><?php echo esc_html( $log->created_at ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<section class="ottermail-panel">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Produtos', 'ottermail' ); ?></h2>
	</div>
	<?php if ( empty( $items ) ) : ?>
		<p class="ottermail-empty"><?php esc_html_e( 'Nenhum item neste carrinho.', 'ottermail' ); ?></p>
	<?php else : ?>
		<table class="widefat striped ottermail-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Produto', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Qtd', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Total', 'ottermail' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $item['name'] ) ? $item['name'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $item['quantity'] ) ? $item['quantity'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $item['line_total'] ) ? $item['line_total'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
</div>
