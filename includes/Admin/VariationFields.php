<?php
/**
 * Variation Fields – "Gift promotions" checkboxes on each variation in the
 * product editor (Product data → Variations), so a promotion can target
 * individual options (e.g. only the 256GB iPads) without tagging the whole
 * product.
 *
 * Saved as variation meta VariationEligibility::META_KEY (int[] of rule IDs).
 * WPML/WCML copies it to translations via wpml-config.xml; the cart also
 * reads the default-language original's value as a fallback.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

use MatrixBogo\Core\Loader;
use MatrixBogo\Database\Repositories\RulesRepository;
use MatrixBogo\Promotions\VariationEligibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VariationFields
 */
final class VariationFields {

	/** Promotion types whose trigger works per product option. */
	private const OPTION_TYPES = [ 'category_get_gift', 'buy_x_get_y' ];

	/** @var array<int, array<string,mixed>>|null */
	private ?array $rules = null;

	public function init( Loader $loader ): void {
		$loader->add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'render' ], 20, 3 );
		$loader->add_action( 'woocommerce_save_product_variation',            [ $this, 'save' ], 10, 2 );
	}

	/**
	 * Renders the checkbox list inside one variation's panel.
	 *
	 * @param int      $loop           Variation index.
	 * @param array    $variation_data Unused.
	 * @param \WP_Post $variation      Variation post.
	 */
	public function render( $loop, $variation_data, $variation ): void {
		$vid      = (int) $variation->ID;
		$selected = VariationEligibility::rule_ids_for( $vid );
		$rules    = $this->rules( $selected );

		if ( empty( $rules ) ) {
			return;
		}
		?>
		<div class="form-row form-row-full matrix-bogo-variation-promos">
			<input type="hidden" name="matrix_bogo_promo_ids_present[<?php echo esc_attr( (string) $loop ); ?>]" value="1">
			<p style="margin:0 0 4px;"><strong><?php esc_html_e( 'Gift promotions for this option', 'matrix-bogo' ); ?></strong>
				<?php echo wc_help_tip( __( 'Tick a promotion to make this option qualify for it even when the product has no matching tag or category. Other options of the same product are not affected.', 'matrix-bogo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>
			<?php foreach ( $rules as $rule ) : ?>
				<?php
				$rid    = (int) $rule['id'];
				$status = (string) ( $rule['status'] ?? '' );
				?>
				<label style="display:inline-block;margin-right:16px;">
					<input type="checkbox"
					       name="matrix_bogo_promo_ids[<?php echo esc_attr( (string) $loop ); ?>][]"
					       value="<?php echo esc_attr( (string) $rid ); ?>"
					       <?php checked( in_array( $rid, $selected, true ) ); ?>>
					<?php echo esc_html( $rule['name'] . ( 'active' !== $status ? ' (' . $status . ')' : '' ) ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Saves the ticked promotions for one variation.
	 *
	 * WooCommerce has already verified the save-variations nonce and the
	 * edit_products capability before firing woocommerce_save_product_variation.
	 *
	 * @param int $variation_id
	 * @param int $i Variation index in the posted arrays.
	 */
	public function save( $variation_id, $i ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['matrix_bogo_promo_ids_present'][ $i ] ) ) {
			return; // Field not rendered (no promotions) — leave the meta alone.
		}
		$ids = isset( $_POST['matrix_bogo_promo_ids'][ $i ] )
			? array_map( 'absint', (array) wp_unslash( $_POST['matrix_bogo_promo_ids'][ $i ] ) )
			: [];
		// phpcs:enable

		VariationEligibility::save( (int) $variation_id, $ids );
	}

	/**
	 * Gift promotions that work per option (not expired), plus any
	 * already-ticked rule whatever its status.
	 *
	 * @param int[] $selected
	 * @return array<int, array<string,mixed>>
	 */
	private function rules( array $selected ): array {
		if ( null === $this->rules ) {
			$repo        = new RulesRepository();
			$this->rules = [];
			foreach ( [ 'active', 'scheduled', 'inactive', 'expired' ] as $status ) {
				foreach ( $repo->get_rules_by_status( $status ) as $rule ) {
					if ( in_array( (string) $rule['type'], self::OPTION_TYPES, true ) ) {
						$this->rules[] = $rule;
					}
				}
			}
		}

		return array_values( array_filter( $this->rules, static function ( array $rule ) use ( $selected ): bool {
			return 'expired' !== (string) $rule['status']
				|| in_array( (int) $rule['id'], $selected, true );
		} ) );
	}
}
