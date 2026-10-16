<?php
/**
 * Report aggregations.
 *
 * @package OtterMail
 */

namespace OtterMail\Reports;

use OtterMail\Automations\AutomationRepository;
use OtterMail\Campaigns\CampaignRepository;
use OtterMail\Cart\CartRepository;
use OtterMail\Support\Tables;
use OtterMail\Tracking\ClickRepository;

defined( 'ABSPATH' ) || exit;

class ReportService {

	/**
	 * Dashboard metrics. Never invents numbers.
	 *
	 * @return array
	 */
	public function dashboard() {
		$report = $this->report();

		return array(
			'emails_sent'       => $report['emails_sent'],
			'campaigns_sent'    => $report['campaigns_sent'],
			'customers_reached' => $report['customers_reached'],
			'carts_abandoned'   => $report['carts_abandoned'],
			'carts_recovered'   => $report['carts_recovered'],
			'recovered_revenue' => $report['recovered_revenue'],
			'links_sent'        => $report['links_sent'],
			'link_clicks'       => $report['link_clicks'],
			'link_purchases'    => $report['link_purchases'],
			'link_revenue'      => $report['link_revenue'],
		);
	}

	/**
	 * Full report used by the reports screen and the dashboard cards.
	 *
	 * @return array
	 */
	public function report() {
		$campaigns  = new CampaignRepository();
		$carts      = new CartRepository();
		$automations = ( new AutomationRepository() )->report_rows();

		$campaign_sent    = $campaigns->count_sent_emails();
		$automation_sent  = $this->automation_sends();
		$cart_abandoned   = $carts->count_status( 'abandoned' );
		$checkout_abandoned = $carts->count_status( 'checkout_abandoned' );
		$restored         = $carts->count_status( 'recovered' );
		$attribution      = $this->attribute_conversions( $carts->converted_for_report() );
		$clicks           = new ClickRepository();
		$click_totals     = $clicks->totals();

		$attributed_count = $attribution['link']['count'] + $attribution['assisted']['count'];

		return array(
			'emails_sent'              => $campaign_sent + $automation_sent,
			'emails_campaign'          => $campaign_sent,
			'emails_automation'        => $automation_sent,
			'emails_failed'            => $campaigns->count_failed_emails(),
			'campaigns_sent'           => $campaigns->count_by_status( 'sent' ),
			'customers_reached'        => $campaigns->count_unique_reached(),
			'carts_abandoned'          => $cart_abandoned + $checkout_abandoned,
			'carts_abandoned_cart'     => $cart_abandoned,
			'carts_abandoned_checkout' => $checkout_abandoned,
			'carts_restored'           => $restored,
			'carts_recovered'          => $restored + $attributed_count,
			'converted_link'           => $attribution['link']['count'],
			'converted_assisted'       => $attribution['assisted']['count'],
			'revenue_link'             => $attribution['link']['revenue'],
			'revenue_assisted'         => $attribution['assisted']['revenue'],
			'recovered_revenue'        => $attribution['link']['revenue'] + $attribution['assisted']['revenue'],
			'automations'              => $automations,
			'automation_enrolled'      => $this->sum_rows( $automations, 'enrolled' ),
			'automation_active'        => $this->sum_rows( $automations, 'active_count' ),
			'automation_completed'     => $this->sum_rows( $automations, 'completed_count' ),
			'automation_failed'        => $this->sum_rows( $automations, 'failed_count' ),
			'links_sent'               => $click_totals['links_sent'],
			'link_clicks'              => $click_totals['clicks'],
			'link_purchases'           => $click_totals['purchases'],
			'link_revenue'             => $click_totals['revenue'],
			'clicks_campaign'          => $clicks->grouped( 'campaign' ),
			'clicks_automation'        => $clicks->grouped( 'automation' ),
		);
	}

	/**
	 * Split converted carts that came from a recovery link or an automation email.
	 *
	 * @param array $rows Converted cart rows.
	 * @return array
	 */
	private function attribute_conversions( $rows ) {
		$buckets = array(
			'link'     => array( 'count' => 0, 'revenue' => 0.0 ),
			'assisted' => array( 'count' => 0, 'revenue' => 0.0 ),
		);

		foreach ( (array) $rows as $row ) {
			if ( ! empty( $row->recovered_at ) ) {
				$key = 'link';
			} elseif ( ! empty( $row->automation_email_count ) ) {
				$key = 'assisted';
			} else {
				continue;
			}

			++$buckets[ $key ]['count'];
			$buckets[ $key ]['revenue'] += $this->order_amount( $row );
		}

		return $buckets;
	}

	/**
	 * Paid order total, or the stored cart total when the order cannot be read.
	 *
	 * @param object $row Cart row.
	 * @return float
	 */
	private function order_amount( $row ) {
		$order_id = isset( $row->order_id ) ? (int) $row->order_id : 0;
		if ( $order_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				return (float) $order->get_total();
			}
		}

		return isset( $row->cart_total ) ? (float) $row->cart_total : 0.0;
	}

	/**
	 * @param array  $rows Rows.
	 * @param string $key  Property.
	 * @return int
	 */
	private function sum_rows( array $rows, $key ) {
		$total = 0;
		foreach ( $rows as $row ) {
			$total += isset( $row->{$key} ) ? (int) $row->{$key} : 0;
		}

		return $total;
	}

	/**
	 * Automation emails logged.
	 *
	 * @return int
	 */
	private function automation_sends() {
		global $wpdb;

		$table = Tables::name( Tables::LOGS );

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_type = %s", 'automation_email_sent' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
