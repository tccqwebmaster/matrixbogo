<?php
/**
 * Promotion Builder – render and save the rule creation/edit form via AJAX.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

use MatrixBogo\Database\Repositories\ConditionsRepository;
use MatrixBogo\Database\Repositories\RewardsRepository;
use MatrixBogo\Database\Repositories\RulesRepository;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PromotionBuilder
 */
final class PromotionBuilder {

	/** @var RulesRepository */
	private RulesRepository $rules;

	/** @var ConditionsRepository */
	private ConditionsRepository $conditions_repo;

	/** @var RewardsRepository */
	private RewardsRepository $rewards_repo;

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	public function __construct( PromotionEngine $engine ) {
		$this->engine          = $engine;
		$this->rules           = new RulesRepository();
		$this->conditions_repo = new ConditionsRepository();
		$this->rewards_repo    = new RewardsRepository();
	}

	// -----------------------------------------------------------------
	// Render form
	// -----------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'matrix-bogo' ) );
		}

		$id         = absint( wp_unslash( $_GET['id'] ?? 0 ) );
		$rule       = $id ? ( $this->rules->find( $id ) ?: [] ) : [];
		$conditions = $id ? $this->conditions_repo->get_for_rule( $id ) : [];
		$rewards    = $id ? $this->rewards_repo->get_for_rule( $id ) : [];
		$is_new     = empty( $rule );
		$page_title = $is_new
			? __( 'New Promotion', 'matrix-bogo' )
			: __( 'Edit Promotion', 'matrix-bogo' );

		$current_type = ! empty( $rule['type'] ) ? (string) $rule['type'] : 'buy_x_get_y';
		$rule_data    = ! empty( $rule['rule_data'] ) ? (array) json_decode( (string) $rule['rule_data'], true ) : [];
		?>
		<div class="wrap matrix-bogo-admin matrix-bogo-builder">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-bogo-promotions' ) ); ?>"
			   class="button" style="margin-left:10px;">&larr; <?php esc_html_e( 'Back to List', 'matrix-bogo' ); ?></a>

			<form id="matrix-bogo-rule-form" method="post">
				<?php wp_nonce_field( 'matrix_bogo_admin', 'matrix_bogo_nonce' ); ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">

				<!-- ── Basic Info ─────────────────────────────────────────── -->
				<div class="matrix-bogo-section">
					<h2><?php esc_html_e( 'Basic Information', 'matrix-bogo' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Name', 'matrix-bogo' ); ?> *</th>
							<td>
								<input type="text" name="name" class="regular-text"
								       value="<?php echo esc_attr( $rule['name'] ?? '' ); ?>" required>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Description', 'matrix-bogo' ); ?></th>
							<td>
								<textarea name="description" rows="2" class="large-text"><?php
									echo esc_textarea( $rule['description'] ?? '' );
								?></textarea>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Promotion Type', 'matrix-bogo' ); ?> *</th>
							<td>
								<select name="type" id="matrix-bogo-type" class="regular-text">
									<?php foreach ( $this->get_types() as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"
											<?php selected( $rule['type'] ?? '', $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Status', 'matrix-bogo' ); ?></th>
							<td>
								<select name="status">
									<option value="inactive" <?php selected( $rule['status'] ?? 'inactive', 'inactive' ); ?>>
										<?php esc_html_e( 'Inactive', 'matrix-bogo' ); ?>
									</option>
									<option value="active" <?php selected( $rule['status'] ?? '', 'active' ); ?>>
										<?php esc_html_e( 'Active', 'matrix-bogo' ); ?>
									</option>
									<option value="scheduled" <?php selected( $rule['status'] ?? '', 'scheduled' ); ?>>
										<?php esc_html_e( 'Scheduled', 'matrix-bogo' ); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Priority', 'matrix-bogo' ); ?></th>
							<td>
								<input type="number" name="priority" value="<?php echo esc_attr( $rule['priority'] ?? 10 ); ?>"
								       min="1" max="9999" class="small-text">
								<p class="description"><?php esc_html_e( 'Lower number = higher priority.', 'matrix-bogo' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- ── Schedule ──────────────────────────────────────────── -->
				<div class="matrix-bogo-section">
					<h2><?php esc_html_e( 'Schedule', 'matrix-bogo' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Start Date', 'matrix-bogo' ); ?></th>
							<td>
								<input type="datetime-local" name="schedule_start"
								       value="<?php echo esc_attr( str_replace( ' ', 'T', $rule['schedule_start'] ?? '' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'End Date', 'matrix-bogo' ); ?></th>
							<td>
								<input type="datetime-local" name="schedule_end"
								       value="<?php echo esc_attr( str_replace( ' ', 'T', $rule['schedule_end'] ?? '' ) ); ?>">
							</td>
						</tr>
					</table>
				</div>

				<!-- ── Usage Limits ──────────────────────────────────────── -->
				<div class="matrix-bogo-section">
					<h2><?php esc_html_e( 'Usage Limits', 'matrix-bogo' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Max Uses (global)', 'matrix-bogo' ); ?></th>
							<td>
								<input type="number" name="max_uses"
								       value="<?php echo esc_attr( $rule['max_uses'] ?? 0 ); ?>"
								       min="0" class="small-text">
								<p class="description"><?php esc_html_e( '0 = unlimited', 'matrix-bogo' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Max Uses per Customer', 'matrix-bogo' ); ?></th>
							<td>
								<input type="number" name="max_uses_per_user"
								       value="<?php echo esc_attr( $rule['max_uses_per_user'] ?? 0 ); ?>"
								       min="0" class="small-text">
								<p class="description"><?php esc_html_e( '0 = unlimited', 'matrix-bogo' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- ── Stacking ──────────────────────────────────────────── -->
				<div class="matrix-bogo-section">
					<h2><?php esc_html_e( 'Stacking & Exclusivity', 'matrix-bogo' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Stackable', 'matrix-bogo' ); ?></th>
							<td>
								<input type="checkbox" name="is_stackable" value="1"
								       <?php checked( ! empty( $rule['is_stackable'] ) ); ?>>
								<span><?php esc_html_e( 'Allow combining with other promotions', 'matrix-bogo' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Exclusive', 'matrix-bogo' ); ?></th>
							<td>
								<input type="checkbox" name="is_exclusive" value="1"
								       <?php checked( ! empty( $rule['is_exclusive'] ) ); ?>>
								<span><?php esc_html_e( 'Prevents all other promotions when this one applies', 'matrix-bogo' ); ?></span>
							</td>
						</tr>
					</table>
				</div>

				<!-- ── Rule Data (type-specific) – PHP-rendered, JS updates on type change ── -->
				<div class="matrix-bogo-section" id="matrix-bogo-rule-data-section">
					<h2><?php esc_html_e( 'Promotion Settings', 'matrix-bogo' ); ?></h2>
					<div id="matrix-bogo-rule-data-container">
						<?php $this->render_rule_data_fields( $current_type, $rule_data ); ?>
					</div>
				</div>

				<!-- ── Conditions ────────────────────────────────────────── -->
				<div class="matrix-bogo-section">
					<h2><?php esc_html_e( 'Conditions', 'matrix-bogo' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Conditions within the same group are combined with AND. Groups are combined with OR.', 'matrix-bogo' ); ?>
					</p>
					<div id="matrix-bogo-conditions-container"
					     data-current="<?php echo esc_attr( wp_json_encode( $conditions ) ); ?>">
					</div>
					<button type="button" class="button" id="matrix-bogo-add-condition-group">
						<?php esc_html_e( '+ Add Condition Group', 'matrix-bogo' ); ?>
					</button>
				</div>

				<!-- ── Rewards ───────────────────────────────────────────── -->
				<div class="matrix-bogo-section">
					<h2><?php esc_html_e( 'Rewards', 'matrix-bogo' ); ?></h2>
					<div id="matrix-bogo-rewards-container"
					     data-current="<?php echo esc_attr( wp_json_encode( $rewards ) ); ?>">
					</div>
					<button type="button" class="button" id="matrix-bogo-add-reward">
						<?php esc_html_e( '+ Add Reward', 'matrix-bogo' ); ?>
					</button>
				</div>

				<!-- ── Save ──────────────────────────────────────────────── -->
				<div class="matrix-bogo-form-actions">
					<button type="submit" class="button button-primary button-large" id="matrix-bogo-save-btn">
						<?php esc_html_e( 'Save Promotion', 'matrix-bogo' ); ?>
					</button>
					<span id="matrix-bogo-save-status"></span>
				</div>
			</form>
		</div>
		<script type="text/javascript">
		/* <![CDATA[ */
		window.matrixBogoBuilderData = <?php
			echo wp_json_encode( [
				'conditions' => array_values( $conditions ),
				'rewards'    => array_values( $rewards ),
				'ruleData'   => ! empty( $rule_data ) ? $rule_data : new \stdClass(),
				'type'       => $current_type,
				'fields'     => $this->get_rule_data_fields( $current_type ),
			] );
		?>;
		/* ]]> */
		</script>
		<?php
	}

	// -----------------------------------------------------------------
	// AJAX: save
	// -----------------------------------------------------------------

	public function ajax_save(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$id         = absint( wp_unslash( $_POST['id'] ?? 0 ) );
		$conditions = json_decode( wp_unslash( $_POST['conditions'] ?? '[]' ), true ) ?: []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rewards    = json_decode( wp_unslash( $_POST['rewards'] ?? '[]' ), true ) ?: [];    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rule_data  = json_decode( wp_unslash( $_POST['rule_data'] ?? '{}' ), true ) ?: [];  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		if ( ! $name ) {
			wp_send_json_error( [ 'message' => __( 'Promotion name is required.', 'matrix-bogo' ) ] );
		}

		$schedule_start = sanitize_text_field( wp_unslash( $_POST['schedule_start'] ?? '' ) );
		$schedule_end   = sanitize_text_field( wp_unslash( $_POST['schedule_end'] ?? '' ) );

		$payload = [
			'name'              => $name,
			'description'       => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'type'              => sanitize_key( $_POST['type'] ?? 'buy_x_get_y' ),
			'status'            => sanitize_key( $_POST['status'] ?? 'inactive' ),
			'priority'          => max( 1, absint( wp_unslash( $_POST['priority'] ?? 10 ) ) ),
			'max_uses'          => max( 0, absint( wp_unslash( $_POST['max_uses'] ?? 0 ) ) ),
			'max_uses_per_user' => max( 0, absint( wp_unslash( $_POST['max_uses_per_user'] ?? 0 ) ) ),
			'is_stackable'      => ! empty( $_POST['is_stackable'] ) ? 1 : 0,
			'is_exclusive'      => ! empty( $_POST['is_exclusive'] ) ? 1 : 0,
			'schedule_start'    => $schedule_start ? str_replace( 'T', ' ', $schedule_start ) : null,
			'schedule_end'      => $schedule_end ? str_replace( 'T', ' ', $schedule_end ) : null,
			'rule_data'         => wp_json_encode( $rule_data ),
		];

		if ( $id ) {
			$this->rules->update( $id, $payload );
			$new_id = $id;
		} else {
			$payload['slug'] = sanitize_title( $name ) . '-' . time();
			$new_id = $this->rules->create( $payload );
		}

		if ( ! $new_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to save promotion.', 'matrix-bogo' ) ] );
		}

		$this->conditions_repo->sync_for_rule( $new_id, $conditions );
		$this->rewards_repo->sync_for_rule( $new_id, $rewards );
		$this->rules->flush_cache();

		wp_send_json_success( [
			'id'      => $new_id,
			'message' => __( 'Promotion saved.', 'matrix-bogo' ),
		] );
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	// -----------------------------------------------------------------
	// Rule data field rendering (PHP → no JS dependency for initial load)
	// -----------------------------------------------------------------

	/**
	 * Returns the field definitions for a given promotion type.
	 * Kept in sync with the JS ruleDataFields object.
	 *
	 * @param string $type Promotion type slug.
	 * @return array<int, array{key:string, label:string, type:string, placeholder:string}>
	 */
	private function get_rule_data_fields( string $type ): array {
		$map = [
			'buy_x_get_y'            => [
				[ 'key' => 'trigger_product_ids', 'label' => __( 'Trigger Products', 'matrix-bogo' ),    'type' => 'product_multi',  'placeholder' => __( 'Search by name, SKU, or ID', 'matrix-bogo' ) ],
				[ 'key' => 'trigger_quantity',    'label' => __( 'Trigger Quantity', 'matrix-bogo' ),    'type' => 'number',         'placeholder' => '1' ],
			],
			'buy_x_get_x'            => [
				[ 'key' => 'trigger_product_ids', 'label' => __( 'Trigger Products', 'matrix-bogo' ),    'type' => 'product_multi',  'placeholder' => __( 'Search by name, SKU, or ID', 'matrix-bogo' ) ],
				[ 'key' => 'trigger_quantity',    'label' => __( 'Trigger Quantity', 'matrix-bogo' ),    'type' => 'number',         'placeholder' => '1' ],
			],
			'spend_amount_get_gift'  => [
				[ 'key' => 'min_amount',           'label' => __( 'Minimum Spend Amount', 'matrix-bogo' ), 'type' => 'number', 'placeholder' => '0.00' ],
			],
			'cart_quantity_get_gift' => [
				[ 'key' => 'min_quantity',         'label' => __( 'Minimum Cart Quantity', 'matrix-bogo' ), 'type' => 'number', 'placeholder' => '1' ],
			],
			'category_get_gift'      => [
				[ 'key' => 'trigger_category_ids', 'label' => __( 'Trigger Categories', 'matrix-bogo' ), 'type' => 'category_multi', 'placeholder' => __( 'Search by category name or ID', 'matrix-bogo' ) ],
				[ 'key' => 'trigger_quantity',     'label' => __( 'Trigger Quantity', 'matrix-bogo' ),   'type' => 'number',          'placeholder' => '1' ],
			],
		];
		return $map[ $type ] ?? [];
	}

	/**
	 * Renders the rule-data input fields for the given type.
	 *
	 * @param string $type      Promotion type slug.
	 * @param array  $rule_data Saved values keyed by field key.
	 */
	private function render_rule_data_fields( string $type, array $rule_data ): void {
		$fields = $this->get_rule_data_fields( $type );
		if ( empty( $fields ) ) {
			echo '<p><em>' . esc_html__( 'No additional settings for this promotion type.', 'matrix-bogo' ) . '</em></p>';
			return;
		}
		echo '<table class="form-table">';
		foreach ( $fields as $field ) {
			$val = isset( $rule_data[ $field['key'] ] ) ? $rule_data[ $field['key'] ] : '';
			echo '<tr><th>' . esc_html( $field['label'] ) . '</th><td>';

			if ( 'product_multi' === $field['type'] || 'category_multi' === $field['type'] ) {
				// Render a Select2-compatible <select multiple> pre-populated with saved values.
				$saved_ids  = array_filter( array_map( 'absint', explode( ',', (string) $val ) ) );
				$css_class  = 'product_multi' === $field['type']
					? 'matrix-bogo-product-select2'
					: 'matrix-bogo-category-select2';
				echo '<select multiple'
					. ' class="' . esc_attr( $css_class . ' matrix-bogo-rule-data-field' ) . '"'
					. ' data-key="' . esc_attr( $field['key'] ) . '"'
					. ' style="min-width:380px">';

				if ( 'product_multi' === $field['type'] ) {
					foreach ( $saved_ids as $pid ) {
						$product = wc_get_product( $pid );
						if ( $product ) {
							$sku  = $product->get_sku();
							$text = $product->get_name() . ( $sku ? ' [' . $sku . ']' : '' ) . ' #' . $pid;
							echo '<option value="' . esc_attr( (string) $pid ) . '" selected>'
								. esc_html( $text ) . '</option>';
						}
					}
				} else {
					foreach ( $saved_ids as $tid ) {
						$term = get_term( $tid, 'product_cat' );
						if ( $term && ! is_wp_error( $term ) ) {
							echo '<option value="' . esc_attr( (string) $tid ) . '" selected>'
								. esc_html( $term->name . ' #' . $tid ) . '</option>';
						}
					}
				}

				echo '</select>';
				echo '<p class="description">' . esc_html( $field['placeholder'] ) . '</p>';
			} else {
				printf(
					'<input type="%s" step="any" class="regular-text matrix-bogo-rule-data-field" data-key="%s" value="%s" placeholder="%s">',
					esc_attr( $field['type'] ),
					esc_attr( $field['key'] ),
					esc_attr( (string) $val ),
					esc_attr( $field['placeholder'] )
				);
			}

			echo '</td></tr>';
		}
		echo '</table>';
	}

	private function get_types(): array {
		return [
			'buy_x_get_y'              => __( 'Buy X Get Y (different product)', 'matrix-bogo' ),
			'buy_x_get_x'              => __( 'Buy X Get X (same product free)', 'matrix-bogo' ),
			'spend_amount_get_gift'    => __( 'Spend Amount Get Gift', 'matrix-bogo' ),
			'cart_quantity_get_gift'   => __( 'Cart Quantity Get Gift', 'matrix-bogo' ),
			'category_get_gift'        => __( 'Category Get Gift', 'matrix-bogo' ),
		];
	}
}
