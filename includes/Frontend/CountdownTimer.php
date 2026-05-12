<?php
/**
 * Countdown Timer – shows a live urgency timer for time-limited promotions.
 *
 * @package MatrixBogo\Frontend
 */

declare( strict_types=1 );

namespace MatrixBogo\Frontend;

use MatrixBogo\Core\Loader;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CountdownTimer
 *
 * Renders countdown banners on cart, checkout, shop and product pages.
 * The countdown is driven entirely by JS (countdown.js) — PHP only outputs
 * the data-end ISO timestamp and the DOM skeleton.
 */
final class CountdownTimer {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	public function __construct( PromotionEngine $engine ) {
		$this->engine = $engine;
	}

	public function init( Loader $loader ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );
		if ( empty( $settings['countdown_timer_enabled'] ) ) {
			return;
		}

		$loader->add_action( 'woocommerce_before_cart',           [ $this, 'render_banner' ], 3 );
		$loader->add_action( 'woocommerce_before_checkout_form',  [ $this, 'render_banner' ], 3 );
		$loader->add_action( 'woocommerce_single_product_summary', [ $this, 'render_product_badge' ], 26 );
		$loader->add_action( 'woocommerce_before_shop_loop',       [ $this, 'render_shop_banner' ], 10 );
	}

	// -----------------------------------------------------------------
	// Render: full banner (cart / checkout)
	// -----------------------------------------------------------------

	public function render_banner(): void {
		$rule = $this->get_soonest_expiring_rule();
		if ( ! $rule ) {
			return;
		}

		$end_iso = gmdate( 'c', strtotime( $rule['schedule_end'] ) );
		?>
		<div class="mb-countdown-banner" data-end="<?php echo esc_attr( $end_iso ); ?>">
			<div class="mb-countdown-banner-inner">
				<span class="mb-countdown-fire" aria-hidden="true">🔥</span>
				<div class="mb-countdown-info">
					<span class="mb-countdown-name"><?php echo esc_html( $rule['name'] ); ?></span>
					<span class="mb-countdown-label"><?php esc_html_e( 'Offer ends in', 'matrix-bogo' ); ?></span>
				</div>
				<div class="mb-countdown-clock">
					<div class="mb-clock-unit">
						<span class="mb-clock-val" data-unit="hours">00</span>
						<span class="mb-clock-lbl"><?php esc_html_e( 'hrs', 'matrix-bogo' ); ?></span>
					</div>
					<span class="mb-clock-sep" aria-hidden="true">:</span>
					<div class="mb-clock-unit">
						<span class="mb-clock-val" data-unit="minutes">00</span>
						<span class="mb-clock-lbl"><?php esc_html_e( 'min', 'matrix-bogo' ); ?></span>
					</div>
					<span class="mb-clock-sep" aria-hidden="true">:</span>
					<div class="mb-clock-unit">
						<span class="mb-clock-val" data-unit="seconds">00</span>
						<span class="mb-clock-lbl"><?php esc_html_e( 'sec', 'matrix-bogo' ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------
	// Render: compact badge (single product page)
	// -----------------------------------------------------------------

	public function render_product_badge(): void {
		$rule = $this->get_soonest_expiring_rule();
		if ( ! $rule ) {
			return;
		}

		$end_iso = gmdate( 'c', strtotime( $rule['schedule_end'] ) );
		?>
		<div class="mb-product-timer" data-end="<?php echo esc_attr( $end_iso ); ?>">
			<span class="mb-product-timer-icon" aria-hidden="true">⏰</span>
			<span><?php esc_html_e( 'Limited offer — ends in', 'matrix-bogo' ); ?></span>
			<strong class="mb-product-timer-value"></strong>
		</div>
		<?php
	}

	// -----------------------------------------------------------------
	// Render: shop/archive banner
	// -----------------------------------------------------------------

	public function render_shop_banner(): void {
		$rule = $this->get_soonest_expiring_rule();
		if ( ! $rule ) {
			return;
		}

		$end_iso = gmdate( 'c', strtotime( $rule['schedule_end'] ) );
		?>
		<div class="mb-shop-ticker" data-end="<?php echo esc_attr( $end_iso ); ?>">
			<span aria-hidden="true">🔥</span>
			<strong><?php echo esc_html( $rule['name'] ); ?></strong>
			<span><?php esc_html_e( '— ends in', 'matrix-bogo' ); ?></span>
			<span class="mb-shop-ticker-time"></span>
		</div>
		<?php
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * Returns the active rule with the soonest schedule_end, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_soonest_expiring_rule(): ?array {
		$rules   = $this->engine->get_rules_repository()->get_active_rules();
		$now     = time();
		$soonest = null;

		foreach ( $rules as $rule ) {
			if ( empty( $rule['schedule_end'] ) ) {
				continue;
			}
			$end_ts = strtotime( $rule['schedule_end'] );
			if ( $end_ts <= $now ) {
				continue;
			}
			if ( ! $soonest || $end_ts < strtotime( $soonest['schedule_end'] ) ) {
				$soonest = $rule;
			}
		}

		return $soonest;
	}
}
