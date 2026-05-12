<?php
/**
 * Template: Gift selector popup.
 *
 * @package MatrixBogo
 * Rendered by GiftPopup::render_popup_container() in wp_footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="matrix-bogo-gift-popup" class="matrix-bogo-popup" style="display:none;"
     role="dialog" aria-modal="true"
     aria-labelledby="matrix-bogo-popup-title">
	<div class="matrix-bogo-popup-overlay"></div>
	<div class="matrix-bogo-popup-content">
		<button class="matrix-bogo-popup-close" aria-label="<?php esc_attr_e( 'Close', 'matrix-bogo' ); ?>">&times;</button>

		<h2 id="matrix-bogo-popup-title">
			<?php esc_html_e( 'Choose Your Free Gift!', 'matrix-bogo' ); ?>
		</h2>
		<p class="matrix-bogo-popup-subtitle">
			<?php esc_html_e( "You've unlocked a free gift. Select your reward below.", 'matrix-bogo' ); ?>
		</p>

		<div id="matrix-bogo-popup-products" class="matrix-bogo-popup-products">
			<!-- Products injected by gift-popup.js -->
			<div class="matrix-bogo-popup-loading">
				<span class="spinner is-active"></span>
				<?php esc_html_e( 'Loading gifts…', 'matrix-bogo' ); ?>
			</div>
		</div>

		<div class="matrix-bogo-popup-actions">
			<button class="button button-primary" id="matrix-bogo-popup-add-btn" disabled>
				<?php esc_html_e( 'Add to Cart', 'matrix-bogo' ); ?>
			</button>
			<button class="button matrix-bogo-popup-skip">
				<?php esc_html_e( 'No Thanks', 'matrix-bogo' ); ?>
			</button>
		</div>
	</div>
</div>
