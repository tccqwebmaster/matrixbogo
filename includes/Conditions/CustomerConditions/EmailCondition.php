<?php
/**
 * Email condition.
 *
 * @package MatrixBogo\Conditions\CustomerConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CustomerConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailCondition extends AbstractCondition {

	public function get_type(): string {
		return 'email';
	}

	public function get_label(): string {
		return __( 'Customer Email', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return false;
		}
		$email    = strtolower( $user->user_email );
		$expected = array_map( 'strtolower', (array) $this->value );

		return match ( $this->operator ) {
			'in'     => in_array( $email, $expected, true ),
			'not_in' => ! in_array( $email, $expected, true ),
			'contains' => (bool) array_filter( $expected, static fn( $e ) => str_contains( $email, $e ) ),
			default    => in_array( $email, $expected, true ),
		};
	}
}
