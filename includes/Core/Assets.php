<?php
/**
 * Plugin asset enqueuing (CSS + JS for admin and frontend).
 *
 * @package MatrixBogo\Core
 */

declare( strict_types=1 );

namespace MatrixBogo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * Centralises all wp_enqueue_scripts / wp_enqueue_style calls.
 */
final class Assets {

	/** @var Loader */
	private Loader $loader;

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------

	public function init( Loader $loader ): void {
		$this->loader = $loader;

		$loader->add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend' ] );
		$loader->add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
	}

	// -----------------------------------------------------------------
	// Frontend assets
	// -----------------------------------------------------------------

	public function enqueue_frontend(): void {
		if ( ! is_cart() && ! is_checkout() && ! is_product() && ! is_shop() ) {
			return;
		}

		wp_enqueue_style(
			'matrix-bogo-frontend',
			MATRIX_BOGO_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			MATRIX_BOGO_VERSION
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'matrix-bogo-rtl',
				MATRIX_BOGO_PLUGIN_URL . 'assets/css/rtl.css',
				[ 'matrix-bogo-frontend' ],
				MATRIX_BOGO_VERSION
			);
		}

		wp_enqueue_script(
			'matrix-bogo-frontend',
			MATRIX_BOGO_PLUGIN_URL . 'assets/js/frontend.js',
			[ 'jquery' ],
			MATRIX_BOGO_VERSION,
			true
		);

		wp_enqueue_script(
			'matrix-bogo-gift-popup',
			MATRIX_BOGO_PLUGIN_URL . 'assets/js/gift-popup.js',
			[ 'jquery', 'matrix-bogo-frontend' ],
			MATRIX_BOGO_VERSION,
			true
		);

		wp_localize_script(
			'matrix-bogo-frontend',
			'matrixBogoFrontend',
			$this->frontend_js_data()
		);
	}

	// -----------------------------------------------------------------
	// Admin assets
	// -----------------------------------------------------------------

	public function enqueue_admin( string $hook_suffix ): void {
		if ( ! $this->is_plugin_page( $hook_suffix ) ) {
			return;
		}

		// Select2 is bundled with WooCommerce — load it for our dropdowns.
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );

		wp_enqueue_style(
			'matrix-bogo-admin',
			MATRIX_BOGO_PLUGIN_URL . 'assets/css/admin.css',
			[],
			MATRIX_BOGO_VERSION
		);

		wp_enqueue_script(
			'matrix-bogo-admin',
			MATRIX_BOGO_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'wc-enhanced-select' ],
			MATRIX_BOGO_VERSION,
			true
		);

		wp_localize_script(
			'matrix-bogo-admin',
			'matrixBogoAdmin',
			$this->admin_js_data()
		);
	}

	// -----------------------------------------------------------------
	// JS data payloads
	// -----------------------------------------------------------------

	/**
	 * @return array<string, mixed>
	 */
	private function frontend_js_data(): array {
		return [
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'restUrl'         => rest_url( 'matrix-bogo/v1/' ),
			'nonce'           => wp_create_nonce( 'matrix_bogo_frontend' ),
			'restNonce'       => wp_create_nonce( 'wp_rest' ),
			'i18n'            => [
				'addToCart'        => __( 'Add to Cart', 'matrix-bogo' ),
				'selectGift'       => __( 'Select Your Free Gift', 'matrix-bogo' ),
				'giftAdded'        => __( 'Free gift added to your cart!', 'matrix-bogo' ),
				'loading'          => __( 'Loading…', 'matrix-bogo' ),
				'close'            => __( 'Close', 'matrix-bogo' ),
				'outOfStock'       => __( 'Out of Stock', 'matrix-bogo' ),
				'selectVariation'  => __( 'Select options', 'matrix-bogo' ),
			],
			'settings'        => [
				'popupStyle'   => get_option( 'matrix_bogo_settings', [] )['popup_style'] ?? 'modal',
				'autoApply'    => (bool) ( get_option( 'matrix_bogo_settings', [] )['auto_apply_promotions'] ?? true ),
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function admin_js_data(): array {
		return [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'restUrl'   => rest_url( 'matrix-bogo/v1/' ),
			'nonce'     => wp_create_nonce( 'matrix_bogo_admin' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'version'   => MATRIX_BOGO_VERSION,
			'listUrl'   => admin_url( 'admin.php?page=matrix-bogo-promotions' ),
			'i18n'      => [
				'save'            => __( 'Save Promotion', 'matrix-bogo' ),
				'saving'          => __( 'Saving…', 'matrix-bogo' ),
				'saved'           => __( 'Promotion saved.', 'matrix-bogo' ),
				'error'           => __( 'An error occurred. Please try again.', 'matrix-bogo' ),
				'confirmDelete'   => __( 'Are you sure you want to delete this promotion?', 'matrix-bogo' ),
				'activateConfirm' => __( 'Activate this promotion?', 'matrix-bogo' ),
				'licenseActive'   => __( 'License activated successfully.', 'matrix-bogo' ),
				'redemptions'     => __( 'Redemptions', 'matrix-bogo' ),
			],
		];
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * Returns true if the current admin page belongs to this plugin.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	private function is_plugin_page( string $hook_suffix ): bool {
		return false !== strpos( $hook_suffix, 'matrix-bogo' );
	}
}
