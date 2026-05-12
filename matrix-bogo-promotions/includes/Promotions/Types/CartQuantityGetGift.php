<?php
/**
 * Cart Quantity Get Gift promotion type.
 *
 * rule_data (flat keys saved by builder):
 *   min_quantity  – minimum cart item count
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

final class CartQuantityGetGift extends AbstractPromotion {

	public function get_type(): string {
		return 'cart_quantity_get_gift';
	}

	public function get_label(): string {
		return __( 'Cart Quantity Get Gift', 'matrix-bogo' );
	}

	public function is_applicable( \WC_Cart $cart ): bool {
		$min_quantity = (int) $this->get_data( 'min_quantity', 1 );
		if ( $min_quantity <= 0 ) {
			return false;
		}
		return (int) $cart->get_cart_contents_count() >= $min_quantity;
	}

	public function calculate_rewards( \WC_Cart $cart ): array {
		return $this->db_rewards_to_descriptors();
	}
}
