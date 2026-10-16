<?php
defined( 'ABSPATH' ) || exit;

$name        = $campaign ? $campaign->name : '';
$subject     = $campaign ? $campaign->subject : '';
$preheader   = $campaign ? $campaign->preheader : '';
$from_name   = $campaign && $campaign->from_name ? $campaign->from_name : $settings['from_name'];
$from_email  = $campaign && $campaign->from_email ? $campaign->from_email : $settings['from_email'];
$reply_to    = $campaign && $campaign->reply_to ? $campaign->reply_to : $settings['reply_to'];
$template_id = $campaign ? (int) $campaign->template_id : 0;
$audience_id = $campaign ? (int) $campaign->audience_id : 0;
$list_id     = $campaign ? (int) $campaign->list_id : 0;
$cid         = $campaign ? (int) $campaign->id : 0;
$lists       = isset( $lists ) ? $lists : array();
$selected_audience = null;
$selected_template = null;
$selected_list     = null;
foreach ( $audiences as $aud ) {
	if ( (int) $aud->id === $audience_id ) {
		$selected_audience = $aud;
		break;
	}
}
foreach ( $templates as $tpl ) {
	if ( (int) $tpl->id === $template_id ) {
		$selected_template = $tpl;
		break;
	}
}
foreach ( $lists as $lst ) {
	if ( (int) $lst->id === $list_id ) {
		$selected_list = $lst;
		break;
	}
}
$recipient_source = $list_id ? 'list' : 'audience';

$system_templates = array();
$custom_templates = array();
foreach ( $templates as $tpl ) {
	if ( $tpl->is_system ) {
		$system_templates[] = $tpl;
	} else {
		$custom_templates[] = $tpl;
	}
}

$title    = $cid ? __( 'Editar campanha', 'ottermail' ) : __( 'Nova campanha', 'ottermail' );
$subtitle = __( 'Configuração, conteúdo, destinatários e revisão.', 'ottermail' );
$actions  = '<a class="ottermail-btn ottermail-btn-secondary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-campaigns' ) ) . '">' . esc_html__( 'Voltar', 'ottermail' ) . '</a>';
include __DIR__ . '/partials-header.php';

$steps = array(
	1 => __( 'Configuração', 'ottermail' ),
	2 => __( 'Conteúdo', 'ottermail' ),
	3 => __( 'Destinatários', 'ottermail' ),
	4 => __( 'Revisão', 'ottermail' ),
);
?>
<nav class="ottermail-steps">
	<?php foreach ( $steps as $n => $label ) : ?>
		<a class="<?php echo (int) $step === $n ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-campaign-edit&id=' . $cid . '&step=' . $n ) ); ?>"><?php echo esc_html( $n . '. ' . $label ); ?></a>
	<?php endforeach; ?>
