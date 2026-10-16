<?php
/**
 * Dashboard.
 *
 * @var array $stats
 * @var array $activity
 *
 * @package OtterMail
 */

defined( 'ABSPATH' ) || exit;

$title      = __( 'Dashboard', 'ottermail' );
$subtitle   = __( 'Campanhas, públicos e recuperação de carrinho no WooCommerce.', 'ottermail' );
$page_class = 'ottermail-is-dashboard';
$actions    = '<a class="ottermail-btn ottermail-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-campaign-edit' ) ) . '">' . esc_html__( 'Criar campanha', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';

$emails     = (int) $stats['emails_sent'];
$campaigns  = (int) $stats['campaigns_sent'];
$customers  = (int) $stats['customers_reached'];
$abandoned  = (int) $stats['carts_abandoned'];
$restored   = isset( $stats['carts_restored'] ) ? (int) $stats['carts_restored'] : 0;
$bought     = isset( $stats['converted_attributed'] ) ? (int) $stats['converted_attributed'] : 0;
$recovered  = (int) $stats['carts_recovered'];
$revenue    = $stats['recovered_revenue'];
$link_clicks = isset( $stats['link_clicks'] ) ? (int) $stats['link_clicks'] : 0;
$link_orders = isset( $stats['link_purchases'] ) ? (int) $stats['link_purchases'] : 0;
$is_fresh   = empty( $activity ) && 0 === $emails && 0 === $abandoned && 0 === $campaigns;

$icon = static function ( $name ) {
	$icons = array(
		'mail'     => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M4 7.5 12 13l8-5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
		'mega'     => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10v4a2 2 0 0 0 2 2h1l2 3h2v-5h1l8 3V7l-8 3H9V8H6a2 2 0 0 0-2 2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>',
		'users'    => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M4 19c.6-3 2.6-4.5 5-4.5S13.4 16 14 19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="17" cy="9" r="2.2" stroke="currentColor" stroke-width="1.7"/><path d="M16.5 14.6c2 .3 3.6 1.6 4.2 4.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
		'cart'     => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h1.5l1.2 9.2a2 2 0 0 0 2 1.8h7.8a2 2 0 0 0 2-1.6L20 8H7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1.3" fill="currentColor"/><circle cx="17" cy="20" r="1.3" fill="currentColor"/></svg>',
		'refresh'  => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.2-5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M20 5v5h-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		'currency' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.2" stroke="currentColor" stroke-width="1.7"/><path d="M12 7.5v9M9.5 9.2c.6-.8 1.5-1.2 2.5-1.2 1.6 0 2.6.8 2.6 2 0 2.7-5.2 1.4-5.2 4.1 0 1.3 1.1 2.2 2.7 2.2 1.1 0 2-.5 2.6-1.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
		'auto'     => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h6l2 3h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="5" cy="7" r="2" stroke="currentColor" stroke-width="1.7"/><circle cx="19" cy="10" r="2" stroke="currentColor" stroke-width="1.7"/><path d="M5 17h8l2-3h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="5" cy="17" r="2" stroke="currentColor" stroke-width="1.7"/><circle cx="19" cy="14" r="2" stroke="currentColor" stroke-width="1.7"/></svg>',
		'template' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="3.5" width="14" height="17" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 8h7M8.5 12h7M8.5 16h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
		'segment'  => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.2" stroke="currentColor" stroke-width="1.7"/><path d="M12 4v16M4.5 10.5h15" stroke="currentColor" stroke-width="1.7"/><path d="M7 18.2A10 10 0 0 0 17 7.2" stroke="currentColor" stroke-width="1.7"/></svg>',
	);

	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
};
?>

<?php if ( $is_fresh ) : ?>
	<div class="ottermail-hero">
		<div class="ottermail-hero-copy">
			<h2><?php esc_html_e( 'Comece a enviar para a sua loja', 'ottermail' ); ?></h2>
			<p><?php esc_html_e( 'Crie um template, defina um público e dispare a primeira campanha. A recuperação de carrinho entra quando houver checkouts abandonados.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-hero-actions">
			<a class="ottermail-btn ottermail-btn-light" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-template-edit' ) ); ?>"><?php esc_html_e( 'Novo template', 'ottermail' ); ?></a>
			<a class="ottermail-btn ottermail-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-audience-edit' ) ); ?>"><?php esc_html_e( 'Novo público', 'ottermail' ); ?></a>
		</div>
	</div>
<?php endif; ?>

<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Marketing', 'ottermail' ); ?></h2>
		<a class="ottermail-section-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-reports' ) ); ?>"><?php esc_html_e( 'Ver relatórios', 'ottermail' ); ?></a>
	</div>
	<div class="ottermail-cards ottermail-cards-3">
		<div class="ottermail-card">
			<div class="ottermail-card-icon"><?php echo $icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Emails enviados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $emails ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Mensagens entregues para envio. Não confirma que a pessoa abriu.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-icon"><?php echo $icon( 'mega' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Campanhas enviadas', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $campaigns ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Campanhas que já foram disparadas.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-icon"><?php echo $icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Clientes alcançados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $customers ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Pessoas diferentes que receberam uma campanha. Emails de carrinho não entram.', 'ottermail' ); ?></p>
		</div>
	</div>
</section>

<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Links dos emails', 'ottermail' ); ?></h2>
		<a class="ottermail-section-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-reports' ) ); ?>"><?php esc_html_e( 'Ver relatórios', 'ottermail' ); ?></a>
	</div>
	<div class="ottermail-cards ottermail-cards-3">
		<div class="ottermail-card">
			<div class="ottermail-card-icon"><?php echo $icon( 'template' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Links enviados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( isset( $stats['links_sent'] ) ? (int) $stats['links_sent'] : 0 ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Links dos emails enviados desde que o rastreamento começou.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-icon"><?php echo $icon( 'refresh' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Cliques', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $link_clicks ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Quantas vezes alguém abriu um link do email.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-icon ottermail-card-icon-ok"><?php echo $icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Compras pelo link', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $link_orders ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'A pessoa abriu o link e pagou neste navegador, dentro do prazo.', 'ottermail' ); ?></p>
		</div>
	</div>
</section>

<section class="ottermail-section">
	<div class="ottermail-section-head">
		<h2><?php esc_html_e( 'Recuperação de carrinho', 'ottermail' ); ?></h2>
		<a class="ottermail-section-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-carts' ) ); ?>"><?php esc_html_e( 'Ver carrinhos', 'ottermail' ); ?></a>
	</div>
	<div class="ottermail-cards ottermail-cards-3">
		<div class="ottermail-card">
			<div class="ottermail-card-icon ottermail-card-icon-warn"><?php echo $icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Abandonados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $abandoned ) ); ?></div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Ainda sem compra.', 'ottermail' ); ?></p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-icon ottermail-card-icon-ok"><?php echo $icon( 'refresh' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Recuperados', 'ottermail' ); ?></div>
			<div class="ottermail-card-value"><?php echo esc_html( number_format_i18n( $recovered ) ); ?></div>
			<p class="ottermail-card-hint">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: people who opened the link and have not paid, 2: paid orders after the email or link */
						__( '%1$s abriram o link e ainda não pagaram. %2$s já compraram.', 'ottermail' ),
						number_format_i18n( $restored ),
						number_format_i18n( $bought )
					)
				);
				?>
			</p>
		</div>
		<div class="ottermail-card">
			<div class="ottermail-card-icon ottermail-card-icon-ok"><?php echo $icon( 'currency' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ottermail-card-label"><?php esc_html_e( 'Receita recuperada', 'ottermail' ); ?></div>
			<div class="ottermail-card-value ottermail-card-value-price">
				<?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $revenue ) ) : esc_html( $revenue ); ?>
			</div>
			<p class="ottermail-card-hint"><?php esc_html_e( 'Pedidos pagos depois que o nosso email saiu, mesmo sem clique.', 'ottermail' ); ?></p>
		</div>
	</div>
