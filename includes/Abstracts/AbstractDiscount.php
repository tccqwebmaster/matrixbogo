<?php
/**
 * Abstract base class for discount types.
 *
 * @package MatrixBogo\Abstracts
 */

declare( strict_types=1 );

namespace MatrixBogo\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractDiscount
 *
 * Every discount implementation calculates the monetary amount to deduct
 * and applies it to the relevant cart item(s).
 */
abstract class AbstractDiscount {

	/** @var array<string,mixed> Reward configuration row. */
	protected array $reward;

	public function __construct( array $reward ) {
		$this->reward = $reward;
	}

	// -----------------------------------------------------------------
	// Contract
	// -----------------------------------------------------------------

	/** Machine-readable discount type key. */
	abstract public function get_type(): string;

	/** Human-readable label. */
	abstract public function get_label(): string;

	/**
	 * Calculates the discount amount for a single cart item.
	 *
	 * @param array<string,mixed> $cart_item WooCommerce cart item array.
	 * @return float  Discount amount (positive).
	 */
	abstract public function calculate( array $cart_item ): float;

	/**
	 * Applies the discount to the cart item by mutating WC item price.
	 *
	 * @param array<string,mixed> $cart_item
	 */
	abstract public function apply( array &$cart_item ): void;

	// -----------------------------------------------------------------
	// Shared helpers
	// -----------------------------------------------------------------

	/** Returns the configured discount value. */
	protected function get_value(): float {
		return (float) ( $this->reward['discount_value'] ?? 0 );
	}

	/** Returns the configured discount sub-type ('free','fixed','percent'). */
	protected function get_discount_type(): string {
		return (string) ( $this->reward['discount_type'] ?? 'free' );
	}

	/**
	 * Safely retrieves the price of a cart item.
	 *
	 * @param array<string,mixed> $cart_item
	 */
	protected function get_item_price( array $cart_item ): float {
		$product = $cart_item['data'] ?? null;
		if ( $product instanceof \WC_Product ) {
			return (float) wc_get_price_excluding_tax( $product );
		}
		return 0.0;
	}
}
