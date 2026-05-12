<?php
/**
 * Main plugin bootstrap singleton.
 *
 * @package MatrixBogo\Core
 */

declare( strict_types=1 );

namespace MatrixBogo\Core;

use MatrixBogo\Admin\Admin;
use MatrixBogo\API\RestAPI;
use MatrixBogo\Cart\CartEngine;
use MatrixBogo\Frontend\Frontend;
use MatrixBogo\Gifts\GiftManager;
use MatrixBogo\Helpers\Logger;
use MatrixBogo\License\LicenseManager;
use MatrixBogo\Promotions\PromotionEngine;
use MatrixBogo\Scheduler\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Central hub for the Matrix BOGO plugin.  All service objects are
 * created here and wired together via the Loader's hook queue.
 */
final class Plugin {

	/** @var Plugin|null Singleton instance. */
	private static ?Plugin $instance = null;

	/** @var Loader Action/filter hook queue. */
	private Loader $loader;

	/** @var string Current locale. */
	private string $locale;

	// -----------------------------------------------------------------
	// Registered services
	// -----------------------------------------------------------------

	private Admin $admin;
	private Frontend $frontend;
	private CartEngine $cart_engine;
	private PromotionEngine $promotion_engine;
	private GiftManager $gift_manager;
	private RestAPI $rest_api;
	private Scheduler $scheduler;
	private LicenseManager $license_manager;
	private Assets $assets;

	// -----------------------------------------------------------------
	// Singleton access
	// -----------------------------------------------------------------

	/**
	 * Returns the single plugin instance, creating it on first call.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/** Private constructor – use Plugin::instance(). */
	private function __construct() {
		$this->locale = determine_locale();
		$this->loader = new Loader();
	}

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------

	/**
	 * Initialises all subsystems.
	 */
	private function init(): void {
		// Bail early if WooCommerce is not active.
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', [ $this, 'notice_woocommerce_missing' ] );
			return;
		}

		// Check minimum WooCommerce version.
		if ( ! $this->is_woocommerce_version_ok() ) {
			add_action( 'admin_notices', [ $this, 'notice_woocommerce_old' ] );
			return;
		}

		$this->load_textdomain();
		$this->register_services();
		$this->loader->run();

		/**
		 * Fires after the plugin has finished loading all services.
		 *
		 * @since 1.0.0
		 */
		do_action( 'matrix_bogo_loaded' );
	}

	// -----------------------------------------------------------------
	// Service wiring
	// -----------------------------------------------------------------

	/**
	 * Instantiates and wires every subsystem.
	 */
	private function register_services(): void {
		// License check first.
		$this->license_manager = new LicenseManager();

		// Core assets.
		$this->assets = new Assets();
		$this->assets->init( $this->loader );

		// Promotion engine (cart-independent, evaluates rules).
		$this->promotion_engine = new PromotionEngine();
		$this->promotion_engine->init( $this->loader );

		// Cart engine (applies promotions to the WC cart).
		$this->cart_engine = new CartEngine( $this->promotion_engine );
		$this->cart_engine->init( $this->loader );

		// Gift manager.
		$this->gift_manager = new GiftManager( $this->promotion_engine );
		$this->gift_manager->init( $this->loader );

		// Scheduler (Action Scheduler integration).
		$this->scheduler = new Scheduler();
		$this->scheduler->init( $this->loader );

		// REST API.
		$this->rest_api = new RestAPI();
		$this->rest_api->init( $this->loader );

		// Admin dashboard (only in admin context).
		if ( is_admin() ) {
			$this->admin = new Admin(
				$this->promotion_engine,
				$this->license_manager
			);
			$this->admin->init( $this->loader );
		}

		// Frontend notices & UI.
		$this->frontend = new Frontend( $this->promotion_engine );
		$this->frontend->init( $this->loader );
	}

	// -----------------------------------------------------------------
	// i18n
	// -----------------------------------------------------------------

	/**
	 * Loads plugin textdomain.
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'matrix-bogo',
			false,
			dirname( MATRIX_BOGO_PLUGIN_BASENAME ) . '/languages'
		);
	}

	// -----------------------------------------------------------------
	// Guards
	// -----------------------------------------------------------------

	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	private function is_woocommerce_version_ok(): bool {
		return defined( 'WC_VERSION' ) &&
			version_compare( WC_VERSION, MATRIX_BOGO_MIN_WC_VERSION, '>=' );
	}

	// -----------------------------------------------------------------
	// Admin notices
	// -----------------------------------------------------------------

	/** @internal */
	public function notice_woocommerce_missing(): void {
		echo '<div class="notice notice-error"><p>' .
			wp_kses_post(
				sprintf(
					/* translators: %s: WooCommerce plugin link */
					__( 'Matrix BOGO requires %s to be installed and activated.', 'matrix-bogo' ),
					'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
				)
			) .
			'</p></div>';
	}

	/** @internal */
	public function notice_woocommerce_old(): void {
		echo '<div class="notice notice-error"><p>' .
			esc_html(
				sprintf(
					/* translators: %s: minimum WooCommerce version */
					__( 'Matrix BOGO requires WooCommerce %s or higher.', 'matrix-bogo' ),
					MATRIX_BOGO_MIN_WC_VERSION
				)
			) .
			'</p></div>';
	}

	// -----------------------------------------------------------------
	// Accessors (allow other code to grab shared service instances)
	// -----------------------------------------------------------------

	public function get_promotion_engine(): PromotionEngine {
		return $this->promotion_engine;
	}

	public function get_cart_engine(): CartEngine {
		return $this->cart_engine;
	}

	public function get_gift_manager(): GiftManager {
		return $this->gift_manager;
	}

	public function get_license_manager(): LicenseManager {
		return $this->license_manager;
	}

	public function get_loader(): Loader {
		return $this->loader;
	}
}
