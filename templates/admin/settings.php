<?php
defined( 'ABSPATH' ) || exit;
$title    = __( 'Configurações', 'ottermail' );
$subtitle = __( 'Remetente, captura de carrinho e aparência dos emails.', 'ottermail' );
include __DIR__ . '/partials-header.php';
$s = $settings;
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form">
	<?php wp_nonce_field( 'ottermail_save_settings' ); ?>
	<input type="hidden" name="action" value="ottermail_save_settings">

	<div class="ottermail-stack">
		<section class="ottermail-panel">
			<div class="ottermail-section-head">
				<h2><?php esc_html_e( 'Geral', 'ottermail' ); ?></h2>
			</div>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Nome do remetente', 'ottermail' ); ?></th>
					<td><input class="regular-text" type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email do remetente', 'ottermail' ); ?></th>
					<td><input class="regular-text" type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email de resposta', 'ottermail' ); ?></th>
					<td><input class="regular-text" type="email" name="reply_to" value="<?php echo esc_attr( $s['reply_to'] ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Emails por lote', 'ottermail' ); ?></th>
					<td>
						<input type="number" min="1" max="200" name="batch_size" value="<?php echo esc_attr( $s['batch_size'] ); ?>">
						<p class="description"><?php esc_html_e( 'Quantidade processada em cada execução da fila.', 'ottermail' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Intervalo mínimo entre lotes', 'ottermail' ); ?></th>
					<td>
						<input type="number" min="10" max="3600" name="batch_interval_seconds" value="<?php echo esc_attr( $s['batch_interval_seconds'] ); ?>"> <?php esc_html_e( 'segundos', 'ottermail' ); ?>
						<p class="description"><?php esc_html_e( 'Evita que todos os lotes sejam disparados imediatamente.', 'ottermail' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Velocidade máxima', 'ottermail' ); ?></th>
					<td>
						<input type="number" min="1" max="100000" name="emails_per_hour" value="<?php echo esc_attr( $s['emails_per_hour'] ); ?>"> <?php esc_html_e( 'emails por hora', 'ottermail' ); ?>
						<p class="description"><?php esc_html_e( 'O intervalo entre lotes aumenta automaticamente para respeitar esta velocidade.', 'ottermail' ); ?></p>
					</td>
				</tr>
			</table>
		</section>

		<section class="ottermail-panel">
			<div class="ottermail-section-head">
				<h2><?php esc_html_e( 'Carrinhos', 'ottermail' ); ?></h2>
			</div>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Captura', 'ottermail' ); ?></th>
					<td><label><input type="checkbox" name="cart_capture_enabled" value="1" <?php checked( $s['cart_capture_enabled'] ); ?>> <?php esc_html_e( 'Ativar captura de carrinhos', 'ottermail' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Considerar abandonado depois de', 'ottermail' ); ?></th>
					<td>
						<input type="number" min="5" name="cart_abandon_minutes" value="<?php echo esc_attr( $s['cart_abandon_minutes'] ); ?>"> <?php esc_html_e( 'minutos', 'ottermail' ); ?>
						<p class="description"><?php esc_html_e( 'Vale só quando não existe automação ativa. Com automação ativa, o carrinho só é abandonado depois do tempo configurado na primeira etapa.', 'ottermail' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Validade do link de recuperação', 'ottermail' ); ?></th>
					<td><input type="number" min="1" name="cart_restore_ttl_days" value="<?php echo esc_attr( $s['cart_restore_ttl_days'] ); ?>"> <?php esc_html_e( 'dias', 'ottermail' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Destino após restaurar', 'ottermail' ); ?></th>
					<td>
						<select name="cart_restore_redirect">
							<option value="cart" <?php selected( $s['cart_restore_redirect'], 'cart' ); ?>><?php esc_html_e( 'Carrinho', 'ottermail' ); ?></option>
							<option value="checkout" <?php selected( $s['cart_restore_redirect'], 'checkout' ); ?>><?php esc_html_e( 'Checkout', 'ottermail' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Usado quando o endereço dos links fica em branco.', 'ottermail' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="ottermail-email-link-url"><?php esc_html_e( 'Endereço dos links', 'ottermail' ); ?></label></th>
					<td>
						<input class="regular-text" type="url" id="ottermail-email-link-url" name="email_link_url" value="<?php echo esc_attr( $s['email_link_url'] ); ?>" placeholder="<?php echo esc_attr( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Em branco, o link do email abre o carrinho da loja. Um endereço deste site aparece no email. Um endereço de outro site abre depois que o clique é contado.', 'ottermail' ); ?></p>
					</td>
				</tr>
			</table>
		</section>

		<section class="ottermail-panel">
			<div class="ottermail-section-head">
				<h2><?php esc_html_e( 'Emails', 'ottermail' ); ?></h2>
			</div>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Largura do template', 'ottermail' ); ?></th>
					<td><input type="number" min="320" max="800" name="email_width" value="<?php echo esc_attr( $s['email_width'] ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Logo padrão', 'ottermail' ); ?></th>
					<td>
						<div class="ottermail-media-field">
							<?php
							$logo_url = (string) $s['logo_url'];
							$has_logo = (bool) $logo_url;
							?>
							<div class="ottermail-media-preview"<?php echo $has_logo ? '' : ' hidden'; ?>>
								<img src="<?php echo $has_logo ? esc_url( $logo_url ) : ''; ?>" alt="">
							</div>
							<input class="regular-text ottermail-media-url" type="url" name="logo_url" id="logo_url" value="<?php echo esc_attr( $logo_url ); ?>" placeholder="https://">
							<p class="ottermail-media-actions">
								<button type="button" class="ottermail-btn ottermail-btn-secondary ottermail-media-select"><?php esc_html_e( 'Selecionar da galeria', 'ottermail' ); ?></button>
								<button type="button" class="ottermail-btn ottermail-btn-secondary ottermail-media-remove"<?php echo $has_logo ? '' : ' hidden'; ?>><?php esc_html_e( 'Remover', 'ottermail' ); ?></button>
							</p>
							<p class="description"><?php esc_html_e( 'Escolha uma imagem da biblioteca de mídia ou informe uma URL.', 'ottermail' ); ?></p>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Texto padrão do rodapé', 'ottermail' ); ?></th>
					<td><textarea class="large-text" rows="3" name="footer_text"><?php echo esc_textarea( $s['footer_text'] ); ?></textarea></td>
				</tr>
			</table>
		</section>

		<section class="ottermail-panel">
			<div class="ottermail-section-head">
				<h2><?php esc_html_e( 'Dados', 'ottermail' ); ?></h2>
			</div>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Desinstalação', 'ottermail' ); ?></th>
					<td>
						<label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $s['delete_data_on_uninstall'] ); ?>> <?php esc_html_e( 'Apagar dados ao desinstalar', 'ottermail' ); ?></label>
						<p class="description"><?php esc_html_e( 'Desativar o plugin não apaga dados. Só a desinstalação com esta opção marcada remove tabelas e configurações.', 'ottermail' ); ?></p>
					</td>
				</tr>
			</table>
		</section>
	</div>

	<div class="ottermail-form-actions">
		<button type="submit" class="ottermail-btn ottermail-btn-primary"><?php esc_html_e( 'Salvar configurações', 'ottermail' ); ?></button>
	</div>
</form>
<p class="ottermail-note"><?php esc_html_e( 'O OtterMail usa wp_mail() e não configura SMTP. Instale um plugin SMTP para entrega real.', 'ottermail' ); ?></p>
</div>
