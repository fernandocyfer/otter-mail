<?php
/**
 * Resolve dynamic audiences without loading every order into memory.
 *
 * Prefers WooCommerce lookup tables; falls back to paginated WC_Order_Query.
 *
 * @package OtterMail
 */

namespace OtterMail\Audiences;

use OtterMail\Automations\AutomationRepository;
use OtterMail\Support\Tables;

defined( 'ABSPATH' ) || exit;

class AudienceBuilder {

	/**
	 * Count distinct matching emails.
	 *
	 * @param Audience $audience Audience.
	 * @return int
	 */
	public function estimate( Audience $audience ) {
		return count( $this->emails( $audience ) );
	}

	/**
	 * Unique recipients: email => [email, first_name, last_name, customer_id].
	 *
	 * @param Audience $audience Audience.
	 * @return array
	 */
	public function emails( Audience $audience ) {
		$conditions = $audience->conditions;
		if ( empty( $conditions ) ) {
			return array();
		}

		$sets = array();
		foreach ( $conditions as $condition ) {
			if ( empty( $condition['field'] ) ) {
				continue;
			}
			$sets[] = $this->emails_for_condition( $condition );
		}

		if ( empty( $sets ) ) {
			return array();
		}

		if ( 'any' === $audience->match_type ) {
			$merged = array();
			foreach ( $sets as $set ) {
				foreach ( $set as $email => $row ) {
					$merged[ $email ] = $row;
				}
			}
			$result = $merged;
		} else {
			$result = array_shift( $sets );
			if ( ! is_array( $result ) ) {
				$result = array();
			}
			foreach ( $sets as $set ) {
				$result = array_intersect_key( $result, $set );
			}
		}

		/**
		 * Filter the resolved recipient map.
		 *
		 * @param array    $result   email => row.
		 * @param Audience $audience Audience.
		 */
		return apply_filters( 'ottermail_campaign_recipient_query', $result, $audience );
	}

	/**
	 * @param array $condition Condition.
	 * @return array
	 */
	private function emails_for_condition( array $condition ) {
		$field = isset( $condition['field'] ) ? $condition['field'] : '';
		$op    = isset( $condition['operator'] ) ? $condition['operator'] : 'eq';
		$value = isset( $condition['value'] ) ? $condition['value'] : '';

		switch ( $field ) {
			case 'customer_email':
				return $this->filter_customers( 'email', $op, $value );
			case 'customer_name':
				return $this->filter_customers( 'name', $op, $value );
			case 'customer_registered':
				return $this->filter_registered( 'yes' === $value || '1' === (string) $value );
			case 'has_order':
				return $this->customers_with_orders( 'yes' === $value || '1' === (string) $value );
			case 'order_status':
				return $this->from_order_stats( array( 'status' => $value ) );
			case 'order_count':
				return $this->from_order_stats( array( 'count_op' => $op, 'count' => (int) $value ) );
			case 'total_spent':
				return $this->from_order_stats( array( 'spent_op' => $op, 'spent' => (float) $value ) );
			case 'purchased_last_days':
				return $this->from_order_stats( array( 'last_within_days' => absint( $value ) ) );
			case 'not_purchased_days':
				return $this->from_order_stats( array( 'last_older_days' => absint( $value ) ) );
			case 'purchased_product':
				return $this->from_products( array( absint( $value ) ), false );
			case 'not_purchased_product':
				return $this->from_products( array( absint( $value ) ), true );
			case 'purchased_category':
				return $this->from_category( absint( $value ), false );
			case 'not_purchased_category':
				return $this->from_category( absint( $value ), true );
			case 'has_abandoned_cart':
				$with = $this->from_carts( array( 'abandoned' => true ) );
				if ( $this->is_affirmative( $value ) ) {
					return $with;
				}
				return array_diff_key( $this->all_customers(), $with );
			case 'abandoned_minutes':
				$minutes = absint( $value );
				if ( $minutes < 1 ) {
					return array();
				}
				return $this->from_carts( array( 'abandoned_minutes' => $minutes ) );
			case 'cart_total':
				return $this->from_carts(
					array(
						'total_op' => $op,
						'total'    => $this->parse_number( $value ),
					)
				);
			case 'cart_contains_product':
				$pid = absint( $value );
				if ( $pid < 1 ) {
					return array();
				}
				return $this->from_carts( array( 'product_id' => $pid ) );
			default:
				return array();
		}
	}

