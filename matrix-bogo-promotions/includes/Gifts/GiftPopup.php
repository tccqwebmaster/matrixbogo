<?php
/**
 * Gift Popup – renders the gift selector modal on the frontend.
 *
 * @package MatrixBogo\Gifts
 */

declare( strict_types=1 );

namespace MatrixBogo\Gifts;

use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GiftPopup
 */
final class GiftPopup {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	public function __construct( PromotionEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Renders the popup container in the footer.
	 * The actual content is loaded via AJAX when the customer qualifies.
	 */
	public function render_popup_container(): void {
		if ( ! is_cart() && ! is_checkout() && ! is_shop() && ! is_product() ) {
			return;
		}

		// Check if any active promotion has customer-choice gifts.
		$rewards = $this->engine->get_session_rewards();
		$has_choice_gift = false;

		foreach ( $rewards as $entry ) {
			foreach ( $entry['rewards'] ?? [] as $reward ) {
				if ( ! empty( $reward['customer_choice'] ) ) {
					$has_choice_gift = true;
					break 2;
				}
			}
		}

		if ( ! $has_choice_gift ) {
			return;
		}

		// Load popup template.
		$template = MATRIX_BOGO_PLUGIN_DIR . 'templates/popup/gift-selector.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
