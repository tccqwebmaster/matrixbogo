<?php
/**
 * Revenue Tracker – attributes revenue to promotion rules.
 *
 * @package MatrixBogo\Analytics
 */

declare( strict_types=1 );

namespace MatrixBogo\Analytics;

use MatrixBogo\Database\Repositories\RedemptionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RevenueTracker
 */
final class RevenueTracker {

	/** @var RedemptionsRepository */
	private RedemptionsRepository $repo;

	/** @var AnalyticsEngine */
	private AnalyticsEngine $engine;

	public function __construct( AnalyticsEngine $engine ) {
		$this->engine = $engine;
		$this->repo   = new RedemptionsRepository();
	}

	/**
	 * Hooks into order completion to update revenue attribution.
	 *
	 * @param int $order_id
	 */
	public function on_order_complete( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$redemptions = $this->repo->get_for_order( $order_id );
		foreach ( $redemptions as $redemption ) {
			$this->engine->record_for_order(
				$order,
				(int) $redemption['rule_id'],
				(float) $redemption['discount_total'],
				(float) $order->get_total()
			);
		}
	}
}
