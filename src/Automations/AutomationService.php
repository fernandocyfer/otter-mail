<?php
/**
 * Abandoned-cart automations.
 *
 * @package OtterMail
 */

namespace OtterMail\Automations;

use OtterMail\Cart\CartRepository;
use OtterMail\Cart\CartRestore;
use OtterMail\Core\Settings;
use OtterMail\Mail\Message;
use OtterMail\Mail\RateLimiter;
use OtterMail\Mail\WpMailMailer;
use OtterMail\Support\Logger;
use OtterMail\Support\Scheduler;
use OtterMail\Templates\TemplateRenderer;
use OtterMail\Templates\TemplateRepository;
use OtterMail\Tracking\ClickTracker;
use OtterMail\Unsubscribe\UnsubscribeService;

defined( 'ABSPATH' ) || exit;

class AutomationService {

	const MAX_SEND_RETRIES = 3;

	/**
	 * Retry delays after each failed attempt.
	 *
	 * @var int[]
	 */
	private $retry_delays = array( 300, 1800, 7200 );

	/**
	 * @var AutomationRepository
	 */
	private $repo;

	/**
	 * Error returned by the most recent local transport attempt.
	 *
	 * @var string
	 */
	private $last_send_error = '';

	public function __construct() {
		$this->repo = new AutomationRepository();
	}

	/**
	 * Register AS callback.
	 */
	public function register() {
		add_action( Scheduler::HOOK_AUTOMATION_STEP, array( $this, 'process_step' ), 10, 1 );
	}

	/**
	 * Enroll a newly abandoned cart into active automations.
	 *
	 * @param object $cart Cart row.
	 */
	public function enroll_cart( $cart ) {
		$this->enroll( $cart, 'cart_abandoned' );
	}

	/**
	 * Enroll a newly abandoned checkout into active checkout automations.
	 *
	 * @param object $cart Cart row.
	 */
	public function enroll_checkout( $cart ) {
		$this->enroll( $cart, 'checkout_abandoned' );
	}

	/**
	 * Enroll a paid order into active post-purchase automations. A new purchase
	 * supersedes outstanding post-purchase emails from an older order so the
	 * customer only receives the most relevant relationship flow.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 */
	public function enroll_order( \WC_Order $order ) {
		$email = strtolower( sanitize_email( $order->get_billing_email() ) );
		if ( ! $email || ( new UnsubscribeService() )->is_suppressed( $email, 'marketing' ) ) {
			return;
		}

		foreach ( $this->repo->enrollments_for_email_by_trigger( $email, 'post_purchase' ) as $enrollment ) {
			if ( (int) $enrollment->cart_id !== (int) $order->get_id() ) {
				$this->cancel_enrollment( $enrollment );
			}
		}

		foreach ( $this->repo->active_by_trigger( 'post_purchase' ) as $automation ) {
			$steps = $this->repo->get_steps( $automation->id );
			if ( empty( $steps ) ) {
				continue;
			}

			$enrollment_id = $this->repo->enroll_order( $automation->id, $order->get_id(), $email );
			$enrollment    = $enrollment_id ? $this->repo->get_enrollment( $enrollment_id ) : null;
			if ( ! $enrollment || (int) $enrollment->current_step > 0 ) {
				continue;
			}

			Logger::log( 'post_purchase_enrolled', __( 'Pedido inscrito na automação pós-compra.', 'ottermail' ), 'order', (int) $order->get_id(), array( 'automation_id' => (int) $automation->id ) );
			$this->schedule_step( $enrollment_id, $steps[0] );
		}
	}

