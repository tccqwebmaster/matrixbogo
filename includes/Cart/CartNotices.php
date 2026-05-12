<?php
/**
 * Cart Notices – displays promotion progress bars and notices.
 *
 * @package MatrixBogo\Cart
 */

declare( strict_types=1 );

namespace MatrixBogo\Cart;

use MatrixBogo\Core\Loader;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartNotices
 *
 * Injects WC notices and promotion-progress HTML into cart / checkout pages.
 */
final class CartNotices {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	public function __construct( PromotionEngine $engine ) {
		$this->engine = $engine;
	}

	public function init( Loader $loader ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );

		if ( ! empty( $settings['show_cart_notices'] ) ) {
			$loader->add_action( 'woocommerce_before_cart',        [ $this, 'render_promotion_progress' ], 5 );
			$loader->add_action( 'woocommerce_before_checkout_form', [ $this, 'render_promotion_summary' ], 5 );
		}
	}

	/**
	 * Renders a progress bar toward the next promotion threshold.
	 */
	public function render_promotion_progress(): void {
		$rewards = $this->engine->get_session_rewards();
		if ( empty( $rewards ) ) {
			return;
		}

		echo '<div class="matrix-bogo-cart-promotions">';
		foreach ( $rewards as $rule_id => $entry ) {
			$rule = $entry['rule'] ?? [];
			if ( empty( $rule ) ) {
				continue;
			}
			echo '<div class="matrix-bogo-promo-notice matrix-bogo-active">';
			echo '<span class="matrix-bogo-promo-icon">🎁</span> ';
			echo '<strong>' . esc_html( $rule['name'] ?? '' ) . '</strong>';
			echo ' – <em>' . esc_html__( 'Applied to your cart!', 'matrix-bogo' ) . '</em>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Renders a compact promotion summary above the checkout form.
	 */
	public function render_promotion_summary(): void {
		$rewards = $this->engine->get_session_rewards();
		if ( empty( $rewards ) ) {
			return;
		}

		echo '<div class="matrix-bogo-checkout-summary woocommerce-info">';
		echo '<strong>' . esc_html__( 'Active Promotions:', 'matrix-bogo' ) . '</strong><ul>';
		foreach ( $rewards as $entry ) {
			$rule = $entry['rule'] ?? [];
			echo '<li>' . esc_html( $rule['name'] ?? '' ) . '</li>';
		}
		echo '</ul></div>';
	}
}
