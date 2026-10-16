<?php
/**
 * Action Scheduler wrapper.
 *
 * @package OtterMail
 */

namespace OtterMail\Support;

defined( 'ABSPATH' ) || exit;

class Scheduler {

	const GROUP = 'ottermail';

	const HOOK_PROCESS_CAMPAIGN   = 'ottermail_process_campaign';
	const HOOK_SEND_BATCH         = 'ottermail_send_campaign_batch';
	const HOOK_CHECK_CARTS        = 'ottermail_check_abandoned_carts';
	const HOOK_AUTOMATION_STEP    = 'ottermail_process_automation_step';
	const HOOK_SEND_TEST          = 'ottermail_send_test_email';

	/**
	 * Whether Action Scheduler is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Schedule a single action if a duplicate is not already queued.
	 *
	 * @param string $hook Hook.
	 * @param array  $args Args.
	 * @param int    $timestamp Unix timestamp.
	 * @return int|bool
	 */
	public static function once( $hook, array $args = array(), $timestamp = 0 ) {
		if ( ! self::available() ) {
			return false;
		}

		if ( self::has_pending_action( $hook, $args ) ) {
			return true;
		}

		if ( $timestamp <= 0 ) {
			$timestamp = time();
		}

		return as_schedule_single_action( $timestamp, $hook, $args, self::GROUP );
	}

	/**
	 * Check only pending actions. An action currently executing must not block
	 * the next batch or automation retry from being scheduled by its callback.
	 *
	 * @param string $hook Hook.
	 * @param array  $args Args.
	 * @return bool
	 */
	private static function has_pending_action( $hook, array $args ) {
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$actions = as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'args'     => $args,
					'group'    => self::GROUP,
					'status'   => 'pending',
					'per_page' => 1,
				),
				'ids'
			);

			return ! empty( $actions );
		}

		return function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, self::GROUP );
	}

	/**
	 * Schedule recurring check for abandoned carts.
	 */
	public static function schedule_recurring_cart_check() {
		if ( ! self::available() ) {
			return;
		}

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK_CHECK_CARTS, array(), self::GROUP ) ) {
			return;
		}

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action( time() + 60, 5 * MINUTE_IN_SECONDS, self::HOOK_CHECK_CARTS, array(), self::GROUP );
		}
	}

	/**
	 * Unschedule every OtterMail action (deactivation).
	 */
	public static function unschedule_all() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		$hooks = array(
			self::HOOK_PROCESS_CAMPAIGN,
			self::HOOK_SEND_BATCH,
			self::HOOK_CHECK_CARTS,
			self::HOOK_AUTOMATION_STEP,
			self::HOOK_SEND_TEST,
		);

		foreach ( $hooks as $hook ) {
			as_unschedule_all_actions( $hook, null, self::GROUP );
		}
	}

	/**
	 * Unschedule automation steps for an enrollment.
	 *
	 * @param int $enrollment_id Enrollment ID.
	 */
	public static function unschedule_enrollment( $enrollment_id ) {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::HOOK_AUTOMATION_STEP, array( (int) $enrollment_id ), self::GROUP );
	}
}
