<?php
/**
 * Logged-in condition.
 *
 * @package MatrixBogo\Conditions\CustomerConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CustomerConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LoggedInCondition extends AbstractCondition {

	public function get_type(): string {
		return 'logged_in';
	}

	public function get_label(): string {
		return __( 'Customer Login Status', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$is_logged_in = is_user_logged_in();
		$expected     = filter_var( $this->value, FILTER_VALIDATE_BOOLEAN );
		return $this->compare( $is_logged_in, $expected, $this->operator );
	}
}
