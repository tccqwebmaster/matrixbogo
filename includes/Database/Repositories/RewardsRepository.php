<?php
/**
 * Rewards repository.
 *
 * @package MatrixBogo\Database\Repositories
 */

declare( strict_types=1 );

namespace MatrixBogo\Database\Repositories;

use MatrixBogo\Abstracts\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RewardsRepository extends AbstractRepository {

	protected function get_table_name(): string {
		return 'matrix_bogo_rewards';
	}

	/**
	 * Returns all rewards for a rule, ordered by sort_order.
	 *
	 * @param int $rule_id
	 * @return array<int, array<string,mixed>>
	 */
	public function get_for_rule( int $rule_id ): array {
		$cache_key = "matrix_bogo_rewards_rule_{$rule_id}";
		$cached    = wp_cache_get( $cache_key, 'matrix_bogo' );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM `{$this->table}` WHERE `rule_id` = %d ORDER BY `sort_order` ASC",
				$rule_id
			),
			ARRAY_A
		);

		$result = is_array( $rows ) ? $rows : [];
		wp_cache_set( $cache_key, $result, 'matrix_bogo', 300 );

		return $result;
	}

	/**
	 * Replaces all rewards for a rule.
	 *
	 * @param int                             $rule_id
	 * @param array<int, array<string,mixed>> $rewards
	 */
	public function sync_for_rule( int $rule_id, array $rewards ): void {
		$this->db->delete( $this->table, [ 'rule_id' => $rule_id ], [ '%d' ] );

		foreach ( $rewards as $reward ) {
			$reward['rule_id'] = $rule_id;

			/*
			 * FIX: JS sends choice_pool as a comma-separated string of product IDs
			 * (e.g. "83332,83331,1204406"). The old code only JSON-encoded if already
			 * an array, so the raw string was stored in the DB.  Then db_rewards_to_descriptors()
			 * called json_decode() on it, got null, cast to [], and the pool was always empty.
			 * Now we normalise to a JSON-encoded integer array regardless of input format.
			 */
			if ( isset( $reward['choice_pool'] ) ) {
				if ( is_array( $reward['choice_pool'] ) ) {
					$reward['choice_pool'] = wp_json_encode(
						array_values( array_filter( array_map( 'intval', $reward['choice_pool'] ) ) )
					);
				} elseif ( is_string( $reward['choice_pool'] ) && '' !== $reward['choice_pool'] ) {
					// Comma-separated string from JS multi-select.
					$ids = array_values( array_filter( array_map( 'intval', explode( ',', $reward['choice_pool'] ) ) ) );
					$reward['choice_pool'] = wp_json_encode( $ids );
				} else {
					$reward['choice_pool'] = '[]';
				}
			}

			$this->create( $reward );
		}

		wp_cache_delete( "matrix_bogo_rewards_rule_{$rule_id}", 'matrix_bogo' );
	}
}
