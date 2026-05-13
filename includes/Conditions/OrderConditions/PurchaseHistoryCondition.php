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
			// Guests have no order history; not_in passes, in fails.
			return $this->operator === 'not_in';
		}

		$user_id     = get_current_user_id();
		$product_ids = array_map( 'intval', (array) $this->value );

		/*
		 * FIX: The old implementation always returned true when ANY product was
		 * purchased, completely ignoring the 'not_in' operator.
		 *
		 * 'in'     → pass if customer bought AT LEAST ONE of the listed products.
		 * 'not_in' → pass if customer has NOT bought ANY of the listed products.
		 */
		$bought_any = false;
		foreach ( $product_ids as $product_id ) {
			if ( wc_customer_bought_product( '', $user_id, $product_id ) ) {
				$bought_any = true;
				break;
			}
		}

		return match ( $this->operator ) {
			'in'     => $bought_any,
			'not_in' => ! $bought_any,
			default  => $bought_any,
		};
	}
}
