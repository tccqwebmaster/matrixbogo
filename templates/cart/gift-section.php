<?php
/**
 * Template: Cart gift selection section.
 *
 * @package MatrixBogo
 * @var array $rewards_map  Session rewards, keyed by rule_id.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="matrix-bogo-gift-section">
	<h3 class="matrix-bogo-gift-title"><?php esc_html_e( 'Choose Your Free Gift', 'matrix-bogo' ); ?></h3>

	<?php foreach ( $rewards_map as $rule_id => $entry ) :
		foreach ( $entry['rewards'] ?? [] as $reward ) :
			if ( empty( $reward['customer_choice'] ) ) {
				continue;
			}
			$pool = array_map( 'intval', (array) ( $reward['choice_pool'] ?? [] ) );
			?>
			<div class="matrix-bogo-gift-group" data-rule-id="<?php echo esc_attr( $rule_id ); ?>">
				<p class="matrix-bogo-gift-group-label">
					<?php echo esc_html( $entry['promotion_label'] ?? __( 'Free Gift', 'matrix-bogo' ) ); ?>
				</p>
				<div class="matrix-bogo-gift-products">
					<?php foreach ( $pool as $product_id ) :
						$product = wc_get_product( $product_id );
						if ( ! $product ) {
							continue;
						}
						?>
						<div class="matrix-bogo-gift-item">
							<label>
								<input type="radio"
								       name="matrix_bogo_gift_choice_<?php echo esc_attr( $rule_id ); ?>"
								       value="<?php echo esc_attr( $product_id ); ?>"
								       class="matrix-bogo-gift-choice"
								       data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
								       data-quantity="<?php echo esc_attr( $reward['quantity'] ?? 1 ); ?>">
								<?php echo wp_kses_post( $product->get_image( 'thumbnail' ) ); ?>
								<span class="matrix-bogo-gift-name"><?php echo esc_html( $product->get_name() ); ?></span>
								<span class="matrix-bogo-gift-free-label">
									<?php esc_html_e( 'FREE', 'matrix-bogo' ); ?>
								</span>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
				<button class="button matrix-bogo-add-gift-btn"
				        data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
				        data-nonce="<?php echo esc_attr( wp_create_nonce( 'matrix_bogo_gift_nonce' ) ); ?>">
					<?php esc_html_e( 'Add Gift to Cart', 'matrix-bogo' ); ?>
				</button>
			</div>
		<?php
		endforeach;
	endforeach; ?>
</div>
