<?php
/**
 * Analytics repository.
 *
 * @package MatrixBogo\Database\Repositories
 */

declare( strict_types=1 );

namespace MatrixBogo\Database\Repositories;

use MatrixBogo\Abstracts\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnalyticsRepository extends AbstractRepository {

	protected function get_table_name(): string {
		return 'matrix_bogo_analytics';
	}

	/**
	 * Upserts daily analytics for a rule.
	 *
	 * @param int    $rule_id
	 * @param string $date       MySQL DATE string (Y-m-d).
	 * @param array<string,float|int> $increments  Columns to increment.
	 */
	public function upsert_day(
		int $rule_id,
		string $date,
		array $increments
	): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $this->db->get_row(
			$this->db->prepare(
				"SELECT `id` FROM `{$this->table}` WHERE `rule_id` = %d AND `date` = %s LIMIT 1",
				$rule_id,
				$date
			),
			ARRAY_A
		);

		if ( $existing ) {
			$set_parts = [];
			$values    = [];
			foreach ( $increments as $col => $delta ) {
				$col        = preg_replace( '/[^a-zA-Z0-9_]/', '', $col );
				$set_parts[] = "`{$col}` = `{$col}` + %f"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$values[]   = (float) $delta;
			}
			$values[] = (int) $existing['id'];
			$sql      = 'UPDATE `' . $this->table . '` SET ' . implode( ', ', $set_parts ) . ' WHERE `id` = %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->db->query( $this->db->prepare( $sql, ...$values ) );
		} else {
			$data = array_merge(
				[ 'rule_id' => $rule_id, 'date' => $date ],
				$increments
			);
			$this->create( $data );
		}
	}

	/**
	 * Returns daily stats for a rule within a date range.
	 *
	 * @param int    $rule_id
	 * @param string $from  Y-m-d
	 * @param string $to    Y-m-d
	 * @return array<int, array<string,mixed>>
	 */
	public function get_daily( int $rule_id, string $from, string $to ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM `{$this->table}`
				 WHERE `rule_id` = %d AND `date` BETWEEN %s AND %s
				 ORDER BY `date` ASC",
				$rule_id,
				$from,
				$to
			),
			ARRAY_A
		);
	}

	/**
	 * Returns aggregate daily stats for ALL rules within a date range.
	 * Used by the Analytics dashboard overview chart.
	 *
	 * @param string $from Y-m-d
	 * @param string $to   Y-m-d
	 * @return array<int, array<string,mixed>>
	 */
	public function get_daily_totals( string $from, string $to ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $this->db->get_results(
			$this->db->prepare(
				"SELECT `date`,
					SUM(`impressions`)    AS impressions,
					SUM(`redemptions`)    AS redemptions,
					SUM(`revenue`)        AS revenue,
					SUM(`discount_total`) AS discount_total
				 FROM `{$this->table}`
				 WHERE `date` BETWEEN %s AND %s
				 GROUP BY `date`
				 ORDER BY `date` ASC",
				$from,
				$to
			),
			ARRAY_A
		);
	}

	/**
	 * Returns aggregate totals across all rules for a date range.
	 *
	 * @param string $from Y-m-d
	 * @param string $to   Y-m-d
	 * @return array<string,mixed>
	 */
	public function get_totals( string $from, string $to ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT
					SUM(`impressions`)    AS total_impressions,
					SUM(`redemptions`)    AS total_redemptions,
					SUM(`revenue`)        AS total_revenue,
					SUM(`discount_total`) AS total_discount
				 FROM `{$this->table}`
				 WHERE `date` BETWEEN %s AND %s",
				$from,
				$to
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : [];
	}

	/**
	 * Returns per-rule performance sorted by redemptions (for leaderboard).
	 *
	 * @param string $from Y-m-d
	 * @param string $to   Y-m-d
	 * @param int    $limit
	 * @return array<int, array<string,mixed>>
	 */
	public function get_top_rules( string $from, string $to, int $limit = 10 ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $this->db->get_results(
			$this->db->prepare(
				"SELECT `rule_id`,
					SUM(`impressions`)    AS impressions,
					SUM(`redemptions`)    AS redemptions,
					SUM(`revenue`)        AS revenue,
					SUM(`discount_total`) AS discount_total
				 FROM `{$this->table}`
				 WHERE `date` BETWEEN %s AND %s
				 GROUP BY `rule_id`
				 ORDER BY `redemptions` DESC
				 LIMIT %d",
				$from,
				$to,
				$limit
			),
			ARRAY_A
		);
	}
}
