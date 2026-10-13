<?php
/**
 * Category Get Gift promotion type.
 *
 * rule_data (flat keys saved by builder):
 *   trigger_category_ids  – comma-separated category IDs
 *   trigger_tag_ids       – comma-separated product tag IDs (a product
 *                           qualifies if it matches ANY category OR ANY tag)
 *   trigger_variation_ids – comma-separated variation IDs that always qualify
 *   exclude_variation_ids – comma-separated variation IDs left out of a
 *                           category/tag match
 *   trigger_quantity      – quantity required per set
 *
 * An option qualifies if (its product matches a category/tag AND the option
 * is not excluded) OR the option is included — via trigger_variation_ids or
 * the per-variation "Gift promotions" setting (VariationEligibility).
 *
 * Rewards come from the matrix_bogo_rewards table.
 *
 * @package MatrixBogo\Promotions\Types
 */

declare( strict_types=1 );

namespace MatrixBogo\Promotions\Types;

use MatrixBogo\Abstracts\AbstractPromotion;
use MatrixBogo\Promotions\RuleEngine;
use MatrixBogo\Promotions\VariationEligibility;

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

	public function get_trigger(): array {
		return array_merge(
			[
				'categories' => VariationEligibility::with_translations( $this->ids_from_data( 'trigger_category_ids' ), 'product_cat' ),
				'tags'       => VariationEligibility::with_translations( $this->ids_from_data( 'trigger_tag_ids' ), 'product_tag' ),
				'quantity'   => max( 1, (int) $this->get_data( 'trigger_quantity', 1 ) ),
			],
			$this->option_trigger()
		);
	}

	public function is_applicable( \WC_Cart $cart ): bool {
		// No categories/tags/options set → the rule can still match options
		// ticked on the variation itself (rule_id), never "any product".
		$result = RuleEngine::evaluate_trigger( $cart, $this->get_trigger() );
		return $result['eligible_count'] >= 1;
	}

	public function calculate_rewards( \WC_Cart $cart ): array {
		$result = RuleEngine::evaluate_trigger( $cart, $this->get_trigger() );
		$times  = max( 0, $result['eligible_count'] );
		if ( $times <= 0 ) {
			return [];
		}
		return $this->db_rewards_to_descriptors( $times, $result['matched_items'] );
	}
}