</section>

<div class="ottermail-grid ottermail-grid-dashboard">
	<section class="ottermail-panel">
		<div class="ottermail-section-head">
			<h2><?php esc_html_e( 'Atalhos', 'ottermail' ); ?></h2>
		</div>
		<div class="ottermail-tiles">
			<a class="ottermail-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-campaign-edit' ) ); ?>">
				<span class="ottermail-tile-icon"><?php echo $icon( 'mega' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="ottermail-tile-body">
					<span class="ottermail-tile-title"><?php esc_html_e( 'Criar campanha', 'ottermail' ); ?></span>
					<span class="ottermail-tile-desc"><?php esc_html_e( 'Enviar um recado para um público.', 'ottermail' ); ?></span>
				</span>
			</a>
			<a class="ottermail-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-automation-edit' ) ); ?>">
				<span class="ottermail-tile-icon"><?php echo $icon( 'auto' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="ottermail-tile-body">
					<span class="ottermail-tile-title"><?php esc_html_e( 'Criar automação', 'ottermail' ); ?></span>
					<span class="ottermail-tile-desc"><?php esc_html_e( 'Sequência de abandono de carrinho.', 'ottermail' ); ?></span>
				</span>
			</a>
			<a class="ottermail-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-audience-edit' ) ); ?>">
				<span class="ottermail-tile-icon"><?php echo $icon( 'segment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="ottermail-tile-body">
					<span class="ottermail-tile-title"><?php esc_html_e( 'Criar público', 'ottermail' ); ?></span>
					<span class="ottermail-tile-desc"><?php esc_html_e( 'Segmentar clientes da loja.', 'ottermail' ); ?></span>
				</span>
			</a>
			<a class="ottermail-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-list-edit' ) ); ?>">
				<span class="ottermail-tile-icon"><?php echo $icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="ottermail-tile-body">
					<span class="ottermail-tile-title"><?php esc_html_e( 'Criar lista', 'ottermail' ); ?></span>
					<span class="ottermail-tile-desc"><?php esc_html_e( 'Contatos, CSV ou formulário Elementor.', 'ottermail' ); ?></span>
				</span>
			</a>
			<a class="ottermail-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-template-edit' ) ); ?>">
				<span class="ottermail-tile-icon"><?php echo $icon( 'template' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="ottermail-tile-body">
					<span class="ottermail-tile-title"><?php esc_html_e( 'Criar template', 'ottermail' ); ?></span>
					<span class="ottermail-tile-desc"><?php esc_html_e( 'Layout do email com preview.', 'ottermail' ); ?></span>
				</span>
			</a>
		</div>
	</section>

	<section class="ottermail-panel">
		<div class="ottermail-section-head">
			<h2><?php esc_html_e( 'Atividade recente', 'ottermail' ); ?></h2>
		</div>
		<?php if ( empty( $activity ) ) : ?>
			<p class="ottermail-empty"><?php esc_html_e( 'Nenhuma atividade ainda. Os eventos aparecem aqui após o primeiro envio ou abandono.', 'ottermail' ); ?></p>
		<?php else : ?>
			<ul class="ottermail-activity">
				<?php foreach ( $activity as $row ) : ?>
					<?php
					$tone = \OtterMail\Support\Logger::event_tone( $row->event_type );
					$when = $row->created_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at ) : '';
					?>
					<li class="ottermail-activity-item ottermail-tone-<?php echo esc_attr( $tone ); ?>">
						<span class="ottermail-activity-dot" aria-hidden="true"></span>
						<div class="ottermail-activity-body">
							<strong><?php echo esc_html( \OtterMail\Support\Logger::event_label( $row->event_type ) ); ?></strong>
							<?php if ( ! empty( $row->message ) ) : ?>
								<span class="ottermail-activity-msg"><?php echo esc_html( $row->message ); ?></span>
							<?php endif; ?>
							<?php if ( $when ) : ?>
								<time class="ottermail-muted" datetime="<?php echo esc_attr( $row->created_at ); ?>"><?php echo esc_html( $when ); ?></time>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>
</div>
