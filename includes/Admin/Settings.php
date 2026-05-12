<?php
/**
 * Plugin Settings page.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

use MatrixBogo\License\LicenseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
final class Settings {

	/** @var LicenseManager */
	private LicenseManager $license;

	/** Settings option key. */
	private const OPTION = 'matrix_bogo_settings';

	public function __construct( LicenseManager $license ) {
		$this->license = $license;
	}

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------

	public function register_settings(): void {
		register_setting(
			'matrix_bogo_settings_group',
			self::OPTION,
			[ $this, 'sanitize_settings' ]
		);
	}

	// -----------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'matrix-bogo' ) );
		}

		if ( isset( $_POST['save_matrix_bogo_settings'] ) ) {
			check_admin_referer( 'matrix_bogo_settings_nonce', 'matrix_bogo_settings_nonce_field' );
			$this->handle_save();
		}

		$s = get_option( self::OPTION, [] );
		?>
		<div class="wrap matrix-bogo-admin">
			<h1><?php esc_html_e( 'Matrix BOGO Settings', 'matrix-bogo' ); ?></h1>

			<?php settings_errors( 'matrix_bogo_settings' ); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'matrix_bogo_settings_nonce', 'matrix_bogo_settings_nonce_field' ); ?>

				<!-- ── General ──────────────────────────────────────────── -->
				<h2 class="nav-tab-wrapper" style="margin-bottom:0;">
					<?php esc_html_e( 'General', 'matrix-bogo' ); ?>
				</h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Auto-Apply Promotions', 'matrix-bogo' ); ?></th>
						<td>
							<input type="checkbox" name="auto_apply_promotions" value="1"
							       <?php checked( ! empty( $s['auto_apply_promotions'] ) ); ?>>
							<span><?php esc_html_e( 'Automatically add free gifts to cart when eligible', 'matrix-bogo' ); ?></span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show Promotion Notices', 'matrix-bogo' ); ?></th>
						<td>
							<input type="checkbox" name="show_notices" value="1"
							       <?php checked( ! empty( $s['show_notices'] ) ); ?>>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show Progress Bar', 'matrix-bogo' ); ?></th>
						<td>
							<input type="checkbox" name="show_progress_bar" value="1"
							       <?php checked( ! empty( $s['show_progress_bar'] ) ); ?>>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show Product Badges', 'matrix-bogo' ); ?></th>
						<td>
							<input type="checkbox" name="show_product_badges" value="1"
							       <?php checked( ! empty( $s['show_product_badges'] ) ); ?>>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Gift Badge Text', 'matrix-bogo' ); ?></th>
						<td>
							<input type="text" name="gift_badge_text" class="regular-text"
							       value="<?php echo esc_attr( $s['gift_badge_text'] ?? __( 'FREE GIFT', 'matrix-bogo' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( '"Free" Price Label', 'matrix-bogo' ); ?></th>
						<td>
							<input type="text" name="free_price_label" class="regular-text"
							       value="<?php echo esc_attr( $s['free_price_label'] ?? __( 'FREE', 'matrix-bogo' ) ); ?>">
						</td>
					</tr>
				</table>

				<!-- ── Logs ─────────────────────────────────────────────── -->
				<h2><?php esc_html_e( 'Logging', 'matrix-bogo' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable Logging', 'matrix-bogo' ); ?></th>
						<td>
							<input type="checkbox" name="enable_logging" value="1"
							       <?php checked( ! empty( $s['enable_logging'] ) ); ?>>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Log Retention (days)', 'matrix-bogo' ); ?></th>
						<td>
							<input type="number" name="log_retention_days"
							       value="<?php echo esc_attr( $s['log_retention_days'] ?? 30 ); ?>"
							       min="1" max="365" class="small-text">
						</td>
					</tr>
				</table>

				<!-- ── Danger Zone ──────────────────────────────────────── -->
				<h2><?php esc_html_e( 'Danger Zone', 'matrix-bogo' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Delete Data on Uninstall', 'matrix-bogo' ); ?></th>
						<td>
							<input type="checkbox" name="delete_data_on_uninstall" value="1"
							       <?php checked( ! empty( $s['delete_data_on_uninstall'] ) ); ?>>
							<span style="color:#c00;">
								<?php esc_html_e( 'WARNING: All plugin data will be permanently deleted when the plugin is uninstalled.', 'matrix-bogo' ); ?>
							</span>
						</td>
					</tr>
				</table>

				<!-- ── License ──────────────────────────────────────────── -->
				<h2><?php esc_html_e( 'License', 'matrix-bogo' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'License Key', 'matrix-bogo' ); ?></th>
						<td>
							<input type="text" name="license_key" class="regular-text"
							       value="<?php echo esc_attr( $this->license->get_license_key() ); ?>">
							<button type="button" class="button" id="matrix-bogo-activate-license"
							        data-nonce="<?php echo esc_attr( wp_create_nonce( 'matrix_bogo_admin' ) ); ?>">
								<?php esc_html_e( 'Activate', 'matrix-bogo' ); ?>
							</button>
							<span id="matrix-bogo-license-status" class="<?php echo esc_attr( $this->license->is_valid() ? 'valid' : 'invalid' ); ?>">
								<?php echo esc_html( $this->license->get_status_label() ); ?>
							</span>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="save_matrix_bogo_settings"
					       class="button button-primary"
					       value="<?php esc_attr_e( 'Save Settings', 'matrix-bogo' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	// -----------------------------------------------------------------
	// Save
	// -----------------------------------------------------------------

	private function handle_save(): void {
		$data = $this->sanitize_settings( $_POST );
		update_option( self::OPTION, $data );
		add_settings_error( 'matrix_bogo_settings', 'saved', __( 'Settings saved.', 'matrix-bogo' ), 'success' );
	}

	public function sanitize_settings( array $input ): array {
		return [
			'auto_apply_promotions'   => ! empty( $input['auto_apply_promotions'] ) ? 1 : 0,
			'show_notices'            => ! empty( $input['show_notices'] ) ? 1 : 0,
			'show_progress_bar'       => ! empty( $input['show_progress_bar'] ) ? 1 : 0,
			'show_product_badges'     => ! empty( $input['show_product_badges'] ) ? 1 : 0,
			'gift_badge_text'         => sanitize_text_field( $input['gift_badge_text'] ?? '' ),
			'free_price_label'        => sanitize_text_field( $input['free_price_label'] ?? '' ),
			'enable_logging'          => ! empty( $input['enable_logging'] ) ? 1 : 0,
			'log_retention_days'      => max( 1, (int) ( $input['log_retention_days'] ?? 30 ) ),
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ) ? 1 : 0,
			'license_key'             => sanitize_text_field( $input['license_key'] ?? '' ),
		];
	}

	// -----------------------------------------------------------------
	// AJAX: license activation
	// -----------------------------------------------------------------

	public function ajax_activate_license(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$key    = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
		$result = $this->license->activate( $key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'License activated successfully.', 'matrix-bogo' ) ] );
	}
}
