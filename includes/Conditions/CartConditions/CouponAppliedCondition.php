<?php
/**
 * Coupon Applied condition.
 *
 * @package MatrixBogo\Conditions\CartConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CartConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CouponAppliedCondition extends AbstractCondition {

	public function get_type(): string {
		return 'coupon_applied';
	}

	public function get_label(): string {
		return __( 'Coupon Applied', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return false;
		}

		$applied_coupons = array_map( 'strtolower', $cart->get_applied_coupons() );
		$expected        = array_map( 'strtolower', (array) $this->value );

		$has_coupon = ! empty( array_intersect( $applied_coupons, $expected ) );

		return match ( $this->operator ) {
			'is'     => $has_coupon,
			'is_not' => ! $has_coupon,
			'in'     => $has_coupon,
			'not_in' => ! $has_coupon,
			default  => $has_coupon,
		};
	}
}
