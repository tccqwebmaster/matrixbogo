<?php
/**
 * Cart Subtotal condition.
 *
 * @package MatrixBogo\Conditions\CartConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CartConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartSubtotalCondition extends AbstractCondition {

	public function get_type(): string {
		return 'cart_subtotal';
	}

	public function get_label(): string {
		return __( 'Cart Subtotal', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return false;
		}

		$subtotal = (float) $cart->get_subtotal();
		$expected = (float) $this->value;

		return $this->compare( $subtotal, $expected, $this->operator );
	}
}
