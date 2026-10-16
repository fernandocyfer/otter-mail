<?php
defined( 'ABSPATH' ) || exit;

$report = isset( $report ) && is_array( $report ) ? $report : array();
$campaigns = isset( $campaigns ) && is_array( $campaigns ) ? $campaigns : array();

$num = static function ( $key ) use ( $report ) {
	return isset( $report[ $key ] ) ? $report[ $key ] : 0;
};

$money = static function ( $amount ) {
	$amount = (float) $amount;
	if ( function_exists( 'wc_price' ) ) {
		return wp_kses_post( wc_price( $amount ) );
	}

	return esc_html( number_format_i18n( $amount, 2 ) );
};

$click_of = static function ( $group, $id, $field ) use ( $report ) {
	$map = isset( $report[ $group ] ) && is_array( $report[ $group ] ) ? $report[ $group ] : array();
	$id  = (int) $id;
	if ( ! isset( $map[ $id ] ) || ! isset( $map[ $id ]->$field ) ) {
		return 0;
	}

	return $map[ $id ]->$field;
};

$status_labels = array(
	'draft'      => __( 'Rascunho', 'ottermail' ),
	'scheduled'  => __( 'Agendada', 'ottermail' ),
	'processing' => __( 'Enviando', 'ottermail' ),
	'sent'       => __( 'Enviada', 'ottermail' ),
	'paused'     => __( 'Pausada', 'ottermail' ),
	'cancelled'  => __( 'Cancelada', 'ottermail' ),
);

$title    = __( 'Relatórios', 'ottermail' );
$subtitle = __( 'Envios, automações e vendas atribuídas ao email. Sem métricas inventadas.', 'ottermail' );
include __DIR__ . '/partials-header.php';
?>
<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Envios', 'ottermail' ); ?></h2>
	</div>
	<div class="ottermail-cards ottermail-cards-3">
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Emails enviados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'emails_sent' ) ) ); ?></div>
			<p class="ottermail-card-hint">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: campaign emails, 2: automation emails */
						__( '%1$s de campanha e %2$s de automação. Aceitos pelo wp_mail.', 'ottermail' ),
						number_format_i18n( $num( 'emails_campaign' ) ),
						number_format_i18n( $num( 'emails_automation' ) )
					)
				);
				?>
			</p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Falhas de campanha', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'emails_failed' ) ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Todos os destinatários recusados pelo transporte, em qualquer campanha.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Clientes alcançados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'customers_reached' ) ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Emails distintos que receberam ao menos uma campanha.', 'ottermail' ); ?></p>
		</div>
	</div>
</section>

<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Cliques', 'ottermail' ); ?></h2>
	</div>
	<div class="ottermail-cards">
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Links enviados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'links_sent' ) ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Cada endereço http de campanha e automação. Emails de teste ficam de fora.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Cliques', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'link_clicks' ) ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Vezes que um link enviado foi clicado.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Compras após o clique', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'link_purchases' ) ) ); ?></div>
			<p class="ottermail-card-hint">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: revenue from clicked links */
						__( 'Primeiro pedido pago no mesmo navegador, dentro da validade do link. Receita: %s.', 'ottermail' ),
						html_entity_decode( wp_strip_all_tags( $money( $num( 'link_revenue' ) ) ) )
					)
				);
				?>
			</p>
		</div>
	</div>
</section>

