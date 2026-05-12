<?php
/**
 * Completed Orders condition.
 *
 * @package MatrixBogo\Conditions\OrderConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\OrderConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CompletedOrdersCondition extends AbstractCondition {

	public function get_type(): string {
		return 'completed_orders';
	}

	public function get_label(): string {
		return __( 'Number of Completed Orders', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id  = get_current_user_id();
		$count    = (int) wc_get_customer_order_count( $user_id );
		$expected = (int) $this->value;

		return $this->compare( $count, $expected, $this->operator );
	}
}
