<?php
/**
 * Cart Products condition.
 *
 * @package MatrixBogo\Conditions\CartConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CartConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartProductsCondition extends AbstractCondition {

	public function get_type(): string {
		return 'cart_products';
	}

	public function get_label(): string {
		return __( 'Cart Contains Product', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return false;
		}

		$expected_ids = array_map( 'intval', (array) $this->value );
		$cart_ids     = [];

		foreach ( $cart->get_cart() as $item ) {
			$cart_ids[] = (int) $item['product_id'];
			if ( ! empty( $item['variation_id'] ) ) {
				$cart_ids[] = (int) $item['variation_id'];
			}
		}

		$cart_ids = array_unique( $cart_ids );
		$overlap  = array_intersect( $cart_ids, $expected_ids );

		return match ( $this->operator ) {
			'in'     => ! empty( $overlap ),
			'not_in' => empty( $overlap ),
			default  => ! empty( $overlap ),
		};
	}
}
