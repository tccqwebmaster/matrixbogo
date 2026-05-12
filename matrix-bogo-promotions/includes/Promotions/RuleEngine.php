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
	 *   'products'   => [int, ...],   // product IDs
	 *   'variations' => [int, ...],   // variation IDs
	 *   'categories' => [int, ...],   // category IDs
	 *   'tags'       => [int, ...],   // tag IDs
	 *   'quantity'   => int,          // required quantity
	 * ]
	 *
	 * @param \WC_Cart            $cart
	 * @param array<string,mixed> $trigger
	 * @return array{matching_qty:int, eligible_count:int}
	 */
	public static function evaluate_trigger( \WC_Cart $cart, array $trigger ): array {
		$product_ids   = array_map( 'intval', (array) ( $trigger['products']   ?? [] ) );
		$variation_ids = array_map( 'intval', (array) ( $trigger['variations'] ?? [] ) );
		$category_ids  = array_map( 'intval', (array) ( $trigger['categories'] ?? [] ) );
		$tag_ids       = array_map( 'intval', (array) ( $trigger['tags']       ?? [] ) );
		$required_qty  = (int) ( $trigger['quantity'] ?? 1 );

		$matching_qty = 0;

		foreach ( $cart->get_cart() as $cart_item ) {
			// Skip items that are already gifts from this plugin.
			if ( ! empty( $cart_item['matrix_bogo_gift'] ) ) {
				continue;
			}

			$pid    = (int) $cart_item['product_id'];
			$vid    = (int) ( $cart_item['variation_id'] ?? 0 );
			$qty    = (int) $cart_item['quantity'];

			$is_match = false;

			if ( ! empty( $product_ids ) && in_array( $pid, $product_ids, true ) ) {
				$is_match = true;
			}
			if ( ! empty( $variation_ids ) && $vid && in_array( $vid, $variation_ids, true ) ) {
				$is_match = true;
			}
			if ( ! $is_match && ! empty( $category_ids ) ) {
				$product_cats = wc_get_product_cat_ids( $pid );
				if ( ! empty( array_intersect( $product_cats, $category_ids ) ) ) {
					$is_match = true;
				}
			}
			if ( ! $is_match && ! empty( $tag_ids ) ) {
				$product_tags = wc_get_product_tag_ids( $pid );
				if ( ! empty( array_intersect( $product_tags, $tag_ids ) ) ) {
					$is_match = true;
				}
			}

			// If all filters are empty → match any product.
			if ( empty( $product_ids ) && empty( $variation_ids ) && empty( $category_ids ) && empty( $tag_ids ) ) {
				$is_match = true;
			}

			if ( $is_match ) {
				$matching_qty += $qty;
			}
		}

		// How many times the trigger fires (floor division).
		$eligible_count = $required_qty > 0 ? (int) floor( $matching_qty / $required_qty ) : 0;

		return [
			'matching_qty'   => $matching_qty,
			'eligible_count' => $eligible_count,
		];
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
				$cats = wc_get_product_cat_ids( $pid );
				if ( ! empty( array_intersect( $cats, $category_ids ) ) ) {
					$match = true;
				}
			}
			if ( ! $match && ! empty( $tag_ids ) ) {
				$tags = wc_get_product_tag_ids( $pid );
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
