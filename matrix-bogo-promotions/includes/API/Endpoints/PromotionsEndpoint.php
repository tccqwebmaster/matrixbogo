<?php
/**
 * Promotions REST Endpoint – CRUD for promotion rules.
 *
 * @package MatrixBogo\API\Endpoints
 */

declare( strict_types=1 );

namespace MatrixBogo\API\Endpoints;

use MatrixBogo\API\RestAPI;
use MatrixBogo\Database\Repositories\ConditionsRepository;
use MatrixBogo\Database\Repositories\RewardsRepository;
use MatrixBogo\Database\Repositories\RulesRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PromotionsEndpoint
 */
final class PromotionsEndpoint {

	/** @var RulesRepository */
	private RulesRepository $rules;

	/** @var ConditionsRepository */
	private ConditionsRepository $conditions;

	/** @var RewardsRepository */
	private RewardsRepository $rewards;

	public function __construct() {
		$this->rules      = new RulesRepository();
		$this->conditions = new ConditionsRepository();
		$this->rewards    = new RewardsRepository();
	}

	public function register_routes(): void {
		register_rest_route( RestAPI::NAMESPACE, '/promotions', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
		] );

		register_rest_route( RestAPI::NAMESPACE, '/promotions/(?P<id>[\d]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
		] );
	}

	public function permission_check(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function get_items( \WP_REST_Request $request ): \WP_REST_Response {
		$status = sanitize_key( $request->get_param( 'status' ) ?? '' );
		$where  = $status ? [ 'status' => $status ] : [];
		$items  = $this->rules->find_by( $where );
		return rest_ensure_response( $items );
	}

	public function get_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request['id'];
		$rule = $this->rules->find( $id );

		if ( ! $rule ) {
			return new \WP_Error( 'not_found', __( 'Promotion not found.', 'matrix-bogo' ), [ 'status' => 404 ] );
		}

		$rule['conditions'] = $this->conditions->get_for_rule( $id );
		$rule['rewards']    = $this->rewards->get_for_rule( $id );

		return rest_ensure_response( $rule );
	}

	public function create_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = $request->get_json_params();
		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Name is required.', 'matrix-bogo' ), [ 'status' => 400 ] );
		}

		$conditions = $data['conditions'] ?? [];
		$rewards    = $data['rewards'] ?? [];
		unset( $data['conditions'], $data['rewards'], $data['id'] );
		$data['slug']   = sanitize_title( $data['name'] ) . '-' . time();
		$data['status'] = sanitize_key( $data['status'] ?? 'inactive' );

		$new_id = $this->rules->create( $data );
		if ( ! $new_id ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create promotion.', 'matrix-bogo' ), [ 'status' => 500 ] );
		}

		$this->conditions->sync_for_rule( $new_id, $conditions );
		$this->rewards->sync_for_rule( $new_id, $rewards );
		$this->rules->flush_cache();

		return rest_ensure_response( $this->rules->find( $new_id ) );
	}

	public function update_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request['id'];
		$data = $request->get_json_params();

		if ( ! $this->rules->find( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Promotion not found.', 'matrix-bogo' ), [ 'status' => 404 ] );
		}

		$conditions = $data['conditions'] ?? null;
		$rewards    = $data['rewards'] ?? null;
		unset( $data['conditions'], $data['rewards'], $data['id'] );

		$this->rules->update( $id, $data );

		if ( null !== $conditions ) {
			$this->conditions->sync_for_rule( $id, $conditions );
		}
		if ( null !== $rewards ) {
			$this->rewards->sync_for_rule( $id, $rewards );
		}

		$this->rules->flush_cache();
		return rest_ensure_response( $this->rules->find( $id ) );
	}

	public function delete_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request['id'];

		if ( ! $this->rules->find( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Promotion not found.', 'matrix-bogo' ), [ 'status' => 404 ] );
		}

		$this->rules->delete( $id );
		$this->rules->flush_cache();

		return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
	}
}
