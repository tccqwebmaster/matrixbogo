<?php
/**
 * Day of Week condition.
 *
 * @package MatrixBogo\Conditions\TimeConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\TimeConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DayOfWeekCondition extends AbstractCondition {

	public function get_type(): string {
		return 'day_of_week';
	}

	public function get_label(): string {
		return __( 'Day of Week', 'matrix-bogo' );
	}

	/**
	 * Maps day abbreviations (mon, tue, …) sent by the JS builder
	 * to PHP date('N') values (1=Monday … 7=Sunday).
	 *
	 * @var array<string,int>
	 */
	private const DAY_MAP = [
		'mon' => 1,
		'tue' => 2,
		'wed' => 3,
		'thu' => 4,
		'fri' => 5,
		'sat' => 6,
		'sun' => 7,
	];

	public function evaluate( array $context = [] ): bool {
		// PHP date('N'): 1=Monday ... 7=Sunday
		$today = (int) date( 'N', current_time( 'timestamp' ) );

		/*
		 * FIX: JS stores selected days as a comma-separated string of abbreviations
		 * e.g. "mon,wed,fri".  The old code used array_map('intval', ...) which
		 * converted 'mon' → 0, never matching date('N') values of 1–7.
		 * Now we map abbreviations to their ISO-8601 integer equivalents.
		 */
		$raw      = (string) ( is_array( $this->value ) ? implode( ',', $this->value ) : $this->value );
		$abbrevs  = array_filter( array_map( 'trim', explode( ',', strtolower( $raw ) ) ) );
		$expected = [];
		foreach ( $abbrevs as $abbr ) {
			if ( isset( self::DAY_MAP[ $abbr ] ) ) {
				$expected[] = self::DAY_MAP[ $abbr ];
			} elseif ( is_numeric( $abbr ) ) {
				// Also accept numeric values (1–7) for forward-compat.
				$expected[] = (int) $abbr;
			}
		}

		return match ( $this->operator ) {
			'in'     => in_array( $today, $expected, true ),
			'not_in' => ! in_array( $today, $expected, true ),
			default  => in_array( $today, $expected, true ),
		};
	}
}
