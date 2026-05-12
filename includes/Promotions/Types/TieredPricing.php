<?php
/**
 * Tiered / Volume Pricing promotion type.
 *
 * rule_data (flat keys saved by builder):
 *   trigger_product_ids – comma-separated product IDs (empty = all products)
 *   tiers               – JSON array of tier objects:
 *     [
 *       { "min": 1,  "max": 4,    "type": "percent", "value": 0  },
 *       { "min": 5,  "max": 9,    "type": "percent", "value": 10 },
 *       { "min": 10, "max": null, "type": "percent", "value": 20 }
 *     ]
 *   apply_to – "product" | "cart"  (default: product)
 *
 * How it works:
 *   For each cart item whose product_id is in trigger_product_ids (or all
 *   items when empty), the engine finds the matching tier by quantity and
 *   emits a negative fee (= discount) via CartModifier-compatible descriptors.
 *
 * @package MatrixBogo\Promotions\Types
 */

declare( strict_types=1 );

namespace MatrixBogo\Promotions\Types;

use MatrixBogo\Abstracts\AbstractPromotion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TieredPricing extends AbstractPromotion {

	public function get_type(): string {
		return 'tiered_pricing';
	}

	public function get_label(): string {
		return __( 'Tiered / Volume Pricing', 'matrix-bogo' );
	}

	// -----------------------------------------------------------------
	// Applicability
	// -----------------------------------------------------------------

	public function is_applicable( \WC_Cart $cart ): bool {
		return ! empty( $this->get_applicable_items( $cart ) );
	}

	// -----------------------------------------------------------------
	// Reward calculation
	// -----------------------------------------------------------------

	public function calculate_rewards( \WC_Cart $cart ): array {
		$tiers    = $this->get_tiers();
		$items    = $this->get_applicable_items( $cart );
		$apply_to = (string) ( $this->rule_data['apply_to'] ?? 'product' );
		$rewards  = [];

		if ( 'cart' === $apply_to ) {
			// Single tier based on total cart quantity of matching items.
			$total_qty = array_sum( array_column( $items, 'quantity' ) );
			$tier      = $this->match_tier( $tiers, $total_qty );
			if ( ! $tier ) {
				return [];
			}

			$subtotal = 0.0;
			foreach ( $items as $item ) {
				$subtotal += (float) wc_get_price_excluding_tax( $item['data'] ) * (int) $item['quantity'];
			}

			$discount = $this->calc_discount( $tier, $subtotal );
			if ( $discount > 0 ) {
				$rewards[] = $this->make_fee_reward( $discount, $tier, $total_qty );
			}
		} else {
			// Per-product tier: each product's qty determines its tier independently.
			foreach ( $items as $cart_key => $item ) {
				$qty  = (int) $item['quantity'];
				$tier = $this->match_tier( $tiers, $qty );
				if ( ! $tier ) {
					continue;
				}

				$unit_price = (float) wc_get_price_excluding_tax( $item['data'] );
				$subtotal   = $unit_price * $qty;
				$discount   = $this->calc_discount( $tier, $subtotal );

				if ( $discount > 0 ) {
					$rewards[] = $this->make_fee_reward( $discount, $tier, $qty, (int) $item['product_id'] );
				}
			}
		}

		return $rewards;
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * Returns parsed tier array from rule_data.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private function get_tiers(): array {
		$raw = $this->rule_data['tiers'] ?? '[]';
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Returns cart items that match the trigger product filter.
	 *
	 * @return array<string, array<string,mixed>>
	 */
	private function get_applicable_items( \WC_Cart $cart ): array {
		$ids     = $this->ids_from_data( 'trigger_product_ids' );
		$matches = [];

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['matrix_bogo_gift'] ) ) {
				continue;
			}
			$pid = (int) $item['product_id'];
			if ( empty( $ids ) || in_array( $pid, $ids, true ) ) {
				$matches[ $key ] = $item;
			}
		}

		return $matches;
	}

	/**
	 * Finds the first tier whose min ≤ $qty and (max is null OR max ≥ $qty).
	 *
	 * @param array<int, array<string,mixed>> $tiers
	 * @return array<string,mixed>|null
	 */
	private function match_tier( array $tiers, int $qty ): ?array {
		foreach ( $tiers as $tier ) {
			$min = (int) ( $tier['min'] ?? 0 );
			$max = isset( $tier['max'] ) && $tier['max'] !== null && $tier['max'] !== '' ? (int) $tier['max'] : null;
			if ( $qty >= $min && ( null === $max || $qty <= $max ) ) {
				return $tier;
			}
		}
		return null;
	}

	/**
	 * Calculates the discount amount for a tier on a given subtotal.
	 */
	private function calc_discount( array $tier, float $subtotal ): float {
		$type  = (string) ( $tier['type'] ?? 'percent' );
		$value = (float) ( $tier['value'] ?? 0 );

		if ( 'percent' === $type ) {
			return round( $subtotal * ( $value / 100 ), wc_get_price_decimals() );
		}
		// fixed
		return min( $value, $subtotal );
	}

	/**
	 * Builds a CartModifier-compatible fee descriptor (negative = discount).
	 *
	 * @param array<string,mixed> $tier
	 */
	private function make_fee_reward( float $discount, array $tier, int $qty, int $product_id = 0 ): array {
		$type  = 'percent' === ( $tier['type'] ?? 'percent' ) ? 'percent' : 'fixed';
		$value = (float) ( $tier['value'] ?? 0 );

		$label = $product_id
			? sprintf(
				/* translators: 1: discount value, 2: qty */
				__( '%1$s off ×%2$d — %3$s', 'matrix-bogo' ),
				'percent' === $type ? $value . '%' : wc_price( $value ),
				$qty,
				$this->get_name()
			)
			: sprintf(
				/* translators: 1: discount value */
				__( 'Volume discount %1$s — %2$s', 'matrix-bogo' ),
				'percent' === $type ? $value . '%' : wc_price( $value ),
				$this->get_name()
			);

		return [
			'rule_id'         => $this->get_id(),
			'product_id'      => $product_id,
			'variation_id'    => 0,
			'quantity'        => 1,
			'discount_type'   => $type,
			'discount_value'  => $discount,   // actual $ amount to deduct
			'max_per_order'   => 0,
			'customer_choice' => false,
			'choice_pool'     => [],
			'label'           => $label,
			'_fee_mode'       => true,        // flag CartModifier to use add_fee()
		];
	}
}
