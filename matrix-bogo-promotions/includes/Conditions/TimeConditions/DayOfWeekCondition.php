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

	public function evaluate( array $context = [] ): bool {
		// PHP date('N'): 1=Monday ... 7=Sunday
		$today    = (int) date( 'N', current_time( 'timestamp' ) );
		$expected = array_map( 'intval', (array) $this->value );

		return match ( $this->operator ) {
			'in'     => in_array( $today, $expected, true ),
			'not_in' => ! in_array( $today, $expected, true ),
			default  => in_array( $today, $expected, true ),
		};
	}
}
