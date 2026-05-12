<?php
/**
 * Database schema – creates and upgrades all custom tables.
 *
 * @package MatrixBogo\Database
 */

declare( strict_types=1 );

namespace MatrixBogo\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema
 */
final class Schema {

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		foreach ( self::get_schema( $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}
		// Run live ALTER for existing installs that already have the old ENUM.
		self::maybe_alter_log_level_enum();
	}

	/**
	 * On existing installs dbDelta cannot change ENUM values, so we run an
	 * explicit ALTER TABLE when the 'debug' value is missing.
	 */
	private static function maybe_alter_log_level_enum(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'matrix_bogo_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'level'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $columns ) ) {
			return;
		}
		$type = $columns[0]['Type'] ?? '';
		if ( strpos( $type, 'debug' ) === false ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "ALTER TABLE `{$table}` MODIFY `level` ENUM('info','warning','error','debug') NOT NULL DEFAULT 'info'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	private static function get_schema( string $charset_collate ): array {
		global $wpdb;

		return [

			"CREATE TABLE `{$wpdb->prefix}matrix_bogo_rules` (
				`id`                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				`name`              VARCHAR(255)         NOT NULL DEFAULT '',
				`slug`              VARCHAR(255)         NOT NULL DEFAULT '',
				`description`       VARCHAR(1000)        NOT NULL DEFAULT '',
				`status`            ENUM('active','inactive','scheduled','expired') NOT NULL DEFAULT 'inactive',
				`type`              VARCHAR(100)         NOT NULL DEFAULT 'buy_x_get_y',
				`priority`          SMALLINT(5) UNSIGNED NOT NULL DEFAULT 10,
				`is_exclusive`      TINYINT(1)           NOT NULL DEFAULT 0,
				`is_stackable`      TINYINT(1)           NOT NULL DEFAULT 1,
				`max_uses`          INT(11)              NOT NULL DEFAULT 0,
				`uses_count`        INT(11)              NOT NULL DEFAULT 0,
				`max_uses_per_user` INT(11)              NOT NULL DEFAULT 0,
				`schedule_start`    DATETIME             NULL     DEFAULT NULL,
				`schedule_end`      DATETIME             NULL     DEFAULT NULL,
				`rule_data`         LONGTEXT             NOT NULL,
				`meta`              LONGTEXT             NULL,
				`created_at`        DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at`        DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `status`   (`status`),
				KEY `type`     (`type`),
				KEY `priority` (`priority`),
				KEY `slug`     (`slug`)
			) $charset_collate;",

			"CREATE TABLE `{$wpdb->prefix}matrix_bogo_conditions` (
				`id`         BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
				`rule_id`    BIGINT(20) UNSIGNED  NOT NULL,
				`group_id`   SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
				`type`       VARCHAR(100)         NOT NULL DEFAULT '',
				`operator`   VARCHAR(50)          NOT NULL DEFAULT 'is',
				`value`      LONGTEXT             NOT NULL,
				`sort_order` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
				`created_at` DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `rule_id` (`rule_id`),
				KEY `type`    (`type`)
			) $charset_collate;",

			"CREATE TABLE `{$wpdb->prefix}matrix_bogo_rewards` (
				`id`              BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
				`rule_id`         BIGINT(20) UNSIGNED  NOT NULL,
				`reward_type`     VARCHAR(100)         NOT NULL DEFAULT 'free_product',
				`product_id`      BIGINT(20) UNSIGNED  NOT NULL DEFAULT 0,
				`variation_id`    BIGINT(20) UNSIGNED  NOT NULL DEFAULT 0,
				`quantity`        SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
				`discount_type`   VARCHAR(50)          NOT NULL DEFAULT 'free',
				`discount_value`  DECIMAL(10,4)        NOT NULL DEFAULT 0.0000,
				`max_per_order`   SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
				`customer_choice` TINYINT(1)           NOT NULL DEFAULT 0,
				`choice_pool`     LONGTEXT             NULL,
				`sort_order`      SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
				`created_at`      DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `rule_id`    (`rule_id`),
				KEY `product_id` (`product_id`)
			) $charset_collate;",

			"CREATE TABLE `{$wpdb->prefix}matrix_bogo_redemptions` (
				`id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				`rule_id`     BIGINT(20) UNSIGNED NOT NULL,
				`order_id`    BIGINT(20) UNSIGNED NOT NULL,
				`user_id`     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				`session_id`  VARCHAR(255)        NOT NULL DEFAULT '',
				`discount`    DECIMAL(10,4)       NOT NULL DEFAULT 0.0000,
				`gifts_data`  LONGTEXT            NULL,
				`redeemed_at` DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `rule_id`  (`rule_id`),
				KEY `order_id` (`order_id`),
				KEY `user_id`  (`user_id`)
			) $charset_collate;",

			"CREATE TABLE `{$wpdb->prefix}matrix_bogo_analytics` (
				`id`             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				`rule_id`        BIGINT(20) UNSIGNED NOT NULL,
				`date`           DATE                NOT NULL,
				`impressions`    INT(11) UNSIGNED    NOT NULL DEFAULT 0,
				`redemptions`    INT(11) UNSIGNED    NOT NULL DEFAULT 0,
				`revenue`        DECIMAL(12,4)       NOT NULL DEFAULT 0.0000,
				`discount_total` DECIMAL(12,4)       NOT NULL DEFAULT 0.0000,
				`aov_impact`     DECIMAL(10,4)       NOT NULL DEFAULT 0.0000,
				`created_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				UNIQUE KEY `rule_date` (`rule_id`, `date`),
				KEY `date` (`date`)
			) $charset_collate;",

			// FIX: Added 'debug' to the ENUM. On existing installs the live
			// ALTER in maybe_alter_log_level_enum() handles migration.
			"CREATE TABLE `{$wpdb->prefix}matrix_bogo_logs` (
				`id`         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				`rule_id`    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				`order_id`   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				`user_id`    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				`event`      VARCHAR(100)        NOT NULL DEFAULT '',
				`message`    TEXT                NOT NULL,
				`context`    LONGTEXT            NULL,
				`level`      ENUM('info','warning','error','debug') NOT NULL DEFAULT 'info',
				`created_at` DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `rule_id`    (`rule_id`),
				KEY `order_id`   (`order_id`),
				KEY `event`      (`event`),
				KEY `level`      (`level`),
				KEY `created_at` (`created_at`)
			) $charset_collate;",

		];
	}

	public static function drop_tables(): void {
		global $wpdb;
		$tables = [
			"{$wpdb->prefix}matrix_bogo_rules",
			"{$wpdb->prefix}matrix_bogo_conditions",
			"{$wpdb->prefix}matrix_bogo_rewards",
			"{$wpdb->prefix}matrix_bogo_redemptions",
			"{$wpdb->prefix}matrix_bogo_analytics",
			"{$wpdb->prefix}matrix_bogo_logs",
		];
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
