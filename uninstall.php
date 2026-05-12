<?php
/**
 * Uninstall handler for Matrix BOGO WooCommerce Promotion.
 *
 * Runs when the plugin is deleted from the WordPress admin panel.
 * Removes all plugin data: database tables, options, transients.
 *
 * @package MatrixBogo
 */

declare( strict_types=1 );

// Security check – only WordPress can call this file.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only delete data if the option is set.
$settings = get_option( 'matrix_bogo_settings', [] );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// -------------------------------------------------------------------------
// Drop custom tables
// -------------------------------------------------------------------------

$tables = [
	'matrix_bogo_rules',
	'matrix_bogo_conditions',
	'matrix_bogo_rewards',
	'matrix_bogo_logs',
	'matrix_bogo_analytics',
	'matrix_bogo_redemptions',
];

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// -------------------------------------------------------------------------
// Remove plugin options
// -------------------------------------------------------------------------

$options = [
	'matrix_bogo_settings',
	'matrix_bogo_db_version',
	'matrix_bogo_license_key',
	'matrix_bogo_license_status',
	'matrix_bogo_activation_data',
	'matrix_bogo_setup_wizard_completed',
	'matrix_bogo_analytics_opt_in',
];

foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}

// -------------------------------------------------------------------------
// Remove scheduled actions (Action Scheduler)
// -------------------------------------------------------------------------

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'matrix_bogo_activate_promotion' );
	as_unschedule_all_actions( 'matrix_bogo_expire_promotion' );
	as_unschedule_all_actions( 'matrix_bogo_analytics_aggregate' );
}

// -------------------------------------------------------------------------
// Clean transients
// -------------------------------------------------------------------------

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_matrix_bogo_%' OR `option_name` LIKE '_transient_timeout_matrix_bogo_%'"
);

// Clean user meta
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE 'matrix_bogo_%'"
);

// Clean post meta
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE '_matrix_bogo_%'"
);
