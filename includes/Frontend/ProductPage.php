<?php
/**
 * Product Page – injects promotion badges and notices on product pages.
 *
 * @package MatrixBogo\Frontend
 */

declare( strict_types=1 );

namespace MatrixBogo\Frontend;

use MatrixBogo\Core\Loader;
use MatrixBogo\Database\Repositories\RulesRepository;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProductPage
 */
final class ProductPage {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	/** @var RulesRepository */
	private RulesRepository $rules;

	public function __construct( PromotionEngine $engine ) {
		$this->engine = $engine;
		$this->rules  = new RulesRepository();
	}

	public function init( Loader $loader ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );

		if ( ! empty( $settings['show_product_badges'] ) ) {
			$loader->add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'render_badge' ], 12 );
			$loader->add_action( 'woocommerce_single_product_summary', [ $this, 'render_single_product_notice' ], 25 );
		}
	}

	/**
	 * Renders a promotional badge on product thumbnails (loop).
	 */
	public function render_badge(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! $this->product_has_active_promotion( $product->get_id() ) ) {
			return;
		}

		$settings   = get_option( 'matrix_bogo_settings', [] );
		$badge_text = ! empty( $settings['gift_badge_text'] ) ? $settings['gift_badge_text'] : __( 'FREE GIFT', 'matrix-bogo' );

		echo '<span class="matrix-bogo-badge">' . esc_html( $badge_text ) . '</span>';
	}

	/**
	 * Renders a promotion notice on the single product page.
	 */
	public function render_single_product_notice(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$promotions = $this->get_applicable_promotions( $product->get_id() );

		if ( empty( $promotions ) ) {
			return;
		}

		wc_get_template(
			'product/promotion-badge.php',
			[ 'promotions' => $promotions ],
			'matrix-bogo/',
			MATRIX_BOGO_PLUGIN_DIR . 'templates/'
		);
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	private function product_has_active_promotion( int $product_id ): bool {
		return ! empty( $this->get_applicable_promotions( $product_id ) );
	}

	private function get_applicable_promotions( int $product_id ): array {
		$cache_key = 'product_promos_' . $product_id;
		$cached    = wp_cache_get( $cache_key, 'matrix_bogo' );

		if ( false !== $cached ) {
			return $cached;
		}

		$rules  = $this->rules->get_active_rules();
		$result = [];

		foreach ( $rules as $rule ) {
			$rule_data = ! empty( $rule['rule_data'] ) ? json_decode( $rule['rule_data'], true ) : [];
			$products  = array_map( 'intval', (array) ( $rule_data['trigger_products'] ?? [] ) );

			if ( empty( $products ) || in_array( $product_id, $products, true ) ) {
				$result[] = $rule;
			}
		}

		wp_cache_set( $cache_key, $result, 'matrix_bogo', 60 );
		return $result;
	}
}
