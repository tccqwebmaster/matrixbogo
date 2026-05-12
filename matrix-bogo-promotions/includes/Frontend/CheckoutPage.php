<?php
/**
 * Checkout Page – displays promotion summary on checkout.
 *
 * @package MatrixBogo\Frontend
 */

declare( strict_types=1 );

namespace MatrixBogo\Frontend;

use MatrixBogo\Core\Loader;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CheckoutPage
 */
final class CheckoutPage {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	public function __construct( PromotionEngine $engine ) {
		$this->engine = $engine;
	}

	public function init( Loader $loader ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );

		if ( ! empty( $settings['show_notices'] ) ) {
			$loader->add_action( 'woocommerce_checkout_before_order_review', [ $this, 'render_applied_promotions' ] );
		}
	}

	public function render_applied_promotions(): void {
		$rewards_map = $this->engine->get_session_rewards();

		if ( empty( $rewards_map ) ) {
			return;
		}

		echo '<div class="matrix-bogo-checkout-promotions">';
		echo '<h3>' . esc_html__( 'Applied Promotions', 'matrix-bogo' ) . '</h3><ul>';
		foreach ( $rewards_map as $entry ) {
			$label = $entry['promotion_label'] ?? '';
			if ( $label ) {
				echo '<li>' . esc_html( $label ) . '</li>';
			}
		}
		echo '</ul></div>';
	}
}
