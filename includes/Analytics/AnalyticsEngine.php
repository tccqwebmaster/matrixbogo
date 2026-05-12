<?php
/**
 * Analytics Engine – records order redemption analytics.
 *
 * @package MatrixBogo\Analytics
 */

declare( strict_types=1 );

namespace MatrixBogo\Analytics;

use MatrixBogo\Database\Repositories\AnalyticsRepository;
use MatrixBogo\Database\Repositories\RedemptionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AnalyticsEngine
 */
final class AnalyticsEngine {

	/** @var AnalyticsRepository */
	private AnalyticsRepository $analytics;

	/** @var RedemptionsRepository */
	private RedemptionsRepository $redemptions;

	public function __construct() {
		$this->analytics   = new AnalyticsRepository();
		$this->redemptions = new RedemptionsRepository();
	}

	/**
	 * Records analytics data when an order containing promoted items is created.
	 *
	 * @param \WC_Order $order
	 * @param int       $rule_id
	 * @param float     $discount
	 * @param float     $revenue  Full order line total attributed to this rule.
	 */
	public function record_for_order( \WC_Order $order, int $rule_id, float $discount, float $revenue ): void {
		$date = $order->get_date_created()?->date( 'Y-m-d' ) ?? date( 'Y-m-d' );

		$this->analytics->upsert_day( $rule_id, $date, [
			'redemptions'    => 1,
			'revenue'        => $revenue,
			'discount_total' => $discount,
			'impressions'    => 0,
		] );
	}

	/**
	 * Records an impression (promotion shown to customer).
	 *
	 * @param int $rule_id
	 */
	public function record_impression( int $rule_id ): void {
		$this->analytics->upsert_day( $rule_id, date( 'Y-m-d' ), [
			'redemptions'    => 0,
			'revenue'        => 0,
			'discount_total' => 0,
			'impressions'    => 1,
		] );
	}
}
