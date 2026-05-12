<?php
/**
 * Analytics admin page.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

use MatrixBogo\Database\Repositories\AnalyticsRepository;
use MatrixBogo\Database\Repositories\RulesRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Analytics
 */
final class Analytics {

	/** @var AnalyticsRepository */
	private AnalyticsRepository $repo;

	/** @var RulesRepository */
	private RulesRepository $rules;

	public function __construct() {
		$this->repo  = new AnalyticsRepository();
		$this->rules = new RulesRepository();
	}

	// -----------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'matrix-bogo' ) );
		}

		$range   = sanitize_key( $_GET['range'] ?? '30' );
		$days    = (int) $range ?: 30;
		$days    = min( $days, 365 );
		$from    = date( 'Y-m-d', strtotime( "-{$days} days" ) );
		$to      = date( 'Y-m-d' );
		$totals  = $this->repo->get_totals( $from, $to );
		$daily   = $this->repo->get_daily_totals( $from, $to );
		$top     = $this->repo->get_top_rules( $from, $to, 10 );
		?>
		<div class="wrap matrix-bogo-admin">
			<h1><?php esc_html_e( 'Analytics', 'matrix-bogo' ); ?></h1>

			<!-- Range Selector -->
			<form method="get" style="margin:10px 0;">
				<input type="hidden" name="page" value="matrix-bogo-analytics">
				<select name="range" onchange="this.form.submit()">
					<?php foreach ( [ 7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '1 year' ] as $d => $label ) : ?>
						<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $days, $d ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>

			<!-- Summary Cards -->
			<div class="matrix-bogo-stats-grid">
				<div class="matrix-bogo-stat-card">
					<span class="matrix-bogo-stat-number">
						<?php echo esc_html( number_format_i18n( (int) ( $totals['total_redemptions'] ?? 0 ) ) ); ?>
					</span>
					<span class="matrix-bogo-stat-label"><?php esc_html_e( 'Total Redemptions', 'matrix-bogo' ); ?></span>
				</div>
				<div class="matrix-bogo-stat-card">
					<span class="matrix-bogo-stat-number">
						<?php echo wp_kses_post( wc_price( (float) ( $totals['total_revenue'] ?? 0 ) ) ); ?>
					</span>
					<span class="matrix-bogo-stat-label"><?php esc_html_e( 'Attributed Revenue', 'matrix-bogo' ); ?></span>
				</div>
				<div class="matrix-bogo-stat-card">
					<span class="matrix-bogo-stat-number">
						<?php echo wp_kses_post( wc_price( (float) ( $totals['total_discount'] ?? 0 ) ) ); ?>
					</span>
					<span class="matrix-bogo-stat-label"><?php esc_html_e( 'Discount Given', 'matrix-bogo' ); ?></span>
				</div>
			</div>

			<!-- Daily Chart (rendered by admin.js) -->
			<div class="matrix-bogo-panel">
				<h2><?php esc_html_e( 'Daily Redemptions', 'matrix-bogo' ); ?></h2>
				<canvas id="matrix-bogo-daily-chart" height="100"
				        data-daily="<?php echo esc_attr( wp_json_encode( $daily ) ); ?>">
				</canvas>
			</div>

			<!-- Top Rules Table -->
			<div class="matrix-bogo-panel">
				<h2><?php esc_html_e( 'Top Performing Promotions', 'matrix-bogo' ); ?></h2>
				<?php if ( empty( $top ) ) : ?>
					<p><?php esc_html_e( 'No data available for this period.', 'matrix-bogo' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Promotion', 'matrix-bogo' ); ?></th>
								<th><?php esc_html_e( 'Redemptions', 'matrix-bogo' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'matrix-bogo' ); ?></th>
								<th><?php esc_html_e( 'Discount', 'matrix-bogo' ); ?></th>
								<th><?php esc_html_e( 'Conversion Rate', 'matrix-bogo' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $top as $row ) :
							$rule = $this->rules->find( (int) $row['rule_id'] );
							$cr   = $row['impressions'] > 0
								? round( ( $row['redemptions'] / $row['impressions'] ) * 100, 2 )
								: 0;
							?>
							<tr>
								<td><?php echo esc_html( $rule['name'] ?? __( 'Unknown', 'matrix-bogo' ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['redemptions'] ) ); ?></td>
								<td><?php echo wp_kses_post( wc_price( (float) $row['revenue'] ) ); ?></td>
								<td><?php echo wp_kses_post( wc_price( (float) $row['discount_total'] ) ); ?></td>
								<td><?php echo esc_html( $cr . '%' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------
	// AJAX
	// -----------------------------------------------------------------

	public function ajax_get_data(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$days  = max( 1, absint( wp_unslash( $_POST['days'] ?? 30 ) ) );
		$from  = date( 'Y-m-d', strtotime( "-{$days} days" ) );
		$to    = date( 'Y-m-d' );

		wp_send_json_success( [
			'totals' => $this->repo->get_totals( $from, $to ),
			'daily'  => $this->repo->get_daily_totals( $from, $to ),
			'top'    => $this->repo->get_top_rules( $from, $to, 10 ),
		] );
	}
}