	/**
	 * @param object $cart Cart row.
	 * @param string $trigger Automation trigger.
	 */
	private function enroll( $cart, $trigger ) {
		if ( empty( $cart->email ) ) {
			return;
		}

		$unsub = new UnsubscribeService();
		if ( $unsub->is_suppressed( $cart->email, 'cart' ) ) {
			return;
		}

		$automations = $this->repo->active_by_trigger( $trigger );
		foreach ( $automations as $automation ) {
			$steps = $this->repo->get_steps( $automation->id );
			if ( empty( $steps ) ) {
				continue;
			}

			$enrollment_id = $this->repo->enroll( $automation->id, $cart->id, $cart->email );
			if ( ! $enrollment_id ) {
				continue;
			}

			$existing = $this->repo->get_enrollment( $enrollment_id );
			if ( $existing && (int) $existing->current_step > 0 ) {
				continue;
			}

			Logger::log( 'automation_enrolled', __( 'Carrinho inscrito na automacao.', 'ottermail' ), 'automation', (int) $automation->id, array( 'cart_id' => (int) $cart->id ) );

			$waited  = $this->idle_seconds( $cart );
			$delay   = $this->delay_seconds( $steps[0]->delay_value, $steps[0]->delay_unit );
			$this->schedule_step( $enrollment_id, $steps[0], max( 0, $delay - $waited ) );
		}
	}

	/**
	 * Cancel remaining emails after conversion.
	 *
	 * @param int $cart_id Cart ID.
	 */
	public function cancel_for_cart( $cart_id ) {
		foreach ( $this->repo->enrollments_for_cart( $cart_id ) as $enrollment ) {
			$this->cancel_enrollment( $enrollment );
		}
	}

	/**
	 * @param object $enrollment Enrollment row.
	 */
	private function cancel_enrollment( $enrollment ) {
		$this->repo->update_enrollment(
			$enrollment->id,
			array(
				'status'       => 'cancelled',
				'cancelled_at' => current_time( 'mysql' ),
			)
		);
		Scheduler::unschedule_enrollment( (int) $enrollment->id );
	}

	/**
	 * Send a step if still eligible, then queue the next.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 */
	public function process_step( $enrollment_id ) {
		$enrollment = $this->repo->get_enrollment( $enrollment_id );
		if ( ! $enrollment || 'active' !== $enrollment->status ) {
			return;
		}

		$automation = $this->repo->get( $enrollment->automation_id );
		if ( ! $automation ) {
			return;
		}

		$step = null;
		foreach ( $this->repo->get_steps( $enrollment->automation_id ) as $candidate ) {
			if ( (int) $candidate->position === ( (int) $enrollment->current_step + 1 ) ) {
				$step = $candidate;
				break;
			}
		}
		if ( ! $step ) {
			return;
		}

		if ( ! $this->repo->claim_enrollment( $enrollment->id, $enrollment->current_step ) ) {
			return;
		}

		$unsub = new UnsubscribeService();
		$order = null;
		$cart  = null;
		if ( 'post_purchase' === $automation->trigger_type ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $enrollment->cart_id ) : null;
			if ( ! $order instanceof \WC_Order || ! $order->has_status( array( 'processing', 'completed' ) ) || $unsub->is_suppressed( $enrollment->email, 'marketing' ) ) {
				$this->cancel_enrollment( $enrollment );
				return;
			}
		} else {
			$carts = new CartRepository();
			$cart  = $carts->get( $enrollment->cart_id );
			$expected_status = 'checkout_abandoned' === $automation->trigger_type ? 'checkout_abandoned' : 'abandoned';
			if ( ! $cart || $expected_status !== $cart->status || $unsub->is_suppressed( $enrollment->email, 'cart' ) ) {
				$this->cancel_enrollment( $enrollment );
				return;
			}
		}

		$templates = new TemplateRepository();
		$template  = $templates->get( $step->template_id );
		if ( ! $template ) {
			$this->fail_enrollment(
				$enrollment,
				$step,
				__( 'O template desta etapa não existe mais.', 'ottermail' )
			);
			return;
		}

