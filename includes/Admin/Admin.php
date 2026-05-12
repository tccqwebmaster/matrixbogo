<?php
/**
 * Admin bootstrap – registers menus and delegates to sub-controllers.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

use MatrixBogo\Core\Loader;
use MatrixBogo\License\LicenseManager;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
final class Admin {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	/** @var LicenseManager */
	private LicenseManager $license;

	// Sub-controllers.
	private Dashboard        $dashboard;
	private PromotionsList   $promotions_list;
	private PromotionBuilder $promotion_builder;
	private Analytics        $analytics;
	private Settings         $settings;
	private Logs             $logs;
	private SetupWizard      $wizard;
	private AjaxSearch       $ajax_search;

	public function __construct( PromotionEngine $engine, LicenseManager $license ) {
		$this->engine           = $engine;
		$this->license          = $license;
		$this->dashboard        = new Dashboard( $engine );
		$this->promotions_list  = new PromotionsList( $engine );
		$this->promotion_builder = new PromotionBuilder( $engine );
		$this->analytics        = new Analytics();
		$this->settings         = new Settings( $license );
		$this->logs             = new Logs();
		$this->wizard           = new SetupWizard();
		$this->ajax_search      = new AjaxSearch();
	}

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------

	public function init( Loader $loader ): void {
		$loader->add_action( 'admin_menu', [ $this, 'register_menus' ] );
		$loader->add_action( 'admin_init', [ $this->settings, 'register_settings' ] );

		// AJAX handlers for admin operations.
		$loader->add_action( 'wp_ajax_matrix_bogo_save_rule',    [ $this->promotion_builder, 'ajax_save' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_delete_rule',  [ $this->promotions_list, 'ajax_delete' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_toggle_rule',  [ $this->promotions_list, 'ajax_toggle' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_clone_rule',   [ $this->promotions_list, 'ajax_clone' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_export_rules', [ $this->promotions_list, 'ajax_export' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_import_rules', [ $this->promotions_list, 'ajax_import' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_get_analytics', [ $this->analytics, 'ajax_get_data' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_activate_license', [ $this->settings, 'ajax_activate_license' ] );

		// Setup wizard.
		$loader->add_action( 'admin_init', [ $this->wizard, 'maybe_redirect' ] );

		// Product & category AJAX search (powers Select2 dropdowns).
		$this->ajax_search->init( $loader );
	}

	// -----------------------------------------------------------------
	// Menu registration
	// -----------------------------------------------------------------

	public function register_menus(): void {
		// Top-level menu.
		add_menu_page(
			__( 'Matrix BOGO', 'matrix-bogo' ),
			__( 'Matrix BOGO', 'matrix-bogo' ),
			'manage_woocommerce',
			'matrix-bogo',
			[ $this->dashboard, 'render' ],
			'dashicons-awards',
			58
		);

		// Dashboard.
		add_submenu_page(
			'matrix-bogo',
			__( 'Dashboard', 'matrix-bogo' ),
			__( 'Dashboard', 'matrix-bogo' ),
			'manage_woocommerce',
			'matrix-bogo',
			[ $this->dashboard, 'render' ]
		);

		// Promotions list.
		add_submenu_page(
			'matrix-bogo',
			__( 'Promotions', 'matrix-bogo' ),
			__( 'Promotions', 'matrix-bogo' ),
			'manage_woocommerce',
			'matrix-bogo-promotions',
			[ $this->promotions_list, 'render' ]
		);

		// Analytics.
		add_submenu_page(
			'matrix-bogo',
			__( 'Analytics', 'matrix-bogo' ),
			__( 'Analytics', 'matrix-bogo' ),
			'manage_woocommerce',
			'matrix-bogo-analytics',
			[ $this->analytics, 'render' ]
		);

		// Logs.
		add_submenu_page(
			'matrix-bogo',
			__( 'Logs', 'matrix-bogo' ),
			__( 'Logs', 'matrix-bogo' ),
			'manage_woocommerce',
			'matrix-bogo-logs',
			[ $this->logs, 'render' ]
		);

		// Settings.
		add_submenu_page(
			'matrix-bogo',
			__( 'Settings', 'matrix-bogo' ),
			__( 'Settings', 'matrix-bogo' ),
			'manage_woocommerce',
			'matrix-bogo-settings',
			[ $this->settings, 'render' ]
		);
	}
}
