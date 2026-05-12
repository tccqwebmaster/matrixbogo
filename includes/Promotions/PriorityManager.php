<?php
/**
 * Priority Manager – resolves conflicts between promotions.
 *
 * @package MatrixBogo\Promotions
 */

declare( strict_types=1 );

namespace MatrixBogo\Promotions;

use MatrixBogo\Abstracts\AbstractPromotion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PriorityManager
 *
 * Applies business rules:
 * - If any matched promo is exclusive, only the highest-priority one runs.
 * - Non-stackable promos stop further processing after the first match.
 * - Stackable promos accumulate.
 */
final class PriorityManager {

	/**
	 * Given a list of matched promotions (already sorted by priority ASC),
	 * returns the subset that should actually be applied.
	 *
	 * @param AbstractPromotion[] $promotions
	 * @return AbstractPromotion[]
	 */
	public function resolve( array $promotions ): array {
		if ( empty( $promotions ) ) {
			return [];
		}

		// Check if any is exclusive.
		$has_exclusive = array_filter(
			$promotions,
			static fn( AbstractPromotion $p ) => $p->is_exclusive()
		);

		if ( ! empty( $has_exclusive ) ) {
			// Only keep the first (highest priority) exclusive promo.
			$first = reset( $has_exclusive );
			return [ $first ];
		}

		// Apply stackable / non-stackable logic.
		$result = [];
		foreach ( $promotions as $promotion ) {
			if ( $promotion->is_stackable() ) {
				$result[] = $promotion;
			} else {
				// First non-stackable wins; stop processing further.
				$result[] = $promotion;
				break;
			}
		}

		/**
		 * Filters the final resolved promotion list.
		 *
		 * @param AbstractPromotion[] $result     Resolved promotions.
		 * @param AbstractPromotion[] $promotions All matched promotions before filtering.
		 */
		return apply_filters( 'matrix_bogo_resolved_promotions', $result, $promotions );
	}
}
