<?php
/**
 * User Role condition.
 *
 * @package MatrixBogo\Conditions\CustomerConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CustomerConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UserRoleCondition extends AbstractCondition {

	public function get_type(): string {
		return 'user_role';
	}

	public function get_label(): string {
		return __( 'User Role', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return $this->compare( [], (array) $this->value, $this->operator );
		}
		$roles    = (array) $user->roles;
		$expected = (array) $this->value;

		return match ( $this->operator ) {
			'in'     => ! empty( array_intersect( $roles, $expected ) ),
			'not_in' => empty( array_intersect( $roles, $expected ) ),
			default  => ! empty( array_intersect( $roles, $expected ) ),
		};
	}
}
