<?php
/**
 * Cart Engine – applies promotions and discounts to the WooCommerce cart.
 *
 * @package MatrixBogo\Cart
 */

declare( strict_types=1 );

namespace MatrixBogo\Cart;

use MatrixBogo\Core\Loader;
use MatrixBogo\Database\Repositories\RedemptionsRepository;
use MatrixBogo\Database\Repositories\RulesRepository;
use MatrixBogo\Helpers\Logger;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartEngine
 *
 * Hooks into the WooCommerce cart lifecycle to:
 * 1. Inject free/discounted gift products.
 * 2. Apply price adjustments via woocommerce_cart_item_price.
 * 3. Record redemptions on checkout.
 * 4. Remove gifts when conditions are no longer met.
 */
final class CartEngine {

	/** @var PromotionEngine */
	private PromotionEngine $promotion_engine;

	/** @var CartModifier */
	private CartModifier $modifier;

	/** @var CartValidator */
	private CartValidator $validator;

	/** @var CartNotices */
	private CartNotices $notices;

	/** @var RedemptionsRepository */
	private RedemptionsRepository $redemptions;

	/** @var RulesRepository */
	private RulesRepository $rules_repo;

	public function __construct( PromotionEngine $engine ) {
		$this->promotion_engine = $engine;
		$this->modifier         = new CartModifier();
		$this->validator        = new CartValidator( $engine );
		$this->notices          = new CartNotices( $engine );
		$this->redemptions      = new RedemptionsRepository();
		$this->rules_repo       = new RulesRepository();
	}

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------

	public function init( Loader $loader ): void {
		// Apply discounts during cart total calculation.
		$loader->add_action( 'woocommerce_cart_calculate_fees',         [ $this, 'apply_promotions' ], 20 );

		// Validate gifts on add-to-cart.
		$loader->add_filter( 'woocommerce_add_to_cart_validation',      [ $this->validator, 'validate_add' ], 10, 3 );

		// Ensure gift price is zero in display.
		$loader->add_filter( 'woocommerce_cart_item_price',             [ $this, 'filter_gift_price' ], 10, 3 );
		$loader->add_filter( 'woocommerce_cart_item_subtotal',          [ $this, 'filter_gift_subtotal' ], 10, 3 );

		// Remove gifts when conditions fail.
		$loader->add_action( 'woocommerce_before_calculate_totals',     [ $this, 'remove_invalid_gifts' ], 10 );

		// Record redemptions after checkout.
		$loader->add_action( 'woocommerce_checkout_order_created',      [ $this, 'record_redemptions' ], 10 );

		// AJAX handlers.
		$loader->add_action( 'wp_ajax_matrix_bogo_apply_gift',          [ $this, 'ajax_apply_gift' ] );
		$loader->add_action( 'wp_ajax_nopriv_matrix_bogo_apply_gift',   [ $this, 'ajax_apply_gift' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_remove_gift',         [ $this, 'ajax_remove_gift' ] );
		$loader->add_action( 'wp_ajax_nopriv_matrix_bogo_remove_gift',  [ $this, 'ajax_remove_gift' ] );

		// Frontend notices.
		$this->notices->init( $loader );
	}

	// -----------------------------------------------------------------
	// Promotion application
	// -----------------------------------------------------------------

	/**
	 * Evaluates promotions and adds WC fees (negative = discount) or free items.
	 *
	 * @param \WC_Cart $cart
	 */
	public function apply_promotions( \WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$rewards_map = $this->promotion_engine->get_session_rewards();

		if ( empty( $rewards_map ) ) {
			return;
		}

		foreach ( $rewards_map as $rule_id => $entry ) {
			$rewards = $entry['rewards'] ?? [];
			foreach ( $rewards as $reward ) {
				$this->modifier->apply_reward( $cart, $reward );
			}
		}
	}

	// -----------------------------------------------------------------
	// Gift price filters
	// -----------------------------------------------------------------

	/**
	 * Displays gift items as "FREE" in cart.
	 *
	 * @param string              $price     Formatted price HTML.
	 * @param array<string,mixed> $cart_item
	 * @param string              $cart_key
	 * @return string
	 */
	public function filter_gift_price( string $price, array $cart_item, string $cart_key ): string {
		if ( ! empty( $cart_item['matrix_bogo_gift'] ) && empty( $cart_item['matrix_bogo_partial_discount'] ) ) {
			return '<span class="matrix-bogo-free-label">' . esc_html__( 'FREE', 'matrix-bogo' ) . '</span>';
		}
		return $price;
	}

	/**
	 * Zeros out subtotal for fully-free gifts.
	 *
	 * @param string              $subtotal
	 * @param array<string,mixed> $cart_item
	 * @param string              $cart_key
	 */
	public function filter_gift_subtotal( string $subtotal, array $cart_item, string $cart_key ): string {
		if ( ! empty( $cart_item['matrix_bogo_gift'] ) && empty( $cart_item['matrix_bogo_partial_discount'] ) ) {
			return wc_price( 0 );
		}
		return $subtotal;
	}

	// -----------------------------------------------------------------
	// Gift removal
	// -----------------------------------------------------------------

	/**
	 * Removes gift items whose triggering promotion is no longer applicable.
	 *
	 * @param \WC_Cart $cart
	 */
	public function remove_invalid_gifts( \WC_Cart $cart ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );
		if ( empty( $settings['gift_auto_remove'] ) ) {
			return;
		}

		$valid_rule_ids = array_keys( $this->promotion_engine->get_session_rewards() );

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item['matrix_bogo_gift'] ) ) {
				continue;
			}

