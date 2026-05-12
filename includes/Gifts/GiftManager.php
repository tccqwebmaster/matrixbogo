<?php
/**
 * Gift Manager – orchestrates the customer gift selection popup.
 *
 * @package MatrixBogo\Gifts
 */

declare( strict_types=1 );

namespace MatrixBogo\Gifts;

use MatrixBogo\Cart\CartModifier;
use MatrixBogo\Core\Loader;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GiftManager
 *
 * Coordinates:
 * - Detecting when the customer is eligible to choose a free gift.
 * - Rendering the gift popup template.
 * - AJAX product search for the popup.
 * - Auto-adding gifts that don't require customer choice.
 */
final class GiftManager {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	/** @var CartModifier */
	private CartModifier $modifier;

	/** @var GiftPopup */
	private GiftPopup $popup;

	/** @var AutoGift */
	private AutoGift $auto_gift;

	/** @var GiftValidator */
	private GiftValidator $validator;

	public function __construct( PromotionEngine $engine ) {
		$this->engine    = $engine;
		$this->modifier  = new CartModifier();
		$this->popup     = new GiftPopup( $engine );
		$this->auto_gift = new AutoGift( $engine, $this->modifier );
		$this->validator = new GiftValidator( $engine );
	}

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------

	public function init( Loader $loader ): void {
		// Render popup container on cart and checkout.
		$loader->add_action( 'wp_footer',                              [ $this->popup, 'render_popup_container' ] );

		// Auto-add non-choice gifts.
		$loader->add_action( 'woocommerce_after_calculate_totals',     [ $this->auto_gift, 'auto_add_gifts' ], 20 );

		// AJAX: product search for popup.
		$loader->add_action( 'wp_ajax_matrix_bogo_search_gifts',       [ $this, 'ajax_search_gifts' ] );
		$loader->add_action( 'wp_ajax_nopriv_matrix_bogo_search_gifts', [ $this, 'ajax_search_gifts' ] );

		// AJAX: check eligibility.
		$loader->add_action( 'wp_ajax_matrix_bogo_check_eligibility',        [ $this, 'ajax_check_eligibility' ] );
		$loader->add_action( 'wp_ajax_nopriv_matrix_bogo_check_eligibility', [ $this, 'ajax_check_eligibility' ] );
	}

	// -----------------------------------------------------------------
	// AJAX: search gift products
	// -----------------------------------------------------------------

	/**
	 * Returns product data for the gift selector popup.
	 * Filters by the rule's choice pool.
	 */
	public function ajax_search_gifts(): void {
		check_ajax_referer( 'matrix_bogo_frontend', 'nonce' );

		$rule_id    = absint( wp_unslash( $_POST['rule_id'] ?? 0 ) );
		$search     = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$page       = max( 1, absint( wp_unslash( $_POST['page'] ?? 1 ) ) );
		$per_page   = 12;

		if ( ! $rule_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid rule.', 'matrix-bogo' ) ] );
		}

		// Find the reward's choice pool for this rule.
		$rewards_map = $this->engine->get_session_rewards();
		$pool        = [];
		foreach ( ( $rewards_map[ $rule_id ]['rewards'] ?? [] ) as $reward ) {
			if ( ! empty( $reward['customer_choice'] ) && ! empty( $reward['choice_pool'] ) ) {
				$pool = array_map( 'intval', $reward['choice_pool'] );
				break;
			}
		}

		$products = $this->get_gift_products( $pool, $search, $per_page, ( $page - 1 ) * $per_page );

		wp_send_json_success( [
			'products' => $products,
			'page'     => $page,
		] );
	}

	// -----------------------------------------------------------------
	// AJAX: eligibility check
	// -----------------------------------------------------------------

	/**
	 * Checks if the cart currently qualifies for any customer-choice gift.
	 */
	public function ajax_check_eligibility(): void {
		check_ajax_referer( 'matrix_bogo_frontend', 'nonce' );

		$rewards_map = $this->engine->evaluate_cart();
		$eligible    = [];

		foreach ( $rewards_map as $rule_id => $entry ) {
			foreach ( $entry['rewards'] ?? [] as $reward ) {
				if ( ! empty( $reward['customer_choice'] ) ) {
					$eligible[] = [
						'rule_id'    => $rule_id,
						'rule_name'  => $entry['rule']['name'] ?? '',
						'quantity'   => $reward['quantity'],
						'choice_pool' => $reward['choice_pool'] ?? [],
					];
				}
			}
		}

		wp_send_json_success( [ 'eligible' => $eligible ] );
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * Fetches gift products from a pool (or all products if pool is empty).
	 *
	 * @param int[]  $pool       Product IDs to filter by.
	 * @param string $search     Optional search term.
	 * @param int    $per_page
	 * @param int    $offset
	 * @return array<int, array<string,mixed>>
	 */
	private function get_gift_products(
		array $pool,
		string $search,
		int $per_page,
		int $offset
	): array {
		$args = [
			'post_type'      => [ 'product', 'product_variation' ],
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_stock_status',
					'value'   => 'instock',
					'compare' => '=',
				],
			],
		];

		if ( ! empty( $pool ) ) {
			$args['post__in'] = $pool;
		}

		if ( $search ) {
			$args['s'] = $search;
		}

		$ids     = get_posts( $args );
		$results = [];

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}

			$results[] = [
				'id'            => $id,
				'name'          => $product->get_name(),
				'price'         => wc_price( $product->get_price() ),
				'thumbnail'     => get_the_post_thumbnail_url( $id, 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src(),
				'is_variable'   => $product->is_type( 'variable' ),
				'in_stock'      => $product->is_in_stock(),
				'permalink'     => get_permalink( $id ),
			];
		}

		return $results;
	}
}
