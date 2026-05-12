<?php
/**
 * Category Get Gift promotion type.
 *
 * rule_data (flat keys saved by builder):
 *   trigger_category_ids  – comma-separated category IDs
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
			'categories' => $this->ids_from_data( 'trigger_category_ids' ),
			'quantity'   => max( 1, (int) $this->get_data( 'trigger_quantity', 1 ) ),
		];
	}

	public function is_applicable( \WC_Cart $cart ): bool {
		$trigger = $this->build_trigger();
		if ( empty( $trigger['categories'] ) ) {
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
