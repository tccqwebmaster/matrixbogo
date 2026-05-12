<?php
/**
 * Plugin installer – creates / upgrades database tables and default options.
 *
 * @package MatrixBogo\Core
 */

declare( strict_types=1 );

namespace MatrixBogo\Core;

use MatrixBogo\Database\Schema;
use MatrixBogo\Scheduler\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Installer
 *
 * Handles plugin activation, deactivation, and database migrations.
 */
final class Installer {

	// -----------------------------------------------------------------
	// Activation
	// -----------------------------------------------------------------

	/**
	 * Runs on plugin activation.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( [ 'fields' => 'ids' ] ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::run_install();
				restore_current_blog();
			}
		} else {
			self::run_install();
		}

		// Flush rewrite rules after activation.
		flush_rewrite_rules();
	}

	// -----------------------------------------------------------------
	// Deactivation
	// -----------------------------------------------------------------

	/**
	 * Runs on plugin deactivation – intentionally minimal (data preserved).
	 */
	public static function deactivate(): void {
		// Clear scheduled tasks.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'matrix_bogo_activate_promotion' );
			as_unschedule_all_actions( 'matrix_bogo_expire_promotion' );
			as_unschedule_all_actions( 'matrix_bogo_analytics_aggregate' );
		}

		flush_rewrite_rules();
	}

	// -----------------------------------------------------------------
	// Core install / update logic
	// -----------------------------------------------------------------

	/**
	 * Installs or upgrades the plugin for the current site.
	 */
	public static function run_install(): void {
		$installed_version = get_option( 'matrix_bogo_db_version', '' );

		Schema::create_tables();
		self::create_default_options();

		if ( '' === $installed_version ) {
			self::first_install();
		} elseif ( version_compare( $installed_version, MATRIX_BOGO_DB_VERSION, '<' ) ) {
			self::run_migrations( $installed_version );
		}

		update_option( 'matrix_bogo_db_version', MATRIX_BOGO_DB_VERSION, false );
	}

	// -----------------------------------------------------------------
	// First-install setup
	// -----------------------------------------------------------------

	/**
	 * One-time setup tasks run only on the very first installation.
	 */
	private static function first_install(): void {
		update_option( 'matrix_bogo_setup_wizard_completed', false, false );
		update_option( 'matrix_bogo_first_installed', current_time( 'mysql' ), false );

		// Seed default settings if absent.
		if ( false === get_option( 'matrix_bogo_settings' ) ) {
			add_option( 'matrix_bogo_settings', self::default_settings(), '', false );
		}
	}

	// -----------------------------------------------------------------
	// Migrations
	// -----------------------------------------------------------------

	/**
	 * Runs sequential DB migrations.
	 *
	 * @param string $from_version Version to migrate from.
	 */
	private static function run_migrations( string $from_version ): void {
		// Re-run dbDelta so any missing columns are added to existing tables.
		Schema::create_tables();
		// Future: load migration classes dynamically.
		// e.g. if ( version_compare( $from_version, '1.2.0', '<' ) ) { Migration_1_2_0::run(); }
		do_action( 'matrix_bogo_run_migrations', $from_version );
	}

	/**
	 * Checks whether the DB schema needs upgrading and runs it if so.
	 * Hooked to plugins_loaded so already-active installs get upgraded
	 * without requiring a deactivate/reactivate cycle.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'matrix_bogo_db_version', '' );
		if ( '' === $installed || version_compare( $installed, MATRIX_BOGO_DB_VERSION, '<' ) ) {
			self::run_install();
		}
	}

	// -----------------------------------------------------------------
	// Default options
	// -----------------------------------------------------------------

	/**
	 * Ensures critical options exist (does not overwrite existing values).
	 */
	private static function create_default_options(): void {
		add_option( 'matrix_bogo_settings', self::default_settings(), '', false );
	}

	/**
	 * Returns the array of default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return [
			'enabled'                     => true,
			'auto_apply_promotions'        => true,
			'gift_auto_remove'             => true,
			'show_cart_notices'            => true,
			'show_product_badges'          => true,
			'popup_style'                  => 'modal',
			'popup_trigger'                => 'cart_update',
			'progress_bar_enabled'         => true,
			'countdown_timer_enabled'      => true,
			'performance_mode'             => false,
			'debug_mode'                   => false,
			'delete_data_on_uninstall'     => false,
			'show_notices'                 => true,
			'analytics_enabled'            => true,
			'license_key'                  => '',
			'license_status'               => 'inactive',
		];
	}
}
