<?php
/**
 * General utilities.
 *
 * @package MatrixBogo\Helpers
 */

declare( strict_types=1 );

namespace MatrixBogo\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Utils
 */
final class Utils {

	/**
	 * Returns whether a rule is within its scheduled window.
	 */
	public static function is_in_schedule( ?string $start, ?string $end ): bool {
		$now = time();

		if ( $start && strtotime( $start ) > $now ) {
			return false;
		}
		if ( $end && strtotime( $end ) < $now ) {
			return false;
		}

		return true;
	}

	/**
	 * Calculates a percentage-based discount, respecting WC tax settings.
	 */
	public static function percent_of( float $price, float $percent ): float {
		return round( $price * ( $percent / 100 ), wc_get_price_decimals() );
	}

	/**
	 * Returns a human-readable list of WC product names from IDs.
	 *
	 * @param int[] $ids
	 */
	public static function product_names( array $ids ): string {
		$names = [];
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$names[] = $product->get_name();
			}
		}
		return implode( ', ', $names );
	}

	/**
	 * Returns all WC product categories as id => name array.
	 */
	public static function get_categories(): array {
		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'fields'     => 'id=>name',
		] );

		return is_array( $terms ) ? $terms : [];
	}
}