		$rate_limiter = new RateLimiter();
		if ( ! $rate_limiter->reserve() ) {
			$scheduled = Scheduler::once(
				Scheduler::HOOK_AUTOMATION_STEP,
				array( (int) $enrollment->id ),
				time() + $rate_limiter->retry_after()
			);

			if ( ! $scheduled ) {
				$this->fail_enrollment(
					$enrollment,
					$step,
					__( 'Não foi possível reagendar o email limitado porque o Action Scheduler não está disponível.', 'ottermail' )
				);
				return;
			}

			$this->repo->update_enrollment( $enrollment->id, array( 'locked_at' => null ) );
			Logger::log(
				'automation_rate_limited',
				__( 'Email de automação adiado para respeitar o limite global.', 'ottermail' ),
				'automation',
				(int) $enrollment->automation_id,
				array(
					'enrollment_id' => (int) $enrollment->id,
					'step_id'       => (int) $step->id,
				)
			);
			return;
		}

		$sent = $order
			? $this->send_order_email( $template, $order, (int) $enrollment->automation_id )
			: $this->send_cart_email( $template, $cart, (int) $enrollment->automation_id );

		if ( ! $sent ) {
			$this->handle_send_failure( $enrollment, $step );
			return;
		}

		Logger::log(
			'automation_email_sent',
			__( 'Email de automacao enviado.', 'ottermail' ),
			$order ? 'order' : 'cart',
			$order ? (int) $order->get_id() : (int) $cart->id,
			array(
				'automation_id' => (int) $enrollment->automation_id,
				'step_id'       => (int) $step->id,
			)
		);

		$this->repo->update_enrollment(
			$enrollment_id,
			array(
				'current_step' => (int) $step->position,
				'retry_count'  => 0,
				'last_error'   => null,
				'locked_at'    => null,
			)
		);

		$all  = $this->repo->get_steps( $enrollment->automation_id );
		$next = null;
		foreach ( $all as $candidate ) {
			if ( (int) $candidate->position === ( (int) $step->position + 1 ) ) {
				$next = $candidate;
				break;
			}
		}

		if ( $next ) {
			$this->schedule_step( $enrollment_id, $next );
			return;
		}

