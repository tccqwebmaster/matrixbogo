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
	}

	public function permission_check(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function get_analytics( \WP_REST_Request $request ): \WP_REST_Response {
		$from = $request->get_param( 'from' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) );
		$to   = $request->get_param( 'to' ) ?: date( 'Y-m-d' );

		return rest_ensure_response( [
			'totals' => $this->repo->get_totals( $from, $to ),
			'daily'  => $this->repo->get_daily( $from, $to ),
		] );
	}

	public function get_top( \WP_REST_Request $request ): \WP_REST_Response {
		$from  = $request->get_param( 'from' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) );
		$to    = $request->get_param( 'to' ) ?: date( 'Y-m-d' );
		$limit = min( 50, max( 1, (int) ( $request->get_param( 'limit' ) ?: 10 ) ) );

		return rest_ensure_response( $this->repo->get_top_rules( $from, $to, $limit ) );
	}
}
