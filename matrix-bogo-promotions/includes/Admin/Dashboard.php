<?php
/**
 * Admin Dashboard page.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

use MatrixBogo\Database\Repositories\AnalyticsRepository;
use MatrixBogo\Database\Repositories\RulesRepository;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard
 */
final class Dashboard {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	/** @var RulesRepository */
	private RulesRepository $rules_repo;

	/** @var AnalyticsRepository */
	private AnalyticsRepository $analytics_repo;

	public function __construct( PromotionEngine $engine ) {
		$this->engine         = $engine;
		$this->rules_repo     = new RulesRepository();
		$this->analytics_repo = new AnalyticsRepository();
	}

	/**
	 * Renders the dashboard HTML.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'matrix-bogo' ) );
		}

		$active_count   = $this->rules_repo->count( [ 'status' => 'active' ] );
		$inactive_count = $this->rules_repo->count( [ 'status' => 'inactive' ] );
		$scheduled_count = $this->rules_repo->count( [ 'status' => 'scheduled' ] );

		$today    = date( 'Y-m-d' );
		$from_7d  = date( 'Y-m-d', strtotime( '-7 days' ) );
		$totals   = $this->analytics_repo->get_totals( $from_7d, $today );
		$top_rules = $this->analytics_repo->get_top_rules( $from_7d, $today, 5 );
		?>
		<div class="wrap matrix-bogo-admin">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Matrix BOGO Dashboard', 'matrix-bogo' ); ?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-bogo-promotions&action=new' ) ); ?>"
			   class="page-title-action">
				<?php esc_html_e( '+ New Promotion', 'matrix-bogo' ); ?>
			</a>
			<hr class="wp-header-end">

			<!-- Stats Cards -->
			<div class="matrix-bogo-stats-grid">
				<div class="matrix-bogo-stat-card matrix-bogo-stat-active">
					<span class="matrix-bogo-stat-number"><?php echo esc_html( $active_count ); ?></span>
					<span class="matrix-bogo-stat-label"><?php esc_html_e( 'Active Promotions', 'matrix-bogo' ); ?></span>
				</div>
				<div class="matrix-bogo-stat-card matrix-bogo-stat-scheduled">
					<span class="matrix-bogo-stat-number"><?php echo esc_html( $scheduled_count ); ?></span>
					<span class="matrix-bogo-stat-label"><?php esc_html_e( 'Scheduled', 'matrix-bogo' ); ?></span>
				</div>
				<div class="matrix-bogo-stat-card matrix-bogo-stat-revenue">
					<span class="matrix-bogo-stat-number">
						<?php echo wp_kses_post( wc_price( (float) ( $totals['total_revenue'] ?? 0 ) ) ); ?>
					</span>
					<span class="matrix-bogo-stat-label"><?php esc_html_e( 'Revenue (7d)', 'matrix-bogo' ); ?></span>
				</div>
				<div class="matrix-bogo-stat-card matrix-bogo-stat-redemptions">
					<span class="matrix-bogo-stat-number">
						<?php echo esc_html( number_format_i18n( (int) ( $totals['total_redemptions'] ?? 0 ) ) ); ?>
					</span>
					<span class="matrix-bogo-stat-label"><?php esc_html_e( 'Redemptions (7d)', 'matrix-bogo' ); ?></span>
				</div>
			</div>

			<!-- Top Performing Promotions -->
			<div class="matrix-bogo-panel">
				<h2><?php esc_html_e( 'Top Promotions (Last 7 Days)', 'matrix-bogo' ); ?></h2>
				<?php if ( empty( $top_rules ) ) : ?>
					<p><?php esc_html_e( 'No analytics data yet.', 'matrix-bogo' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Rule', 'matrix-bogo' ); ?></th>
								<th><?php esc_html_e( 'Redemptions', 'matrix-bogo' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'matrix-bogo' ); ?></th>
								<th><?php esc_html_e( 'Discount Given', 'matrix-bogo' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $top_rules as $stat ) :
							$rule = $this->rules_repo->find( (int) $stat['rule_id'] );
							if ( ! $rule ) {
								continue;
							}
							?>
							<tr>
								<td><?php echo esc_html( $rule['name'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $stat['redemptions'] ) ); ?></td>
								<td><?php echo wp_kses_post( wc_price( (float) $stat['revenue'] ) ); ?></td>
								<td><?php echo wp_kses_post( wc_price( (float) $stat['discount_total'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Quick Links -->
			<div class="matrix-bogo-panel">
				<h2><?php esc_html_e( 'Quick Actions', 'matrix-bogo' ); ?></h2>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-bogo-promotions&action=new' ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Create Promotion', 'matrix-bogo' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-bogo-analytics' ) ); ?>"
					   class="button">
						<?php esc_html_e( 'View Analytics', 'matrix-bogo' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-bogo-settings' ) ); ?>"
					   class="button">
						<?php esc_html_e( 'Settings', 'matrix-bogo' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}
