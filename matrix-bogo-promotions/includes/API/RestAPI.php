<?php
/**
 * REST API bootstrap – registers all plugin REST routes.
 *
 * @package MatrixBogo\API
 */

declare( strict_types=1 );

namespace MatrixBogo\API;

use MatrixBogo\API\Endpoints\AnalyticsEndpoint;
use MatrixBogo\API\Endpoints\LogsEndpoint;
use MatrixBogo\API\Endpoints\PromotionsEndpoint;
use MatrixBogo\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestAPI
 */
final class RestAPI {

	public const NAMESPACE = 'matrix-bogo/v1';

	public function init( Loader $loader ): void {
		$loader->add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new PromotionsEndpoint() )->register_routes();
		( new AnalyticsEndpoint() )->register_routes();
		( new LogsEndpoint() )->register_routes();
	}
}
