<?php
/**
 * Category Get Gift promotion type.
 *
 * rule_data (flat keys saved by builder):
 *   trigger_category_ids  – comma-separated category IDs
 *   trigger_tag_ids       – comma-separated product tag IDs (a product
 *                           qualifies if it matches ANY category OR ANY tag)
 *   trigger_quantity      – quantity required per set
 *
 * Rewards come from the matrix_bogo_rewards table.
 *
 * @package MatrixBogo\Promotions\Types
 */

declare( strict_types=1 );

namespace MatrixBogo\Promotions\Types;

use MatrixBogo\Abstracts\AbstractPromotion;
use MatrixBogo\Promotions\RuleEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CategoryGetGift extends AbstractPromotion {

	public function get_type(): string {
		return 'category_get_gift';
	}

	public function get_label(): string {
		return __( 'Buy Category Get Gift', 'matrix-bogo' );
	}

	// -----------------------------------------------------------------

	private function build_trigger(): array {
		return [
			'categories' => $this->with_translations( $this->ids_from_data( 'trigger_category_ids' ), 'product_cat' ),
			'tags'       => $this->with_translations( $this->ids_from_data( 'trigger_tag_ids' ), 'product_tag' ),
			'quantity'   => max( 1, (int) $this->get_data( 'trigger_quantity', 1 ) ),
		];
	}

	/**
	 * Adds each term's WPML translations so a promotion set up with the
	 * English term also matches cart lines in other languages (WPML gives
	 * every translation its own term ID). No-op without WPML.
	 *
	 * @param int[]  $ids
	 * @param string $taxonomy
	 * @return int[]
	 */
	private function with_translations( array $ids, string $taxonomy ): array {
		if ( empty( $ids ) || ! has_filter( 'wpml_object_id' ) ) {
			return $ids;
		}
		$languages = (array) apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
		$out       = $ids;
		foreach ( $ids as $id ) {
			foreach ( array_keys( $languages ) as $lang ) {
				$tid = (int) apply_filters( 'wpml_object_id', $id, $taxonomy, false, $lang );
				if ( $tid > 0 ) {
					$out[] = $tid;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	public function is_applicable( \WC_Cart $cart ): bool {
		$trigger = $this->build_trigger();
		if ( empty( $trigger['categories'] ) && empty( $trigger['tags'] ) ) {
			return false;
		}
		$result = RuleEngine::evaluate_trigger( $cart, $trigger );
		return $result['eligible_count'] >= 1;
	}

	public function calculate_rewards( \WC_Cart $cart ): array {
		$trigger = $this->build_trigger();
		$result  = RuleEngine::evaluate_trigger( $cart, $trigger );
		$times   = max( 0, $result['eligible_count'] );
		if ( $times <= 0 ) {
			return [];
		}
		return $this->db_rewards_to_descriptors( $times );
	}
}
