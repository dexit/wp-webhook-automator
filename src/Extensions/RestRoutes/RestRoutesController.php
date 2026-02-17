<?php
/**
 * Rest Routes Admin Controller
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\RestRoutes;

use Hookly\Rest\RestController;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class RestRoutesController extends RestController {

	/**
	 * Route base.
	 */
	protected $rest_base = 'rest-routes';

	/**
	 * Repository.
	 */
	private RestRouteRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new RestRouteRepository();
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				],
			],
			'schema' => [ $this, 'get_item_schema' ]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Get items.
	 */
	public function get_items( $request ) {
		$routes = $this->repository->findAll();
		$items  = array_map( fn( $route ) => $route->toArray(), $routes );

		return $this->success( $items );
	}

	/**
	 * Get item.
	 */
	public function get_item( $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$route = $this->repository->find( $id );

		if ( ! $route ) {
			return $this->error( 'route_not_found', __( 'Route not found.', 'hookly-webhook-automator' ), 404 );
		}

		return $this->success( $route->toArray() );
	}

	/**
	 * Create item.
	 */
	public function create_item( $request ) {
		$route = new RestRoute();
		$this->update_route_from_request( $route, $request );

		$id = $this->repository->save( $route );
		if ( ! $id ) {
			return $this->error( 'route_save_failed', __( 'Failed to save route.', 'hookly-webhook-automator' ), 500 );
		}

		$route = $this->repository->find( $id );
		return $this->success( $route->toArray(), __( 'Route created successfully.', 'hookly-webhook-automator' ), 201 );
	}

	/**
	 * Update item.
	 */
	public function update_item( $request ) {
		$id    = absint( $request->get_param( 'id' ) );
		$route = $this->repository->find( $id );

		if ( ! $route ) {
			return $this->error( 'route_not_found', __( 'Route not found.', 'hookly-webhook-automator' ), 404 );
		}

		$this->update_route_from_request( $route, $request );
		$this->repository->save( $route );

		return $this->success( $route->toArray(), __( 'Route updated successfully.', 'hookly-webhook-automator' ) );
	}

	/**
	 * Delete item.
	 */
	public function delete_item( $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = $this->repository->delete( $id );

		if ( ! $deleted ) {
			return $this->error( 'route_delete_failed', __( 'Failed to delete route.', 'hookly-webhook-automator' ), 500 );
		}

		return $this->success( null, __( 'Route deleted successfully.', 'hookly-webhook-automator' ) );
	}

	/**
	 * Get the item schema.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'rest_route',
			'type'       => 'object',
			'properties' => [
				'id'            => [
					'description' => __( 'Unique identifier for the route.', 'hookly-webhook-automator' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'name'          => [
					'description' => __( 'The name of the route.', 'hookly-webhook-automator' ),
					'type'        => 'string',
					'required'    => true,
					'context'     => [ 'view', 'edit' ],
				],
				'route_path'    => [
					'description' => __( 'The relative path of the route.', 'hookly-webhook-automator' ),
					'type'        => 'string',
					'required'    => true,
					'context'     => [ 'view', 'edit' ],
				],
				'methods'       => [
					'description' => __( 'The HTTP methods allowed for the route.', 'hookly-webhook-automator' ),
					'type'        => 'array',
					'items'       => [
						'type' => 'string',
						'enum' => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ],
					],
					'default'     => [ 'POST' ],
					'context'     => [ 'view', 'edit' ],
				],
				'actions'       => [
					'description' => __( 'Sequence of actions to perform.', 'hookly-webhook-automator' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'type'   => [ 'type' => 'string' ],
							'config' => [ 'type' => 'object' ],
						],
					],
					'context'     => [ 'view', 'edit' ],
				],
				'is_active'     => [
					'description' => __( 'Whether the route is active.', 'hookly-webhook-automator' ),
					'type'        => 'boolean',
					'default'     => true,
					'context'     => [ 'view', 'edit' ],
				],
				'is_async'      => [
					'description' => __( 'Whether the actions should be processed in the background.', 'hookly-webhook-automator' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => [ 'view', 'edit' ],
				],
				'secret_key'    => [
					'description' => __( 'A secret key for validation (optional).', 'hookly-webhook-automator' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
			],
		];

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Update route entity from request.
	 */
	private function update_route_from_request( RestRoute $route, WP_REST_Request $request ): void {
		$schema     = $this->get_item_schema();
		$properties = $schema['properties'];
		$params     = $request->get_params();

		if ( isset( $params['name'] ) ) {
			$route->setName( rest_sanitize_value_from_schema( $params['name'], $properties['name'] ) );
		}
		if ( isset( $params['route_path'] ) ) {
			$route->setRoutePath( rest_sanitize_value_from_schema( $params['route_path'], $properties['route_path'] ) );
		}
		if ( isset( $params['methods'] ) ) {
			$route->setMethods( rest_sanitize_value_from_schema( $params['methods'], $properties['methods'] ) );
		}
		if ( isset( $params['actions'] ) ) {
			$route->setActions( (array) $params['actions'] );
		}
		if ( isset( $params['is_active'] ) ) {
			$route->setIsActive( rest_sanitize_value_from_schema( $params['is_active'], $properties['is_active'] ) );
		}
		if ( isset( $params['is_async'] ) ) {
			$route->setIsAsync( rest_sanitize_value_from_schema( $params['is_async'], $properties['is_async'] ) );
		}
		if ( isset( $params['secret_key'] ) ) {
			$route->setSecretKey( rest_sanitize_value_from_schema( $params['secret_key'], $properties['secret_key'] ) );
		}
	}
}
