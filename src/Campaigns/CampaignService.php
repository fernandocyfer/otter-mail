<?php
/**
 * Campaign send lifecycle.
 *
 * @package OtterMail
 */

namespace OtterMail\Campaigns;

use OtterMail\Audiences\AudienceBuilder;
use OtterMail\Audiences\AudienceRepository;
use OtterMail\Core\Settings;
use OtterMail\Lists\ContactListRepository;
use OtterMail\Lists\ElementorForms;
use OtterMail\Mail\Message;
use OtterMail\Mail\RateLimiter;
use OtterMail\Mail\WpMailMailer;
use OtterMail\Support\Logger;
use OtterMail\Support\Scheduler;
use OtterMail\Templates\TemplateRenderer;
use OtterMail\Templates\TemplateRepository;
use OtterMail\Templates\Variables;
use OtterMail\Tracking\ClickTracker;
use OtterMail\Unsubscribe\UnsubscribeService;

defined( 'ABSPATH' ) || exit;

class CampaignService {

	/**
	 * @var CampaignRepository
	 */
	private $campaigns;

	/**
	 * @var AudienceRepository
	 */
	private $audiences;

	/**
	 * @var AudienceBuilder
	 */
	private $builder;

	/**
	 * @var TemplateRepository
	 */
	private $templates;

	/**
	 * @var TemplateRenderer
	 */
	private $renderer;

	/**
	 * @var WpMailMailer
	 */
	private $mailer;

	/**
	 * @var UnsubscribeService
	 */
	private $unsubscribe;

	public function __construct() {
		$this->campaigns   = new CampaignRepository();
		$this->audiences   = new AudienceRepository();
		$this->builder     = new AudienceBuilder();
		$this->templates   = new TemplateRepository();
		$this->renderer    = new TemplateRenderer();
		$this->mailer      = new WpMailMailer();
		$this->unsubscribe = new UnsubscribeService();
	}

	/**
	 * Register Action Scheduler callbacks.
	 */
	public function register() {
		add_action( Scheduler::HOOK_PROCESS_CAMPAIGN, array( $this, 'process' ), 10, 1 );
		add_action( Scheduler::HOOK_SEND_BATCH, array( $this, 'send_batch' ), 10, 1 );
	}

	/**
	 * Queue a campaign for immediate processing.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return true|\WP_Error
	 */
	public function send_now( $campaign_id ) {
		$campaign = $this->campaigns->get( $campaign_id );
		if ( ! $campaign ) {
			return new \WP_Error( 'missing', __( 'Campanha nao encontrada.', 'ottermail' ) );
		}
		if ( ! $campaign->audience_id && ! $campaign->list_id ) {
			return new \WP_Error( 'audience', __( 'Selecione um publico da loja ou uma lista de newsletter antes de enviar.', 'ottermail' ) );
		}
		if ( ! $campaign->template_id || ! $campaign->subject ) {
			return new \WP_Error( 'content', __( 'Assunto e template sao obrigatorios.', 'ottermail' ) );
		}

		$this->campaigns->patch(
			$campaign_id,
			array(
				'status'     => 'processing',
				'started_at' => current_time( 'mysql' ),
			)
		);

		Logger::log( 'campaign_started', __( 'Campanha iniciada.', 'ottermail' ), 'campaign', $campaign_id );
		Scheduler::once( Scheduler::HOOK_PROCESS_CAMPAIGN, array( (int) $campaign_id ) );

		return true;
	}

	/**
	 * Schedule a campaign.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $datetime    MySQL datetime in site timezone.
	 * @return true|\WP_Error
	 */
	public function schedule( $campaign_id, $datetime ) {
		$ts = strtotime( $datetime );
		if ( ! $ts || $ts < time() ) {
			return new \WP_Error( 'schedule', __( 'Horario de agendamento invalido.', 'ottermail' ) );
		}

		$this->campaigns->patch(
			$campaign_id,
			array(
				'status'       => 'scheduled',
				'scheduled_at' => $datetime,
			)
		);

		Scheduler::once( Scheduler::HOOK_PROCESS_CAMPAIGN, array( (int) $campaign_id ), $ts );

		return true;
	}

