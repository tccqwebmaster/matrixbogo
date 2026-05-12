<?php
/**
 * Template: Product promotion badge (single product page).
 *
 * @package MatrixBogo
 * @var array $promotions  Array of active rule rows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $promotions ) ) {
	return;
}
?>
<div class="matrix-bogo-product-promotions">
	<?php foreach ( $promotions as $promo ) : ?>
		<div class="matrix-bogo-product-promo-badge">
			<span class="matrix-bogo-promo-icon">🎁</span>
			<span class="matrix-bogo-promo-name">
				<?php echo esc_html( $promo['name'] ); ?>
			</span>
			<?php if ( ! empty( $promo['description'] ) ) : ?>
				<span class="matrix-bogo-promo-desc">
					<?php echo esc_html( $promo['description'] ); ?>
				</span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
