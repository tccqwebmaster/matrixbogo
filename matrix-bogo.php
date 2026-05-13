<?php
/**
 * Plugin Name:       Matrix BOGO WooCommerce Promotion
 * Plugin URI:        https://matrixplugins.com/matrix-bogo
 * Description:       Enterprise-grade WooCommerce BOGO & promotion engine with advanced rule builder, gift selector, analytics, and more.
 * Version:           1.2.0
 * Author:            Matrix Plugins
 * Author URI:        https://matrixplugins.com
 * Text Domain:       matrix-bogo
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:       6.9
 * Requires PHP:       8.1
 * WC requires at least: 8.0
 * WC tested up to:   10.7.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package MatrixBogo
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Matrix BOGO requires PHP 8.1 or higher. Please upgrade your PHP version.', 'matrix-bogo' ) .
				'</p></div>';
		}
	);
	return;
}

define( 'MATRIX_BOGO_VERSION',         '1.2.0' );
define( 'MATRIX_BOGO_DB_VERSION',      '1.0.2' );
define( 'MATRIX_BOGO_PLUGIN_FILE',     __FILE__ );
define( 'MATRIX_BOGO_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'MATRIX_BOGO_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'MATRIX_BOGO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MATRIX_BOGO_MIN_WP_VERSION',  '6.0' );
define( 'MATRIX_BOGO_MIN_PHP_VERSION', '8.1' );
define( 'MATRIX_BOGO_MIN_WC_VERSION',  '8.0' );

require_once MATRIX_BOGO_PLUGIN_DIR . 'includes/Core/Autoloader.php';
\MatrixBogo\Core\Autoloader::register();

function matrix_bogo(): \MatrixBogo\Core\Plugin {
	return \MatrixBogo\Core\Plugin::instance();
}

add_action( 'plugins_loaded', 'matrix_bogo', 10 );
add_action( 'plugins_loaded', [ \MatrixBogo\Core\Installer::class, 'maybe_upgrade' ], 5 );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables',  __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook(   __FILE__, [ \MatrixBogo\Core\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \MatrixBogo\Core\Installer::class, 'deactivate' ] );
