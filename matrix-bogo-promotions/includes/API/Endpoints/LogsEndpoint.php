<?php
/**
 * Logs REST Endpoint.
 *
 * @package MatrixBogo\API\Endpoints
 */

declare( strict_types=1 );

namespace MatrixBogo\API\Endpoints;

use MatrixBogo\API\RestAPI;
use MatrixBogo\Database\Repositories\LogsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LogsEndpoint
 */
final class LogsEndpoint {

	/** @var LogsRepository */
	private LogsRepository $repo;

	public function __construct() {
		$this->repo = new LogsRepository();
	}

	public function register_routes(): void {
		register_rest_route( RestAPI::NAMESPACE, '/logs', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_logs' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( RestAPI::NAMESPACE, '/logs/(?P<id>[\d]+)', [
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'delete_log' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );
	}

	public function permission_check(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function get_logs( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = min( 200, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 50 ) ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$level    = sanitize_key( $request->get_param( 'level' ) ?: '' ) ?: null;
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?: '' ) ?: null;

		return rest_ensure_response( $this->repo->paginate( compact( 'per_page', 'page', 'level', 'search' ) ) );
	}

	public function delete_log( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request['id'];
		$this->repo->delete( $id );
		return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
	}
}