	/**
	 * @return bool
	 */
	private function has_lookup_tables() {
		global $wpdb;

		$stats = $wpdb->prefix . 'wc_order_stats';
		$cust  = $wpdb->prefix . 'wc_customer_lookup';

		return $this->table_exists( $stats ) && $this->table_exists( $cust );
	}

	/**
	 * @param string $table Table.
	 * @return bool
	 */
	private function table_exists( $table ) {
		global $wpdb;

		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return $found === $table;
	}

	/**
	 * @param string $kind email|name.
	 * @param string $op   Operator.
	 * @param string $value Value.
	 * @return array
	 */
	private function filter_customers( $kind, $op, $value ) {
		global $wpdb;

		if ( ! $this->has_lookup_tables() ) {
			return $this->fallback_orders_to_emails();
		}

		$table = $wpdb->prefix . 'wc_customer_lookup';
		$like  = '%' . $wpdb->esc_like( $value ) . '%';

		if ( 'name' === $kind ) {
			$sql = "SELECT email, first_name, last_name, user_id FROM {$table} WHERE first_name LIKE %s OR last_name LIKE %s";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $like, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} elseif ( 'contains' === $op ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT email, first_name, last_name, user_id FROM {$table} WHERE email LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT email, first_name, last_name, user_id FROM {$table} WHERE email = %s", strtolower( $value ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return $this->index_rows( $rows );
	}

	/**
	 * @param bool $registered Registered users.
	 * @return array
	 */
	private function filter_registered( $registered ) {
		global $wpdb;

		if ( ! $this->has_lookup_tables() ) {
			return $registered ? $this->wp_customers() : array();
		}

		$table = $wpdb->prefix . 'wc_customer_lookup';
		$where = $registered ? 'user_id > 0' : '(user_id IS NULL OR user_id = 0)';
		$rows  = $wpdb->get_results( "SELECT email, first_name, last_name, user_id FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->index_rows( $rows );
	}

	/**
	 * @param bool $has Whether they have orders.
	 * @return array
	 */
	private function customers_with_orders( $has ) {
		$with = $this->from_order_stats( array() );
		if ( $has ) {
			return $with;
		}

		$all = $this->all_customers();
		return array_diff_key( $all, $with );
	}

	/**
	 * @return array
	 */
	private function all_customers() {
		global $wpdb;

		if ( ! $this->has_lookup_tables() ) {
			return $this->fallback_orders_to_emails();
		}

		$table = $wpdb->prefix . 'wc_customer_lookup';
		$rows  = $wpdb->get_results( "SELECT email, first_name, last_name, user_id FROM {$table} WHERE email <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->index_rows( $rows );
	}

	/**
	 * Query wc_order_stats joined to customer lookup.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	private function from_order_stats( array $filters ) {
		global $wpdb;

		if ( ! $this->has_lookup_tables() ) {
			return $this->fallback_order_filters( $filters );
		}

		$stats = $wpdb->prefix . 'wc_order_stats';
		$cust  = $wpdb->prefix . 'wc_customer_lookup';

		$inner_where = array( '1=1' );
		$having      = array( '1=1' );
		$inner_args  = array();
		$having_args = array();

		if ( ! empty( $filters['status'] ) ) {
			$status = $filters['status'];
			if ( 0 !== strpos( $status, 'wc-' ) ) {
				$status = 'wc-' . $status;
			}
			$inner_where[] = 'status = %s';
			$inner_args[]  = $status;
		}

		if ( isset( $filters['count'] ) ) {
			$having[]      = 'cnt ' . $this->sql_op( isset( $filters['count_op'] ) ? $filters['count_op'] : 'gte' ) . ' %d';
			$having_args[] = (int) $filters['count'];
		}

		if ( isset( $filters['spent'] ) ) {
			$having[]      = 'spent ' . $this->sql_op( isset( $filters['spent_op'] ) ? $filters['spent_op'] : 'gte' ) . ' %f';
			$having_args[] = (float) $filters['spent'];
		}

		if ( ! empty( $filters['last_within_days'] ) ) {
			$having[]      = 'last_order >= DATE_SUB(%s, INTERVAL %d DAY)';
			$having_args[] = current_time( 'mysql' );
			$having_args[] = (int) $filters['last_within_days'];
		}

		if ( ! empty( $filters['last_older_days'] ) ) {
			$having[]      = 'last_order <= DATE_SUB(%s, INTERVAL %d DAY)';
			$having_args[] = current_time( 'mysql' );
			$having_args[] = (int) $filters['last_older_days'];
		}

		$sql = "SELECT c.email, c.first_name, c.last_name, c.user_id
			FROM {$cust} c
			INNER JOIN (
				SELECT customer_id, COUNT(*) AS cnt, SUM(total_sales) AS spent, MAX(date_created) AS last_order
				FROM {$stats}
				WHERE " . implode( ' AND ', $inner_where ) . '
				GROUP BY customer_id
				HAVING ' . implode( ' AND ', $having ) . "
			) s ON s.customer_id = c.customer_id
			WHERE c.email <> ''";

		$args = array_merge( $inner_args, $having_args );
		if ( $args ) {
			$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $this->index_rows( $rows );
	}

	/**
	 * @param array $product_ids IDs.
	 * @param bool  $exclude     Exclude buyers.
	 * @return array
	 */
	private function from_products( array $product_ids, $exclude ) {
		global $wpdb;

		$product_ids = array_filter( array_map( 'absint', $product_ids ) );
		if ( empty( $product_ids ) ) {
			return array();
		}

		$buyers = array();

		if ( $this->table_exists( $wpdb->prefix . 'wc_order_product_lookup' ) && $this->has_lookup_tables() ) {
			$lookup = $wpdb->prefix . 'wc_order_product_lookup';
			$cust   = $wpdb->prefix . 'wc_customer_lookup';
			$in     = implode( ',', $product_ids );
			$rows   = $wpdb->get_results(
				"SELECT DISTINCT c.email, c.first_name, c.last_name, c.user_id
				FROM {$lookup} p
				INNER JOIN {$cust} c ON c.customer_id = p.customer_id
				WHERE p.product_id IN ({$in}) AND c.email <> ''" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			$buyers = $this->index_rows( $rows );
		} else {
			$buyers = $this->fallback_product_buyers( $product_ids );
		}

		if ( ! $exclude ) {
			return $buyers;
		}

		return array_diff_key( $this->all_customers(), $buyers );
	}

	/**
	 * @param int  $term_id Term ID.
	 * @param bool $exclude Exclude.
	 * @return array
	 */
	private function from_category( $term_id, $exclude ) {
		if ( ! $term_id || ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$ids = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => 200,
				'return'   => 'ids',
				'category' => array(),
				'tax_query' => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => array( $term_id ),
					),
				),
			)
		);

		if ( empty( $ids ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'product',
					'posts_per_page' => 200,
					'fields'         => 'ids',
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => array( $term_id ),
						),
					),
				)
			);
		}

		return $this->from_products( (array) $ids, $exclude );
	}

	/**
	 * Resolve cart-based audience conditions.
	 *
	 * Idle `active` carts count as abandoned: the cron may not have flipped
	 * status yet, and last_activity_at is the real idle clock.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	private function from_carts( array $filters ) {
		global $wpdb;

		$table = Tables::name( Tables::CARTS );
		$open  = "status IN ('active','abandoned','recovered')";
		$where = array( "email <> ''", $open );
		$args  = array();

		if ( ! empty( $filters['abandoned'] ) ) {
			$automations = new AutomationRepository();
			$minutes     = min(
				$automations->abandon_delay_minutes( 'cart_abandoned' ),
				$automations->abandon_delay_minutes( 'checkout_abandoned' )
			);
			$minutes     = max( 1, $minutes );
			$where[] = "( status IN ('abandoned','recovered') OR ( status = 'active' AND last_activity_at <= DATE_SUB(%s, INTERVAL %d MINUTE) ) )";
			$args[]  = current_time( 'mysql' );
			$args[]  = $minutes;
		}

		if ( ! empty( $filters['abandoned_minutes'] ) ) {
			$where[] = 'last_activity_at <= DATE_SUB(%s, INTERVAL %d MINUTE)';
			$args[]  = current_time( 'mysql' );
			$args[]  = (int) $filters['abandoned_minutes'];
		}

		if ( isset( $filters['total'] ) ) {
			$where[] = 'cart_total ' . $this->sql_op( isset( $filters['total_op'] ) ? $filters['total_op'] : 'gte' ) . ' %s';
			$args[]  = number_format( (float) $filters['total'], 2, '.', '' );
		}

		$sql = 'SELECT email, first_name, customer_id, cart_contents FROM ' . $table . ' WHERE ' . implode( ' AND ', $where );

		if ( $args ) {
			$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! is_string( $sql ) || '' === $sql ) {
			return array();
		}

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$pid  = ! empty( $filters['product_id'] ) ? (int) $filters['product_id'] : 0;
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$email = strtolower( sanitize_email( isset( $row->email ) ? $row->email : '' ) );
			if ( ! is_email( $email ) ) {
				continue;
			}

			if ( $pid && ! $this->cart_has_product( isset( $row->cart_contents ) ? $row->cart_contents : '', $pid ) ) {
				continue;
			}

			$out[ $email ] = array(
				'email'       => $email,
				'first_name'  => isset( $row->first_name ) ? (string) $row->first_name : '',
				'last_name'   => '',
				'customer_id' => isset( $row->customer_id ) ? (int) $row->customer_id : 0,
			);
		}

		return $out;
	}

	/**
	 * Whether stored cart JSON contains a product or variation ID.
	 *
	 * @param string $raw Raw JSON.
	 * @param int    $pid Product ID.
	 * @return bool
	 */
	private function cart_has_product( $raw, $pid ) {
		$contents = json_decode( (string) $raw, true );
		if ( is_string( $contents ) ) {
			$contents = json_decode( $contents, true );
		}
		if ( ! is_array( $contents ) ) {
			return false;
		}

		$items = array();
		if ( isset( $contents['items'] ) && is_array( $contents['items'] ) ) {
			$items = $contents['items'];
		} elseif ( $contents && array_keys( $contents ) === range( 0, count( $contents ) - 1 ) ) {
			$items = $contents;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$var      = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			if ( $item_pid === $pid || $var === $pid ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Empty value means yes for boolean cart/customer flags.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function is_affirmative( $value ) {
		$normalized = strtolower( trim( (string) $value ) );
		if ( '' === $normalized ) {
			return true;
		}

		if ( in_array( $normalized, array( '0', 'no', 'nao', 'não', 'false', 'n' ), true ) ) {
			return false;
		}

		return in_array( $normalized, array( '1', 'yes', 'sim', 'true', 'y' ), true ) || (bool) $normalized;
	}

	/**
	 * Parse a lojista-typed number (1.234,56 or 1234.56).
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	private function parse_number( $value ) {
		$value = trim( (string) $value );
		$value = preg_replace( '/[^\d,.\-]/', '', $value );
		if ( ! is_string( $value ) || '' === $value ) {
			return 0.0;
		}

		if ( false !== strpos( $value, ',' ) && false !== strpos( $value, '.' ) ) {
			$value = str_replace( '.', '', $value );
			$value = str_replace( ',', '.', $value );
		} elseif ( false !== strpos( $value, ',' ) ) {
			$value = str_replace( ',', '.', $value );
		}

		return (float) $value;
	}

	/**
	 * Paginated order fallback when lookup tables are missing.
	 *
	 * @return array
	 */
	private function fallback_orders_to_emails() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$out   = array();
		$page  = 1;
		$limit = 100;

		do {
			$orders = wc_get_orders(
				array(
					'limit'  => $limit,
					'page'   => $page,
					'return' => 'objects',
					'type'   => 'shop_order',
				)
			);

			if ( empty( $orders ) ) {
				break;
			}

			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}
				$email = strtolower( sanitize_email( $order->get_billing_email() ) );
				if ( ! $email ) {
					continue;
				}
				$out[ $email ] = array(
					'email'       => $email,
					'first_name'  => $order->get_billing_first_name(),
					'last_name'   => $order->get_billing_last_name(),
					'customer_id' => $order->get_customer_id(),
				);
			}

			++$page;
		} while ( count( $orders ) === $limit && $page <= 50 );

		return $out;
	}

	/**
	 * @param array $filters Filters.
	 * @return array
	 */
	private function fallback_order_filters( array $filters ) {
		$all = $this->fallback_orders_to_emails();
		if ( empty( $filters ) ) {
			return $all;
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$args = array(
			'limit'  => 100,
			'return' => 'objects',
			'type'   => 'shop_order',
		);

		if ( ! empty( $filters['status'] ) ) {
			$args['status'] = array( $filters['status'] );
		}

		$out  = array();
		$page = 1;
		do {
			$args['page'] = $page;
			$orders       = wc_get_orders( $args );
			if ( empty( $orders ) ) {
				break;
			}
			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}
				$email = strtolower( sanitize_email( $order->get_billing_email() ) );
				if ( $email ) {
					$out[ $email ] = array(
						'email'       => $email,
						'first_name'  => $order->get_billing_first_name(),
						'last_name'   => $order->get_billing_last_name(),
						'customer_id' => $order->get_customer_id(),
					);
				}
			}
			++$page;
		} while ( count( $orders ) === 100 && $page <= 50 );

		return $out;
	}

	/**
	 * @param array $product_ids IDs.
	 * @return array
	 */
	private function fallback_product_buyers( array $product_ids ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$out  = array();
		$page = 1;
		do {
			$orders = wc_get_orders(
				array(
					'limit'  => 100,
					'page'   => $page,
					'return' => 'objects',
					'type'   => 'shop_order',
					'status' => array( 'completed', 'processing' ),
				)
			);
			if ( empty( $orders ) ) {
				break;
			}
			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}
				foreach ( $order->get_items() as $item ) {
					$pid = (int) $item->get_product_id();
					$vid = (int) $item->get_variation_id();
					if ( in_array( $pid, $product_ids, true ) || in_array( $vid, $product_ids, true ) ) {
						$email         = strtolower( sanitize_email( $order->get_billing_email() ) );
						$out[ $email ] = array(
							'email'       => $email,
							'first_name'  => $order->get_billing_first_name(),
							'last_name'   => $order->get_billing_last_name(),
							'customer_id' => $order->get_customer_id(),
						);
						break;
					}
				}
			}
			++$page;
		} while ( count( $orders ) === 100 && $page <= 50 );

		return $out;
	}

	/**
	 * @return array
	 */
	private function wp_customers() {
		$users = get_users(
			array(
				'role__in' => array( 'customer', 'subscriber' ),
				'number'   => 5000,
				'fields'   => array( 'ID', 'user_email' ),
			)
		);

		$out = array();
		foreach ( $users as $user ) {
			$email = strtolower( sanitize_email( $user->user_email ) );
			if ( ! $email ) {
				continue;
			}
			$out[ $email ] = array(
				'email'       => $email,
				'first_name'  => get_user_meta( $user->ID, 'first_name', true ),
				'last_name'   => get_user_meta( $user->ID, 'last_name', true ),
				'customer_id' => (int) $user->ID,
			);
		}

		return $out;
	}

	/**
	 * @param array|null $rows Rows.
	 * @return array
	 */
	private function index_rows( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			$email = strtolower( sanitize_email( isset( $row->email ) ? $row->email : '' ) );
			if ( ! is_email( $email ) ) {
				continue;
			}
			$out[ $email ] = array(
				'email'       => $email,
				'first_name'  => isset( $row->first_name ) ? (string) $row->first_name : '',
				'last_name'   => isset( $row->last_name ) ? (string) $row->last_name : '',
				'customer_id' => isset( $row->user_id ) ? (int) $row->user_id : 0,
			);
		}

		return $out;
	}

	/**
	 * @param string $op Operator.
	 * @return string
	 */
	private function sql_op( $op ) {
		switch ( $op ) {
			case 'gt':
				return '>';
			case 'lt':
				return '<';
			case 'lte':
				return '<=';
			case 'eq':
				return '=';
			case 'gte':
			default:
				return '>=';
		}
	}
}
