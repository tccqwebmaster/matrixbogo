<?php
/**
 * Promotions list page.
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
 * Class PromotionsList
 */
final class PromotionsList {

	/** @var RulesRepository */
	private RulesRepository $rules;

	/** @var ConditionsRepository */
	private ConditionsRepository $conditions;

	/** @var RewardsRepository */
	private RewardsRepository $rewards;

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	/** Human-readable type labels. */
	private const TYPE_LABELS = [
		'buy_x_get_y'            => 'Buy X Get Y',
		'buy_x_get_x'            => 'Buy X Get X (Same)',
		'spend_amount_get_gift'  => 'Spend Amount Get Gift',
		'cart_quantity_get_gift' => 'Cart Qty Get Gift',
		'category_get_gift'      => 'Category Get Gift',
		'tiered_pricing'         => 'Tiered / Volume Pricing',
	];

	/** Human-readable condition type labels. */
	private const CONDITION_LABELS = [
		'user_role'        => 'User Role',
		'logged_in'        => 'Logged In',
		'specific_user'    => 'Specific User',
		'email'            => 'Email',
		'first_order'      => 'First Order',
		'repeat_customer'  => 'Repeat Customer',
		'cart_subtotal'    => 'Cart Subtotal',
		'cart_quantity'    => 'Cart Quantity',
		'cart_categories'  => 'Cart Has Categories',
		'cart_products'    => 'Cart Has Products',
		'coupon_applied'   => 'Coupon Applied',
		'purchase_history' => 'Purchased Product',
		'total_spent'      => 'Total Spent',
		'completed_orders' => 'Completed Orders',
		'date_range'       => 'Date Range',
		'day_of_week'      => 'Day of Week',
		'time_range'       => 'Time Range',
	];

	/** Human-readable reward type labels. */
	private const REWARD_LABELS = [
		'free_product'     => 'Free Product',
		'fixed_discount'   => 'Fixed Discount',
		'percent_discount' => '% Discount',
		'cheapest_free'    => 'Cheapest Free',
	];

	public function __construct( PromotionEngine $engine ) {
		$this->engine     = $engine;
		$this->rules      = new RulesRepository();
		$this->conditions = new ConditionsRepository();
		$this->rewards    = new RewardsRepository();
	}

