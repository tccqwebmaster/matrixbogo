<?php
/**
 * Scheduler – integrates with Action Scheduler for time-based tasks.
 *
 * @package MatrixBogo\Scheduler
 */

declare( strict_types=1 );

namespace MatrixBogo\Scheduler;

use MatrixBogo\Core\Loader;
use MatrixBogo\Scheduler\Tasks\PromotionActivationTask;
use MatrixBogo\Scheduler\Tasks\PromotionExpirationTask;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scheduler
 */
final class Scheduler {

	public const HOOK_ACTIVATION  = 'matrix_bogo_activate_promotion';
	public const HOOK_EXPIRATION  = 'matrix_bogo_expire_promotion';
	public const HOOK_PRUNE_LOGS  = 'matrix_bogo_prune_logs';

	public function init( Loader $loader ): void {
		$loader->add_action( self::HOOK_ACTIVATION, [ new PromotionActivationTask(), 'run' ] );
		$loader->add_action( self::HOOK_EXPIRATION, [ new PromotionExpirationTask(), 'run' ] );
		$loader->add_action( self::HOOK_PRUNE_LOGS, [ $this, 'run_prune_logs' ] );

		// Schedule daily log prune – deferred until Action Scheduler data store is ready.
		add_action( 'action_scheduler_stored_action', [ $this, 'maybe_schedule_log_prune' ], 1 );
		add_action( 'action_scheduler_pre_init', [ $this, 'maybe_schedule_log_prune' ] );
		add_action( 'init', [ $this, 'maybe_schedule_log_prune' ], 20 );
	}

	/**
	 * Schedules the daily log prune action once Action Scheduler is initialized.
	 */
	public function maybe_schedule_log_prune(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}
		if ( ! as_next_scheduled_action( self::HOOK_PRUNE_LOGS ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, self::HOOK_PRUNE_LOGS, [], 'matrix-bogo' );
		}
		remove_action( 'action_scheduler_stored_action', [ $this, 'maybe_schedule_log_prune' ], 1 );
		remove_action( 'action_scheduler_pre_init', [ $this, 'maybe_schedule_log_prune' ] );
		remove_action( 'init', [ $this, 'maybe_schedule_log_prune' ], 20 );
	}

	/**
	 * Schedules activation and expiration jobs for a rule.
	 *
	 * @param int         $rule_id
	 * @param string|null $start
	 * @param string|null $end
	 */
	public static function schedule_rule( int $rule_id, ?string $start, ?string $end ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		if ( $start ) {
			as_schedule_single_action(
				strtotime( $start ),
				self::HOOK_ACTIVATION,
				[ 'rule_id' => $rule_id ],
				'matrix-bogo'
			);
		}

		if ( $end ) {
			as_schedule_single_action(
				strtotime( $end ),
				self::HOOK_EXPIRATION,
				[ 'rule_id' => $rule_id ],
				'matrix-bogo'
			);
		}
	}

	/**
	 * Cancels any scheduled actions for a rule.
	 */
	public static function unschedule_rule( int $rule_id ): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::HOOK_ACTIVATION, [ 'rule_id' => $rule_id ], 'matrix-bogo' );
		as_unschedule_all_actions( self::HOOK_EXPIRATION, [ 'rule_id' => $rule_id ], 'matrix-bogo' );
	}

	public function run_prune_logs(): void {
		$settings = get_option( 'matrix_bogo_settings', [] );
		$days     = max( 1, (int) ( $settings['log_retention_days'] ?? 30 ) );
		( new \MatrixBogo\Database\Repositories\LogsRepository() )->prune( $days );
	}
}
