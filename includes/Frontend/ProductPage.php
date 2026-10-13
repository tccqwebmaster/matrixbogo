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
use MatrixBogo\Promotions\RuleEngine;
use MatrixBogo\Promotions\VariationEligibility;

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

	/** @var array<int, array<int, array<string,mixed>>> Option-level rules per product ID. */
	private array $option_rules = [];

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

		// Option-level promotions (always on): the notice follows the chosen
		// option. Products without option rules are untouched.
		$loader->add_filter( 'woocommerce_available_variation', [ $this, 'variation_promos' ], 20, 3 );
		$loader->add_action( 'woocommerce_single_variation',    [ $this, 'render_variation_container' ], 5 );
	}

	/**
	 * Adds the option's eligible promotion labels to the variation JSON the
	 * add-to-cart form receives (read by frontend.js on found_variation).
	 *
	 * @param array<string,mixed> $data
	 * @param \WC_Product         $product   Parent variable product.
	 * @param \WC_Product         $variation
	 * @return array<string,mixed>
	 */
	public function variation_promos( $data, $product, $variation ) {
		if ( ! is_array( $data ) || ! $product instanceof \WC_Product || ! $variation instanceof \WC_Product ) {
			return $data;
		}
		// Never let promotion lookups disturb the loop's globals (Porto swatches read them).
		$saved  = [ $GLOBALS['post'] ?? null, $GLOBALS['product'] ?? null ];
		$promos = [];
		foreach ( $this->option_rules_for( $product ) as $promotion ) {
			if ( null !== RuleEngine::match_line( $promotion->get_trigger(), $product->get_id(), $variation->get_id() ) ) {
				$promos[] = [
					'id'    => $promotion->get_id(),
					'label' => $this->promo_label( $promotion->get_rule() ),
				];
			}
		}
		if ( $this->option_rules_for( $product ) ) {
			$data['matrix_bogo_promos'] = $promos;
		}
		[ $GLOBALS['post'], $GLOBALS['product'] ] = $saved;
		return $data;
	}

	/**
	 * Empty notice container inside the variation form; filled by JS.
	 */
	public function render_variation_container(): void {
		global $product;
		if ( ! $product instanceof \WC_Product || ! $this->option_rules_for( $product ) ) {
			return;
		}
		echo '<div class="matrix-bogo-product-promotions matrix-bogo-variation-promos" aria-live="polite" hidden></div>';
	}

	/**
	 * Active promotions that set something per option on this variable
	 * product (an included / excluded variation, or a variation ticked in
	 * the editor). Only those switch the notice per option.
	 *
	 * @return array<int, \MatrixBogo\Abstracts\AbstractPromotion>
	 */
	private function option_rules_for( \WC_Product $product ): array {
		$pid = $product->get_id();
		if ( isset( $this->option_rules[ $pid ] ) ) {
			return $this->option_rules[ $pid ];
		}
		$out      = [];
		$children = $product->is_type( 'variable' ) ? array_map( 'intval', $product->get_children() ) : [];
		if ( $children ) {
			foreach ( $this->rules->get_active_rules() as $rule ) {
				$promotion = $this->engine->make_promotion( $rule );
				$trigger   = $promotion ? $promotion->get_trigger() : [];
				if ( empty( $trigger ) ) {
					continue;
				}
				$listed = array_merge( (array) ( $trigger['variations'] ?? [] ), (array) ( $trigger['exclude_variations'] ?? [] ) );
				foreach ( $children as $vid ) {
					if ( in_array( $vid, $listed, true ) || in_array( $promotion->get_id(), VariationEligibility::rule_ids_for( $vid ), true ) ) {
						$out[] = $promotion;
						break;
					}
				}
			}
		}
		return $this->option_rules[ $pid ] = $out;
	}

	/**
	 * Customer-facing promotion label: the Arabic label on the Arabic side
	 * of the store (WPML / locale) when set, else the promotion name.
	 *
	 * @param array<string,mixed> $rule
	 */
	private function promo_label( array $rule ): string {
		$data     = ! empty( $rule['rule_data'] ) ? (array) json_decode( (string) $rule['rule_data'], true ) : [];
		$label_ar = sanitize_text_field( (string) ( $data['label_ar'] ?? '' ) );
		$lang     = (string) apply_filters( 'wpml_current_language', null );
		if ( '' === $lang ) {
			$lang = substr( determine_locale(), 0, 2 );
		}
		return ( 'ar' === $lang && '' !== $label_ar ) ? $label_ar : (string) ( $rule['name'] ?? '' );
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
