<?php
/**
 * Cart Modifier – adds/modifies cart items for promotions.
 *
 * @package MatrixBogo\Cart
 */

declare( strict_types=1 );

namespace MatrixBogo\Cart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartModifier
 *
 * Handles the low-level WC cart manipulation: adding gift products,
 * adjusting prices, and adding fee-based discounts.
 */
final class CartModifier {

	/**
	 * Applies a single reward descriptor to the cart.
	 *
	 * @param \WC_Cart            $cart
	 * @param array<string,mixed> $reward
	 */
	public function apply_reward( \WC_Cart $cart, array $reward ): void {
		$discount_type = (string) ( $reward['discount_type'] ?? 'free' );

		// Customer-choice gifts are handled separately via popup.
		if ( ! empty( $reward['customer_choice'] ) ) {
			return;
		}

		match ( $discount_type ) {
			'free'         => $this->apply_free_product( $cart, $reward ),
			'fixed'        => $this->apply_fixed_discount( $cart, $reward ),
			'percent'      => $this->apply_percent_discount( $cart, $reward ),
			'cheapest_free'=> $this->apply_cheapest_free( $cart, $reward ),
			default        => null,
		};
	}

	// -----------------------------------------------------------------
	// Free product
	// -----------------------------------------------------------------

	/**
	 * Adds (or ensures) a free gift product in the cart.
	 *
	 * @param \WC_Cart            $cart
	 * @param array<string,mixed> $reward
	 */
	private function apply_free_product( \WC_Cart $cart, array $reward ): void {
		$product_id   = (int) $reward['product_id'];
		$variation_id = (int) ( $reward['variation_id'] ?? 0 );
		$quantity     = max( 1, (int) ( $reward['quantity'] ?? 1 ) );
		$rule_id      = (int) ( $reward['rule_id'] ?? 0 );

		// Check if this gift is already in the cart.
		foreach ( $cart->get_cart() as $key => $item ) {
			if (
				! empty( $item['matrix_bogo_gift'] ) &&
				(int) $item['product_id']   === $product_id &&
				(int) $item['matrix_bogo_rule_id'] === $rule_id
			) {
				// Update quantity if needed.
				if ( (int) $item['quantity'] !== $quantity ) {
					$cart->set_quantity( $key, $quantity, false );
				}
				return;
			}
		}

		// Add gift to cart.
		$this->add_gift_to_cart( $product_id, $quantity, $rule_id, $variation_id );
	}

	// -----------------------------------------------------------------
	// Fee-based discounts
	// -----------------------------------------------------------------

	/**
	 * Applies a fixed discount fee for matching items.
	 *
	 * @param \WC_Cart            $cart
	 * @param array<string,mixed> $reward
	 */
	private function apply_fixed_discount( \WC_Cart $cart, array $reward ): void {
		$value   = (float) ( $reward['discount_value'] ?? 0 );
		$rule_id = (int) ( $reward['rule_id'] ?? 0 );
		$label   = sprintf(
			/* translators: %s: promotion name */
			__( 'Promotion: %s', 'matrix-bogo' ),
			sanitize_text_field( $reward['label'] ?? '' )
		);

		$cart->add_fee( $label, -$value, false );
	}

	/**
	 * Applies a percentage discount fee.
	 *
	 * @param \WC_Cart            $cart
	 * @param array<string,mixed> $reward
	 */
	private function apply_percent_discount( \WC_Cart $cart, array $reward ): void {
		$percent    = (float) ( $reward['discount_value'] ?? 0 );
		$product_id = (int) ( $reward['product_id'] ?? 0 );

		if ( $product_id > 0 ) {
			// Discount applies only to a specific product.
			$subtotal = 0.0;
			foreach ( $cart->get_cart() as $item ) {
				if ( (int) $item['product_id'] === $product_id && empty( $item['matrix_bogo_gift'] ) ) {
					$subtotal += (float) wc_get_price_excluding_tax( $item['data'] ) * (int) $item['quantity'];
				}
			}
			$discount = $subtotal * ( $percent / 100 );
		} else {
			// Discount applies to whole cart subtotal.
			$discount = (float) $cart->get_subtotal() * ( $percent / 100 );
		}

		if ( $discount <= 0 ) {
			return;
		}

		$label = sprintf(
			/* translators: %1$s: percentage, %2$s: promotion name */
			__( '%1$s%% off \u2013 %2$s', 'matrix-bogo' ),
			$percent,
			sanitize_text_field( $reward['label'] ?? '' )
		);

		$cart->add_fee( $label, -$discount, false );
	}

