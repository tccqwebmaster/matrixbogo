<?php
/**
 * Abstract base class for all promotion types.
 *
 * @package MatrixBogo\Abstracts
 */

declare( strict_types=1 );

namespace MatrixBogo\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractPromotion
 *
 * Defines the contract every promotion type must fulfil.
 */
abstract class AbstractPromotion {

	/** @var array<string,mixed> Raw rule data row from the database. */
	protected array $rule;

	/** @var array<string,mixed> Parsed rule_data JSON. */
	protected array $rule_data;

	/** @var array<int, array<string,mixed>> Reward rows from matrix_bogo_rewards table. */
	protected array $db_rewards = [];

	public function __construct( array $rule ) {
		$this->rule      = $rule;
		$this->rule_data = isset( $rule['rule_data'] )
			? (array) json_decode( $rule['rule_data'], true )
			: [];
	}

	/**
	 * Inject the DB rewards rows (loaded by PromotionEngine before evaluation).
	 *
	 * @param array<int, array<string,mixed>> $db_rewards
	 */
	public function set_db_rewards( array $db_rewards ): void {
		$this->db_rewards = $db_rewards;
	}

	/**
	 * Converts DB reward rows into CartModifier-compatible reward descriptors.
	 *
	 * Maps reward_type → discount_type and normalises all fields.
	 *
	 * @param int $qty_multiplier Multiply the stored quantity by this (e.g. eligible sets).
	 * @return array<int, array<string,mixed>>
	 */
	protected function db_rewards_to_descriptors( int $qty_multiplier = 1 ): array {
		$out = [];
		foreach ( $this->db_rewards as $row ) {
			$reward_type = (string) ( $row['reward_type'] ?? 'free_product' );

			$discount_type = match ( $reward_type ) {
				'fixed_discount'   => 'fixed',
				'percent_discount' => 'percent',
				'cheapest_free'    => 'cheapest_free',
				default            => 'free',
			};

			$choice_pool_raw = $row['choice_pool'] ?? '[]';
			if ( is_array( $choice_pool_raw ) ) {
				$choice_pool = $choice_pool_raw;
			} else {
				$decoded = json_decode( (string) $choice_pool_raw, true );
				if ( is_array( $decoded ) ) {
					$choice_pool = $decoded;
				} else {
					/*
					 * FIX: Fallback for legacy rows stored as a comma-separated string
					 * (e.g. "83332,83331,1204406") before the sync_for_rule fix was applied.
					 * json_decode() returns null for that format; parse manually instead.
					 */
					$raw_str     = trim( (string) $choice_pool_raw );
					$choice_pool = '' !== $raw_str
						? array_values( array_filter( array_map( 'intval', explode( ',', $raw_str ) ) ) )
						: [];
				}
			}

			$out[] = [
				'rule_id'         => $this->get_id(),
				'product_id'      => (int) ( $row['product_id'] ?? 0 ),
				'variation_id'    => (int) ( $row['variation_id'] ?? 0 ),
				'quantity'        => max( 1, (int) ( $row['quantity'] ?? 1 ) ) * $qty_multiplier,
				'discount_type'   => $discount_type,
				'discount_value'  => (float) ( $row['discount_value'] ?? 0 ),
				'max_per_order'   => (int) ( $row['max_per_order'] ?? 0 ),
				'customer_choice' => (bool) ( $row['customer_choice'] ?? false ),
				'choice_pool'     => array_map( 'intval', $choice_pool ),
				'label'           => $this->get_name(),
			];
		}
		return $out;
	}

	/**
	 * Converts comma-separated IDs string from rule_data into an int array.
	 *
	 * @param string $key rule_data key, e.g. 'trigger_product_ids'.
	 * @return int[]
	 */
	protected function ids_from_data( string $key ): array {
		$raw = (string) ( $this->rule_data[ $key ] ?? '' );
		if ( '' === $raw ) {
			return [];
		}
		return array_values( array_filter( array_map( 'intval', explode( ',', $raw ) ) ) );
	}

	// -----------------------------------------------------------------
	// Identity
	// -----------------------------------------------------------------

	/** Returns the machine-readable type key, e.g. 'buy_x_get_y'. */
	abstract public function get_type(): string;

	/** Returns the human-readable label shown in the admin. */
	abstract public function get_label(): string;

	// -----------------------------------------------------------------
	// Evaluation
	// -----------------------------------------------------------------

	/**
	 * Determines whether this promotion should trigger for the given cart.
	 *
	 * @param \WC_Cart $cart The WooCommerce cart.
	 * @return bool
	 */
	abstract public function is_applicable( \WC_Cart $cart ): bool;

	/**
	 * Calculates the rewards earned for the given cart.
	 *
	 * @param \WC_Cart $cart
	 * @return array<int, array<string,mixed>>  Array of reward descriptors.
	 */
	abstract public function calculate_rewards( \WC_Cart $cart ): array;

	// -----------------------------------------------------------------
	// Shared helpers
	// -----------------------------------------------------------------

	/** Returns the rule ID. */
	public function get_id(): int {
		return (int) ( $this->rule['id'] ?? 0 );
	}

	/** Returns the rule name. */
	public function get_name(): string {
		return (string) ( $this->rule['name'] ?? '' );
	}

	/** Returns the rule priority (lower = higher priority). */
	public function get_priority(): int {
		return (int) ( $this->rule['priority'] ?? 10 );
	}

	/** Whether the promotion is exclusive (no other promos apply). */
	public function is_exclusive(): bool {
		return (bool) ( $this->rule['is_exclusive'] ?? false );
	}

	/** Whether the promotion can stack with others. */
	public function is_stackable(): bool {
		return (bool) ( $this->rule['is_stackable'] ?? true );
	}

	/** Returns the raw rule array. */
	public function get_rule(): array {
		return $this->rule;
	}

	/** Returns a typed rule_data value. */
	protected function get_data( string $key, mixed $default = null ): mixed {
		return $this->rule_data[ $key ] ?? $default;
	}

	// -----------------------------------------------------------------
	// Usage limits
	// -----------------------------------------------------------------

	/**
	 * Checks if global usage limit has been reached.
	 */
	public function is_usage_limit_reached(): bool {
		$max   = (int) ( $this->rule['max_uses'] ?? 0 );
		$used  = (int) ( $this->rule['uses_count'] ?? 0 );
		return $max > 0 && $used >= $max;
	}

	/**
	 * Checks if per-user usage limit has been reached for a given user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public function is_user_usage_limit_reached( int $user_id ): bool {
		$max = (int) ( $this->rule['max_uses_per_user'] ?? 0 );
		if ( $max <= 0 || $user_id <= 0 ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->prefix}matrix_bogo_redemptions`
				 WHERE `rule_id` = %d AND `user_id` = %d",
				$this->get_id(),
				$user_id
			)
		);

		return $count >= $max;
	}
}