			$rule_id = (int) ( $item['matrix_bogo_rule_id'] ?? 0 );
			if ( ! in_array( $rule_id, $valid_rule_ids, true ) ) {
				$cart->remove_cart_item( $key );

				do_action( 'matrix_bogo_gift_removed', $rule_id, $key, $item );

				wc_add_notice(
					sprintf(
						/* translators: %s: product name */
						__( '"%s" was removed as the promotion no longer applies.', 'matrix-bogo' ),
						esc_html( $item['data']->get_name() )
					),
					'notice'
				);
			}
		}
	}

	// -----------------------------------------------------------------
	// Redemption recording
	// -----------------------------------------------------------------

	/**
	 * Records all redemptions when the order is created.
	 *
	 * @param \WC_Order $order
	 */
	public function record_redemptions( \WC_Order $order ): void {
		$rewards_map = (array) ( WC()->session ? WC()->session->get( 'matrix_bogo_rewards' ) : [] );

		foreach ( $rewards_map as $rule_id => $entry ) {
			$discount   = 0.0;
			$gifts_data = [];

			foreach ( $entry['rewards'] ?? [] as $reward ) {
				$discount    += (float) ( $reward['total_discount'] ?? 0 );
				$gifts_data[] = $reward;
			}

			$this->redemptions->record(
				(int) $rule_id,
				(int) $order->get_id(),
				(int) $order->get_user_id(),
				$discount,
				$gifts_data
			);

			$this->rules_repo->increment_uses( (int) $rule_id );

			do_action( 'matrix_bogo_after_redemption_recorded', (int) $rule_id, $order );
		}

		// Clear session.
		if ( WC()->session ) {
			WC()->session->set( 'matrix_bogo_rewards', null );
		}
	}

	// -----------------------------------------------------------------
	// AJAX handlers
	// -----------------------------------------------------------------

	/**
	 * AJAX: customer selects and applies a free gift.
	 */
	public function ajax_apply_gift(): void {
		check_ajax_referer( 'matrix_bogo_frontend', 'nonce' );

		$rule_id    = absint( wp_unslash( $_POST['rule_id']    ?? 0 ) );
		$product_id = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
		$qty        = max( 1, absint( wp_unslash( $_POST['quantity'] ?? 1 ) ) );

		if ( ! $rule_id || ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'matrix-bogo' ) ] );
		}

		$result = $this->modifier->add_gift_to_cart( $product_id, $qty, $rule_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		do_action( 'matrix_bogo_after_gift_added', $rule_id, $product_id, $qty );

		wp_send_json_success( [
			'message'       => __( 'Free gift added to cart!', 'matrix-bogo' ),
			'cart_hash'     => WC()->cart->get_cart_hash(),
			'cart_count'    => WC()->cart->get_cart_contents_count(),
		] );
	}

	/**
	 * AJAX: removes a specific gift from the cart.
	 */
	public function ajax_remove_gift(): void {
		check_ajax_referer( 'matrix_bogo_frontend', 'nonce' );

		$cart_key = sanitize_text_field( wp_unslash( $_POST['cart_key'] ?? '' ) );

		if ( ! $cart_key ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'matrix-bogo' ) ] );
		}

		WC()->cart->remove_cart_item( $cart_key );

		wp_send_json_success( [
			'message'    => __( 'Gift removed.', 'matrix-bogo' ),
			'cart_hash'  => WC()->cart->get_cart_hash(),
		] );
	}
}
