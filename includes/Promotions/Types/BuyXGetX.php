<?php
/**
 * Buy X Get X (same product) promotion type.
 *
 * rule_data (flat keys saved by builder):
 *   trigger_product_ids  – comma-separated product IDs
 *   trigger_quantity     – quantity required to trigger one set
 *
 * Rewards come from the matrix_bogo_rewards table. If no reward is configured,
 * the trigger products themselves are given as free gifts.
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

final class BuyXGetX extends AbstractPromotion {

	public function get_type(): string {
		return 'buy_x_get_x';
	}

	public function get_label(): string {
		return __( 'Buy X Get X (Same Product)', 'matrix-bogo' );
	}

	// -----------------------------------------------------------------

	private function build_trigger(): array {
		return [
			'products' => $this->ids_from_data( 'trigger_product_ids' ),
			'quantity' => max( 1, (int) $this->get_data( 'trigger_quantity', 1 ) ),
		];
	}

	public function is_applicable( \WC_Cart $cart ): bool {
		$trigger = $this->build_trigger();
		if ( empty( $trigger['products'] ) ) {
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

		// Use DB-configured rewards when available.
		$descs = $this->db_rewards_to_descriptors( $times );
		if ( ! empty( $descs ) ) {
			return $descs;
		}

		// Fallback: give one of each trigger product free per set.
		$rewards = [];
		foreach ( $trigger['products'] as $pid ) {
			$rewards[] = [
				'rule_id'         => $this->get_id(),
				'product_id'      => $pid,
				'variation_id'    => 0,
				'quantity'        => $times,
				'discount_type'   => 'free',
				'discount_value'  => 0.0,
				'customer_choice' => false,
				'choice_pool'     => [],
				'label'           => $this->get_name(),
			];
		}
		return $rewards;
	}
}
