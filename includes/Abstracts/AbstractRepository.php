<?php
/**
 * Abstract base repository providing common CRUD helpers.
 *
 * @package MatrixBogo\Abstracts
 */

declare( strict_types=1 );

namespace MatrixBogo\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractRepository
 *
 * Provides a thin, secure wrapper around $wpdb for plugin repositories.
 * All public methods use prepared statements.
 */
abstract class AbstractRepository {

	/** @var \wpdb */
	protected \wpdb $db;

	/** @var string Full table name (with prefix). */
	protected string $table;

	/** @var string Primary key column name. */
	protected string $primary_key = 'id';

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $this->db->prefix . $this->get_table_name();
	}

	/**
	 * Returns the un-prefixed table name.
	 */
	abstract protected function get_table_name(): string;

	// -----------------------------------------------------------------
	// Create
	// -----------------------------------------------------------------

	/**
	 * Inserts a new row and returns its ID, or false on failure.
	 *
	 * @param array<string,mixed> $data   Column-value pairs.
	 * @param array<string,string> $format Printf format strings per value.
	 */
	public function create( array $data, array $format = [] ): int|false {
		$result = $this->db->insert( $this->table, $data, $format ?: null );
		return $result !== false ? (int) $this->db->insert_id : false;
	}

	// -----------------------------------------------------------------
	// Read
	// -----------------------------------------------------------------

	/**
	 * Returns a single row by primary key, or null if not found.
	 *
	 * @param int $id Row ID.
	 * @return array<string,mixed>|null
	 */
	public function find( int $id ): ?array {
		$cache_key = "matrix_bogo_{$this->get_table_name()}_{$id}";
		$cached    = wp_cache_get( $cache_key, 'matrix_bogo' );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM `{$this->table}` WHERE `{$this->primary_key}` = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		$value = $row ?? null;
		wp_cache_set( $cache_key, $value, 'matrix_bogo', 300 );
		return $value;
	}

	/**
	 * Returns all rows matching arbitrary WHERE conditions.
	 *
	 * @param array<string,mixed> $where   Column-value pairs (ANDed).
	 * @param string              $orderby ORDER BY clause (e.g. 'created_at DESC').
	 * @param int                 $limit   0 = no limit.
	 * @param int                 $offset  Row offset.
	 * @return array<int,array<string,mixed>>
	 */
	public function find_by(
		array $where = [],
		string $orderby = '',
		int $limit = 0,
		int $offset = 0
	): array {
		$sql    = "SELECT * FROM `{$this->table}`"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values = [];

		if ( ! empty( $where ) ) {
			$clauses = [];
			foreach ( $where as $col => $val ) {
				$clauses[] = "`{$col}` = %s"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$values[]  = $val;
			}
			$sql .= ' WHERE ' . implode( ' AND ', $clauses );
		}

		if ( $orderby ) {
			// Whitelist: only alphanumeric + underscore + space + comma + ASC/DESC.
			$orderby = preg_replace( '/[^a-zA-Z0-9_, ]/', '', $orderby );
			$sql    .= " ORDER BY {$orderby}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$values[] = $limit;
			$values[] = $offset;
		}

		if ( $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $this->db->prepare( $sql, ...$values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $this->db->get_results( $sql, ARRAY_A );
	}

	/**
	 * Counts rows in the table.
	 *
	 * @param array<string,mixed> $where Optional WHERE conditions.
	 */
	public function count( array $where = [] ): int {
		$sql    = "SELECT COUNT(*) FROM `{$this->table}`"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values = [];

		if ( ! empty( $where ) ) {
			$clauses = [];
			foreach ( $where as $col => $val ) {
				$clauses[] = "`{$col}` = %s"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$values[]  = $val;
			}
			$sql .= ' WHERE ' . implode( ' AND ', $clauses );
		}

		if ( $values ) {
			$sql = $this->db->prepare( $sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->db->get_var( $sql );
	}

	// -----------------------------------------------------------------
	// Update
	// -----------------------------------------------------------------

	/**
	 * Updates a row by primary key.
	 *
	 * @param int                  $id     Row ID.
	 * @param array<string,mixed>  $data   New values.
	 * @param array<string,string> $format Printf formats.
	 */
	public function update( int $id, array $data, array $format = [] ): bool {
		$result = $this->db->update(
			$this->table,
			$data,
			[ $this->primary_key => $id ],
			$format ?: null,
			[ '%d' ]
		);

		// Bust cache.
		wp_cache_delete( "matrix_bogo_{$this->get_table_name()}_{$id}", 'matrix_bogo' );

		return $result !== false;
	}

	// -----------------------------------------------------------------
	// Delete
	// -----------------------------------------------------------------

	/**
	 * Deletes a row by primary key.
	 *
	 * @param int $id Row ID.
	 */
	public function delete( int $id ): bool {
		$result = $this->db->delete(
			$this->table,
			[ $this->primary_key => $id ],
			[ '%d' ]
		);

		wp_cache_delete( "matrix_bogo_{$this->get_table_name()}_{$id}", 'matrix_bogo' );

		return $result !== false;
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * Returns the last database error.
	 */
	public function get_last_error(): string {
		return $this->db->last_error;
	}
}
