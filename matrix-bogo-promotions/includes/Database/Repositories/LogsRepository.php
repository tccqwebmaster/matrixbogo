<?php
/**
 * Logs repository.
 *
 * @package MatrixBogo\Database\Repositories
 */

declare( strict_types=1 );

namespace MatrixBogo\Database\Repositories;

use MatrixBogo\Abstracts\AbstractRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogsRepository extends AbstractRepository {

	protected function get_table_name(): string {
		return 'matrix_bogo_logs';
	}

	/**
	 * Inserts a log entry.
	 *
	 * @param string              $event    Event slug.
	 * @param string              $message  Human-readable description.
	 * @param array<string,mixed> $context  Extra JSON context.
	 * @param int                 $rule_id  Optional.
	 * @param int                 $order_id Optional.
	 * @param string              $level    'info'|'warning'|'error'
	 */
	public function log(
		string $event,
		string $message,
		array $context = [],
		int $rule_id = 0,
		int $order_id = 0,
		string $level = 'info'
	): void {
		$this->create( [
			'rule_id'   => $rule_id,
			'order_id'  => $order_id,
			'user_id'   => get_current_user_id(),
			'event'     => $event,
			'message'   => $message,
			'context'   => $context ? wp_json_encode( $context ) : null,
			'level'     => in_array( $level, [ 'info', 'warning', 'error' ], true ) ? $level : 'info',
		] );
	}

	/**
	 * Paginates log entries with optional filters.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array{rows:array, total:int}
	 */
	public function paginate( array $args = [] ): array {
		$per_page  = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page      = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset    = ( $page - 1 ) * $per_page;
		$level     = sanitize_key( $args['level'] ?? '' );
		$rule_id   = (int) ( $args['rule_id'] ?? 0 );
		$search    = sanitize_text_field( $args['search'] ?? '' );

		$where  = 'WHERE 1=1';
		$values = [];

		if ( $level ) {
			$where   .= ' AND `level` = %s';
			$values[] = $level;
		}
		if ( $rule_id ) {
			$where   .= ' AND `rule_id` = %d';
			$values[] = $rule_id;
		}
		if ( $search ) {
			$where   .= ' AND `message` LIKE %s';
			$values[] = '%' . $this->db->esc_like( $search ) . '%';
		}

		$count_sql = "SELECT COUNT(*) FROM `{$this->table}` {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data_sql  = "SELECT * FROM `{$this->table}` {$where} ORDER BY `created_at` DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$all_values   = $values;
		$count_values = $values;
		$all_values[] = $per_page;
		$all_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $this->db->get_var( $this->db->prepare( $count_sql, ...$count_values ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = (array) $this->db->get_results( $this->db->prepare( $data_sql, ...$all_values ), ARRAY_A );

		return [ 'rows' => $rows, 'total' => $total ];
	}

	/**
	 * Deletes log entries older than N days.
	 *
	 * @param int $days
	 */
	public function prune( int $days = 90 ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->db->query(
			$this->db->prepare(
				"DELETE FROM `{$this->table}` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
		return (int) $this->db->rows_affected;
	}
}
