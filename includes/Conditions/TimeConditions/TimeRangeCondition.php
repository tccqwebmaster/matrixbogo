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
		$value        = (array) $this->value;

		$start = isset( $value['start'] ) ? (int) str_replace( ':', '', $value['start'] ) : 0;
		$end   = isset( $value['end'] )   ? (int) str_replace( ':', '', $value['end'] )   : 2359;

		$in_range = $current_time >= $start && $current_time <= $end;

		return match ( $this->operator ) {
			'is'     => $in_range,
			'is_not' => ! $in_range,
			default  => $in_range,
		};
	}
}
