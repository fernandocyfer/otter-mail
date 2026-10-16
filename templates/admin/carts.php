<?php
defined( 'ABSPATH' ) || exit;
$title      = __( 'Carrinhos', 'ottermail' );
$subtitle   = __( 'Identifique a jornada, os e-mails enviados e a atribuição de cada venda.', 'ottermail' );
include __DIR__ . '/partials-header.php';

$status_labels = array(
	'active'     => __( 'Ativo', 'ottermail' ),
	'abandoned'  => __( 'Abandonado', 'ottermail' ),
	'checkout_abandoned' => __( 'Checkout abandonado', 'ottermail' ),
	'recovered'  => __( 'Recuperado', 'ottermail' ),
	'converted'  => __( 'Convertido', 'ottermail' ),
	'expired'    => __( 'Expirado', 'ottermail' ),
);
$current       = isset( $status ) ? (string) $status : '';
?>
<nav class="ottermail-filters" aria-label="<?php esc_attr_e( 'Filtrar carrinhos', 'ottermail' ); ?>">
	<a class="<?php echo '' === $current ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-carts' ) ); ?>"><?php esc_html_e( 'Todos', 'ottermail' ); ?></a>
	<?php foreach ( $status_labels as $st => $label ) : ?>
		<a class="<?php echo $current === $st ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-carts&status=' . $st ) ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
<?php if ( empty( $carts ) ) : ?>
	<div class="ottermail-empty-state">
		<h2><?php esc_html_e( 'Nenhum carrinho encontrado', 'ottermail' ); ?></h2>
		<p><?php esc_html_e( 'Os carrinhos aparecem aqui após a captura no checkout.', 'ottermail' ); ?></p>
	</div>
<?php else : ?>
	<table class="widefat striped ottermail-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Cliente', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Jornada', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'E-mails', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Resultado', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Pedido / receita', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Atualizado em', 'ottermail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $carts as $cart ) : ?>
				<?php
					$is_checkout = ! empty( $cart->checkout_started_at );
					$email_count = isset( $cart->automation_email_count ) ? (int) $cart->automation_email_count : 0;
					if ( 'converted' === $cart->status && ! empty( $cart->recovered_at ) ) {
						$result = __( 'Venda recuperada pelo link do e-mail', 'ottermail' );
						$tone   = 'success';
					} elseif ( 'converted' === $cart->status && $email_count > 0 ) {
						$result = __( 'Venda após e-mail (assistida)', 'ottermail' );
						$tone   = 'success';
					} elseif ( 'converted' === $cart->status ) {
						$result = __( 'Venda sem atribuição de e-mail', 'ottermail' );
						$tone   = 'neutral';
					} elseif ( 'recovered' === $cart->status ) {
						$result = __( 'Carrinho restaurado; aguardando compra', 'ottermail' );
						$tone   = 'warning';
					} else {
						$result = isset( $status_labels[ $cart->status ] ) ? $status_labels[ $cart->status ] : $cart->status;
						$tone   = $cart->status;
					}
				?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-cart-view&id=' . (int) $cart->id ) ); ?>"><?php echo esc_html( $cart->first_name ? $cart->first_name : '—' ); ?></a></td>
					<td>
						<strong><?php echo esc_html( $is_checkout ? __( 'Checkout', 'ottermail' ) : __( 'Carrinho', 'ottermail' ) ); ?></strong><br>
						<span class="ottermail-muted"><?php echo esc_html( $cart->email ); ?></span>
					</td>
					<td>
						<?php echo esc_html( sprintf( _n( '%d enviado', '%d enviados', $email_count, 'ottermail' ), $email_count ) ); ?>
						<?php if ( ! empty( $cart->last_automation_email_at ) ) : ?><br><span class="ottermail-muted"><?php echo esc_html( $cart->last_automation_email_at ); ?></span><?php endif; ?>
					</td>
					<td><span class="ottermail-badge ottermail-badge-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $result ); ?></span></td>
					<td>
						<?php if ( $cart->order_id ) : ?>
							#<?php echo esc_html( $cart->order_id ); ?><br><?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $cart->cart_total ) ) : esc_html( $cart->cart_total ); ?>
						<?php else : ?>—<?php endif; ?>
					</td>
					<td><?php echo esc_html( $cart->last_activity_at ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>