	// -----------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'matrix-bogo' ) );
		}

		// Handle action parameter for new/edit.
		$action = sanitize_key( $_GET['action'] ?? '' );
		if ( in_array( $action, [ 'new', 'edit' ], true ) ) {
			( new PromotionBuilder( $this->engine ) )->render();
			return;
		}

		$filter_status = sanitize_key( $_GET['status'] ?? '' );
		$search        = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$per_page      = 20;
		$page          = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) );

		$where = [];
		if ( $filter_status ) {
			$where['status'] = $filter_status;
		}

		$all_rules = $this->rules->find_by( $where, 'priority ASC, created_at DESC' );
		if ( $search ) {
			$all_rules = array_filter(
				$all_rules,
				static fn( $r ) => false !== stripos( $r['name'], $search )
			);
		}

		$total     = count( $all_rules );
		$all_rules = array_slice( $all_rules, ( $page - 1 ) * $per_page, $per_page );
		?>
		<div class="wrap matrix-bogo-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Promotions', 'matrix-bogo' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=matrix-bogo-promotions&action=new' ) ); ?>"
			   class="page-title-action"><?php esc_html_e( '+ New Promotion', 'matrix-bogo' ); ?></a>

			<form method="get">
				<input type="hidden" name="page" value="matrix-bogo-promotions">
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
					       placeholder="<?php esc_attr_e( 'Search promotions…', 'matrix-bogo' ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'matrix-bogo' ); ?></button>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped matrix-bogo-rules-table">
				<thead>
					<tr>
						<th style="width:24px"></th>
						<th><?php esc_html_e( 'Name', 'matrix-bogo' ); ?></th>
						<th><?php esc_html_e( 'Type', 'matrix-bogo' ); ?></th>
						<th><?php esc_html_e( 'Status', 'matrix-bogo' ); ?></th>
						<th style="width:70px"><?php esc_html_e( 'Priority', 'matrix-bogo' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Uses', 'matrix-bogo' ); ?></th>
						<th><?php esc_html_e( 'Schedule', 'matrix-bogo' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'matrix-bogo' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $all_rules ) ) : ?>
					<tr>
						<td colspan="8">
							<?php esc_html_e( 'No promotions found. Create your first promotion!', 'matrix-bogo' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $all_rules as $rule ) :
						$rule_id    = (int) $rule['id'];
						$edit_url   = admin_url( 'admin.php?page=matrix-bogo-promotions&action=edit&id=' . $rule_id );
						$status_cls = 'status-' . sanitize_html_class( $rule['status'] );
						$max_uses   = (int) $rule['max_uses'];
						$uses_label = $max_uses > 0
							? $rule['uses_count'] . ' / ' . $max_uses
							: $rule['uses_count'] . ' / ∞';
						$type_label = self::TYPE_LABELS[ $rule['type'] ] ?? esc_html( $rule['type'] );

						// Load details for the expandable row.
						$rule_conditions = $this->conditions->get_for_rule( $rule_id );
						$rule_rewards    = $this->rewards->get_for_rule( $rule_id );
						$rule_data       = ! empty( $rule['rule_data'] )
							? (array) json_decode( $rule['rule_data'], true )
							: [];
						?>
						<tr class="mblist-main-row" data-rule="<?php echo esc_attr( $rule_id ); ?>">
							<td class="mblist-toggle-cell">
								<button type="button" class="mblist-toggle button-link" aria-expanded="false"
								        data-target="mblist-detail-<?php echo esc_attr( $rule_id ); ?>"
								        title="<?php esc_attr_e( 'Show details', 'matrix-bogo' ); ?>">&#9654;</button>
							</td>
							<td>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="matrix-bogo-rule-name"
								   style="font-weight:700;color:var(--mb-primary)">
									<?php echo esc_html( $rule['name'] ); ?>
								</a>
								<?php if ( ! empty( $rule['description'] ) ) : ?>
									<p style="margin:2px 0 0;font-size:.75rem;color:var(--mb-text-muted)">
										<?php echo esc_html( wp_trim_words( $rule['description'], 12 ) ); ?>
									</p>
								<?php endif; ?>
							</td>
							<td>
								<span class="mblist-type-pill"><?php echo esc_html( $type_label ); ?></span>
							</td>
							<td>
								<span class="matrix-bogo-status-badge <?php echo esc_attr( $status_cls ); ?>">
									<?php echo esc_html( $rule['status'] ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $rule['priority'] ); ?></td>
							<td><?php echo esc_html( $uses_label ); ?></td>
							<td>
								<?php if ( $rule['schedule_start'] ) : ?>
									<?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $rule['schedule_start'] ) ) ); ?>
									–
									<?php echo $rule['schedule_end']
										? esc_html( date_i18n( 'd/m/Y', strtotime( $rule['schedule_end'] ) ) )
										: esc_html__( '∞', 'matrix-bogo' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Always', 'matrix-bogo' ); ?>
								<?php endif; ?>
							</td>
							<td class="matrix-bogo-row-actions">
								<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'matrix-bogo' ); ?>
								</a>
								<button class="button button-small matrix-bogo-toggle-rule"
								        data-id="<?php echo esc_attr( $rule_id ); ?>"
								        data-nonce="<?php echo esc_attr( wp_create_nonce( 'matrix_bogo_admin' ) ); ?>">
									<?php echo 'active' === $rule['status']
										? esc_html__( 'Deactivate', 'matrix-bogo' )
										: esc_html__( 'Activate', 'matrix-bogo' ); ?>
								</button>
								<button class="button button-small matrix-bogo-clone-rule"
								        data-id="<?php echo esc_attr( $rule_id ); ?>"
								        data-nonce="<?php echo esc_attr( wp_create_nonce( 'matrix_bogo_admin' ) ); ?>">
									<?php esc_html_e( 'Clone', 'matrix-bogo' ); ?>
								</button>
								<button class="button button-small button-link-delete matrix-bogo-delete-rule"
								        data-id="<?php echo esc_attr( $rule_id ); ?>"
								        data-nonce="<?php echo esc_attr( wp_create_nonce( 'matrix_bogo_admin' ) ); ?>">
									<?php esc_html_e( 'Delete', 'matrix-bogo' ); ?>
								</button>
							</td>
						</tr>

						<!-- Detail expansion row -->
						<tr id="mblist-detail-<?php echo esc_attr( $rule_id ); ?>"
						    class="mblist-detail-row" style="display:none">
							<td></td>
							<td colspan="7">
								<div class="mblist-detail-inner">

									<?php // ── Trigger / Rule Settings ───────────── ?>
									<div class="mblist-detail-block">
										<h4>&#9654; Promotion Settings</h4>
										<dl class="mblist-dl">
										<?php
										$rtype = $rule['type'];
										if ( 'buy_x_get_y' === $rtype || 'buy_x_get_x' === $rtype ) {
											$ids   = array_filter( array_map( 'intval', explode( ',', (string) ( $rule_data['trigger_product_ids'] ?? '' ) ) ) );
											$names = [];
											foreach ( $ids as $pid ) {
												$p       = wc_get_product( $pid );
												$names[] = $p ? esc_html( $p->get_name() . ' #' . $pid ) : '#' . $pid;
											}
											echo '<dt>Trigger Products</dt><dd>' . ( $names ? implode( ', ', $names ) : '—' ) . '</dd>';
											echo '<dt>Trigger Qty</dt><dd>' . esc_html( $rule_data['trigger_quantity'] ?? '1' ) . '</dd>';
										} elseif ( 'spend_amount_get_gift' === $rtype ) {
											echo '<dt>Min. Spend</dt><dd>' . wp_kses_post( wc_price( (float) ( $rule_data['min_amount'] ?? 0 ) ) ) . '</dd>';
										} elseif ( 'cart_quantity_get_gift' === $rtype ) {
											echo '<dt>Min. Cart Qty</dt><dd>' . esc_html( $rule_data['min_quantity'] ?? '1' ) . '</dd>';
										} elseif ( 'category_get_gift' === $rtype ) {
											$cat_ids   = array_filter( array_map( 'intval', explode( ',', (string) ( $rule_data['trigger_category_ids'] ?? '' ) ) ) );
											$cat_names = [];
											foreach ( $cat_ids as $tid ) {
												$term        = get_term( $tid, 'product_cat' );
												$cat_names[] = ( $term && ! is_wp_error( $term ) )
													? esc_html( $term->name . ' #' . $tid )
													: '#' . $tid;
											}
											echo '<dt>Trigger Categories</dt><dd>' . ( $cat_names ? implode( ', ', $cat_names ) : '—' ) . '</dd>';
											echo '<dt>Trigger Qty</dt><dd>' . esc_html( $rule_data['trigger_quantity'] ?? '1' ) . '</dd>';
										}
										?>
										<dt>Stackable</dt><dd><?php echo (int) ( $rule['is_stackable'] ?? 1 ) ? 'Yes' : 'No'; ?></dd>
										<dt>Exclusive</dt><dd><?php echo (int) ( $rule['is_exclusive'] ?? 0 ) ? 'Yes' : 'No'; ?></dd>
										</dl>
									</div>

									<?php // ── Conditions ──────────────────────── ?>
									<div class="mblist-detail-block">
										<h4>⚙ Conditions
											<span class="mblist-count"><?php echo count( $rule_conditions ); ?></span>
										</h4>
										<?php if ( empty( $rule_conditions ) ) : ?>
											<p class="mblist-empty">None — promotion applies to all carts.</p>
										<?php else : ?>
											<?php
											// Group by group_id for OR/AND display.
											$groups = [];
											foreach ( $rule_conditions as $cond ) {
												$groups[ (int) $cond['group_id'] ][] = $cond;
											}
											foreach ( $groups as $gidx => $group ) :
												if ( $gidx > 0 ) echo '<div class="mblist-or">— OR —</div>';
											?>
											<ul class="mblist-cond-list">
												<?php foreach ( $group as $cond ) :
													$clabel = self::CONDITION_LABELS[ $cond['type'] ] ?? $cond['type'];
													$val    = $cond['value'] ?? '';
												?>
													<li>
														<strong><?php echo esc_html( $clabel ); ?></strong>
														<code><?php echo esc_html( $cond['operator'] ); ?></code>
														<span><?php echo esc_html( $val ?: '—' ); ?></span>
													</li>
												<?php endforeach; ?>
											</ul>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>

									<?php // ── Rewards ──────────────────────────── ?>
									<div class="mblist-detail-block">
										<h4>🎁 Rewards
											<span class="mblist-count"><?php echo count( $rule_rewards ); ?></span>
										</h4>
										<?php if ( empty( $rule_rewards ) ) : ?>
											<p class="mblist-empty">No rewards configured.</p>
										<?php else : ?>
											<ul class="mblist-reward-list">
											<?php foreach ( $rule_rewards as $rw ) :
												$rlabel    = self::REWARD_LABELS[ $rw['reward_type'] ?? '' ] ?? $rw['reward_type'];
												$pid       = (int) ( $rw['product_id'] ?? 0 );
												$product   = $pid ? wc_get_product( $pid ) : null;
												$pname     = $product ? $product->get_name() . ' #' . $pid : ( $pid ? '#' . $pid : '' );
												$qty       = (int) ( $rw['quantity'] ?? 1 );
												$disc_val  = (float) ( $rw['discount_value'] ?? 0 );
												$cc        = (bool) ( $rw['customer_choice'] ?? false );
												$pool_raw  = $rw['choice_pool'] ?? '[]';
												$pool      = is_array( $pool_raw ) ? $pool_raw : (array) json_decode( (string) $pool_raw, true );
												$pool_ids  = array_filter( array_map( 'intval', $pool ) );
												$pool_names = [];
												foreach ( $pool_ids as $ppid ) {
													$pp = wc_get_product( $ppid );
													$pool_names[] = $pp ? $pp->get_name() . ' #' . $ppid : '#' . $ppid;
												}
											?>
												<li class="mblist-reward-item">
													<span class="mblist-reward-type"><?php echo esc_html( $rlabel ); ?></span>
													<?php if ( $rw['reward_type'] === 'free_product' ) : ?>
														<?php if ( $cc && ! empty( $pool_names ) ) : ?>
															<span>Customer's choice from:
																<em><?php echo esc_html( implode( ', ', $pool_names ) ); ?></em>
															</span>
														<?php elseif ( $pname ) : ?>
															<span>Product: <em><?php echo esc_html( $pname ); ?></em></span>
														<?php endif; ?>
														<span>Qty: <strong><?php echo esc_html( $qty ); ?></strong></span>
													<?php elseif ( $rw['reward_type'] === 'fixed_discount' ) : ?>
														<span>Discount: <strong><?php echo wp_kses_post( wc_price( $disc_val ) ); ?></strong></span>
													<?php elseif ( $rw['reward_type'] === 'percent_discount' ) : ?>
														<span>Discount: <strong><?php echo esc_html( $disc_val ); ?>%</strong></span>
													<?php elseif ( $rw['reward_type'] === 'cheapest_free' ) : ?>
														<span>Cheapest item in cart is free</span>
													<?php endif; ?>
													<?php if ( (int) ( $rw['max_per_order'] ?? 0 ) > 0 ) : ?>
														<span class="mblist-meta">Max/order: <?php echo esc_html( $rw['max_per_order'] ); ?></span>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>

								</div><!-- .mblist-detail-inner -->
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total > $per_page ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post( paginate_links( [
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => ceil( $total / $per_page ),
						] ) );
						?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Import / Export -->
			<div class="matrix-bogo-panel" style="margin-top:20px;">
				<button class="button" id="matrix-bogo-export-btn"
				        data-nonce="<?php echo esc_attr( wp_create_nonce( 'matrix_bogo_admin' ) ); ?>">
					<?php esc_html_e( 'Export All Promotions', 'matrix-bogo' ); ?>
				</button>
				<button class="button" id="matrix-bogo-import-btn"
				        data-nonce="<?php echo esc_attr( wp_create_nonce( 'matrix_bogo_admin' ) ); ?>">
					<?php esc_html_e( 'Import Promotions', 'matrix-bogo' ); ?>
				</button>
			</div>
		</div>
		<script>
		(function($){
			$(document).on('click', '.mblist-toggle', function(){
				var $btn    = $(this);
				var target  = $btn.data('target');
				var $row    = $('#' + target);
				var open    = $btn.attr('aria-expanded') === 'true';
				$row.toggle(!open);
				$btn.attr('aria-expanded', !open ? 'true' : 'false');
				$btn.html(!open ? '&#9660;' : '&#9654;');
			});
		})(jQuery);
		</script>
		<?php
	}

	// -----------------------------------------------------------------
	// AJAX: delete
	// -----------------------------------------------------------------

	public function ajax_delete(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$id = absint( wp_unslash( $_POST['id'] ?? 0 ) );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid ID.', 'matrix-bogo' ) ] );
		}

		$this->rules->delete( $id );
		$this->rules->flush_cache();
		wp_send_json_success( [ 'message' => __( 'Promotion deleted.', 'matrix-bogo' ) ] );
	}

	// -----------------------------------------------------------------
	// AJAX: toggle status
	// -----------------------------------------------------------------

	public function ajax_toggle(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$id   = absint( wp_unslash( $_POST['id'] ?? 0 ) );
		$rule = $this->rules->find( $id );
		if ( ! $rule ) {
			wp_send_json_error( [ 'message' => __( 'Rule not found.', 'matrix-bogo' ) ] );
		}

		$new_status = 'active' === $rule['status'] ? 'inactive' : 'active';
		$this->rules->update( $id, [ 'status' => $new_status ] );
		$this->rules->flush_cache();

		wp_send_json_success( [
			'status'  => $new_status,
			'message' => sprintf(
				/* translators: %s: status */
				__( 'Status changed to %s.', 'matrix-bogo' ),
				$new_status
			),
		] );
	}

	// -----------------------------------------------------------------
	// AJAX: clone
	// -----------------------------------------------------------------

	public function ajax_clone(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$id   = absint( wp_unslash( $_POST['id'] ?? 0 ) );
		$rule = $this->rules->find( $id );
		if ( ! $rule ) {
			wp_send_json_error( [ 'message' => __( 'Rule not found.', 'matrix-bogo' ) ] );
		}

		// Create cloned rule.
		unset( $rule['id'], $rule['uses_count'] );
		$rule['name']   = sprintf( __( 'Copy of %s', 'matrix-bogo' ), $rule['name'] );
		$rule['slug']   = sanitize_title( $rule['name'] ) . '-' . time();
		$rule['status'] = 'inactive';

		$new_id = $this->rules->create( $rule );
		if ( ! $new_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to clone promotion.', 'matrix-bogo' ) ] );
		}

		// Clone conditions and rewards.
		$conditions = $this->conditions->get_for_rule( $id );
		$this->conditions->sync_for_rule( $new_id, $conditions );

		$rewards = $this->rewards->get_for_rule( $id );
		$this->rewards->sync_for_rule( $new_id, $rewards );

		$this->rules->flush_cache();
		wp_send_json_success( [ 'new_id' => $new_id, 'message' => __( 'Promotion cloned.', 'matrix-bogo' ) ] );
	}

	// -----------------------------------------------------------------
	// AJAX: export
	// -----------------------------------------------------------------

	public function ajax_export(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$rules = $this->rules->find_by( [], 'id ASC' );
		$export = [];

		foreach ( $rules as $rule ) {
			$rule['conditions'] = $this->conditions->get_for_rule( (int) $rule['id'] );
			$rule['rewards']    = $this->rewards->get_for_rule( (int) $rule['id'] );
			$export[]           = $rule;
		}

		wp_send_json_success( [
			'data'     => $export,
			'filename' => 'matrix-bogo-export-' . date( 'Y-m-d' ) . '.json',
		] );
	}

	// -----------------------------------------------------------------
	// AJAX: import
	// -----------------------------------------------------------------

	public function ajax_import(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$raw  = sanitize_textarea_field( wp_unslash( $_POST['data'] ?? '' ) );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid import data.', 'matrix-bogo' ) ] );
		}

		$imported = 0;
		foreach ( $data as $item ) {
			$conditions = $item['conditions'] ?? [];
			$rewards    = $item['rewards'] ?? [];
			unset( $item['id'], $item['conditions'], $item['rewards'] );
			$item['status'] = 'inactive'; // Import as inactive for safety.

			$new_id = $this->rules->create( $item );
			if ( $new_id ) {
				$this->conditions->sync_for_rule( $new_id, $conditions );
				$this->rewards->sync_for_rule( $new_id, $rewards );
				++$imported;
			}
		}

		$this->rules->flush_cache();
		wp_send_json_success( [
			'imported' => $imported,
			'message'  => sprintf(
				/* translators: %d: number of promotions */
				__( 'Imported %d promotion(s).', 'matrix-bogo' ),
				$imported
			),
		] );
	}
}
