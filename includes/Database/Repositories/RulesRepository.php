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
	 * FIX: The previous implementation used md5(current_time('mysql')) as the
	 * cache key, which creates a brand-new key every second — effectively
	 * bypassing the object cache entirely and issuing a DB query on every single
	 * cart recalculation. We now use a static cache key and bust it explicitly
	 * whenever a rule is created, updated, deleted, or its uses_count changes.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function get_active_rules(): array {
		$now       = current_time( 'mysql' );
		$cache_key = 'matrix_bogo_active_rules';
		$cached    = wp_cache_get( $cache_key, 'matrix_bogo' );

		if ( false !== $cached ) {
			/*
			 * The cached result was built at an arbitrary point in time. Rules
			 * with schedule_start or schedule_end boundaries may have crossed a
			 * threshold since then. We re-filter in PHP so we don't serve stale
			 * scheduling data while still avoiding a full DB hit on every request.
			 */
			return $this->filter_by_schedule( (array) $cached, $now );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM `{$this->table}`
				 WHERE `status` = 'active'
				 ORDER BY `priority` ASC, `id` ASC",
			),
			ARRAY_A
		);

		$result = is_array( $rows ) ? $rows : [];

		/*
		 * Cache ALL active rules regardless of schedule, then filter in PHP.
		 * TTL of 5 minutes is intentionally short so changes propagate quickly
		 * even if flush_cache() is somehow not called.
		 */
		wp_cache_set( $cache_key, $result, 'matrix_bogo', 300 );

		return $this->filter_by_schedule( $result, $now );
	}

	/**
	 * Filters a pre-loaded rule list to those within their scheduled window.
	 *
	 * @param array<int, array<string,mixed>> $rules
	 * @param string $now MySQL datetime string.
	 * @return array<int, array<string,mixed>>
	 */
	private function filter_by_schedule( array $rules, string $now ): array {
		return array_values( array_filter( $rules, static function ( array $rule ) use ( $now ): bool {
			if ( ! empty( $rule['schedule_start'] ) && $rule['schedule_start'] > $now ) {
				return false;
			}
			if ( ! empty( $rule['schedule_end'] ) && $rule['schedule_end'] < $now ) {
				return false;
			}
			return true;
		} ) );
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
	 *
	 * FIX: Previous code called wp_cache_delete() with a wildcard pattern which
	 * never matches anything in WP object cache. We now bust the correct static
	 * cache key used by get_active_rules().
	 */
	public function increment_uses( int $rule_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->db->query(
			$this->db->prepare(
				"UPDATE `{$this->table}` SET `uses_count` = `uses_count` + 1 WHERE `id` = %d",
				$rule_id
			)
		);

		// Bust the per-row cache.
		wp_cache_delete( "matrix_bogo_{$this->get_table_name()}_{$rule_id}", 'matrix_bogo' );
		// Bust the active-rules list so stale uses_count is not served.
		wp_cache_delete( 'matrix_bogo_active_rules', 'matrix_bogo' );

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
		wp_cache_delete( 'matrix_bogo_active_rules', 'matrix_bogo' );
		wp_cache_flush_group( 'matrix_bogo' );
	}
}
