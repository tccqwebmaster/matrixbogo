<?php
/**
 * Conversion Tracker – tracks promotion impressions and conversions.
 *
 * @package MatrixBogo\Analytics
 */

declare( strict_types=1 );

namespace MatrixBogo\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConversionTracker
 */
final class ConversionTracker {

	/** @var AnalyticsEngine */
	private AnalyticsEngine $engine;

	public function __construct( AnalyticsEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Records a promotion impression (rule was shown/evaluated).
	 *
	 * @param int $rule_id
	 */
	public function track_impression( int $rule_id ): void {
		// Avoid double-counting per session.
		$session_key = 'matrix_bogo_impression_' . $rule_id;
		if ( WC()->session && WC()->session->get( $session_key ) ) {
			return;
		}

		$this->engine->record_impression( $rule_id );

		if ( WC()->session ) {
			WC()->session->set( $session_key, true );
		}
	}
}
