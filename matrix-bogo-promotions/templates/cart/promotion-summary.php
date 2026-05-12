<?php
/**
 * Template: Cart promotion summary / progress bar.
 *
 * @package MatrixBogo
 * @var array $rewards_map  Keyed by rule_id; each entry has 'promotion_label', 'rewards'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="matrix-bogo-promotion-summary">
	<?php foreach ( $rewards_map as $rule_id => $entry ) :
		$label = $entry['promotion_label'] ?? '';
		if ( ! $label ) {
			continue;
		}
		?>
		<div class="matrix-bogo-promo-notice">
			<span class="matrix-bogo-promo-icon">🎁</span>
			<span class="matrix-bogo-promo-label">
				<?php echo esc_html( $label ); ?>
			</span>
		</div>
	<?php endforeach; ?>
</div>
