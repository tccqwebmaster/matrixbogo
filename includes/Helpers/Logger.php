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
 * Usage: Logger::info('event', 'message');
 */
final class Logger {

	// -----------------------------------------------------------------
	// Static shortcuts
	// -----------------------------------------------------------------

	public static function info( string $event, string $message, array $context = [], ?int $rule_id = null, ?int $order_id = null ): void {
		self::log( $event, $message, $context, $rule_id, $order_id, 'info' );
	}

	public static function warning( string $event, string $message, array $context = [], ?int $rule_id = null, ?int $order_id = null ): void {
		self::log( $event, $message, $context, $rule_id, $order_id, 'warning' );
	}

	public static function error( string $event, string $message, array $context = [], ?int $rule_id = null, ?int $order_id = null ): void {
		self::log( $event, $message, $context, $rule_id, $order_id, 'error' );
	}

	public static function debug( string $event, string $message, array $context = [], ?int $rule_id = null, ?int $order_id = null ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $settings['enable_logging'] ) ) {
			self::log( $event, $message, $context, $rule_id, $order_id, 'debug' );
		}
	}

	// -----------------------------------------------------------------
	// Core
	// -----------------------------------------------------------------

	private static function log(
		string $event,
		string $message,
		array $context,
		?int $rule_id,
		?int $order_id,
		string $level
	): void {
		$settings = get_option( 'matrix_bogo_settings', [] );
		if ( empty( $settings['enable_logging'] ) && 'error' !== $level ) {
			return;
		}

		( new LogsRepository() )->log( $event, $message, $context, $rule_id, $order_id, $level );
	}
}
