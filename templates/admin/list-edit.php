<?php
defined( 'ABSPATH' ) || exit;

$list          = isset( $list ) ? $list : null;
$id            = $list ? (int) $list->id : 0;
$name          = $list ? $list->name : '';
$source        = $list ? $list->source : 'manual';
$form_key      = $list && $list->elementor_form_id ? $list->elementor_post_id . '|' . $list->elementor_form_id : '';
$forms         = isset( $elementor_forms ) ? $elementor_forms : array();
$contacts      = isset( $contacts ) ? $contacts : array();
$contact_count = isset( $contact_count ) ? (int) $contact_count : 0;

$title      = $id ? __( 'Editar lista', 'ottermail' ) : __( 'Nova lista', 'ottermail' );
$subtitle   = __( 'Monte a base de emails da newsletter: cadastro manual, CSV ou formulário do Elementor.', 'ottermail' );
$actions    = '<a class="ottermail-btn ottermail-btn-secondary" href="' . esc_url( admin_url( 'admin.php?page=ottermail-lists' ) ) . '">' . esc_html__( 'Voltar', 'ottermail' ) . '</a>';
$page_class = 'ottermail-is-list-edit';
include __DIR__ . '/partials-header.php';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form" id="ottermail-list-form">
	<?php wp_nonce_field( 'ottermail_save_list' ); ?>
	<input type="hidden" name="action" value="ottermail_save_list">
	<input type="hidden" name="list_id" value="<?php echo esc_attr( $id ); ?>">

	<section class="ottermail-panel">
		<div class="ottermail-section-head">
			<h2><?php esc_html_e( 'Lista', 'ottermail' ); ?></h2>
		</div>
		<div class="ottermail-field">
			<label for="list_name"><?php esc_html_e( 'Nome', 'ottermail' ); ?></label>
			<input class="regular-text" type="text" name="name" id="list_name" value="<?php echo esc_attr( $name ); ?>" required placeholder="<?php esc_attr_e( 'Ex.: Newsletter do site', 'ottermail' ); ?>">
		</div>
		<div class="ottermail-field">
			<span class="ottermail-field-label"><?php esc_html_e( 'Origem dos contatos', 'ottermail' ); ?></span>
			<div class="ottermail-choice ottermail-choice-3">
				<label class="ottermail-choice-card">
					<input type="radio" name="source" value="manual" <?php checked( $source, 'manual' ); ?>>
					<span class="ottermail-choice-title"><?php esc_html_e( 'Manual', 'ottermail' ); ?></span>
					<span class="ottermail-choice-desc"><?php esc_html_e( 'Adicione emails um a um.', 'ottermail' ); ?></span>
				</label>
				<label class="ottermail-choice-card">
					<input type="radio" name="source" value="csv" <?php checked( $source, 'csv' ); ?>>
					<span class="ottermail-choice-title"><?php esc_html_e( 'Importar CSV', 'ottermail' ); ?></span>
					<span class="ottermail-choice-desc"><?php esc_html_e( 'Planilha com email, nome e sobrenome.', 'ottermail' ); ?></span>
				</label>
				<label class="ottermail-choice-card">
					<input type="radio" name="source" value="elementor" <?php checked( $source, 'elementor' ); ?>>
					<span class="ottermail-choice-title"><?php esc_html_e( 'Formulário Elementor', 'ottermail' ); ?></span>
					<span class="ottermail-choice-desc"><?php esc_html_e( 'Sincroniza envios já salvos e novos.', 'ottermail' ); ?></span>
				</label>
			</div>
		</div>
		<div class="ottermail-field ottermail-list-source-panel" data-source="elementor" <?php echo 'elementor' === $source ? '' : 'hidden'; ?>>
			<label for="elementor_form"><?php esc_html_e( 'Formulário do Elementor', 'ottermail' ); ?></label>
			<?php if ( $forms ) : ?>
				<select name="elementor_form" id="elementor_form">
					<option value=""><?php esc_html_e( 'Selecionar formulário', 'ottermail' ); ?></option>
					<?php foreach ( $forms as $form ) : ?>
						<option value="<?php echo esc_attr( $form['key'] ); ?>" <?php selected( $form_key, $form['key'] ); ?>>
							<?php echo esc_html( $form['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Novos envios entram na lista automaticamente. Se o Elementor Pro salvar submissões, o botão de sincronizar importa o histórico.', 'ottermail' ); ?></p>
			<?php else : ?>
				<p class="ottermail-note"><?php esc_html_e( 'Nenhum formulário do Elementor foi encontrado nas páginas. Publique um widget Form e recarregue.', 'ottermail' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="ottermail-form-actions">
			<button type="submit" class="ottermail-btn ottermail-btn-primary"><?php esc_html_e( 'Salvar lista', 'ottermail' ); ?></button>
			<?php if ( $id && 'elementor' === $source ) : ?>
				<button type="submit" class="ottermail-btn ottermail-btn-secondary" form="ottermail-sync-elementor"><?php esc_html_e( 'Sincronizar Elementor agora', 'ottermail' ); ?></button>
			<?php endif; ?>
		</div>
	</section>
</form>

<?php if ( $id ) : ?>
	<?php if ( 'csv' === $source || 'manual' === $source ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form" enctype="multipart/form-data">
			<?php wp_nonce_field( 'ottermail_import_csv' ); ?>
			<input type="hidden" name="action" value="ottermail_import_csv">
			<input type="hidden" name="list_id" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="name" value="<?php echo esc_attr( $name ); ?>">
			<section class="ottermail-panel">
				<div class="ottermail-section-head">
					<h2><?php esc_html_e( 'Importar CSV', 'ottermail' ); ?></h2>
				</div>
				<p class="ottermail-panel-lead"><?php esc_html_e( 'Colunas reconhecidas: email, first_name/nome, last_name/sobrenome. Sem cabeçalho, usa email, nome, sobrenome. Até 5.000 linhas por importação.', 'ottermail' ); ?></p>
				<div class="ottermail-field">
					<label for="csv"><?php esc_html_e( 'Arquivo CSV', 'ottermail' ); ?></label>
					<input type="file" name="csv" id="csv" accept=".csv,text/csv" required>
				</div>
				<div class="ottermail-form-actions">
					<button type="submit" class="ottermail-btn ottermail-btn-secondary"><?php esc_html_e( 'Importar contatos', 'ottermail' ); ?></button>
				</div>
			</section>
		</form>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form">
		<?php wp_nonce_field( 'ottermail_add_contact' ); ?>
		<input type="hidden" name="action" value="ottermail_add_contact">
		<input type="hidden" name="list_id" value="<?php echo esc_attr( $id ); ?>">
		<section class="ottermail-panel">
			<div class="ottermail-section-head">
				<h2><?php esc_html_e( 'Adicionar contato', 'ottermail' ); ?></h2>
			</div>
			<div class="ottermail-grid">
				<div class="ottermail-field">
					<label for="contact_email"><?php esc_html_e( 'Email', 'ottermail' ); ?></label>
					<input type="email" name="email" id="contact_email" required>
				</div>
				<div class="ottermail-field">
					<label for="contact_first"><?php esc_html_e( 'Nome', 'ottermail' ); ?></label>
					<input type="text" name="first_name" id="contact_first">
				</div>
				<div class="ottermail-field">
					<label for="contact_last"><?php esc_html_e( 'Sobrenome', 'ottermail' ); ?></label>
					<input type="text" name="last_name" id="contact_last">
				</div>
			</div>
			<div class="ottermail-form-actions">
				<button type="submit" class="ottermail-btn ottermail-btn-secondary"><?php esc_html_e( 'Adicionar', 'ottermail' ); ?></button>
			</div>
		</section>
	</form>

	<section class="ottermail-panel">
		<div class="ottermail-section-head">
			<h2><?php esc_html_e( 'Contatos', 'ottermail' ); ?></h2>
			<span class="ottermail-muted"><?php echo esc_html( sprintf( __( '%s emails', 'ottermail' ), number_format_i18n( $contact_count ) ) ); ?></span>
		</div>
		<?php if ( empty( $contacts ) ) : ?>
			<p class="ottermail-empty"><?php esc_html_e( 'Nenhum contato nesta lista ainda.', 'ottermail' ); ?></p>
		<?php else : ?>
			<table class="widefat striped ottermail-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'ottermail' ); ?></th>
						<th><?php esc_html_e( 'Nome', 'ottermail' ); ?></th>
						<th><?php esc_html_e( 'Origem', 'ottermail' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $contacts as $contact ) : ?>
						<tr>
							<td><?php echo esc_html( $contact->email ); ?></td>
							<td><?php echo esc_html( trim( $contact->first_name . ' ' . $contact->last_name ) ); ?></td>
							<td><?php echo esc_html( $contact->source ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-inline-form">
									<?php wp_nonce_field( 'ottermail_delete_contact' ); ?>
									<input type="hidden" name="action" value="ottermail_delete_contact">
									<input type="hidden" name="list_id" value="<?php echo esc_attr( $id ); ?>">
									<input type="hidden" name="contact_id" value="<?php echo esc_attr( $contact->id ); ?>">
									<button type="submit" class="ottermail-btn ottermail-btn-danger" onclick="return confirm('<?php echo esc_js( __( 'Remover este contato?', 'ottermail' ) ); ?>');"><?php esc_html_e( 'Remover', 'ottermail' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $contact_count > count( $contacts ) ) : ?>
				<p class="ottermail-note"><?php esc_html_e( 'Mostrando os 200 contatos mais recentes.', 'ottermail' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</section>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ottermail-form">
		<?php wp_nonce_field( 'ottermail_delete_list' ); ?>
		<input type="hidden" name="action" value="ottermail_delete_list">
		<input type="hidden" name="list_id" value="<?php echo esc_attr( $id ); ?>">
		<div class="ottermail-form-actions">
			<button type="submit" class="ottermail-btn ottermail-btn-danger" onclick="return confirm('<?php echo esc_js( __( 'Excluir esta lista e todos os contatos?', 'ottermail' ) ); ?>');"><?php esc_html_e( 'Excluir lista', 'ottermail' ); ?></button>
		</div>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ottermail-sync-elementor" class="ottermail-inline-form">
		<?php wp_nonce_field( 'ottermail_sync_elementor' ); ?>
		<input type="hidden" name="action" value="ottermail_sync_elementor">
		<input type="hidden" name="list_id" value="<?php echo esc_attr( $id ); ?>">
	</form>
<?php endif; ?>
</div>
