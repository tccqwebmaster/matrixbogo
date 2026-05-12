<?php
/**
 * Conditions engine – evaluates all conditions for a rule against the current context.
 *
 * @package MatrixBogo\Conditions
 */

declare( strict_types=1 );

namespace MatrixBogo\Conditions;

use MatrixBogo\Abstracts\AbstractCondition;
use MatrixBogo\Database\Repositories\ConditionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConditionEngine
 *
 * Loads condition rows for a rule, instantiates the correct condition class,
 * and evaluates them using AND-within-groups / OR-between-groups logic.
 */
final class ConditionEngine {

	/** @var ConditionsRepository */
	private ConditionsRepository $repo;

	/**
	 * Registry map: condition type => class name.
	 *
	 * @var array<string,string>
	 */
	private array $registry = [];

	public function __construct() {
		$this->repo = new ConditionsRepository();
		$this->register_defaults();
	}

	// -----------------------------------------------------------------
	// Registry
	// -----------------------------------------------------------------

	/**
	 * Registers a condition type.
	 *
	 * @param string $type       Machine-readable type key.
	 * @param string $class_name Fully-qualified class name extending AbstractCondition.
	 */
	public function register( string $type, string $class_name ): void {
		$this->registry[ $type ] = $class_name;
	}

	/**
	 * Registers all built-in condition types.
	 */
	private function register_defaults(): void {
		// Customer conditions.
		$this->register( 'user_role',        CustomerConditions\UserRoleCondition::class );
		$this->register( 'logged_in',        CustomerConditions\LoggedInCondition::class );
		$this->register( 'specific_user',    CustomerConditions\SpecificUserCondition::class );
		$this->register( 'email',            CustomerConditions\EmailCondition::class );
		$this->register( 'first_order',      CustomerConditions\FirstOrderCondition::class );
		$this->register( 'repeat_customer',  CustomerConditions\RepeatCustomerCondition::class );

		// Cart conditions.
		$this->register( 'cart_subtotal',    CartConditions\CartSubtotalCondition::class );
		$this->register( 'cart_quantity',    CartConditions\CartQuantityCondition::class );
		$this->register( 'cart_categories',  CartConditions\CartCategoriesCondition::class );
		$this->register( 'cart_products',    CartConditions\CartProductsCondition::class );
		$this->register( 'coupon_applied',   CartConditions\CouponAppliedCondition::class );

		// Order history conditions.
		$this->register( 'purchase_history',   OrderConditions\PurchaseHistoryCondition::class );
		$this->register( 'total_spent',        OrderConditions\TotalSpentCondition::class );
		$this->register( 'completed_orders',   OrderConditions\CompletedOrdersCondition::class );

		// Time conditions.
		$this->register( 'date_range',    TimeConditions\DateRangeCondition::class );
		$this->register( 'day_of_week',   TimeConditions\DayOfWeekCondition::class );
		$this->register( 'time_range',    TimeConditions\TimeRangeCondition::class );

		/**
		 * Allows third-party code to register custom conditions.
		 *
		 * @param ConditionEngine $engine
		 */
		do_action( 'matrix_bogo_register_conditions', $this );
	}

	// -----------------------------------------------------------------
	// Evaluation
	// -----------------------------------------------------------------

	/**
	 * Returns true when ALL condition groups pass for the given rule.
	 *
	 * Conditions within the same group_id are ANDed.
	 * Groups are ORed.
	 * An empty condition set always passes (no restrictions).
	 *
	 * @param int                 $rule_id
	 * @param array<string,mixed> $context
	 */
	public function evaluate( int $rule_id, array $context = [] ): bool {
		$rows = $this->repo->get_for_rule( $rule_id );

		if ( empty( $rows ) ) {
			return true; // No conditions → always matches.
		}

		// Group conditions.
		/** @var array<int, AbstractCondition[]> $groups */
		$groups = [];
		foreach ( $rows as $row ) {
			$condition = $this->make( $row );
			if ( $condition ) {
				$groups[ (int) $row['group_id'] ][] = $condition;
			}
		}

		// OR between groups: at least one group must pass.
		foreach ( $groups as $conditions ) {
			if ( $this->evaluate_group( $conditions, $context ) ) {
				return true;
			}
		}

		return false;
	}

	// -----------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------

	/**
	 * All conditions in the group must pass (AND logic).
	 *
	 * @param AbstractCondition[] $conditions
	 * @param array<string,mixed> $context
	 */
	private function evaluate_group( array $conditions, array $context ): bool {
		foreach ( $conditions as $condition ) {
			if ( ! $condition->evaluate( $context ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Instantiates a condition object for a DB row.
	 *
	 * @param array<string,mixed> $row
	 */
	private function make( array $row ): ?AbstractCondition {
		$type  = (string) ( $row['type'] ?? '' );
		$class = $this->registry[ $type ] ?? null;

		if ( ! $class || ! class_exists( $class ) ) {
			return null;
		}

		return new $class( $row );
	}

	/**
	 * Returns an array of all registered condition types for the admin UI.
	 *
	 * @return array<string, string>  [type => label]
	 */
	public function get_registered_types(): array {
		$types = [];
		foreach ( $this->registry as $type => $class ) {
			if ( class_exists( $class ) ) {
				/** @var AbstractCondition $obj */
				$obj           = new $class( [ 'type' => $type, 'operator' => 'is', 'value' => '' ] );
				$types[ $type ] = $obj->get_label();
			}
		}
		return $types;
	}
}
