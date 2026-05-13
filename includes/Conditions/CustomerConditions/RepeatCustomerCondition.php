<?php
declare( strict_types=1 );
namespace MatrixBogo\Conditions\CustomerConditions;
use MatrixBogo\Abstracts\AbstractCondition;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class RepeatCustomerCondition extends AbstractCondition {
	public function get_type(): string { return 'repeat_customer'; }
	public function get_label(): string { return __( 'Repeat Customer', 'matrix-bogo' ); }
	public function evaluate( array $context = [] ): bool {
		if ( ! is_user_logged_in() ) {
			return $this->operator === 'is_false';
		}
		$is_repeat = wc_get_customer_order_count( get_current_user_id() ) > 0;
		return $this->compare( $is_repeat, true, $this->operator );
	}
}