		$this->repo->update_enrollment(
			$enrollment_id,
			array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * @param int    $enrollment_id Enrollment ID.
	 * @param object $step          Step.
	 */
	private function schedule_step( $enrollment_id, $step, $seconds = null ) {
		if ( null === $seconds ) {
			$seconds = $this->delay_seconds( $step->delay_value, $step->delay_unit );
		}
		Scheduler::once(
			Scheduler::HOOK_AUTOMATION_STEP,
			array( (int) $enrollment_id ),
			time() + $seconds
		);
	}

	/**
	 * Retry only the failed enrollment without blocking other queued actions.
	 *
	 * @param object $enrollment Enrollment row.
	 * @param object $step       Current step.
	 */
	private function handle_send_failure( $enrollment, $step ) {
		$failure_count = (int) $enrollment->retry_count + 1;
		$error         = $this->last_send_error ? $this->last_send_error : __( 'O transporte local recusou o email.', 'ottermail' );

		if ( $failure_count <= self::MAX_SEND_RETRIES ) {
			$delay     = $this->retry_delays[ $failure_count - 1 ];
			$scheduled = Scheduler::once(
				Scheduler::HOOK_AUTOMATION_STEP,
				array( (int) $enrollment->id ),
				time() + $delay
			);

			if ( $scheduled ) {
				$this->repo->update_enrollment(
					$enrollment->id,
					array(
						'retry_count' => $failure_count,
						'last_error'  => $error,
						'locked_at'   => null,
					)
				);
				Logger::log(
					'automation_email_retry_scheduled',
					__( 'Nova tentativa de email de automação agendada.', 'ottermail' ),
					'automation',
					(int) $enrollment->automation_id,
					array(
						'enrollment_id' => (int) $enrollment->id,
						'step_id'       => (int) $step->id,
						'attempt'       => $failure_count,
						'retry_in'      => $delay,
					)
				);
				return;
			}

			$error = __( 'Não foi possível agendar uma nova tentativa porque o Action Scheduler não está disponível.', 'ottermail' );
		}

		$this->fail_enrollment( $enrollment, $step, $error, $failure_count );
	}

	/**
	 * Mark an enrollment as failed after a permanent error or exhausted retries.
	 *
	 * @param object $enrollment   Enrollment row.
	 * @param object $step         Current step.
	 * @param string $error        Failure reason.
	 * @param int    $failure_count Number of failed attempts.
	 */
	private function fail_enrollment( $enrollment, $step, $error, $failure_count = 0 ) {
		$this->repo->update_enrollment(
			$enrollment->id,
			array(
				'status'      => 'failed',
				'retry_count' => absint( $failure_count ),
				'last_error'  => sanitize_textarea_field( $error ),
				'failed_at'   => current_time( 'mysql' ),
				'locked_at'   => null,
			)
		);

		Logger::log(
			'automation_enrollment_failed',
			$error,
			'automation',
			(int) $enrollment->automation_id,
			array(
				'enrollment_id' => (int) $enrollment->id,
				'step_id'       => (int) $step->id,
				'attempts'      => absint( $failure_count ),
			)
		);
	}

	/**
	 * @param int    $value Value.
	 * @param string $unit  Unit.
	 * @return int
	 */
	/**
	 * Seconds since the customer last touched the cart or the checkout.
	 *
	 * @param object $cart Cart row.
	 * @return int
	 */
	private function idle_seconds( $cart ) {
		$stamps = array();
		if ( ! empty( $cart->last_activity_at ) ) {
			$stamps[] = strtotime( $cart->last_activity_at );
		}
		if ( ! empty( $cart->checkout_started_at ) ) {
			$stamps[] = strtotime( $cart->checkout_started_at );
		}
		if ( ! $stamps ) {
			return 0;
		}

		return max( 0, current_time( 'timestamp' ) - max( $stamps ) );
	}

	/**
	 * @param int    $value Value.
	 * @param string $unit  Unit.
	 * @return int
	 */
	private function delay_seconds( $value, $unit ) {
		$value = max( 0, (int) $value );
		switch ( $unit ) {
			case 'minutes':
				return $value * MINUTE_IN_SECONDS;
			case 'days':
				return $value * DAY_IN_SECONDS;
			case 'hours':
			default:
				return $value * HOUR_IN_SECONDS;
		}
	}

	/**
	 * @param \OtterMail\Templates\Template $template      Template.
	 * @param object                        $cart          Cart.
	 * @param int                           $automation_id Automation ID.
	 * @return bool True when the local transport accepted the message.
	 */
	private function send_cart_email( $template, $cart, $automation_id ) {
		$this->last_send_error = '';
		$contents = json_decode( (string) $cart->cart_contents, true );
		$items    = ( isset( $contents['items'] ) && is_array( $contents['items'] ) ) ? $contents['items'] : array();
		$renderer = new TemplateRenderer();

		$total_html = function_exists( 'wc_price' ) ? wc_price( $cart->cart_total ) : (string) $cart->cart_total;

		$context = array(
			'customer' => array(
				'first_name' => $cart->first_name,
				'last_name'  => '',
				'email'      => $cart->email,
			),
			'cart'     => array(
				'items'       => $items,
				'total_html'  => $total_html,
				'restore_url' => CartRestore::url_for_cart( (int) $cart->id ),
			),
			'coupon'   => $template->coupon_code,
		);

		$html    = $renderer->render( $template, $context );
		$tracked = ( new ClickTracker() )->apply(
			$html,
			array(
				'email'       => $cart->email,
				'source_type' => 'automation',
				'source_id'   => (int) $automation_id,
			)
		);
		$html    = $tracked['html'];
		$subject = $renderer->subject( $template->subject, $context );

		$message             = new Message();
		$message->to         = $cart->email;
		$message->subject    = $subject;
		$message->html       = $html;
		$message->from_name  = Settings::get( 'from_name' );
		$message->from_email = Settings::get( 'from_email' );
		$message->reply_to   = Settings::get( 'reply_to' );

		$mailer = new WpMailMailer();
		$ok     = $mailer->send( $message );
		if ( ! $ok ) {
			( new ClickTracker() )->discard( $tracked['ids'] );
			$this->last_send_error = $mailer->last_error();
			Logger::log(
				'email_failed',
				$this->last_send_error,
				'cart',
				(int) $cart->id,
				array( 'kind' => 'automation' )
			);
		}

		return $ok;
	}

	/**
	 * Send one post-purchase automation email with the source order context.
	 *
	 * @param \OtterMail\Templates\Template $template      Template.
	 * @param \WC_Order                     $order         WooCommerce order.
	 * @param int                           $automation_id Automation ID.
	 * @return bool True when the local transport accepted the message.
	 */
	private function send_order_email( $template, \WC_Order $order, $automation_id ) {
		$this->last_send_error = '';
		$renderer = new TemplateRenderer();
		$items    = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'line_total' => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $order->get_line_total( $item, true, true ) ) ) : $order->get_line_total( $item, true, true ),
				'image'      => $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '',
			);
		}

		$context = array(
			'customer' => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
			),
			'order'    => array(
				'number'          => $order->get_order_number(),
				'total_html'      => function_exists( 'wc_price' ) ? wc_price( $order->get_total() ) : (string) $order->get_total(),
				'items_html'      => $renderer->render_cart_items( $items ),
				'cross_sell_html' => $this->cross_sell_html( $order ),
			),
			'coupon'   => $template->coupon_code,
		);

		$message             = new Message();
		$message->to         = $order->get_billing_email();
		$message->subject    = $renderer->subject( $template->subject, $context );
		$rendered            = $renderer->render( $template, $context );
		$tracked             = ( new ClickTracker() )->apply(
			$rendered,
			array(
				'email'       => $order->get_billing_email(),
				'source_type' => 'automation',
				'source_id'   => (int) $automation_id,
			)
		);
		$message->html       = $tracked['html'];
		$message->from_name  = Settings::get( 'from_name' );
		$message->from_email = Settings::get( 'from_email' );
		$message->reply_to   = Settings::get( 'reply_to' );

		$mailer = new WpMailMailer();
		$ok     = $mailer->send( $message );
		if ( ! $ok ) {
			( new ClickTracker() )->discard( $tracked['ids'] );
			$this->last_send_error = $mailer->last_error();
			Logger::log( 'email_failed', $this->last_send_error, 'order', (int) $order->get_id(), array( 'kind' => 'post_purchase' ) );
		}

		return $ok;
	}

	/**
	 * Product cards based first on configured WooCommerce cross-sells, then on
	 * shared categories. Products already in the order are never suggested.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	private function cross_sell_html( \WC_Order $order ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$purchased = array();
		$candidates = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$purchased[] = (int) $product->get_id();
			$candidates  = array_merge( $candidates, array_map( 'absint', $product->get_cross_sell_ids() ) );
		}

		if ( empty( $candidates ) && $purchased ) {
			$categories = wp_get_object_terms( $purchased, 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $categories ) && $categories ) {
				$candidates = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 4,
						'fields'         => 'ids',
						'post__not_in'   => $purchased,
						'tax_query'      => array(
							array(
								'taxonomy' => 'product_cat',
								'field'    => 'term_id',
								'terms'    => $categories,
							),
						),
					)
				);
			}
		}

		$candidates = array_slice( array_values( array_unique( array_diff( array_map( 'absint', $candidates ), $purchased ) ) ), 0, 4 );
		if ( empty( $candidates ) ) {
			return '';
		}

		$html = '<p><strong>' . esc_html__( 'Peças que combinam com sua escolha:', 'ottermail' ) . '</strong></p><ul>';
		foreach ( $candidates as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$html .= '<li><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a> — ' . wp_kses_post( $product->get_price_html() ) . '</li>';
			}
		}

		return $html . '</ul>';
	}
}
