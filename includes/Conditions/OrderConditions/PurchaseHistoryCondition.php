<?php
/**
 * Purchase History condition.
 *
 * @package MatrixBogo\Conditions\OrderConditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions\OrderConditions;

use MatrixBogo\Abstracts\AbstractCondition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PurchaseHistoryCondition extends AbstractCondition {

	public function get_type(): string {
		return 'purchase_history';
	}

	public function get_label(): string {
		return __( 'Previously Purchased Product', 'matrix-bogo' );
	}

	public function evaluate( array $context = [] ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id      = get_current_user_id();
		$product_ids  = array_map( 'intval', (array) $this->value );

		foreach ( $product_ids as $product_id ) {
			if ( wc_customer_bought_product( '', $user_id, $product_id ) ) {
				return true;
			}
		}

		return false;
	}
}
