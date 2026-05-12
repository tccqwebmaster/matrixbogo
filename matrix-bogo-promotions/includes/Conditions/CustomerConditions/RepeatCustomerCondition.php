<?php
/**
 * Repeat Customer condition.
 *
 * @package MatrixBogo\Conditions\CustomerConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CustomerConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RepeatCustomerCondition extends AbstractCondition {

	public function get_type(): string {
		return 'repeat_customer';
	}

	public function get_label(): string {
		return __( 'Repeat Customer', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id      = get_current_user_id();
		$order_count  = wc_get_customer_order_count( $user_id );
		$is_repeat    = $order_count > 0;
		$expected     = filter_var( $this->value, FILTER_VALIDATE_BOOLEAN );

		return $this->compare( $is_repeat, $expected, $this->operator );
	}
}
