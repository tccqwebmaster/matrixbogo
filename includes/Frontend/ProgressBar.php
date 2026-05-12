<?php
/**
 * Progress Bar – renders a live cart progress bar toward promotion thresholds.
 *
 * @package MatrixBogo\Frontend
 */

declare( strict_types=1 );

namespace MatrixBogo\Frontend;

use MatrixBogo\Core\Loader;
use MatrixBogo\Database\Repositories\RulesRepository;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProgressBar
 *
 * Evaluates active rules against the current cart and renders a styled
 * progress bar for each rule that has a measurable threshold.
 *
 * Supports:
 *   - spend_amount_get_gift  ($ remaining)
 *   - cart_quantity_get_gift (items remaining)
 *   - buy_x_get_y / buy_x_get_x (trigger qty remaining)
 */
final class ProgressBar {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	/** @var RulesRepository */
	private RulesRepository $rules_repo;

	public function __construct( PromotionEngine $engine ) {
		$this->engine     = $engine;
		$this->rules_repo = $engine->get_rules_repository();
	}

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------

	public function init( Loader $loader ): void {
		$settings = get_option( 'matrix_bogo_settings', [] );
		if ( empty( $settings['progress_bar_enabled'] ) ) {
			return;
		}

		$loader->add_action( 'woocommerce_before_cart',          [ $this, 'render' ], 4 );
		$loader->add_action( 'woocommerce_before_checkout_form', [ $this, 'render' ], 4 );

		// AJAX refresh — called by frontend.js after cart update.
		$loader->add_action( 'wp_ajax_matrix_bogo_get_progress',        [ $this, 'ajax_progress' ] );
		$loader->add_action( 'wp_ajax_nopriv_matrix_bogo_get_progress',  [ $this, 'ajax_progress' ] );
	}

	// -----------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------

	public function render(): void {
		$bars = $this->build_bars();
		if ( empty( $bars ) ) {
			return;
		}
		echo '<div class="mb-progress-region" id="mb-progress-region">';
		foreach ( $bars as $bar ) {
			$this->render_bar( $bar );
		}
		echo '</div>';
	}

