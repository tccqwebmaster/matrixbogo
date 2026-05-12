<?php
/**
 * Cart Quantity condition.
 *
 * @package MatrixBogo\Conditions\CartConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CartConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartQuantityCondition extends AbstractCondition {

	public function get_type(): string {
		return 'cart_quantity';
	}

	public function get_label(): string {
		return __( 'Cart Item Quantity', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return false;
		}

		$quantity = (int) $cart->get_cart_contents_count();
		$expected = (int) $this->value;

		return $this->compare( $quantity, $expected, $this->operator );
	}
}
