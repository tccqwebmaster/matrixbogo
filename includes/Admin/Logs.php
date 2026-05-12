<?php
/**
 * Logs admin page.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

use MatrixBogo\Database\Repositories\LogsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logs
 */
final class Logs {

	/** @var LogsRepository */
	private LogsRepository $repo;

	public function __construct() {
		$this->repo = new LogsRepository();
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'matrix-bogo' ) );
		}

		// Handle clear action.
		if ( isset( $_POST['matrix_bogo_clear_logs'] ) ) {
			check_admin_referer( 'matrix_bogo_clear_logs_nonce', 'matrix_bogo_clear_logs_nonce_field' );
			$days = max( 0, absint( wp_unslash( $_POST['prune_days'] ?? 0 ) ) );
			$this->repo->prune( $days );
			add_settings_error( 'matrix_bogo_logs', 'pruned', __( 'Logs cleared.', 'matrix-bogo' ), 'success' );
		}

		$per_page = 50;
		$page     = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) );
		$level    = sanitize_key( $_GET['level'] ?? '' );
		$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

		$result = $this->repo->paginate( [
			'per_page' => $per_page,
			'page'     => $page,
			'level'    => $level ?: null,
			'search'   => $search ?: null,
		] );

		$rows  = $result['rows'] ?? [];
		$total = $result['total'] ?? 0;
		?>
		<div class="wrap matrix-bogo-admin">
			<h1><?php esc_html_e( 'Logs', 'matrix-bogo' ); ?></h1>

			<?php settings_errors( 'matrix_bogo_logs' ); ?>

			<!-- Filter bar -->
			<form method="get" style="margin-bottom:10px;">
				<input type="hidden" name="page" value="matrix-bogo-logs">
				<select name="level" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All Levels', 'matrix-bogo' ); ?></option>
					<?php foreach ( [ 'info', 'warning', 'error', 'debug' ] as $l ) : ?>
						<option value="<?php echo esc_attr( $l ); ?>" <?php selected( $level, $l ); ?>>
							<?php echo esc_html( ucfirst( $l ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
				       placeholder="<?php esc_attr_e( 'Search…', 'matrix-bogo' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'matrix-bogo' ); ?></button>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:150px;"><?php esc_html_e( 'Date', 'matrix-bogo' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Level', 'matrix-bogo' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Event', 'matrix-bogo' ); ?></th>
						<th><?php esc_html_e( 'Message', 'matrix-bogo' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Rule', 'matrix-bogo' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No logs found.', 'matrix-bogo' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr class="matrix-bogo-log-<?php echo esc_attr( $row['level'] ); ?>">
							<td><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', strtotime( $row['created_at'] ) ) ); ?></td>
							<td>
								<span class="matrix-bogo-log-level matrix-bogo-log-<?php echo esc_attr( $row['level'] ); ?>">
									<?php echo esc_html( strtoupper( $row['level'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $row['event'] ); ?></td>
							<td><?php echo esc_html( $row['message'] ); ?></td>
							<td><?php echo $row['rule_id'] ? esc_html( '#' . $row['rule_id'] ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total > $per_page ) :
				echo wp_kses_post( paginate_links( [
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $page,
					'total'   => ceil( $total / $per_page ),
				] ) );
			endif; ?>

			<!-- Clear Logs -->
			<div class="matrix-bogo-panel" style="margin-top:20px;">
				<h3><?php esc_html_e( 'Clear Logs', 'matrix-bogo' ); ?></h3>
				<form method="post">
					<?php wp_nonce_field( 'matrix_bogo_clear_logs_nonce', 'matrix_bogo_clear_logs_nonce_field' ); ?>
					<label>
						<?php esc_html_e( 'Delete logs older than', 'matrix-bogo' ); ?>
						<input type="number" name="prune_days" value="30" min="0" class="small-text">
						<?php esc_html_e( 'days', 'matrix-bogo' ); ?>
					</label>
					<button type="submit" name="matrix_bogo_clear_logs" class="button">
						<?php esc_html_e( 'Clear Logs', 'matrix-bogo' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
	}
}
