<?php
/**
 * Buy X Get Y promotion type.
 *
 * rule_data (flat keys saved by builder):
 *   trigger_product_ids  – comma-separated product IDs
 *   trigger_variation_ids – comma-separated variation IDs that always qualify
 *   exclude_variation_ids – comma-separated variation IDs of trigger products left out
 *   trigger_quantity     – quantity of trigger products required per set
 *
 * Rewards come from the matrix_bogo_rewards table (injected via set_db_rewards).
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

final class BuyXGetY extends AbstractPromotion {

	public function get_type(): string {
		return 'buy_x_get_y';
	}

	public function get_label(): string {
		return __( 'Buy X Get Y', 'matrix-bogo' );
	}

	// -----------------------------------------------------------------

	public function get_trigger(): array {
		return array_merge(
			[
				'products' => $this->ids_from_data( 'trigger_product_ids' ),
				'quantity' => max( 1, (int) $this->get_data( 'trigger_quantity', 1 ) ),
			],
			$this->option_trigger()
		);
	}

	public function is_applicable( \WC_Cart $cart ): bool {
		// No products/options configured → nothing matches: RuleEngine never
		// falls back to "any product" when a rule_id is in the trigger.
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