<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Recuperação', 'ottermail' ); ?></h2>
		<a class="ottermail-section-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-carts' ) ); ?>"><?php esc_html_e( 'Ver carrinhos', 'ottermail' ); ?></a>
	</div>
	<div class="ottermail-cards ottermail-cards-3">
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Abandonados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'carts_abandoned' ) ) ); ?></div>
			<p class="ottermail-card-hint">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: abandoned carts, 2: abandoned checkouts */
						__( '%1$s no carrinho e %2$s no checkout, ainda sem compra.', 'ottermail' ),
						number_format_i18n( $num( 'carts_abandoned_cart' ) ),
						number_format_i18n( $num( 'carts_abandoned_checkout' ) )
					)
				);
				?>
			</p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Recuperados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'carts_recovered' ) ) ); ?></div>
			<p class="ottermail-card-hint">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: restored carts, 2: attributed orders */
						__( '%1$s restaurados pelo link e %2$s pedidos atribuídos ao email.', 'ottermail' ),
						number_format_i18n( $num( 'carts_restored' ) ),
						number_format_i18n( (int) $num( 'converted_link' ) + (int) $num( 'converted_assisted' ) )
					)
				);
				?>
			</p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Receita recuperada', 'ottermail' ); ?></div>
			<div class="ottermail-card-value ottermail-card-value-price"><?php echo $money( $num( 'recovered_revenue' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Soma dos pedidos pagos depois do link ou de um email de automação.', 'ottermail' ); ?></p>
		</div>
	</div>
</section>

<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Atribuição das vendas', 'ottermail' ); ?></h2>
	</div>
	<table class="widefat striped ottermail-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Origem', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Pedidos', 'ottermail' ); ?></th>
				<th><?php esc_html_e( 'Receita', 'ottermail' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Link de recuperação', 'ottermail' ); ?></td>
				<td><?php echo esc_html( number_format_i18n( $num( 'converted_link' ) ) ); ?></td>
				<td><?php echo $money( $num( 'revenue_link' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Email enviado, sem clique no link', 'ottermail' ); ?></td>
				<td><?php echo esc_html( number_format_i18n( $num( 'converted_assisted' ) ) ); ?></td>
				<td><?php echo $money( $num( 'revenue_assisted' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
		</tbody>
	</table>
</section>

<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Automações', 'ottermail' ); ?></h2>
		<a class="ottermail-section-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-automations' ) ); ?>"><?php esc_html_e( 'Ver automações', 'ottermail' ); ?></a>
	</div>
	<?php
	$automations = isset( $report['automations'] ) && is_array( $report['automations'] ) ? $report['automations'] : array();
	$automation_status = array(
		'draft'  => __( 'Rascunho', 'ottermail' ),
		'active' => __( 'Ativa', 'ottermail' ),
		'paused' => __( 'Pausada', 'ottermail' ),
	);
	$automation_trigger = array(
		'cart_abandoned'     => __( 'Carrinho abandonado', 'ottermail' ),
		'checkout_abandoned' => __( 'Checkout abandonado', 'ottermail' ),
		'post_purchase'      => __( 'Pós-compra', 'ottermail' ),
	);
	?>
	<div class="ottermail-cards">
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Inscrições', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'automation_enrolled' ) ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Pessoas que entraram em alguma sequência.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Em andamento', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'automation_active' ) ) ); ?></div>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Concluídas', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'automation_completed' ) ) ); ?></div>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-label"><?php esc_html_e( 'Falhas', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $num( 'automation_failed' ) ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Sequências que pararam depois das tentativas de envio.', 'ottermail' ); ?></p>
		</div>
	</div>
	<?php if ( empty( $automations ) ) : ?>
		<p class="ottermail-empty"><?php esc_html_e( 'Nenhuma automação ainda.', 'ottermail' ); ?></p>
	<?php else : ?>
		<table class="widefat striped ottermail-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nome', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Gatilho', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Inscritos', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Em andamento', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Concluídas', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Canceladas', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Falhas', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Emails', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Cliques', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Compras', 'ottermail' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $automations as $automation ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-automation-edit&id=' . (int) $automation->id ) ); ?>">
								<?php echo esc_html( $automation->name ); ?>
							</a>
						</td>
						<td><?php echo esc_html( isset( $automation_trigger[ $automation->trigger_type ] ) ? $automation_trigger[ $automation->trigger_type ] : $automation->trigger_type ); ?></td>
						<td>
							<span class="ottermail-badge ottermail-badge-<?php echo esc_attr( $automation->status ); ?>">
								<?php echo esc_html( isset( $automation_status[ $automation->status ] ) ? $automation_status[ $automation->status ] : $automation->status ); ?>
							</span>
						</td>
						<td><?php echo esc_html( number_format_i18n( $automation->enrolled ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $automation->active_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $automation->completed_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $automation->cancelled_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $automation->failed_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $automation->sent_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $click_of( 'clicks_automation', $automation->id, 'clicks' ) ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $click_of( 'clicks_automation', $automation->id, 'purchases' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>

<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Campanhas', 'ottermail' ); ?></h2>
		<a class="ottermail-section-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-campaigns' ) ); ?>"><?php esc_html_e( 'Ver campanhas', 'ottermail' ); ?></a>
	</div>
	<?php if ( empty( $campaigns ) ) : ?>
		<p class="ottermail-empty"><?php esc_html_e( 'Nenhuma campanha ainda.', 'ottermail' ); ?></p>
	<?php else : ?>
		<table class="widefat striped ottermail-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nome', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Destinatários', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Enviados', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Falhas', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Cliques', 'ottermail' ); ?></th>
					<th><?php esc_html_e( 'Compras', 'ottermail' ); ?></th>
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
						<td>
							<span class="ottermail-badge ottermail-badge-<?php echo esc_attr( $campaign->status ); ?>">
								<?php echo esc_html( isset( $status_labels[ $campaign->status ] ) ? $status_labels[ $campaign->status ] : $campaign->status ); ?>
							</span>
						</td>
						<td><?php echo esc_html( number_format_i18n( $campaign->recipients_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $campaign->sent_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $campaign->failed_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $click_of( 'clicks_campaign', $campaign->id, 'clicks' ) ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $click_of( 'clicks_campaign', $campaign->id, 'purchases' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( count( $campaigns ) >= 100 ) : ?>
			<p class="ottermail-note"><?php esc_html_e( 'A tabela mostra as 100 campanhas mais recentes. Os totais de envio e falha acima incluem todas.', 'ottermail' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</section>

<p class="ottermail-note"><?php esc_html_e( 'O link do email abre o carrinho da loja, ou o endereço salvo nas configurações. O clique e a compra seguinte, no mesmo navegador, entram no relatório. A abertura do email não é medida.', 'ottermail' ); ?></p>
</div>
