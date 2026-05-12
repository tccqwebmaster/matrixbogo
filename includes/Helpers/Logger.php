<?php
/**
 * Logger helper – writes structured log entries via LogsRepository.
 *
 * @package MatrixBogo\Helpers
 */

declare( strict_types=1 );

namespace MatrixBogo\Helpers;

use MatrixBogo\Database\Repositories\LogsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 *
 * FIX: 'debug' is now a valid ENUM value in the logs table (Schema.php updated).
 * The level is validated here before reaching the DB; invalid values fall back
 * to 'info' so no row is ever corrupted.
 */
final class Logger {

	/** Must match the `level` ENUM in the DB schema exactly. */
	private const ALLOWED_LEVELS = [ 'info', 'warning', 'error', 'debug' ];

	public static function info( string $event, string $message, array $context = [], ?int $rule_id = null, ?int $order_id = null ): void {
		self::log( $event, $message, $context, $rule_id, $order_id, 'info' );
	}

	public static function warning( string $event, string $message, array $context = [], ?int $rule_id = null, ?int $order_id = null ): void {
		self::log( $event, $message, $context, $rule_id, $order_id, 'warning' );
	}

	/** Errors are always persisted regardless of the enable_logging toggle. */
	public static function error( string $event, string $message, array $context = [], ?int $rule_id = null, ?int $order_id = null ): void {
		self::log( $event, $message, $context, $rule_id, $order_id, 'error', true );
	}

	public static function debug( string $event, string $message, array $context = [], ?int $rule_id = null, ?int $order_id = null ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ! empty( $settings['enable_logging'] ) ) {
			self::log( $event, $message, $context, $rule_id, $order_id, 'debug' );
		}
	}

	private static function log(
		string $event,
		string $message,
		array $context,
		?int $rule_id,
		?int $order_id,
		string $level,
		bool $force = false
	): void {
		if ( ! in_array( $level, self::ALLOWED_LEVELS, true ) ) {
			$level = 'info';
		}

		if ( ! $force ) {
			$settings = get_option( 'matrix_bogo_settings', [] );
			if ( empty( $settings['enable_logging'] ) ) {
				return;
			}
		}

		( new LogsRepository() )->log(
			$event,
			$message,
			$context,
			(int) $rule_id,
			(int) $order_id,
			$level
		);
	}
}
