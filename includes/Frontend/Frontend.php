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

final class Frontend {

	private PromotionEngine $engine;
	private ProductPage     $product_page;
	private CartPage        $cart_page;
	private CheckoutPage    $checkout_page;
	private ProgressBar     $progress_bar;
	private CountdownTimer  $countdown_timer;

	public function __construct( PromotionEngine $engine ) {
		$this->engine          = $engine;
		$this->product_page    = new ProductPage( $engine );
		$this->cart_page       = new CartPage( $engine );
		$this->checkout_page   = new CheckoutPage( $engine );
		$this->progress_bar    = new ProgressBar( $engine );
		$this->countdown_timer = new CountdownTimer( $engine );
	}

	public function init( Loader $loader ): void {
		$this->product_page->init( $loader );
		$this->cart_page->init( $loader );
		$this->checkout_page->init( $loader );
		$this->progress_bar->init( $loader );
		$this->countdown_timer->init( $loader );
	}
}
