<?php
/**
 * Promotion Engine – the core evaluation hub.
 *
 * @package MatrixBogo\Promotions
 */

declare( strict_types=1 );

namespace MatrixBogo\Promotions;

use MatrixBogo\Abstracts\AbstractPromotion;
use MatrixBogo\Conditions\ConditionEngine;
use MatrixBogo\Core\Loader;
use MatrixBogo\Database\Repositories\RulesRepository;
use MatrixBogo\Database\Repositories\RewardsRepository;
use MatrixBogo\Database\Repositories\RedemptionsRepository;
use MatrixBogo\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PromotionEngine
 */
final class PromotionEngine {

	/** @var RulesRepository */
	private RulesRepository $rules_repo;

	/** @var RedemptionsRepository */
	private RedemptionsRepository $redemptions_repo;

	/** @var RewardsRepository */
	private RewardsRepository $rewards_repo;

	/** @var ConditionEngine */
	private ConditionEngine $condition_engine;

	/** @var RuleEngine */
	private RuleEngine $rule_engine;

	/** @var PriorityManager */
	private PriorityManager $priority_manager;

	/** @var array<string, string> [type => class] */
	private array $type_registry = [];

	public function __construct() {
		$this->rules_repo       = new RulesRepository();
		$this->redemptions_repo = new RedemptionsRepository();
		$this->rewards_repo     = new RewardsRepository();
		$this->condition_engine = new ConditionEngine();
		$this->rule_engine      = new RuleEngine();
		$this->priority_manager = new PriorityManager();

		$this->register_default_types();
	}

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------

	public function init( Loader $loader ): void {
		$loader->add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'evaluate_cart' ], 15 );
		$loader->add_action( 'woocommerce_after_calculate_totals',   [ $this, 'evaluate_cart' ], 15 );
	}

	// -----------------------------------------------------------------
	// Type registry
	// -----------------------------------------------------------------

	private function register_default_types(): void {
		$this->register_type( 'buy_x_get_y',            Types\BuyXGetY::class );
		$this->register_type( 'buy_x_get_x',            Types\BuyXGetX::class );
		$this->register_type( 'spend_amount_get_gift',  Types\SpendAmountGetGift::class );
		$this->register_type( 'cart_quantity_get_gift', Types\CartQuantityGetGift::class );
		$this->register_type( 'category_get_gift',      Types\CategoryGetGift::class );

		do_action( 'matrix_bogo_register_promotion_types', $this );
	}

	/**
	 * Registers a custom promotion type.
	 */
	public function register_type( string $type, string $class_name ): void {
		$this->type_registry[ $type ] = $class_name;
	}

	// -----------------------------------------------------------------
	// Main evaluation
	// -----------------------------------------------------------------

	/**
	 * Evaluates all active promotions against the current WC cart.
	 *
	 * FIX: The previous implementation stored rewards in the session without
	 * a 'promotion_label' key, but CartPage, CartNotices and CheckoutPage all
	 * read $entry['promotion_label']. That meant every cart notice was blank.
	 * We now populate 'promotion_label' from the rule name.
	 *
	 * @param \WC_Cart|null $cart
	 * @return array<int, array<string,mixed>>
	 */
	public function evaluate_cart( ?\WC_Cart $cart = null ): array {
		if ( null === $cart ) {
			$cart = WC()->cart;
		}
		if ( ! $cart || $cart->is_empty() ) {
			$this->clear_session_rewards();
			return [];
		}

		$rules = $this->rules_repo->get_active_rules();
		if ( empty( $rules ) ) {
			$this->clear_session_rewards();
			return [];
		}

		$user_id = get_current_user_id();
		$context = [ 'cart' => $cart, 'user_id' => $user_id ];
		$matched = [];

		foreach ( $rules as $rule ) {
			$promotion = $this->make_promotion( $rule );
			if ( ! $promotion ) {
				continue;
			}

			$db_rewards = $this->rewards_repo->get_for_rule( (int) $rule['id'] );
			$promotion->set_db_rewards( $db_rewards );

			if ( $promotion->is_usage_limit_reached() ) {
				continue;
			}
			if ( $user_id && $promotion->is_user_usage_limit_reached( $user_id ) ) {
				continue;
			}

			if ( ! $this->condition_engine->evaluate( $promotion->get_id(), $context ) ) {
				continue;
			}

			if ( ! $promotion->is_applicable( $cart ) ) {
				continue;
			}

			$matched[] = $promotion;
		}

		$matched = $this->priority_manager->resolve( $matched );

		$rewards = [];
		foreach ( $matched as $promotion ) {
			$rule_rewards = $promotion->calculate_rewards( $cart );
			if ( ! empty( $rule_rewards ) ) {
				/*
				 * FIX: Populate 'promotion_label' so every part of the UI that
				 * reads $entry['promotion_label'] receives a non-empty string.
				 */
				$rewards[ $promotion->get_id() ] = [
					'rule'            => $promotion->get_rule(),
					'rewards'         => $rule_rewards,
					'promotion_label' => $promotion->get_name(),
				];
			}
		}

		/**
		 * Filters the final resolved reward sets.
		 *
		 * @param array<int, array<string,mixed>> $rewards
		 * @param \WC_Cart                        $cart
		 */
		$rewards = apply_filters( 'matrix_bogo_resolved_rewards', $rewards, $cart );

		if ( WC()->session ) {
			WC()->session->set( 'matrix_bogo_rewards', $rewards );
		}

		return $rewards;
	}

	/**
	 * Returns rewards currently stored in the WC session.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function get_session_rewards(): array {
		if ( ! WC()->session ) {
			return [];
		}
		return (array) ( WC()->session->get( 'matrix_bogo_rewards' ) ?? [] );
	}

	/**
	 * Clears the session reward map (e.g. when the cart empties).
	 */
	public function clear_session_rewards(): void {
		if ( WC()->session ) {
			WC()->session->set( 'matrix_bogo_rewards', [] );
		}
	}

	// -----------------------------------------------------------------
	// Factory
	// -----------------------------------------------------------------

	/**
	 * Instantiates the correct promotion-type class for a rule row.
	 *
	 * @param array<string,mixed> $rule
	 */
	public function make_promotion( array $rule ): ?AbstractPromotion {
		$type  = (string) ( $rule['type'] ?? '' );
		$class = $this->type_registry[ $type ] ?? null;

		if ( ! $class || ! class_exists( $class ) ) {
			Logger::warning(
				'matrix_bogo_unknown_type',
				sprintf( 'Unknown promotion type: %s for rule ID %d', $type, (int) ( $rule['id'] ?? 0 ) )
			);
			return null;
		}

		return new $class( $rule );
	}

	// -----------------------------------------------------------------
	// Accessors
	// -----------------------------------------------------------------

	public function get_rules_repository(): RulesRepository {
		return $this->rules_repo;
	}

	public function get_condition_engine(): ConditionEngine {
		return $this->condition_engine;
	}

	public function get_registered_types(): array {
		return array_keys( $this->type_registry );
	}
}