	private function render_bar( array $bar ): void {
		$pct   = min( 100, round( $bar['percent'], 1 ) );
		$done  = $pct >= 100;
		$class = 'mb-progress-card' . ( $done ? ' mb-progress-card--done' : '' );
		?>
		<div class="<?php echo esc_attr( $class ); ?>" data-rule-id="<?php echo esc_attr( (string) $bar['rule_id'] ); ?>">
			<div class="mb-progress-message">
				<span class="mb-progress-emoji" aria-hidden="true">
					<?php echo $done ? '🎉' : '🎁'; ?>
				</span>
				<span class="mb-progress-text">
					<?php echo wp_kses_post( $done ? $bar['message_done'] : $bar['message'] ); ?>
				</span>
			</div>

			<div class="mb-progress-track" role="progressbar"
			     aria-valuenow="<?php echo esc_attr( (string) $pct ); ?>"
			     aria-valuemin="0" aria-valuemax="100">
				<div class="mb-progress-fill" style="width:<?php echo esc_attr( $pct . '%' ); ?>">
					<?php if ( $pct > 18 && ! $done ) : ?>
						<span class="mb-progress-pct-label"><?php echo esc_html( round( $pct ) . '%' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! $done && ! empty( $bar['schedule_end'] ) ) : ?>
				<div class="mb-progress-timer">
					<span class="mb-progress-timer-label"><?php esc_html_e( 'Offer ends in', 'matrix-bogo' ); ?></span>
					<span class="mb-inline-countdown"
					      data-end="<?php echo esc_attr( gmdate( 'c', strtotime( $bar['schedule_end'] ) ) ); ?>">
					</span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------
	// AJAX
	// -----------------------------------------------------------------

	public function ajax_progress(): void {
		check_ajax_referer( 'matrix_bogo_frontend', 'nonce' );
		ob_start();
		$this->render();
		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => (string) $html ] );
	}

	// -----------------------------------------------------------------
	// Data builder
	// -----------------------------------------------------------------

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private function build_bars(): array {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return [];
		}

		$rules    = $this->rules_repo->get_active_rules();
		$subtotal = (float) $cart->get_subtotal();
		$qty      = (int) $cart->get_cart_contents_count();
		$bars     = [];

		foreach ( $rules as $rule ) {
			$rd  = ! empty( $rule['rule_data'] ) ? (array) json_decode( (string) $rule['rule_data'], true ) : [];
			$bar = null;

			switch ( $rule['type'] ) {

				case 'spend_amount_get_gift':
					$min = (float) ( $rd['min_amount'] ?? 0 );
					if ( $min <= 0 ) {
						break;
					}
					$remaining = max( 0.0, $min - $subtotal );
					$bar = [
						'rule_id'      => $rule['id'],
						'percent'      => ( $subtotal / $min ) * 100,
						'schedule_end' => $rule['schedule_end'] ?? '',
						'message'      => sprintf(
							/* translators: %s: price */
							__( 'Add <strong>%s more</strong> to unlock your free gift! 🎁', 'matrix-bogo' ),
							wc_price( $remaining )
						),
						'message_done' => __( '<strong>Free gift unlocked!</strong> Choose your reward below.', 'matrix-bogo' ),
					];
					break;

				case 'cart_quantity_get_gift':
					$min = (int) ( $rd['min_quantity'] ?? 0 );
					if ( $min <= 0 ) {
						break;
					}
					$remaining = max( 0, $min - $qty );
					$bar = [
						'rule_id'      => $rule['id'],
						'percent'      => ( $qty / $min ) * 100,
						'schedule_end' => $rule['schedule_end'] ?? '',
						'message'      => sprintf(
							/* translators: %d: item count */
							_n(
								'Add <strong>%d more item</strong> to unlock your free gift! 🎁',
								'Add <strong>%d more items</strong> to unlock your free gift! 🎁',
								$remaining,
								'matrix-bogo'
							),
							$remaining
						),
						'message_done' => __( '<strong>Free gift unlocked!</strong> Choose your reward below.', 'matrix-bogo' ),
					];
					break;

				case 'buy_x_get_y':
				case 'buy_x_get_x':
					$ids     = array_filter( array_map( 'intval', explode( ',', (string) ( $rd['trigger_product_ids'] ?? '' ) ) ) );
					$req_qty = max( 1, (int) ( $rd['trigger_quantity'] ?? 1 ) );
					if ( empty( $ids ) ) {
						break;
					}

					$matching = 0;
					foreach ( $cart->get_cart() as $item ) {
						if ( ! empty( $item['matrix_bogo_gift'] ) ) {
							continue;
						}
						if ( in_array( (int) $item['product_id'], $ids, true ) ) {
							$matching += (int) $item['quantity'];
						}
					}

					$remaining = max( 0, $req_qty - $matching );
					// Build a short product name label.
					$names = [];
					foreach ( array_slice( $ids, 0, 2 ) as $pid ) {
						$p = wc_get_product( $pid );
						if ( $p ) {
							$names[] = $p->get_name();
						}
					}
					$label = ! empty( $names )
						? implode( ' ' . __( 'or', 'matrix-bogo' ) . ' ', $names )
						: __( 'qualifying product', 'matrix-bogo' );

					$bar = [
						'rule_id'      => $rule['id'],
						'percent'      => ( $matching / $req_qty ) * 100,
						'schedule_end' => $rule['schedule_end'] ?? '',
						'message'      => sprintf(
							/* translators: 1: count, 2: product name */
							_n(
								'Add <strong>%1$d more %2$s</strong> to unlock BOGO! 🎁',
								'Add <strong>%1$d more %2$s</strong> to unlock BOGO! 🎁',
								$remaining,
								'matrix-bogo'
							),
							$remaining,
							esc_html( $label )
						),
						'message_done' => __( '<strong>BOGO unlocked!</strong> Your free item has been added.', 'matrix-bogo' ),
					];
					break;
			}

			if ( $bar ) {
				$bars[] = $bar;
			}
		}

		return $bars;
	}
}
