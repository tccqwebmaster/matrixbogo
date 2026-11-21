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

	// -----------------------------------------------------------------
	// Security: a gift line must be EARNED (1.4.3)
	// -----------------------------------------------------------------

	/**
	 * Cart keys of gift lines that no reward in $rewards_map pays for.
	 *
	 * 21 Nov 2026: `matrix_bogo_apply_gift` added ANY product (iPhones, a
	 * MacBook) at price 0 for any rule id, with no qualifying purchase, and
	 * those COD orders went through. A gift line is valid only when a reward
	 * of its own rule, in a FRESH evaluation of the cart (gift lines never
	 * count as triggers), covers that product and that quantity.
	 *
	 * @param array<int, array<string,mixed>> $rewards_map  evaluate_cart() result.
	 * @param array<string, array{rule:int,pid:int,vid:int,qty:int}> $lines Gift lines.
	 * @param bool $choice_only Only customer-choice rewards may cover the lines.
	 * @return string[] Keys of $lines that are not covered.
	 */
	public static function uncovered_gift_lines( array $rewards_map, array $lines, bool $choice_only = false ): array {
		$allowances = [];
		foreach ( $rewards_map as $rule_id => $entry ) {
			foreach ( (array) ( $entry['rewards'] ?? [] ) as $reward ) {
				if ( 'free' !== (string) ( $reward['discount_type'] ?? 'free' ) ) {
					continue; // fee-type rewards never create gift lines
				}
				$choice = ! empty( $reward['customer_choice'] );
				if ( $choice_only && ! $choice ) {
					continue;
				}
				$qty = max( 1, (int) ( $reward['quantity'] ?? 1 ) );
				$max = (int) ( $reward['max_per_order'] ?? 0 );
				if ( $max > 0 ) {
					$qty = min( $qty, $max );
				}
				$allowances[ (int) $rule_id ][] = [
					'choice' => $choice,
					'pool'   => array_map( 'intval', (array) ( $reward['choice_pool'] ?? [] ) ),
					'pid'    => (int) ( $reward['product_id'] ?? 0 ),
					'vid'    => (int) ( $reward['variation_id'] ?? 0 ),
					'left'   => $qty,
				];
			}
		}

		$bad = [];
		foreach ( $lines as $key => $line ) {
			$rule = (int) $line['rule'];
			$ids  = array_filter( [ (int) $line['pid'], (int) $line['vid'] ] );
			$need = max( 1, (int) $line['qty'] );
			$ok   = false;

			foreach ( $allowances[ $rule ] ?? [] as $i => $a ) {
				if ( $a['left'] < $need ) {
					continue;
				}
				if ( $a['choice'] ) {
					$match = empty( $a['pool'] ) || array_intersect( $ids, $a['pool'] );
				} else {
					$match = ( $a['pid'] && in_array( $a['pid'], $ids, true ) )
						|| ( $a['vid'] && in_array( $a['vid'], $ids, true ) );
				}
				if ( $match ) {
					$allowances[ $rule ][ $i ]['left'] -= $need;
					$ok = true;
					break;
				}
			}

			if ( ! $ok ) {
				$bad[] = (string) $key;
			}
		}
		return $bad;
	}

	/**
	 * The cart's gift lines in uncovered_gift_lines() shape.
	 *
	 * @param \WC_Cart $cart
	 * @return array<string, array{rule:int,pid:int,vid:int,qty:int}>
	 */
	public static function gift_lines( \WC_Cart $cart ): array {
		$lines = [];
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item['matrix_bogo_gift'] ) ) {
				continue;
			}
			$lines[ (string) $key ] = [
				'rule' => (int) ( $item['matrix_bogo_rule_id'] ?? 0 ),
				'pid'  => (int) ( $item['product_id'] ?? 0 ),
				'vid'  => (int) ( $item['variation_id'] ?? 0 ),
				'qty'  => (int) ( $item['quantity'] ?? 1 ),
			];
		}
		return $lines;
	}

	/**
	 * Removes unearned gift lines before an order can be placed.
	 *
	 * Hooked to `woocommerce_check_cart_items`, which runs on the cart page,
	 * the checkout page, inside WC_Checkout::process_checkout() (an error
	 * notice there stops the order) and in the Store API cart validation.
	 */
	public function enforce_earned_gifts(): void {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}
		$lines = self::gift_lines( $cart );
		if ( empty( $lines ) ) {
			return;
		}

		$bad = self::uncovered_gift_lines( $this->engine->evaluate_cart( $cart ), $lines );
		if ( empty( $bad ) ) {
			return;
		}

		foreach ( $bad as $key ) {
			$cart->remove_cart_item( $key );
		}
		$cart->calculate_totals();

		wc_add_notice(
			__( 'A free gift in your cart is not covered by an active promotion and was removed. Please review your cart.', 'matrix-bogo' ),
			'error'
		);
	}
}
