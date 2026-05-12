<?php
/**
 * First Order condition.
 *
 * @package MatrixBogo\Conditions\CustomerConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CustomerConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FirstOrderCondition extends AbstractCondition {

	public function get_type(): string {
		return 'first_order';
	}

	public function get_label(): string {
		return __( 'First Order', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id      = get_current_user_id();
		$order_count  = wc_get_customer_order_count( $user_id );
		$is_first     = $order_count === 0;
		$expected     = filter_var( $this->value, FILTER_VALIDATE_BOOLEAN );

		return $this->compare( $is_first, $expected, $this->operator );
	}
}
