<?php
/**
 * Rule Engine – helper utilities for matching cart items against rule triggers.
 *
 * @package MatrixBogo\Promotions
 */

declare( strict_types=1 );

namespace MatrixBogo\Promotions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RuleEngine
 *
 * Provides static helper methods for computing buy-trigger quantities
 * (e.g., how many qualifying items are in the cart).
 */
final class RuleEngine {

	/**
	 * Counts how many cart items match a "buy" trigger definition.
	 *
	 * Trigger definition (all fields optional):
	 * [
	 *   'products'           => [int, ...],   // product IDs
	 *   'variations'         => [int, ...],   // variation IDs — explicitly included options
	 *   'exclude_variations' => [int, ...],   // variation IDs left out of a product/category/tag match
	 *   'categories'         => [int, ...],   // category IDs
	 *   'tags'               => [int, ...],   // tag IDs
	 *   'rule_id'            => int,          // also include options ticked for this rule on the variation itself
	 *   'quantity'           => int,          // required quantity
	 * ]
	 *
	 * A line qualifies when (its product matches products/categories/tags AND
	 * its option is not excluded) OR (its option is explicitly included).
	 *
	 * @param \WC_Cart            $cart
	 * @param array<string,mixed> $trigger
	 * @return array{matching_qty:int, eligible_count:int, matched_items:array<int,array<string,int|string>>}
	 */
	public static function evaluate_trigger( \WC_Cart $cart, array $trigger ): array {
		$required_qty  = (int) ( $trigger['quantity'] ?? 1 );
		$matching_qty  = 0;
		$matched_items = [];

		foreach ( $cart->get_cart() as $cart_item ) {
			// Skip items that are already gifts from this plugin.
			if ( ! empty( $cart_item['matrix_bogo_gift'] ) ) {
				continue;
			}

			$pid = (int) $cart_item['product_id'];
			$vid = (int) ( $cart_item['variation_id'] ?? 0 );
			$qty = (int) $cart_item['quantity'];

			$via = self::match_line( $trigger, $pid, $vid );
			if ( null !== $via ) {
				$matching_qty   += $qty;
				$matched_items[] = [
					'product_id'   => $pid,
					'variation_id' => $vid,
					'quantity'     => $qty,
					'via'          => $via,
				];
			}
		}

		// How many times the trigger fires (floor division).
		$eligible_count = $required_qty > 0 ? (int) floor( $matching_qty / $required_qty ) : 0;

		return [
			'matching_qty'   => $matching_qty,
			'eligible_count' => $eligible_count,
			'matched_items'  => $matched_items,
		];
	}

	/**
	 * Why a product / option matches a trigger: 'option' (explicitly
	 * included), 'product', 'category', 'tag', 'any' (empty trigger) — or
	 * null when it does not qualify.
	 *
	 * @param array<string,mixed> $trigger See evaluate_trigger().
	 * @param int                 $pid     Parent product ID.
	 * @param int                 $vid     Variation ID (0 for simple products).
	 */
	public static function match_line( array $trigger, int $pid, int $vid = 0 ): ?string {
		$product_ids   = array_map( 'intval', (array) ( $trigger['products']           ?? [] ) );
		$variation_ids = array_map( 'intval', (array) ( $trigger['variations']         ?? [] ) );
		$excluded_ids  = array_map( 'intval', (array) ( $trigger['exclude_variations'] ?? [] ) );
		$category_ids  = array_map( 'intval', (array) ( $trigger['categories']         ?? [] ) );
		$tag_ids       = array_map( 'intval', (array) ( $trigger['tags']               ?? [] ) );
		$rule_id       = (int) ( $trigger['rule_id'] ?? 0 );

		// Explicitly included option — wins over everything else.
		if ( $vid ) {
			if ( in_array( $vid, $variation_ids, true ) ) {
				return 'option';
			}
			if ( $rule_id && in_array( $rule_id, VariationEligibility::rule_ids_for( $vid ), true ) ) {
				return 'option';
			}
			if ( in_array( $vid, $excluded_ids, true ) ) {
				return null;
			}
		}

		if ( ! empty( $product_ids ) && in_array( $pid, $product_ids, true ) ) {
			return 'product';
		}
		if ( ! empty( $category_ids ) && array_intersect( self::term_ids( $pid, 'product_cat' ), $category_ids ) ) {
			return 'category';
		}
		if ( ! empty( $tag_ids ) && array_intersect( self::term_ids( $pid, 'product_tag' ), $tag_ids ) ) {
			return 'tag';
		}

		// All filters empty → match any product (not for rules that select by option).
		if ( ! $rule_id && empty( $product_ids ) && empty( $variation_ids ) && empty( $category_ids ) && empty( $tag_ids ) ) {
			return 'any';
		}

		return null;
	}

