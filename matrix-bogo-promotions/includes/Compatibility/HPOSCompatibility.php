<?php
/**
 * HPOS Compatibility declaration.
 *
 * @package MatrixBogo\Compatibility
 */

declare( strict_types=1 );

namespace MatrixBogo\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HPOSCompatibility
 *
 * Declares compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
final class HPOSCompatibility {

	public function declare(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				MATRIX_BOGO_PLUGIN_FILE,
				true
			);

			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				MATRIX_BOGO_PLUGIN_FILE,
				true
			);
		}
	}
}
