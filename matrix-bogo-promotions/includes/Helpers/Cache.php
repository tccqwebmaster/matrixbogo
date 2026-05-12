<?php
/**
 * Cache helper – thin wrapper around WP Object Cache with group management.
 *
 * @package MatrixBogo\Helpers
 */

declare( strict_types=1 );

namespace MatrixBogo\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache
 */
final class Cache {

	public const GROUP = 'matrix_bogo';
	public const TTL   = 300;

	public static function get( string $key ): mixed {
		return wp_cache_get( $key, self::GROUP );
	}

	public static function set( string $key, mixed $value, int $ttl = self::TTL ): void {
		wp_cache_set( $key, $value, self::GROUP, $ttl );
	}

	public static function delete( string $key ): void {
		wp_cache_delete( $key, self::GROUP );
	}

	public static function flush_group(): void {
		wp_cache_flush_group( self::GROUP );
	}
}