	/**
	 * Category / tag IDs of a product. Under WPML, the terms of its
	 * default-language original are included too, so a cart line in another
	 * language still matches a promotion set up with the original's terms
	 * (translations are separate posts and may not carry the term).
	 *
	 * @param int    $pid      Product ID.
	 * @param string $taxonomy 'product_cat' or 'product_tag'.
	 * @return int[]
	 */
	private static function term_ids( int $pid, string $taxonomy ): array {
		// wc_get_product_term_ids(): WooCommerce has no wc_get_product_tag_ids().
		$ids = wc_get_product_term_ids( $pid, $taxonomy );

		if ( has_filter( 'wpml_object_id' ) ) {
			$default = (string) apply_filters( 'wpml_default_language', null );
			$orig    = (int) apply_filters( 'wpml_object_id', $pid, 'product', false, $default );
			if ( $orig > 0 && $orig !== $pid ) {
				$ids = array_merge( $ids, wc_get_product_term_ids( $orig, $taxonomy ) );
			}
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Returns all cart items that match a product/category/tag filter.
	 *
	 * @param \WC_Cart            $cart
	 * @param array<string,mixed> $filter
	 * @return array<string, array<string,mixed>>  Keyed by cart item key.
	 */
	public static function get_matching_items( \WC_Cart $cart, array $filter ): array {
		$product_ids  = array_map( 'intval', (array) ( $filter['products']   ?? [] ) );
		$category_ids = array_map( 'intval', (array) ( $filter['categories'] ?? [] ) );
		$tag_ids      = array_map( 'intval', (array) ( $filter['tags']       ?? [] ) );

		$matches = [];

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['matrix_bogo_gift'] ) ) {
				continue;
			}

			$pid    = (int) $item['product_id'];
			$match  = false;

			if ( ! empty( $product_ids ) && in_array( $pid, $product_ids, true ) ) {
				$match = true;
			}
			if ( ! $match && ! empty( $category_ids ) ) {
				$cats = self::term_ids( $pid, 'product_cat' );
				if ( ! empty( array_intersect( $cats, $category_ids ) ) ) {
					$match = true;
				}
			}
			if ( ! $match && ! empty( $tag_ids ) ) {
				$tags = self::term_ids( $pid, 'product_tag' );
				if ( ! empty( array_intersect( $tags, $tag_ids ) ) ) {
					$match = true;
				}
			}
			if ( empty( $product_ids ) && empty( $category_ids ) && empty( $tag_ids ) ) {
				$match = true;
			}

			if ( $match ) {
				$matches[ $key ] = $item;
			}
		}

		return $matches;
	}

	/**
	 * Finds the cheapest item (by unit price) in a set of cart items.
	 *
	 * @param array<string, array<string,mixed>> $items
	 * @return array<string,mixed>|null
	 */
	public static function get_cheapest_item( array $items ): ?array {
		$cheapest     = null;
		$cheapest_price = PHP_INT_MAX;

		foreach ( $items as $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$price = (float) wc_get_price_excluding_tax( $product );
			if ( $price < $cheapest_price ) {
				$cheapest_price = $price;
				$cheapest       = $item;
			}
		}

		return $cheapest;
	}
}
