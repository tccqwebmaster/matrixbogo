<?php
/**
 * Abstract base class for promotion conditions.
 *
 * @package MatrixBogo\Abstracts
 */

declare( strict_types=1 );

namespace MatrixBogo\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractCondition
 *
 * Every concrete condition must implement evaluate() which returns true
 * when the condition passes.
 */
abstract class AbstractCondition {

	/** @var array<string,mixed> Raw condition row from the database. */
	protected array $condition;

	/** @var mixed Decoded condition value. */
	protected mixed $value;

	/** @var string Comparison operator (is, is_not, >, <, >=, <=, contains, etc.). */
	protected string $operator;

	public function __construct( array $condition ) {
		$this->condition = $condition;
		$this->operator  = (string) ( $condition['operator'] ?? 'is' );

		// Decode JSON value or use raw string.
		$raw          = $condition['value'] ?? '';
		$decoded      = json_decode( $raw, true );
		$this->value  = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $raw;
	}

	// -----------------------------------------------------------------
	// Contract
	// -----------------------------------------------------------------

	/** Machine-readable type key, e.g. 'user_role'. */
	abstract public function get_type(): string;

	/** Human-readable label for the admin UI. */
	abstract public function get_label(): string;

	/**
	 * Evaluates the condition against the current request context.
	 *
	 * @param array<string,mixed> $context  Extra context (cart, user, etc.).
	 * @return bool  True if the condition is satisfied.
	 */
	abstract public function evaluate( array $context = [] ): bool;

	// -----------------------------------------------------------------
	// Shared helpers
	// -----------------------------------------------------------------

	/** Returns the condition group ID (conditions in one group are ANDed). */
	public function get_group_id(): int {
		return (int) ( $this->condition['group_id'] ?? 0 );
	}

	/** Returns the sort order within its group. */
	public function get_sort_order(): int {
		return (int) ( $this->condition['sort_order'] ?? 0 );
	}

	/**
	 * Applies a standard comparison operator to two values.
	 *
	 * @param mixed  $actual   The actual/current value.
	 * @param mixed  $expected The configured expected value.
	 * @param string $operator Operator string.
	 */
	protected function compare( mixed $actual, mixed $expected, string $operator ): bool {
		return match ( $operator ) {
			'is'           => $actual == $expected,  // phpcs:ignore WordPress.PHP.StrictComparisons
			'is_not'       => $actual != $expected,  // phpcs:ignore WordPress.PHP.StrictComparisons
			'>'            => $actual > $expected,
			'>='           => $actual >= $expected,
			'<'            => $actual < $expected,
			'<='           => $actual <= $expected,
			'contains'     => is_string( $actual ) && str_contains( $actual, (string) $expected ),
			'not_contains' => is_string( $actual ) && ! str_contains( $actual, (string) $expected ),
			'in'           => is_array( $expected ) && in_array( $actual, $expected, true ),
			'not_in'       => is_array( $expected ) && ! in_array( $actual, $expected, true ),
			default        => false,
		};
	}
}
