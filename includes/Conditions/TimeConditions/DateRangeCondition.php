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
		$now   = current_time( 'timestamp' );
		$value = (array) $this->value;
		$start = ! empty( $value['start'] ) ? strtotime( $value['start'] ) : 0;
		$end   = ! empty( $value['end'] )   ? strtotime( $value['end'] )   : PHP_INT_MAX;

		$in_range = $now >= $start && $now <= $end;

		return match ( $this->operator ) {
			'is'     => $in_range,
			'is_not' => ! $in_range,
			default  => $in_range,
		};
	}
}
