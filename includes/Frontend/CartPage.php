<?php
/**
 * Cart Page – renders promotion notices and progress on cart.
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
 * Class CartPage
 */
final class CartPage {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	public function __construct( PromotionEngine $engine ) {
		$this->engine = $engine;
	}

	public function init( Loader $loader ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );

		if ( ! empty( $settings['show_progress_bar'] ) ) {
			$loader->add_action( 'woocommerce_before_cart', [ $this, 'render_progress' ] );
		}

		if ( ! empty( $settings['show_notices'] ) ) {
			$loader->add_action( 'woocommerce_before_cart_table', [ $this, 'render_applied_promotions' ] );
		}

		// Always render gift section.
		$loader->add_action( 'woocommerce_cart_collaterals', [ $this, 'render_gift_section' ] );
	}

	/**
	 * Renders a progress bar toward the next promotion threshold.
	 */
	public function render_progress(): void {
		$rewards_map = $this->engine->get_session_rewards();

		if ( empty( $rewards_map ) ) {
			return;
		}

		wc_get_template(
			'cart/promotion-summary.php',
			[ 'rewards_map' => $rewards_map ],
			'matrix-bogo/',
			MATRIX_BOGO_PLUGIN_DIR . 'templates/'
		);
	}

	/**
	 * Shows a notice listing which promotions are currently applied.
	 */
	public function render_applied_promotions(): void {
		$rewards_map = $this->engine->get_session_rewards();

		if ( empty( $rewards_map ) ) {
			return;
		}

		foreach ( $rewards_map as $entry ) {
			$label = esc_html( $entry['promotion_label'] ?? '' );
			if ( $label ) {
				wc_print_notice(
					sprintf(
						/* translators: %s: promotion name */
						__( '🎁 Promotion applied: %s', 'matrix-bogo' ),
						$label
					),
					'success'
				);
			}
		}
	}

	/**
	 * Renders the gift product selection section on cart.
	 */
	public function render_gift_section(): void {
		$rewards_map = $this->engine->get_session_rewards();
		$has_choice  = false;

		foreach ( $rewards_map as $entry ) {
			foreach ( $entry['rewards'] ?? [] as $reward ) {
				if ( ! empty( $reward['customer_choice'] ) ) {
					$has_choice = true;
					break 2;
				}
			}
		}

		if ( ! $has_choice ) {
			return;
		}

		wc_get_template(
			'cart/gift-section.php',
			[ 'rewards_map' => $rewards_map ],
			'matrix-bogo/',
			MATRIX_BOGO_PLUGIN_DIR . 'templates/'
		);
	}
}
