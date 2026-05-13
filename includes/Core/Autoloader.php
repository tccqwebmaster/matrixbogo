<?php
/**
 * PSR-4 Autoloader for Matrix BOGO.
 *
 * @package MatrixBogo\Core
 */

declare( strict_types=1 );

namespace MatrixBogo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 *
 * Registers a PSR-4-compliant autoloader that maps the `MatrixBogo\\`
 * namespace to the `includes/` directory of the plugin.
 *
 * Security: the resolved file path is verified to sit inside the plugin's
 * own `includes/` directory before it is included, preventing any
 * path-traversal / file-inclusion attack (CWE-98).
 */
final class Autoloader {

	/** @var string Base namespace */
	private const BASE_NAMESPACE = 'MatrixBogo\\';

	/** @var string Absolute path to the `includes/` directory (with trailing slash). */
	private static string $base_dir = '';

	/**
	 * Registers the autoloader with the SPL autoload stack.
	 */
	public static function register(): void {
		self::$base_dir = realpath( MATRIX_BOGO_PLUGIN_DIR . 'includes' ) . DIRECTORY_SEPARATOR;
		spl_autoload_register( [ static::class, 'load' ], true, true );
	}

	/**
	 * Loads the class file for a given fully-qualified class name.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( string $class ): void {
		// Only handle classes in our namespace.
		if ( strncmp( $class, self::BASE_NAMESPACE, strlen( self::BASE_NAMESPACE ) ) !== 0 ) {
			return;
		}

		// Strip the base namespace and convert namespace separators to directory separators.
		$relative = substr( $class, strlen( self::BASE_NAMESPACE ) );

		/*
		 * Security: allow only word characters and namespace separators.
		 * Blocks null bytes, dots, slashes before path-building even starts.
		 */
		if ( ! preg_match( '/^[a-zA-Z0-9_\\\\]+$/', $relative ) ) {
			return;
		}

		$file = self::$base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		/*
		 * Security: resolve the real path and confirm it sits inside $base_dir.
		 * Eliminates path-traversal via symlinks or encoded sequences.
		 */
		$real = realpath( $file );
		if ( false === $real || strncmp( $real, self::$base_dir, strlen( self::$base_dir ) ) !== 0 ) {
			return;
		}

		require_once $real;
	}
}
