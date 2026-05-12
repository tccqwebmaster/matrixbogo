<?php
/**
 * Gift Validator – validates gift items before and during cart operations.
 *
 * @package MatrixBogo\Gifts
 */

declare( strict_types=1 );

namespace MatrixBogo\Gifts;

use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GiftValidator
 */
final class GiftValidator {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	public function __construct( PromotionEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Validates that a proposed gift product is in the rule's choice pool.
	 *
	 * @param int $rule_id
	 * @param int $product_id
	 * @return bool
	 */
	public function is_product_in_pool( int $rule_id, int $product_id ): bool {
		$rewards_map = $this->engine->get_session_rewards();
		$entry       = $rewards_map[ $rule_id ] ?? null;

		if ( ! $entry ) {
			return false;
		}

		foreach ( $entry['rewards'] as $reward ) {
			if ( empty( $reward['customer_choice'] ) ) {
				continue;
			}
			$pool = array_map( 'intval', (array) ( $reward['choice_pool'] ?? [] ) );
			if ( empty( $pool ) || in_array( $product_id, $pool, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verifies a product is in stock and purchasable.
	 *
	 * @param int $product_id
	 * @return \WP_Error|true
	 */
	public function validate_product( int $product_id ): \WP_Error|bool {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new \WP_Error( 'invalid_product', __( 'Product not found.', 'matrix-bogo' ) );
		}
		if ( ! $product->is_purchasable() ) {
			return new \WP_Error( 'not_purchasable', __( 'Gift product is not purchasable.', 'matrix-bogo' ) );
		}
		if ( ! $product->is_in_stock() ) {
			return new \WP_Error( 'out_of_stock', __( 'Gift product is out of stock.', 'matrix-bogo' ) );
		}

		return true;
	}
}
