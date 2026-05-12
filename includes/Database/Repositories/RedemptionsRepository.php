<?php
/**
 * Redemptions repository.
 *
 * @package MatrixBogo\Database\Repositories
 */

declare( strict_types=1 );

namespace MatrixBogo\Database\Repositories;

use MatrixBogo\Abstracts\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RedemptionsRepository extends AbstractRepository {

	protected function get_table_name(): string {
		return 'matrix_bogo_redemptions';
	}

	/**
	 * Records a redemption event.
	 *
	 * @param int   $rule_id
	 * @param int   $order_id
	 * @param int   $user_id
	 * @param float $discount
	 * @param array $gifts_data
	 * @return int|false
	 */
	public function record(
		int $rule_id,
		int $order_id,
		int $user_id,
		float $discount,
		array $gifts_data = []
	): int|false {
		return $this->create( [
			'rule_id'    => $rule_id,
			'order_id'   => $order_id,
			'user_id'    => $user_id,
			'session_id' => WC()->session ? (string) WC()->session->get_customer_id() : '',
			'discount'   => $discount,
			'gifts_data' => wp_json_encode( $gifts_data ),
		] );
	}

	/**
	 * Returns the number of times a user has redeemed a specific rule.
	 *
	 * @param int $rule_id
	 * @param int $user_id
	 */
	public function count_for_user( int $rule_id, int $user_id ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM `{$this->table}` WHERE `rule_id` = %d AND `user_id` = %d",
				$rule_id,
				$user_id
			)
		);
	}

	/**
	 * Returns all redemption rows for a given WooCommerce order ID.
	 *
	 * FIX: RevenueTracker::on_order_complete() calls $this->repo->get_for_order()
	 * but this method was completely absent, causing a fatal error on every
	 * completed order.
	 *
	 * @param int $order_id
	 * @return array<int, array<string,mixed>>
	 */
	public function get_for_order( int $order_id ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM `{$this->table}` WHERE `order_id` = %d",
				$order_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Returns totals for analytics: total orders, total discount, total revenue.
	 *
	 * @param int    $rule_id
	 * @param string $from  MySQL datetime.
	 * @param string $to    MySQL datetime.
	 * @return array{orders:int,discount:float,revenue:float}
	 */
	public function get_totals( int $rule_id, string $from = '', string $to = '' ): array {
		$where  = $this->db->prepare( 'WHERE `rule_id` = %d', $rule_id );
		if ( $from && $to ) {
			$where .= $this->db->prepare( ' AND `redeemed_at` BETWEEN %s AND %s', $from, $to );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->db->get_row(
			"SELECT COUNT(*) AS orders, SUM(`discount`) AS discount FROM `{$this->table}` {$where}",
			ARRAY_A
		);

		return [
			'orders'   => (int) ( $row['orders'] ?? 0 ),
			'discount' => (float) ( $row['discount'] ?? 0 ),
			'revenue'  => 0.0,
		];
	}
}
