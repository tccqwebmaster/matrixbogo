<?php
/**
 * Specific User condition.
 *
 * @package MatrixBogo\Conditions\CustomerConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CustomerConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SpecificUserCondition extends AbstractCondition {

	public function get_type(): string {
		return 'specific_user';
	}

	public function get_label(): string {
		return __( 'Specific Users', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$user_id  = get_current_user_id();
		$expected = array_map( 'intval', (array) $this->value );
		return $this->compare( $user_id, $expected, $this->operator );
	}
}
