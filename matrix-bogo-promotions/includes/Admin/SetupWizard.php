<?php
/**
 * Setup Wizard – onboarding first-run wizard.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SetupWizard
 */
final class SetupWizard {

	private const TRANSIENT = 'matrix_bogo_do_setup_wizard';

	// -----------------------------------------------------------------
	// Redirect check
	// -----------------------------------------------------------------

	public function maybe_redirect(): void {
		if ( ! get_transient( self::TRANSIENT ) ) {
			return;
		}

		if ( wp_doing_ajax() || is_network_admin() ) {
			return;
		}

		delete_transient( self::TRANSIENT );

		// Only redirect if no other plugins are being activated.
		if ( ! isset( $_GET['activate-multi'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=matrix-bogo-setup-wizard' ) );
			exit;
		}
	}

	// -----------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'matrix-bogo' ) );
		}
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<title><?php esc_html_e( 'Matrix BOGO Setup', 'matrix-bogo' ); ?></title>
			<?php wp_print_styles( 'matrix-bogo-admin' ); ?>
		</head>
		<body class="matrix-bogo-wizard">
			<div class="matrix-bogo-wizard-wrap">
				<div class="matrix-bogo-wizard-header">
					<h1><?php esc_html_e( 'Welcome to Matrix BOGO!', 'matrix-bogo' ); ?></h1>
					<p><?php esc_html_e( "Let's create your first promotion in seconds.", 'matrix-bogo' ); ?></p>
				</div>

				<div class="matrix-bogo-wizard-body">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-bogo-promotions&action=new' ) ); ?>"
					   class="button button-primary button-hero">
						<?php esc_html_e( 'Create My First Promotion', 'matrix-bogo' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-bogo' ) ); ?>"
					   class="button button-hero">
						<?php esc_html_e( 'Skip Wizard', 'matrix-bogo' ); ?>
					</a>
				</div>
			</div>
		</body>
		</html>
		<?php
		exit;
	}
}