	/**
	 * Makes the cheapest non-gift item in the cart free.
	 *
	 * @param \WC_Cart            $cart
	 * @param array<string,mixed> $reward
	 */
	private function apply_cheapest_free( \WC_Cart $cart, array $reward ): void {
		$cheapest_price = PHP_FLOAT_MAX;
		$cheapest_key   = '';
		$total_qty      = 0;

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['matrix_bogo_gift'] ) ) {
				continue;
			}
			$total_qty += (int) $item['quantity'];
			$price      = (float) wc_get_price_excluding_tax( $item['data'] );
			if ( $price < $cheapest_price ) {
				$cheapest_price = $price;
				$cheapest_key   = $key;
			}
		}

		// Need at least 2 items in the cart — you must "buy" something to get one free.
		if ( $total_qty < 2 ) {
			return;
		}

		if ( '' === $cheapest_key || $cheapest_price <= 0 ) {
			return;
		}

		$label = sprintf(
			/* translators: %s: promotion name */
			__( 'Cheapest item free \u2013 %s', 'matrix-bogo' ),
			sanitize_text_field( $reward['label'] ?? '' )
		);

		$cart->add_fee( $label, -$cheapest_price, false );
	}

	// -----------------------------------------------------------------
	// Public: add a gift product to the cart
	// -----------------------------------------------------------------

	/**
	 * Adds a gift product to the WC cart with BOGO metadata.
	 *
	 * @param int $product_id
	 * @param int $quantity
	 * @param int $rule_id
	 * @param int $variation_id
	 * @return string|false  Cart item key or false on failure.
	 */
	public function add_gift_to_cart(
		int $product_id,
		int $quantity,
		int $rule_id,
		int $variation_id = 0
	): string|false|\WP_Error {
		$product = wc_get_product( $variation_id ?: $product_id );

		if ( ! $product || ! $product->is_purchasable() ) {
			return new \WP_Error(
				'matrix_bogo_invalid_product',
				__( 'Gift product is not available.', 'matrix-bogo' )
			);
		}

		if ( ! $product->is_in_stock() ) {
			return new \WP_Error(
				'matrix_bogo_out_of_stock',
				__( 'Gift product is out of stock.', 'matrix-bogo' )
			);
		}

		/**
		 * Fires before a gift is added to the cart.
		 *
		 * @param int $product_id
		 * @param int $quantity
		 * @param int $rule_id
		 */
		do_action( 'matrix_bogo_before_gift_add', $product_id, $quantity, $rule_id );

		$cart_item_data = [
			'matrix_bogo_gift'    => true,
			'matrix_bogo_rule_id' => $rule_id,
		];

		$key = WC()->cart->add_to_cart(
			$product_id,
			$quantity,
			$variation_id,
			[],
			$cart_item_data
		);

		if ( $key ) {
			// Force price to 0 immediately.
			WC()->cart->cart_contents[ $key ]['data']->set_price( 0 );

			/**
			 * Fires after a gift was successfully added.
			 *
			 * @param int    $product_id
			 * @param int    $quantity
			 * @param int    $rule_id
			 * @param string $cart_key
			 */
			do_action( 'matrix_bogo_after_gift_add', $product_id, $quantity, $rule_id, $key );
		}

		return $key;
	}
}