</nav>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form">
	<?php wp_nonce_field( 'ottermail_save_campaign' ); ?>
	<input type="hidden" name="action" value="ottermail_save_campaign">
	<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $cid ); ?>">
	<input type="hidden" name="wizard_step" value="<?php echo esc_attr( min( 4, (int) $step + 1 ) ); ?>">

	<?php if ( 1 === (int) $step ) : ?>
		<section class="ottermail-panel">
			<div class="ottermail-section-head">
				<h2><?php esc_html_e( 'Configuração', 'ottermail' ); ?></h2>
			</div>
			<table class="form-table">
				<tr>
					<th><label for="name"><?php esc_html_e( 'Nome interno', 'ottermail' ); ?></label></th>
					<td><input class="regular-text" type="text" id="name" name="name" value="<?php echo esc_attr( $name ); ?>" required></td>
				</tr>
				<tr>
					<th><label for="subject"><?php esc_html_e( 'Assunto', 'ottermail' ); ?></label></th>
					<td><input class="regular-text" type="text" id="subject" name="subject" value="<?php echo esc_attr( $subject ); ?>" required></td>
				</tr>
				<tr>
					<th><label for="preheader"><?php esc_html_e( 'Preheader', 'ottermail' ); ?></label></th>
					<td><input class="regular-text" type="text" id="preheader" name="preheader" value="<?php echo esc_attr( $preheader ); ?>"></td>
				</tr>
				<tr>
					<th><label for="from_name"><?php esc_html_e( 'Remetente', 'ottermail' ); ?></label></th>
					<td><input class="regular-text" type="text" id="from_name" name="from_name" value="<?php echo esc_attr( $from_name ); ?>"></td>
				</tr>
				<tr>
					<th><label for="from_email"><?php esc_html_e( 'Email do remetente', 'ottermail' ); ?></label></th>
					<td><input class="regular-text" type="email" id="from_email" name="from_email" value="<?php echo esc_attr( $from_email ); ?>"></td>
				</tr>
				<tr>
					<th><label for="reply_to"><?php esc_html_e( 'Responder para', 'ottermail' ); ?></label></th>
					<td><input class="regular-text" type="email" id="reply_to" name="reply_to" value="<?php echo esc_attr( $reply_to ); ?>"></td>
				</tr>
			</table>
		</section>
		<input type="hidden" name="template_id" value="<?php echo esc_attr( $template_id ); ?>">
		<input type="hidden" name="audience_id" value="<?php echo esc_attr( $audience_id ); ?>">
		<input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>">
		<div class="ottermail-form-actions">
			<button type="submit" class="ottermail-btn ottermail-btn-primary"><?php esc_html_e( 'Salvar e continuar', 'ottermail' ); ?></button>
		</div>
	<?php elseif ( 2 === (int) $step ) : ?>
		<input type="hidden" name="name" value="<?php echo esc_attr( $name ); ?>">
		<input type="hidden" name="subject" value="<?php echo esc_attr( $subject ); ?>">
		<input type="hidden" name="preheader" value="<?php echo esc_attr( $preheader ); ?>">
		<input type="hidden" name="from_name" value="<?php echo esc_attr( $from_name ); ?>">
		<input type="hidden" name="from_email" value="<?php echo esc_attr( $from_email ); ?>">
		<input type="hidden" name="reply_to" value="<?php echo esc_attr( $reply_to ); ?>">
		<input type="hidden" name="audience_id" value="<?php echo esc_attr( $audience_id ); ?>">
		<input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>">
		<section class="ottermail-panel">
			<div class="ottermail-section-head">
				<h2><?php esc_html_e( 'Conteúdo', 'ottermail' ); ?></h2>
			</div>
			<p class="ottermail-panel-lead"><?php esc_html_e( 'Escolha um layout, personalize o existente ou crie uma cópia. Templates de sistema não são alterados.', 'ottermail' ); ?></p>
			<div class="ottermail-field">
				<label for="template_id"><?php esc_html_e( 'Layout do email', 'ottermail' ); ?></label>
				<select name="template_id" id="template_id">
					<option value="0"><?php esc_html_e( 'Selecionar', 'ottermail' ); ?></option>
					<?php if ( $system_templates ) : ?>
						<optgroup label="<?php echo esc_attr__( 'Templates de sistema', 'ottermail' ); ?>">
							<?php foreach ( $system_templates as $tpl ) : ?>
								<option value="<?php echo esc_attr( $tpl->id ); ?>" data-system="1" <?php selected( $template_id, $tpl->id ); ?>><?php echo esc_html( $tpl->name ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
					<?php if ( $custom_templates ) : ?>
						<optgroup label="<?php echo esc_attr__( 'Meus layouts', 'ottermail' ); ?>">
							<?php foreach ( $custom_templates as $tpl ) : ?>
								<option value="<?php echo esc_attr( $tpl->id ); ?>" data-system="0" <?php selected( $template_id, $tpl->id ); ?>><?php echo esc_html( $tpl->name ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
				</select>
			</div>
			<p class="ottermail-template-system-hint" <?php echo ( $selected_template && $selected_template->is_system ) ? '' : 'hidden'; ?>><?php esc_html_e( 'Este é um template de sistema. Personalizar cria uma cópia editável, sem alterar o original.', 'ottermail' ); ?></p>
			<div class="ottermail-panel-actions">
				<button type="button" class="ottermail-btn ottermail-btn-secondary ottermail-preview-btn ottermail-needs-template" <?php disabled( $template_id < 1 ); ?>><?php esc_html_e( 'Preview', 'ottermail' ); ?></button>
				<button type="submit" name="ottermail_do" value="customize_template" class="ottermail-btn ottermail-btn-secondary ottermail-needs-template" <?php disabled( $template_id < 1 ); ?>><?php esc_html_e( 'Personalizar layout', 'ottermail' ); ?></button>
				<button type="submit" name="ottermail_do" value="copy_template" class="ottermail-btn ottermail-btn-secondary ottermail-needs-template" <?php disabled( $template_id < 1 ); ?>><?php esc_html_e( 'Criar cópia', 'ottermail' ); ?></button>
				<button type="submit" name="ottermail_do" value="new_template" class="ottermail-btn ottermail-btn-secondary"><?php esc_html_e( 'Novo template', 'ottermail' ); ?></button>
			</div>
			<div class="ottermail-preview-frame ottermail-campaign-preview is-desktop">
				<iframe title="<?php esc_attr_e( 'Preview do email', 'ottermail' ); ?>"></iframe>
			</div>
		</section>
		<div class="ottermail-form-actions">
			<button type="submit" class="ottermail-btn ottermail-btn-primary"><?php esc_html_e( 'Salvar e continuar', 'ottermail' ); ?></button>
		</div>
	<?php elseif ( 3 === (int) $step ) : ?>
		<input type="hidden" name="name" value="<?php echo esc_attr( $name ); ?>">
		<input type="hidden" name="subject" value="<?php echo esc_attr( $subject ); ?>">
		<input type="hidden" name="preheader" value="<?php echo esc_attr( $preheader ); ?>">
		<input type="hidden" name="from_name" value="<?php echo esc_attr( $from_name ); ?>">
		<input type="hidden" name="from_email" value="<?php echo esc_attr( $from_email ); ?>">
		<input type="hidden" name="reply_to" value="<?php echo esc_attr( $reply_to ); ?>">
		<input type="hidden" name="template_id" value="<?php echo esc_attr( $template_id ); ?>">
		<section class="ottermail-panel">
			<div class="ottermail-section-head">
				<h2><?php esc_html_e( 'Destinatários', 'ottermail' ); ?></h2>
			</div>
			<p class="ottermail-panel-lead"><?php esc_html_e( 'Envie para um público dinâmico da loja ou para uma lista de newsletter (manual, CSV ou formulário do Elementor).', 'ottermail' ); ?></p>
			<div class="ottermail-field">
				<span class="ottermail-field-label"><?php esc_html_e( 'Quem recebe', 'ottermail' ); ?></span>
				<div class="ottermail-choice">
					<label class="ottermail-choice-card">
						<input type="radio" name="recipient_source" value="audience" <?php checked( $recipient_source, 'audience' ); ?>>
						<span class="ottermail-choice-title"><?php esc_html_e( 'Público WooCommerce', 'ottermail' ); ?></span>
						<span class="ottermail-choice-desc"><?php esc_html_e( 'Segmento dinâmico de clientes da loja.', 'ottermail' ); ?></span>
					</label>
					<label class="ottermail-choice-card">
						<input type="radio" name="recipient_source" value="list" <?php checked( $recipient_source, 'list' ); ?>>
						<span class="ottermail-choice-title"><?php esc_html_e( 'Lista de newsletter', 'ottermail' ); ?></span>
						<span class="ottermail-choice-desc"><?php esc_html_e( 'Contatos cadastrados, importados ou do Elementor.', 'ottermail' ); ?></span>
					</label>
				</div>
			</div>
			<div class="ottermail-field ottermail-source-audience" <?php echo 'list' === $recipient_source ? 'hidden' : ''; ?>>
				<label for="audience_id"><?php esc_html_e( 'Público', 'ottermail' ); ?></label>
				<select name="audience_id" id="audience_id">
					<option value="0"><?php esc_html_e( 'Selecionar público', 'ottermail' ); ?></option>
					<?php foreach ( $audiences as $aud ) : ?>
						<option value="<?php echo esc_attr( $aud->id ); ?>" <?php selected( $audience_id, $aud->id ); ?>>
							<?php
							echo esc_html( $aud->name );
							if ( null !== $aud->last_estimate ) {
								echo ' (' . esc_html( number_format_i18n( $aud->last_estimate ) ) . ')';
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'O público é dinâmico até o disparo. No envio é criado um snapshot.', 'ottermail' ); ?></p>
				<p><a class="ottermail-section-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-audience-edit' ) ); ?>"><?php esc_html_e( 'Criar público', 'ottermail' ); ?></a></p>
			</div>
			<div class="ottermail-field ottermail-source-list" <?php echo 'list' === $recipient_source ? '' : 'hidden'; ?>>
				<label for="list_id"><?php esc_html_e( 'Lista de newsletter', 'ottermail' ); ?></label>
				<select name="list_id" id="list_id">
					<option value="0"><?php esc_html_e( 'Selecionar lista', 'ottermail' ); ?></option>
					<?php foreach ( $lists as $lst ) : ?>
						<option value="<?php echo esc_attr( $lst->id ); ?>" <?php selected( $list_id, $lst->id ); ?>>
							<?php
							echo esc_html( $lst->name );
							echo ' (' . esc_html( number_format_i18n( $lst->contact_count ) ) . ')';
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Inclui listas manuais, CSV e formulários do Elementor. No disparo, formulários são sincronizados de novo.', 'ottermail' ); ?></p>
				<p><a class="ottermail-section-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ottermail-list-edit' ) ); ?>"><?php esc_html_e( 'Criar ou importar lista', 'ottermail' ); ?></a></p>
			</div>
		</section>
		<div class="ottermail-form-actions">
			<button type="submit" class="ottermail-btn ottermail-btn-primary"><?php esc_html_e( 'Salvar e continuar', 'ottermail' ); ?></button>
		</div>
	<?php else : ?>
		<input type="hidden" name="name" value="<?php echo esc_attr( $name ); ?>">
		<input type="hidden" name="subject" value="<?php echo esc_attr( $subject ); ?>">
		<input type="hidden" name="preheader" value="<?php echo esc_attr( $preheader ); ?>">
		<input type="hidden" name="from_name" value="<?php echo esc_attr( $from_name ); ?>">
		<input type="hidden" name="from_email" value="<?php echo esc_attr( $from_email ); ?>">
		<input type="hidden" name="reply_to" value="<?php echo esc_attr( $reply_to ); ?>">
		<input type="hidden" name="template_id" value="<?php echo esc_attr( $template_id ); ?>">
		<input type="hidden" name="audience_id" value="<?php echo esc_attr( $audience_id ); ?>">
		<input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>">

		<div class="ottermail-stack">
			<section class="ottermail-panel">
				<div class="ottermail-section-head">
					<h2><?php esc_html_e( 'Revisão', 'ottermail' ); ?></h2>
				</div>
				<p class="ottermail-panel-lead"><?php esc_html_e( 'Confira os dados antes de enviar ou agendar.', 'ottermail' ); ?></p>
				<div class="ottermail-review-grid">
					<div class="ottermail-review-item">
						<strong><?php esc_html_e( 'Nome interno', 'ottermail' ); ?></strong>
						<p><?php echo $name ? esc_html( $name ) : esc_html__( 'Não definido', 'ottermail' ); ?></p>
					</div>
					<div class="ottermail-review-item">
						<strong><?php esc_html_e( 'Assunto', 'ottermail' ); ?></strong>
						<p><?php echo $subject ? esc_html( $subject ) : esc_html__( 'Não definido', 'ottermail' ); ?></p>
					</div>
					<div class="ottermail-review-item">
						<strong><?php esc_html_e( 'Preheader', 'ottermail' ); ?></strong>
						<p><?php echo $preheader ? esc_html( $preheader ) : esc_html__( 'Não definido', 'ottermail' ); ?></p>
					</div>
					<div class="ottermail-review-item">
						<strong><?php esc_html_e( 'Remetente', 'ottermail' ); ?></strong>
						<p><?php echo esc_html( $from_name . ' <' . $from_email . '>' ); ?></p>
					</div>
					<div class="ottermail-review-item">
						<strong><?php esc_html_e( 'Layout', 'ottermail' ); ?></strong>
						<p>
							<?php
							if ( $selected_template ) {
								echo esc_html( $selected_template->name );
								if ( $selected_template->is_system ) {
									echo ' · ' . esc_html__( 'Sistema', 'ottermail' );
								}
							} else {
								esc_html_e( 'Nenhum template selecionado.', 'ottermail' );
							}
							?>
						</p>
						<?php if ( $template_id ) : ?>
							<button type="submit" name="ottermail_do" value="customize_template" class="ottermail-btn ottermail-btn-secondary ottermail-review-edit"><?php esc_html_e( 'Personalizar layout', 'ottermail' ); ?></button>
						<?php endif; ?>
					</div>
					<div class="ottermail-review-item">
						<strong><?php esc_html_e( 'Destinatários', 'ottermail' ); ?></strong>
						<p>
							<?php
							if ( $selected_list ) {
								echo esc_html( $selected_list->name );
								echo ' — ' . esc_html( sprintf( __( 'Lista: %s contatos', 'ottermail' ), number_format_i18n( $selected_list->contact_count ) ) );
							} elseif ( $selected_audience ) {
								echo esc_html( $selected_audience->name );
								if ( null !== $selected_audience->last_estimate ) {
									echo ' — ' . esc_html( sprintf( __( 'Estimativa: %s destinatários', 'ottermail' ), number_format_i18n( $selected_audience->last_estimate ) ) );
								}
							} else {
								esc_html_e( 'Nenhum público ou lista selecionado.', 'ottermail' );
							}
							?>
						</p>
					</div>
				</div>
			</section>

			<section class="ottermail-panel">
				<div class="ottermail-section-head">
					<h2><?php esc_html_e( 'Enviar', 'ottermail' ); ?></h2>
				</div>
				<p class="ottermail-panel-lead"><?php esc_html_e( 'Salve o rascunho, envie um teste ou dispare a campanha agora.', 'ottermail' ); ?></p>
				<div class="ottermail-form-actions">
					<button type="submit" class="ottermail-btn ottermail-btn-secondary"><?php esc_html_e( 'Salvar rascunho', 'ottermail' ); ?></button>
					<button type="button" class="ottermail-btn ottermail-btn-secondary ottermail-test-btn" data-template="<?php echo esc_attr( $template_id ); ?>" data-subject="<?php echo esc_attr( $subject ); ?>"><?php esc_html_e( 'Enviar teste', 'ottermail' ); ?></button>
					<button type="submit" name="ottermail_do" value="send_now" class="ottermail-btn ottermail-btn-primary ottermail-confirm-send"><?php esc_html_e( 'Enviar agora', 'ottermail' ); ?></button>
				</div>
			</section>

			<section class="ottermail-panel">
				<div class="ottermail-section-head">
					<h2><?php esc_html_e( 'Agendar', 'ottermail' ); ?></h2>
				</div>
				<p class="ottermail-panel-lead"><?php esc_html_e( 'Defina data e hora para o disparo automático.', 'ottermail' ); ?></p>
				<div class="ottermail-field">
					<label for="scheduled_at"><?php esc_html_e( 'Enviar em', 'ottermail' ); ?></label>
					<input type="datetime-local" name="scheduled_at" id="scheduled_at">
				</div>
				<div class="ottermail-form-actions">
					<button type="submit" name="ottermail_do" value="schedule" class="ottermail-btn ottermail-btn-secondary"><?php esc_html_e( 'Agendar', 'ottermail' ); ?></button>
				</div>
			</section>
		</div>
	<?php endif; ?>
</form>
</div>
