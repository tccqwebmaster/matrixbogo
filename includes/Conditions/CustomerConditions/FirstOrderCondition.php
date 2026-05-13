<?php
declare( strict_types=1 );
namespace MatrixBogo\Conditions\CustomerConditions;
use MatrixBogo\Abstracts\AbstractCondition;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FirstOrderCondition extends AbstractCondition {
	public function get_type(): string { return 'first_order'; }
	public function get_label(): string { return __( 'First Order', 'matrix-bogo' ); }
	public function evaluate( array $context = [] ): bool {
		if ( ! is_user_logged_in() ) {
			// Guests might be placing their first order — treat as first order.
			return $this->operator === 'is_true';
		}
		$is_first = wc_get_customer_order_count( get_current_user_id() ) === 0;
		return $this->compare( $is_first, true, $this->operator );
	}
}
