<?php
/**
 * Public unsubscribe page.
 *
 * @package OtterMail
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Descadastro', 'ottermail' ); ?></title>
	<style>
		body { font-family: Arial, Helvetica, sans-serif; background: #f4f4f5; color: #1d2327; margin: 0; padding: 48px 16px; }
		.box { max-width: 480px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; text-align: center; }
	</style>
</head>
<body>
	<div class="box">
		<?php if ( ! empty( $success ) ) : ?>
			<h1><?php esc_html_e( 'Descadastrado', 'ottermail' ); ?></h1>
			<p><?php esc_html_e( 'Voce nao recebera mais emails de marketing desta loja.', 'ottermail' ); ?></p>
		<?php else : ?>
			<h1><?php esc_html_e( 'Link invalido', 'ottermail' ); ?></h1>
			<p><?php esc_html_e( 'Nao foi possivel confirmar o descadastro.', 'ottermail' ); ?></p>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Voltar a loja', 'ottermail' ); ?></a></p>
	</div>
</body>
</html>