	/**
	 * Build snapshot and enqueue batches.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function process( $campaign_id ) {
		$campaign = $this->campaigns->get( $campaign_id );
		if ( ! $campaign || in_array( $campaign->status, array( 'cancelled', 'paused' ), true ) ) {
			return;
		}

		$resolved = $this->resolve_recipients( $campaign );
		if ( null === $resolved ) {
			$this->campaigns->patch( $campaign_id, array( 'status' => 'cancelled' ) );
			return;
		}
		$queued   = array();
		$skipped  = 0;

		foreach ( $resolved as $row ) {
			if ( $this->unsubscribe->is_suppressed( $row['email'], 'marketing' ) ) {
				++$skipped;
				continue;
			}
			$queued[] = $row;
		}

		$inserted = $this->campaigns->insert_recipients( $campaign_id, $queued );
		$counters = $this->campaigns->refresh_counters( $campaign_id );
		$this->campaigns->patch(
			$campaign_id,
			array(
				'status'        => 'processing',
				'skipped_count' => $skipped,
			)
		);

		Logger::log(
			'campaign_queued',
			sprintf(
				/* translators: %d recipient count */
				__( 'Snapshot criado com %d destinatarios.', 'ottermail' ),
				$inserted
			),
			'campaign',
			$campaign_id
		);

		$queued_left = isset( $counters->queued ) ? (int) $counters->queued : 0;
		if ( $queued_left < 1 ) {
			$this->campaigns->patch(
				$campaign_id,
				array(
					'status'       => 'sent',
					'completed_at' => current_time( 'mysql' ),
					'sent_at'      => current_time( 'mysql' ),
				)
			);
			return;
		}

		Scheduler::once( Scheduler::HOOK_SEND_BATCH, array( (int) $campaign_id ) );
	}

	/**
	 * Send one batch then re-queue if needed.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function send_batch( $campaign_id ) {
		$campaign = $this->campaigns->get( $campaign_id );
		if ( ! $campaign || in_array( $campaign->status, array( 'cancelled', 'paused' ), true ) ) {
			return;
		}

		$template = $this->templates->get( $campaign->template_id );
		if ( ! $template ) {
			$this->campaigns->patch( $campaign_id, array( 'status' => 'paused' ) );
			return;
		}

		$limit = max( 1, min(
			(int) Settings::get( 'batch_size', 50 ),
			(int) Settings::get( 'emails_per_hour', 500 )
		) );
		$batch = $this->campaigns->claim_batch( $campaign_id, $limit );

		$sendable = array();
		foreach ( $batch as $recipient ) {
			if ( $this->unsubscribe->is_suppressed( $recipient->email, 'marketing' ) ) {
				$this->campaigns->mark_recipient( $recipient->id, 'skipped' );
				continue;
			}
			$sendable[] = $recipient;
		}
		$batch = $sendable;

		if ( $batch ) {
			$rate_limiter = new RateLimiter();
			if ( ! $rate_limiter->reserve( count( $batch ) ) ) {
				$this->campaigns->requeue_batch( wp_list_pluck( $batch, 'id' ) );
				Scheduler::once(
					Scheduler::HOOK_SEND_BATCH,
					array( (int) $campaign_id ),
					time() + $rate_limiter->retry_after()
				);
				Logger::log(
					'campaign_rate_limited',
					__( 'Lote adiado para respeitar o limite global de envio.', 'ottermail' ),
					'campaign',
					$campaign_id
				);
				return;
			}
		}

		foreach ( $batch as $recipient ) {
			$context = array(
				'customer' => array(
					'first_name' => $recipient->first_name,
					'last_name'  => $recipient->last_name,
					'email'      => $recipient->email,
				),
				'coupon'   => $template->coupon_code,
			);

			$html    = $this->renderer->render( $template, $context );
			$subject = $this->renderer->subject( $campaign->subject, $context );
			$tracked = ( new ClickTracker() )->apply(
				$html,
				array(
					'email'       => $recipient->email,
					'source_type' => 'campaign',
					'source_id'   => (int) $campaign_id,
				)
			);
			$html    = $tracked['html'];

			$message             = new Message();
			$message->to         = $recipient->email;
			$message->subject    = $subject;
			$message->html       = $html;
			$message->from_name  = $campaign->from_name ? $campaign->from_name : Settings::get( 'from_name' );
			$message->from_email = $campaign->from_email ? $campaign->from_email : Settings::get( 'from_email' );
			$message->reply_to   = $campaign->reply_to ? $campaign->reply_to : Settings::get( 'reply_to' );

			$ok  = $this->mailer->send( $message );
			$err = $ok ? '' : $this->mailer->last_error();
			if ( ! $ok ) {
				( new ClickTracker() )->discard( $tracked['ids'] );
			}
			$this->campaigns->mark_recipient( $recipient->id, $ok ? 'sent' : 'failed', $err );

			if ( ! $ok ) {
				Logger::log(
					'email_failed',
					$err ? $err : __( 'Falha ao enviar email de campanha.', 'ottermail' ),
					'campaign',
					$campaign_id,
					array(
						'kind'       => 'campaign',
						'email_hash' => md5( $recipient->email ),
					)
				);
			}
		}

		$counters = $this->campaigns->refresh_counters( $campaign_id );
		$queued   = isset( $counters->queued ) ? (int) $counters->queued : 0;

		if ( $queued > 0 ) {
			Scheduler::once(
				Scheduler::HOOK_SEND_BATCH,
				array( (int) $campaign_id ),
				time() + $this->next_batch_delay( count( $batch ) )
			);
			return;
		}

		$this->campaigns->patch(
			$campaign_id,
			array(
				'status'       => 'sent',
				'sent_at'      => current_time( 'mysql' ),
				'completed_at' => current_time( 'mysql' ),
			)
		);

		Logger::log( 'campaign_completed', __( 'Campanha concluida.', 'ottermail' ), 'campaign', $campaign_id );
	}

	/**
	 * Pace campaign batches according to both the minimum interval and the
	 * configured hourly throughput.
	 *
	 * @param int $batch_count Number of recipients processed in this batch.
	 * @return int Delay in seconds.
	 */
	private function next_batch_delay( $batch_count ) {
		$minimum = max( 10, (int) Settings::get( 'batch_interval_seconds', 60 ) );
		$hourly  = max( 1, (int) Settings::get( 'emails_per_hour', 500 ) );
		$pacing  = (int) ceil( max( 1, absint( $batch_count ) ) * HOUR_IN_SECONDS / $hourly );

		return max( $minimum, $pacing );
	}

	/**
	 * Recipients from a WooCommerce audience or a newsletter list.
	 *
	 * @param Campaign $campaign Campaign.
	 * @return array|null
	 */
	private function resolve_recipients( Campaign $campaign ) {
		if ( $campaign->list_id ) {
			$lists = new ContactListRepository();
			$list  = $lists->get( $campaign->list_id );
			if ( ! $list ) {
				return null;
			}
			if ( 'elementor' === $list->source ) {
				ElementorForms::sync( $list );
			}

			return $lists->emails_for_send( $list->id );
		}

		$audience = $this->audiences->get( $campaign->audience_id );
		if ( ! $audience ) {
			return null;
		}

		return $this->builder->emails( $audience );
	}

	/**
	 * Pause processing.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function pause( $campaign_id ) {
		$this->campaigns->patch( $campaign_id, array( 'status' => 'paused' ) );
	}

	/**
	 * Cancel a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 */
	public function cancel( $campaign_id ) {
		$this->campaigns->patch( $campaign_id, array( 'status' => 'cancelled' ) );
	}

	/**
	 * Send a test email (not counted in metrics).
	 *
	 * @param int    $template_id Template ID.
	 * @param string $to          Destination.
	 * @param string $subject     Optional subject.
	 * @return bool|\WP_Error
	 */
	public function send_test( $template_id, $to, $subject = '' ) {
		$template = $this->templates->get( $template_id );
		if ( ! $template ) {
			return new \WP_Error( 'template', __( 'Template nao encontrado.', 'ottermail' ) );
		}

		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return new \WP_Error( 'email', __( 'Email de teste invalido.', 'ottermail' ) );
		}

		$context = Variables::sample_context();
		$html    = $this->renderer->render( $template, $context );
		$tracked = ( new ClickTracker() )->apply(
			$html,
			array(
				'email'       => $to,
				'source_type' => 'test',
				'source_id'   => (int) $template_id,
			)
		);
		$html    = $tracked['html'];
		$subj    = $subject ? $subject : $template->subject;
		$subj    = '[TESTE] ' . $this->renderer->subject( $subj, $context );

		$message             = new Message();
		$message->to         = $to;
		$message->subject    = $subj;
		$message->html       = $html;
		$message->from_name  = Settings::get( 'from_name' );
		$message->from_email = Settings::get( 'from_email' );
		$message->reply_to   = Settings::get( 'reply_to' );

		$ok = $this->mailer->send( $message );
		if ( ! $ok ) {
			( new ClickTracker() )->discard( $tracked['ids'] );
		}
		if ( $ok ) {
			Logger::log(
				'test_email_sent',
				__( 'Email de teste aceito pelo transporte local.', 'ottermail' ),
				'template',
				(int) $template_id,
				array( 'email_hash' => hash( 'sha256', strtolower( $to ) ) )
			);
			return true;
		}

		$reason = $this->mailer->last_error();
		Logger::log(
			'email_failed',
			sprintf(
				/* translators: %s error reason */
				__( 'Falha no email de teste: %s', 'ottermail' ),
				$reason
			),
			'template',
			(int) $template_id,
			array(
				'kind'       => 'test',
				'email_hash' => hash( 'sha256', strtolower( $to ) ),
			)
		);

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'OtterMail teste: ' . $reason ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return new \WP_Error( 'send', $reason );
	}
}
