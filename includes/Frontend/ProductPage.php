<?php
/**
 * Product Page – injects promotion badges and notices on product pages.
 *
 * @package MatrixBogo\Frontend
 */

declare( strict_types=1 );

namespace MatrixBogo\Frontend;

use MatrixBogo\Core\Loader;
use MatrixBogo\Database\Repositories\RewardsRepository;
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

		if ( ! $this->product_has_active_promotion( $product ) ) {
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

		$promotions = $this->get_applicable_promotions( $product );

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

	private function product_has_active_promotion( \WC_Product $product ): bool {
		return ! empty( $this->get_applicable_promotions( $product ) );
	}

	/**
	 * Active promotions that give a FREE PRODUCT and concern this product:
	 * it is one of the gift products, or it (or one of its options) matches
	 * the promotion's trigger. Discount rewards (fixed / percent / cheapest)
	 * never earn the gift badge, and a promotion with no products,
	 * categories, tags or options set badges nothing — it used to badge
	 * every product (the old code read a non-existent 'trigger_products' key).
	 *
	 * @return array<int, array<string,mixed>> Rule rows.
	 */
	private function get_applicable_promotions( \WC_Product $product ): array {
		$pid       = $product->get_id();
		$cache_key = 'product_promos_' . $pid;
		$cached    = wp_cache_get( $cache_key, 'matrix_bogo' );

		if ( false !== $cached ) {
			return $cached;
		}

		$rewards_repo = new RewardsRepository();
		$children     = $product->is_type( 'variable' ) ? array_map( 'intval', $product->get_children() ) : [];
		$orig         = VariationEligibility::original_id( $pid, 'product' );
		$ids          = array_filter( [ $pid, $orig ] );
		$result       = [];

		// Keep the loop's globals intact (see variation_promos()).
		$saved = [ $GLOBALS['post'] ?? null, $GLOBALS['product'] ?? null ];

		foreach ( $this->rules->get_active_rules() as $rule ) {
			$gift_ids = [];
			foreach ( $rewards_repo->get_for_rule( (int) $rule['id'] ) as $reward ) {
				if ( 'free_product' !== (string) ( $reward['reward_type'] ?? 'free_product' ) ) {
					continue;
				}
				$gift_ids[] = (int) ( $reward['product_id'] ?? 0 );
				$pool       = $reward['choice_pool'] ?? '';
				$pool       = is_array( $pool ) ? $pool : ( json_decode( (string) $pool, true ) ?? explode( ',', (string) $pool ) );
				$gift_ids   = array_merge( $gift_ids, array_map( 'intval', (array) $pool ) );
			}
			if ( empty( $gift_ids ) ) {
				continue; // No free-product reward → no gift badge.
			}

			// The product is a gift itself.
			if ( array_intersect( $ids, array_filter( $gift_ids ) ) ) {
				$result[] = $rule;
				continue;
			}

			// The product (or one of its options) triggers the promotion.
			$promotion = $this->engine->make_promotion( $rule );
			$trigger   = $promotion ? $promotion->get_trigger() : [];
			if ( empty( $trigger ) ) {
				continue;
			}
			$matches = null !== RuleEngine::match_line( $trigger, $pid, 0 );
			foreach ( $children as $vid ) {
				if ( $matches ) {
					break;
				}
				$matches = null !== RuleEngine::match_line( $trigger, $pid, $vid );
			}
			if ( $matches ) {
				$result[] = $rule;
			}
		}

		[ $GLOBALS['post'], $GLOBALS['product'] ] = $saved;

		wp_cache_set( $cache_key, $result, 'matrix_bogo', 60 );
		return $result;
	}
}
