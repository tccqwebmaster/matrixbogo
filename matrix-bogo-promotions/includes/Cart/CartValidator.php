<?php
/**
 * Cart Validator – prevents invalid or duplicate gift additions.
 *
 * @package MatrixBogo\Cart
 */

declare( strict_types=1 );

namespace MatrixBogo\Cart;

use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartValidator
 */
final class CartValidator {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	public function __construct( PromotionEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Validates an add-to-cart action to prevent duplicate gifts.
	 *
	 * Hooked to `woocommerce_add_to_cart_validation`.
	 *
	 * @param bool $passed      Current validation state.
	 * @param int  $product_id
	 * @param int  $quantity
	 * @return bool
	 */
	public function validate_add( bool $passed, int $product_id, int $quantity ): bool {
		if ( ! $passed ) {
			return false;
		}

		// Nothing extra to validate for standard add-to-cart.
		// Gift validation is handled inside CartModifier::add_gift_to_cart().
		return $passed;
	}

	/**
	 * Checks that all gift items in the cart are still backed by an active rule.
	 *
	 * @return array<string> Cart item keys that are invalid.
	 */
	public function get_invalid_gift_keys(): array {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return [];
		}

		$valid_rule_ids = array_keys( $this->engine->get_session_rewards() );
		$invalid_keys   = [];

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item['matrix_bogo_gift'] ) ) {
				continue;
			}

			$rule_id = (int) ( $item['matrix_bogo_rule_id'] ?? 0 );
			if ( ! in_array( $rule_id, $valid_rule_ids, true ) ) {
				$invalid_keys[] = $key;
			}
		}

		return $invalid_keys;
	}
}
