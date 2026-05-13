<?php
/**
 * Template: Cart gift selection section (inline, no popup).
 *
 * Products are rendered server-side from session rewards — no AJAX needed.
 * Customer picks one product per promotion via radio button and clicks
 * "Add Gift to Cart", which fires frontend.js → matrix_bogo_apply_gift.
 *
 * @package MatrixBogo
 * @var array $rewards_map Session rewards, keyed by rule_id.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Build a list of groups that have at least one valid product in the pool.
$valid_groups = [];

foreach ( $rewards_map as $rule_id => $entry ) {
	foreach ( $entry['rewards'] ?? [] as $reward ) {
		if ( empty( $reward['customer_choice'] ) ) {
			continue;
		}

		$pool     = array_filter( array_map( 'intval', (array) ( $reward['choice_pool'] ?? [] ) ) );
		$qty      = max( 1, (int) ( $reward['quantity'] ?? 1 ) );
		$label    = $entry['promotion_label'] ?? __( 'Free Gift', 'matrix-bogo' );
		$nonce    = wp_create_nonce( 'matrix_bogo_frontend' );

		// Build list of valid, in-stock, purchasable products.
		$products = [];
		foreach ( $pool as $pid ) {
			$p = wc_get_product( $pid );
			if ( $p && $p->is_in_stock() && $p->is_purchasable() ) {
				$products[] = $p;
			}
		}

		// Skip this reward if the pool is empty (bad config or all OOS).
		if ( empty( $products ) ) {
			continue;
		}

		$valid_groups[] = compact( 'rule_id', 'label', 'qty', 'nonce', 'products' );
	}
}

if ( empty( $valid_groups ) ) {
	return; // Nothing valid to show.
}
?>
<div class="matrix-bogo-gift-section">

	<h3 class="matrix-bogo-gift-title">
		<?php esc_html_e( '🎁 Choose Your Free Gift', 'matrix-bogo' ); ?>
	</h3>

	<?php foreach ( $valid_groups as $group ) :
		$rule_id  = $group['rule_id'];
		$label    = $group['label'];
		$qty      = $group['qty'];
		$nonce    = $group['nonce'];
		$products = $group['products'];
		?>

		<div class="matrix-bogo-gift-group"
		     data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
		     data-quantity="<?php echo esc_attr( $qty ); ?>">

			<p class="matrix-bogo-gift-group-label">
				<strong><?php echo esc_html( $label ); ?></strong>
				<?php if ( $qty > 1 ) : ?>
					&mdash; <?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of free items */
							_n( 'Select %d item', 'Select %d items', $qty, 'matrix-bogo' ),
							$qty
						)
					);
					?>
				<?php endif; ?>
			</p>

			<div class="matrix-bogo-gift-products">
				<?php foreach ( $products as $product ) :
					$product_id = $product->get_id();
					?>
					<label class="matrix-bogo-gift-item">
						<input type="radio"
						       name="matrix_bogo_gift_choice_<?php echo esc_attr( $rule_id ); ?>"
						       value="<?php echo esc_attr( $product_id ); ?>"
						       class="matrix-bogo-gift-choice"
						       data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
						       data-quantity="<?php echo esc_attr( $qty ); ?>">
						<span class="matrix-bogo-gift-item-inner">
							<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
							<span class="matrix-bogo-gift-name">
								<?php echo esc_html( $product->get_name() ); ?>
							</span>
							<span class="matrix-bogo-gift-free-badge">
								<?php esc_html_e( 'FREE', 'matrix-bogo' ); ?>
							</span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="matrix-bogo-gift-actions">
				<button type="button"
				        class="button button-primary matrix-bogo-add-gift-btn"
				        data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
				        data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Add Gift to Cart', 'matrix-bogo' ); ?>
				</button>
				<span class="matrix-bogo-gift-hint">
					<?php esc_html_e( 'Select one gift above, then click the button.', 'matrix-bogo' ); ?>
				</span>
			</div>

		</div>

	<?php endforeach; ?>

</div>
