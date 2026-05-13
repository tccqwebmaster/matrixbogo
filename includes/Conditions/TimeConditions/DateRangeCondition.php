<?php
/**
 * Date Range condition.
 *
 * @package MatrixBogo\Conditions\TimeConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\TimeConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DateRangeCondition extends AbstractCondition {

	public function get_type(): string {
		return 'date_range';
	}

	public function get_label(): string {
		return __( 'Date Range', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$now = current_time( 'timestamp' );

		/*
		 * FIX: JS stores value as a pipe-separated string "2026-01-01|2026-12-31".
		 * The old code expected an array with 'start'/'end' keys which never arrived,
		 * so start defaulted to 0 and end to PHP_INT_MAX — condition always passed.
		 */
		$raw   = (string) ( is_array( $this->value ) ? '' : $this->value );
		$parts = explode( '|', $raw, 2 );
		$start = ! empty( $parts[0] ) ? strtotime( trim( $parts[0] ) ) : 0;
		$end   = ! empty( $parts[1] ) ? strtotime( trim( $parts[1] ) ) : PHP_INT_MAX;

		// If start/end are provided but unparseable, fail safely.
		if ( false === $start ) {
			$start = 0;
		}
		if ( false === $end ) {
			$end = PHP_INT_MAX;
		}

		$in_range = $now >= $start && $now <= $end;

		return match ( $this->operator ) {
			'is'     => $in_range,
			'is_not' => ! $in_range,
			'between' => $in_range,
			default  => $in_range,
		};
	}
}
