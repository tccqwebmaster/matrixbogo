<?php
/**
 * Repository for promotion rules.
 *
 * @package MatrixBogo\Database\Repositories
 */

declare( strict_types=1 );

namespace MatrixBogo\Database\Repositories;

use MatrixBogo\Abstracts\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RulesRepository
 */
final class RulesRepository extends AbstractRepository {

	protected function get_table_name(): string {
		return 'matrix_bogo_rules';
	}

	// -----------------------------------------------------------------
	// Domain-specific finders
	// -----------------------------------------------------------------

	/**
	 * Returns all active (or scheduled-and-due) rules ordered by priority.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function get_active_rules(): array {
		$now = current_time( 'mysql' );

		$cache_key = 'matrix_bogo_active_rules_' . md5( $now );
		$cached    = wp_cache_get( $cache_key, 'matrix_bogo' );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM `{$this->table}`
				 WHERE `status` = 'active'
				   AND ( `schedule_start` IS NULL OR `schedule_start` <= %s )
				   AND ( `schedule_end`   IS NULL OR `schedule_end`   >= %s )
				 ORDER BY `priority` ASC, `id` ASC",
				$now,
				$now
			),
			ARRAY_A
		);

		$result = is_array( $rows ) ? $rows : [];
		wp_cache_set( $cache_key, $result, 'matrix_bogo', 60 );

		return $result;
	}

	/**
	 * Returns rules by status.
	 *
	 * @param string $status 'active'|'inactive'|'scheduled'|'expired'
	 * @return array<int, array<string,mixed>>
	 */
	public function get_rules_by_status( string $status ): array {
		return $this->find_by( [ 'status' => $status ], 'priority ASC, id ASC' );
	}

	/**
	 * Increments the usage counter for a rule atomically.
	 */
	public function increment_uses( int $rule_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->query(
			$this->db->prepare(
				"UPDATE `{$this->table}` SET `uses_count` = `uses_count` + 1 WHERE `id` = %d",
				$rule_id
			)
		);

		wp_cache_delete( "matrix_bogo_{$this->get_table_name()}_{$rule_id}", 'matrix_bogo' );
		wp_cache_delete( 'matrix_bogo_active_rules_*', 'matrix_bogo' );

		return $result !== false;
	}

	/**
	 * Saves (upsert) a full rule including rule_data JSON.
	 *
	 * @param array<string,mixed> $data Rule field values.
	 * @return int|false  Inserted/updated ID, or false.
	 */
	public function save( array $data ): int|false {
		if ( ! empty( $data['rule_data'] ) && is_array( $data['rule_data'] ) ) {
			$data['rule_data'] = wp_json_encode( $data['rule_data'] );
		}
		if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}

		if ( ! empty( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			return $this->update( $id, $data ) ? $id : false;
		}

		return $this->create( $data );
	}

	/**
	 * Invalidates the active rules cache (call after any rule status change).
	 */
	public function flush_cache(): void {
		wp_cache_flush_group( 'matrix_bogo' );
	}
}
