<?php
/**
 * Rest Route Manager
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\RestRoutes;

class RestRouteManager {

	/**
	 * Repository.
	 *
	 * @var RestRouteRepository
	 */
	private RestRouteRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new RestRouteRepository();
	}

	/**
	 * Initialize the manager.
	 */
	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_custom_routes' ] );
		add_action( 'hookly_process_rest_route_action', [ $this, 'handle_async_action' ], 10, 2 );
	}

	/**
	 * Register custom REST routes.
	 */
	public function register_custom_routes(): void {
		// Only run if the table exists to avoid errors during installation
		global $wpdb;
		$table_name = $wpdb->prefix . 'hookly_rest_routes';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return;
		}

		$routes     = $this->repository->findAll();
		$controller = new IncomingController();

		foreach ( $routes as $route ) {
			if ( ! $route->isActive() ) {
				continue;
			}

			$path = ltrim( $route->getRoutePath(), '/' );

			register_rest_route(
				'hookly/v1/incoming',
				'/' . $path,
				[
					'methods'             => $route->getMethods(),
					'callback'            => function( $request ) use ( $controller, $route ) {
						return $controller->handle_request( $request, $route );
					},
					'permission_callback' => '__return_true',
				]
			);
		}
	}

	/**
	 * Handle async action processing.
	 *
	 * @param int   $route_id The route ID.
	 * @param array $data     The request data.
	 */
	public function handle_async_action( int $route_id, array $data ): void {
		$route = $this->repository->find( $route_id );
		if ( ! $route || ! $route->isActive() ) {
			return;
		}

		$processor = new ActionProcessor();
		$processor->process( $route, $data );
	}
}
