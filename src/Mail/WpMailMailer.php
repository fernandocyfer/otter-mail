<?php
/**
 * wp_mail() transport.
 *
 * @package OtterMail
 */

namespace OtterMail\Mail;

defined( 'ABSPATH' ) || exit;

class WpMailMailer implements MailerInterface {

	/**
	 * Last failure message from wp_mail / PHPMailer.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Human-readable reason for the last failed send.
	 *
	 * @return string
	 */
	public function last_error() {
		return $this->last_error;
	}

	/**
	 * {@inheritdoc}
	 */
	public function send( Message $message ) {
		$this->last_error = '';

		$to = sanitize_email( $message->to );
		if ( ! $to ) {
			$this->last_error = __( 'Destinatário inválido.', 'ottermail' );
			return false;
		}

		$from_email = sanitize_email( $message->from_email );
		if ( ! $from_email ) {
			$this->last_error = __( 'Email do remetente está vazio. Defina em OtterMail → Configurações.', 'ottermail' );
			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$from_name = sanitize_text_field( $message->from_name );
		$from      = $from_name ? sprintf( '%s <%s>', $from_name, $from_email ) : $from_email;
		$headers[] = 'From: ' . $from;

		$reply = sanitize_email( $message->reply_to );
		if ( $reply ) {
			$headers[] = 'Reply-To: ' . $reply;
		}

		foreach ( $message->headers as $header ) {
			$headers[] = $header;
		}

		/**
		 * Fires before an OtterMail message is handed to wp_mail().
		 *
		 * @param Message $message Message.
		 */
		do_action( 'ottermail_before_send_email', $message );

		add_action( 'wp_mail_failed', array( $this, 'capture_failure' ) );
		$result = wp_mail( $to, $message->subject, $message->html, $headers );
		remove_action( 'wp_mail_failed', array( $this, 'capture_failure' ) );

		if ( ! $result && ! $this->last_error ) {
			$this->last_error = __( 'wp_mail recusou o envio. Verifique o plugin SMTP e o email do remetente.', 'ottermail' );
		}

		if ( $this->last_error ) {
			$this->last_error = $this->humanize( $this->last_error );
		}

		/**
		 * Fires after wp_mail() returns.
		 *
		 * @param Message $message Message.
		 * @param bool    $result  Accepted by local transport.
		 */
		do_action( 'ottermail_after_send_email', $message, (bool) $result );

		return (bool) $result;
	}

	/**
	 * Store PHPMailer / wp_mail failure.
	 *
	 * @param \WP_Error $error Mail error.
	 */
	public function capture_failure( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		$this->last_error = $error->get_error_message();
	}

	/**
	 * Turn common PHPMailer strings into actionable Portuguese copy.
	 *
	 * @param string $raw Raw error.
	 * @return string
	 */
	private function humanize( $raw ) {
		$raw = trim( (string) $raw );
		$low = strtolower( $raw );

		if ( false !== strpos( $low, 'instantiate mail function' ) || false !== strpos( $low, 'could not instantiate' ) ) {
			return __( 'O PHP mail() não está disponível neste ambiente. Instale e configure um plugin SMTP (WP Mail SMTP, FluentSMTP) para o WordPress conseguir enviar.', 'ottermail' );
		}

		if ( false !== strpos( $low, 'smtp connect' ) || false !== strpos( $low, 'failed to connect' ) ) {
			return sprintf(
				/* translators: %s original SMTP error */
				__( 'Falha ao conectar no SMTP: %s', 'ottermail' ),
				$raw
			);
		}

		if ( false !== strpos( $low, 'authentication' ) || false !== strpos( $low, 'auth' ) ) {
			return sprintf(
				/* translators: %s original SMTP error */
				__( 'Autenticação SMTP recusada: %s', 'ottermail' ),
				$raw
			);
		}

		return $raw;
	}
}
