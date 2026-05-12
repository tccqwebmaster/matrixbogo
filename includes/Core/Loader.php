<?php
/**
 * Registers and fires all WordPress action and filter hooks.
 *
 * @package MatrixBogo\Core
 */

declare( strict_types=1 );

namespace MatrixBogo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 *
 * Collects action and filter registrations throughout plugin initialisation
 * and fires them all in one pass, keeping hook wiring explicit and testable.
 */
final class Loader {

	/**
	 * Registered action hooks.
	 *
	 * @var array<int, array{hook:string, callback:callable, priority:int, accepted_args:int}>
	 */
	private array $actions = [];

	/**
	 * Registered filter hooks.
	 *
	 * @var array<int, array{hook:string, callback:callable, priority:int, accepted_args:int}>
	 */
	private array $filters = [];

	// -----------------------------------------------------------------
	// Registration helpers
	// -----------------------------------------------------------------

	/**
	 * Queues a WordPress action.
	 *
	 * @param string   $hook          The action hook name.
	 * @param callable $callback      The callback to invoke.
	 * @param int      $priority      Optional. Default 10.
	 * @param int      $accepted_args Optional. Default 1.
	 */
	public function add_action(
		string $hook,
		callable $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions[] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Queues a WordPress filter.
	 *
	 * @param string   $hook          The filter hook name.
	 * @param callable $callback      The callback to invoke.
	 * @param int      $priority      Optional. Default 10.
	 * @param int      $accepted_args Optional. Default 1.
	 */
	public function add_filter(
		string $hook,
		callable $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters[] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	}

	// -----------------------------------------------------------------
	// Execute
	// -----------------------------------------------------------------

	/**
	 * Registers every queued action and filter with WordPress.
	 */
	public function run(): void {
		foreach ( $this->actions as $action ) {
			add_action(
				$action['hook'],
				$action['callback'],
				$action['priority'],
				$action['accepted_args']
			);
		}

		foreach ( $this->filters as $filter ) {
			add_filter(
				$filter['hook'],
				$filter['callback'],
				$filter['priority'],
				$filter['accepted_args']
			);
		}
	}
}
