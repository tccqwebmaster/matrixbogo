<?php
/**
 * Time Range condition (e.g. 09:00 – 17:00).
 *
 * @package MatrixBogo\Conditions\TimeConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\TimeConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TimeRangeCondition extends AbstractCondition {

	public function get_type(): string {
		return 'time_range';
	}

	public function get_label(): string {
		return __( 'Time of Day', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$current_time = (int) date( 'Hi', current_time( 'timestamp' ) ); // e.g. 1430 = 14:30

		/*
		 * FIX: JS stores value as "09:00|17:00" (pipe-separated).
		 * The old code expected an array with 'start'/'end' keys which was never populated,
		 * so start defaulted to 0 (midnight) and end to 2359 — condition always passed.
		 */
		$raw   = (string) ( is_array( $this->value ) ? '' : $this->value );
		$parts = explode( '|', $raw, 2 );
		$start = ! empty( $parts[0] ) ? (int) str_replace( ':', '', trim( $parts[0] ) ) : 0;
		$end   = ! empty( $parts[1] ) ? (int) str_replace( ':', '', trim( $parts[1] ) ) : 2359;

		$in_range = $current_time >= $start && $current_time <= $end;

		return match ( $this->operator ) {
			'is'      => $in_range,
			'is_not'  => ! $in_range,
			'between' => $in_range,
			default   => $in_range,
		};
	}
}
