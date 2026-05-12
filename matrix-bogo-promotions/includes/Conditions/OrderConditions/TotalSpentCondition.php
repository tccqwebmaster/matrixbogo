<?php
/**
 * Total Spent condition.
 *
 * @package MatrixBogo\Conditions\OrderConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\OrderConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TotalSpentCondition extends AbstractCondition {

	public function get_type(): string {
		return 'total_spent';
	}

	public function get_label(): string {
		return __( 'Total Amount Spent', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id     = get_current_user_id();
		$spent       = (float) wc_get_customer_total_spent( $user_id );
		$expected    = (float) $this->value;

		return $this->compare( $spent, $expected, $this->operator );
	}
}
