<?php
/**
 * Spend Amount Get Gift promotion type.
 *
 * rule_data (flat keys saved by builder):
 *   min_amount  – minimum cart subtotal
 *
 * Rewards come from the matrix_bogo_rewards table.
 *
 * @package MatrixBogo\Promotions\Types
 */

declare( strict_types=1 );

namespace MatrixBogo\Promotions\Types;

use MatrixBogo\Abstracts\AbstractPromotion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SpendAmountGetGift extends AbstractPromotion {

	public function get_type(): string {
		return 'spend_amount_get_gift';
	}

	public function get_label(): string {
		return __( 'Spend Amount Get Gift', 'matrix-bogo' );
	}

	public function is_applicable( \WC_Cart $cart ): bool {
		$min_amount = (float) $this->get_data( 'min_amount', 0 );
		if ( $min_amount <= 0 ) {
			return false;
		}
		return (float) $cart->get_subtotal() >= $min_amount;
	}

	public function calculate_rewards( \WC_Cart $cart ): array {
		return $this->db_rewards_to_descriptors();
	}
}
