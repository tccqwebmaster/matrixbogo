<?php
/**
 * Frontend bootstrap – registers all frontend hooks.
 *
 * @package MatrixBogo\Frontend
 */

declare( strict_types=1 );

namespace MatrixBogo\Frontend;

use MatrixBogo\Core\Loader;
use MatrixBogo\Promotions\PromotionEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Frontend
 */
final class Frontend {

	/** @var PromotionEngine */
	private PromotionEngine $engine;

	/** @var ProductPage */
	private ProductPage $product_page;

	/** @var CartPage */
	private CartPage $cart_page;

	/** @var CheckoutPage */
	private CheckoutPage $checkout_page;

	public function __construct( PromotionEngine $engine ) {
		$this->engine        = $engine;
		$this->product_page  = new ProductPage( $engine );
		$this->cart_page     = new CartPage( $engine );
		$this->checkout_page = new CheckoutPage( $engine );
	}

	public function init( Loader $loader ): void {
		$this->product_page->init( $loader );
		$this->cart_page->init( $loader );
		$this->checkout_page->init( $loader );
	}
}
