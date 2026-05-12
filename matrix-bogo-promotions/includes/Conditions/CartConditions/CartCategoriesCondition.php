<?php
/**
 * Cart Categories condition – passes if the cart contains products from given categories.
 *
 * @package MatrixBogo\Conditions\CartConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\CartConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartCategoriesCondition extends AbstractCondition {

	public function get_type(): string {
		return 'cart_categories';
	}

	public function get_label(): string {
		return __( 'Cart Contains Category', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return false;
		}

		$expected_cats = array_map( 'intval', (array) $this->value );
		$cart_cats     = [];

		foreach ( $cart->get_cart() as $item ) {
			$product_id = (int) ( $item['variation_id'] ?: $item['product_id'] );
			$terms      = wc_get_product_cat_ids( $product_id );
			$cart_cats  = array_merge( $cart_cats, $terms );
		}

		$cart_cats = array_unique( $cart_cats );
		$overlap   = array_intersect( $cart_cats, $expected_cats );

		return match ( $this->operator ) {
			'in'     => ! empty( $overlap ),
			'not_in' => empty( $overlap ),
			default  => ! empty( $overlap ),
		};
	}
}
