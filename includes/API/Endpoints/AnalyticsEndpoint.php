<?php
/**
 * Analytics REST Endpoint.
 *
 * @package MatrixBogo\API\Endpoints
 */

declare( strict_types=1 );

namespace MatrixBogo\API\Endpoints;

use MatrixBogo\API\RestAPI;
use MatrixBogo\Database\Repositories\AnalyticsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AnalyticsEndpoint
 *
 * FIX: The original get_analytics() called $this->repo->get_daily($from, $to)
 * but get_daily() requires (int $rule_id, string $from, string $to).
 * That caused a TypeError / fatal on every REST call to this endpoint.
 * The aggregate overview must use get_daily_totals($from, $to) instead.
 */
final class AnalyticsEndpoint {

	/** @var AnalyticsRepository */
	private AnalyticsRepository $repo;

	public function __construct() {
		$this->repo = new AnalyticsRepository();
	}

	public function register_routes(): void {
		register_rest_route( RestAPI::NAMESPACE, '/analytics', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_analytics' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'from' => [ 'sanitize_callback' => 'sanitize_text_field' ],
				'to'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );

		register_rest_route( RestAPI::NAMESPACE, '/analytics/top', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_top' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		// New: per-rule breakdown endpoint, correctly using get_daily().
		register_rest_route( RestAPI::NAMESPACE, '/analytics/rule/(?P<rule_id>[\d]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_rule_analytics' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );
	}

	public function permission_check(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * GET /analytics — aggregate overview across all rules.
	 * FIX: now uses get_daily_totals() not get_daily().
	 */
	public function get_analytics( \WP_REST_Request $request ): \WP_REST_Response {
		$from = $request->get_param( 'from' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) );
		$to   = $request->get_param( 'to' )   ?: date( 'Y-m-d' );

		// Safety: clamp to 365 days.
		if ( ( strtotime( $to ) - strtotime( $from ) ) > 365 * DAY_IN_SECONDS ) {
			$from = date( 'Y-m-d', strtotime( $to ) - 365 * DAY_IN_SECONDS );
		}

		return rest_ensure_response( [
			'totals' => $this->repo->get_totals( $from, $to ),
			'daily'  => $this->repo->get_daily_totals( $from, $to ), // FIX
		] );
	}

	/**
	 * GET /analytics/top — promotion leaderboard.
	 */
	public function get_top( \WP_REST_Request $request ): \WP_REST_Response {
		$from  = $request->get_param( 'from' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) );
		$to    = $request->get_param( 'to' )   ?: date( 'Y-m-d' );
		$limit = min( 50, max( 1, (int) ( $request->get_param( 'limit' ) ?: 10 ) ) );

		return rest_ensure_response( $this->repo->get_top_rules( $from, $to, $limit ) );
	}

	/**
	 * GET /analytics/rule/{rule_id} — per-rule daily breakdown.
	 */
	public function get_rule_analytics( \WP_REST_Request $request ): \WP_REST_Response {
		$rule_id = (int) $request['rule_id'];
		$from    = sanitize_text_field( $request->get_param( 'from' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) ) );
		$to      = sanitize_text_field( $request->get_param( 'to' )   ?: date( 'Y-m-d' ) );

		return rest_ensure_response( [
			'rule_id' => $rule_id,
			'daily'   => $this->repo->get_daily( $rule_id, $from, $to ),
		] );
	}
}
