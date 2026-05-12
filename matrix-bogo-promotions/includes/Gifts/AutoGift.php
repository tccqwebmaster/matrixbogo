<?php
/**
 * Auto Gift – automatically adds non-choice gifts to the cart.
 *
 * @package MatrixBogo\Gifts
 */

declare( strict_types=1 );

namespace MatrixBogo\Gifts;

use MatrixBogo\Cart\CartModifier;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AutoGift
 *
 * When a promotion resolves and the gift does NOT require customer choice,
 * this class adds the product automatically.
 */
final class AutoGift {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	/** @var CartModifier */
	private CartModifier $modifier;

	public function __construct( PromotionEngine $engine, CartModifier $modifier ) {
		$this->engine   = $engine;
		$this->modifier = $modifier;
	}

	/**
	 * Scans session rewards and auto-adds any non-choice free gifts.
	 *
	 * Hooked to `woocommerce_after_calculate_totals`.
	 *
	 * @param \WC_Cart $cart
	 */
	public function auto_add_gifts( \WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$settings = get_option( 'matrix_bogo_settings', [] );
		if ( empty( $settings['auto_apply_promotions'] ) ) {
			return;
		}

		$rewards_map = $this->engine->get_session_rewards();

		foreach ( $rewards_map as $rule_id => $entry ) {
			foreach ( $entry['rewards'] ?? [] as $reward ) {
				// Skip customer-choice gifts – the customer must pick via popup.
				if ( ! empty( $reward['customer_choice'] ) ) {
					continue;
				}

				$product_id   = (int) ( $reward['product_id'] ?? 0 );
				$variation_id = (int) ( $reward['variation_id'] ?? 0 );
				$quantity     = max( 1, (int) ( $reward['quantity'] ?? 1 ) );
				$disc_type    = (string) ( $reward['discount_type'] ?? 'free' );

				if ( 'free' !== $disc_type || ! $product_id ) {
					continue;
				}

				// Check if already in cart.
				$already_in_cart = false;
				foreach ( $cart->get_cart() as $item ) {
					if (
						! empty( $item['matrix_bogo_gift'] ) &&
						(int) $item['product_id'] === $product_id &&
						(int) ( $item['matrix_bogo_rule_id'] ?? 0 ) === (int) $rule_id
					) {
						$already_in_cart = true;
						break;
					}
				}

				if ( ! $already_in_cart ) {
					$this->modifier->add_gift_to_cart( $product_id, $quantity, (int) $rule_id, $variation_id );
				}
			}
		}
	}
}
