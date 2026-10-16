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
			'carts_restored'    => $report['carts_restored'],
			'carts_recovered'   => $report['carts_recovered'],
			'converted_attributed' => $report['converted_attributed'],
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
		$click_orders     = array_merge(
			(array) $clicks->orders_for_source( 'automation' ),
			(array) $clicks->orders_for_source( 'campaign' )
		);
		$extra_clicks     = $this->unlisted_click_orders( $attribution['order_ids'], $click_orders );
		$automations      = $this->attach_purchases( $automations, $clicks->orders_for_source( 'automation' ) );

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
			'carts_recovered'          => $restored + $attribution['link']['count'] + $attribution['assisted']['count'] + $extra_clicks['count'],
			'converted_link'           => $attribution['link']['count'],
			'converted_assisted'       => $attribution['assisted']['count'],
			'converted_attributed'     => $attribution['link']['count'] + $attribution['assisted']['count'] + $extra_clicks['count'],
			'revenue_link'             => $attribution['link']['revenue'],
			'revenue_assisted'         => $attribution['assisted']['revenue'],
			'recovered_revenue'        => $attribution['link']['revenue'] + $attribution['assisted']['revenue'] + $extra_clicks['revenue'],
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
	 * Split converted carts between a restore click and a purchase after the plugin email.
	 *
	 * @param array $rows Converted cart rows.
	 * @return array
	 */
	private function attribute_conversions( $rows ) {
		$buckets = array(
			'link'      => array( 'count' => 0, 'revenue' => 0.0 ),
			'assisted'  => array( 'count' => 0, 'revenue' => 0.0 ),
			'order_ids' => array(),
		);

		foreach ( (array) $rows as $row ) {
			if ( ! empty( $row->recovered_at ) ) {
				$key = 'link';
			} elseif ( ! empty( $row->automation_email_count ) && $this->ordered_after( $row, isset( $row->first_email_at ) ? $row->first_email_at : '' ) ) {
				$key = 'assisted';
			} else {
				continue;
			}

			++$buckets[ $key ]['count'];
			$buckets[ $key ]['revenue'] += $this->order_amount( $row );

			$order_id = isset( $row->order_id ) ? (int) $row->order_id : 0;
			if ( $order_id > 0 ) {
				$buckets['order_ids'][ $order_id ] = true;
			}
		}

		return $buckets;
	}

	/**
	 * Click purchases whose order is not already in the recovered revenue.
	 *
	 * @param array $known_orders Order IDs already counted.
	 * @param array $click_orders Click rows with order_id and order_total.
	 * @return array
	 */
	private function unlisted_click_orders( array $known_orders, array $click_orders ) {
		$seen    = $known_orders;
		$count   = 0;
		$revenue = 0.0;

		foreach ( $click_orders as $click ) {
			$order_id = isset( $click->order_id ) ? (int) $click->order_id : 0;
			if ( $order_id < 1 || isset( $seen[ $order_id ] ) ) {
				continue;
			}
			$seen[ $order_id ] = true;
			++$count;
			$revenue += isset( $click->order_total ) ? (float) $click->order_total : 0.0;
		}

		return array(
			'count'   => $count,
			'revenue' => $revenue,
		);
	}

	/**
	 * Count a purchase on the automation that emailed the cart before the order.
	 * A later click purchase is added when that order is not already counted.
	 *
	 * @param array $automations Automation rows.
	 * @param array $click_orders Click rows with source_id, order_id and order_total.
	 * @return array
	 */
	private function attach_purchases( array $automations, array $click_orders ) {
		global $wpdb;

		$carts = Tables::name( Tables::CARTS );
		$logs  = Tables::name( Tables::LOGS );
		$rows  = $wpdb->get_results(
			"SELECT c.id AS cart_id, c.order_id, l.created_at,
				CAST(JSON_UNQUOTE(JSON_EXTRACT(l.context, '$.automation_id')) AS UNSIGNED) AS automation_id
			FROM {$carts} c
			INNER JOIN {$logs} l ON l.object_type = 'cart' AND l.object_id = c.id AND l.event_type = 'automation_email_sent'
			WHERE c.status = 'converted' AND l.context IS NOT NULL" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$latest      = array();
		$seen_orders = array();
		$order_times = array();

		foreach ( (array) $rows as $row ) {
			$cart_id       = (int) $row->cart_id;
			$automation_id = (int) $row->automation_id;
			$order_id      = (int) $row->order_id;
			if ( $cart_id < 1 || $automation_id < 1 || $order_id < 1 ) {
				continue;
			}
			if ( ! isset( $order_times[ $order_id ] ) ) {
				$order_times[ $order_id ] = $this->order_timestamp( $order_id );
			}
			$sent = $this->local_mysql_to_timestamp( $row->created_at );
			if ( ! $sent || ! $order_times[ $order_id ] || $order_times[ $order_id ] < $sent ) {
				continue;
			}
			if ( ! isset( $latest[ $cart_id ] ) || strcmp( (string) $row->created_at, (string) $latest[ $cart_id ]->created_at ) > 0 ) {
				$latest[ $cart_id ] = $row;
			}
		}

		$counts = array();
		foreach ( $latest as $row ) {
			$automation_id = (int) $row->automation_id;
			$order_id      = (int) $row->order_id;
			if ( isset( $seen_orders[ $order_id ] ) ) {
				continue;
			}
			$seen_orders[ $order_id ] = true;
			if ( ! isset( $counts[ $automation_id ] ) ) {
				$counts[ $automation_id ] = 0;
			}
			++$counts[ $automation_id ];
		}

		foreach ( $click_orders as $click ) {
			$order_id      = isset( $click->order_id ) ? (int) $click->order_id : 0;
			$automation_id = isset( $click->source_id ) ? (int) $click->source_id : 0;
			if ( $order_id < 1 || $automation_id < 1 || isset( $seen_orders[ $order_id ] ) ) {
				continue;
			}
			$seen_orders[ $order_id ] = true;
			if ( ! isset( $counts[ $automation_id ] ) ) {
				$counts[ $automation_id ] = 0;
			}
			++$counts[ $automation_id ];
		}

		foreach ( $automations as $automation ) {
			$id = (int) $automation->id;
			$automation->purchases = isset( $counts[ $id ] ) ? $counts[ $id ] : 0;
		}

		return $automations;
	}

	/**
	 * True when the paid order was created at or after a plugin email.
	 *
	 * @param object $row        Cart row with order_id.
	 * @param string $mysql_local Email time in the site timezone.
	 * @return bool
	 */
	private function ordered_after( $row, $mysql_local ) {
		$sent = $this->local_mysql_to_timestamp( $mysql_local );
		if ( ! $sent ) {
			return false;
		}

		$order_id = isset( $row->order_id ) ? (int) $row->order_id : 0;
		$ordered  = $this->order_timestamp( $order_id );

		return $ordered && $ordered >= $sent;
	}

	/**
	 * @param int $order_id Order ID.
	 * @return int Unix timestamp, or 0 when the order cannot be read.
	 */
	private function order_timestamp( $order_id ) {
		if ( $order_id < 1 || ! function_exists( 'wc_get_order' ) ) {
			return 0;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_date_created() ) {
			return 0;
		}

		return (int) $order->get_date_created()->getTimestamp();
	}

	/**
	 * @param string $mysql_local Datetime stored with current_time( 'mysql' ).
	 * @return int
	 */
	private function local_mysql_to_timestamp( $mysql_local ) {
		if ( ! is_string( $mysql_local ) || '' === $mysql_local ) {
			return 0;
		}

		$stamp = strtotime( get_gmt_from_date( $mysql_local ) . ' GMT' );

		return $stamp ? (int) $stamp : 0;
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
