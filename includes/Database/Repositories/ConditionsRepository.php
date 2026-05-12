<?php
/**
 * Conditions repository.
 *
 * @package MatrixBogo\Database\Repositories
 */

declare( strict_types=1 );

namespace MatrixBogo\Database\Repositories;

use MatrixBogo\Abstracts\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConditionsRepository extends AbstractRepository {

	protected function get_table_name(): string {
		return 'matrix_bogo_conditions';
	}

	/**
	 * Returns all conditions for a rule, ordered by group then sort_order.
	 *
	 * @param int $rule_id
	 * @return array<int, array<string,mixed>>
	 */
	public function get_for_rule( int $rule_id ): array {
		$cache_key = "matrix_bogo_conditions_rule_{$rule_id}";
		$cached    = wp_cache_get( $cache_key, 'matrix_bogo' );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM `{$this->table}` WHERE `rule_id` = %d ORDER BY `group_id` ASC, `sort_order` ASC",
				$rule_id
			),
			ARRAY_A
		);

		$result = is_array( $rows ) ? $rows : [];
		wp_cache_set( $cache_key, $result, 'matrix_bogo', 300 );

		return $result;
	}

	/**
	 * Replaces all conditions for a rule.
	 *
	 * @param int                          $rule_id
	 * @param array<int, array<string,mixed>> $conditions
	 */
	public function sync_for_rule( int $rule_id, array $conditions ): void {
		// Delete existing conditions.
		$this->db->delete( $this->table, [ 'rule_id' => $rule_id ], [ '%d' ] );

		// Insert new ones.
		foreach ( $conditions as $condition ) {
			$condition['rule_id'] = $rule_id;
			if ( isset( $condition['value'] ) && is_array( $condition['value'] ) ) {
				$condition['value'] = wp_json_encode( $condition['value'] );
			}
			$this->create( $condition );
		}

		wp_cache_delete( "matrix_bogo_conditions_rule_{$rule_id}", 'matrix_bogo' );
	}
}
