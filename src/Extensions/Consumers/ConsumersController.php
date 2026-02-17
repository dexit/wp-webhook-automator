<?php
/**
 * Consumers Admin Controller
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\Consumers;

use Hookly\Rest\RestController;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ConsumersController extends RestController {

	protected $rest_base = 'consumers';
	private ConsumerRepository $repository;

	public function __construct() {
		$this->repository = new ConsumerRepository();
	}

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
				],
			]
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
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
				],
			]
		);
	}

	public function get_items( $request ) {
		$consumers = $this->repository->findAll();
		return $this->success( array_map( fn( $c ) => $c->toArray(), $consumers ) );
	}

	public function get_item( $request ) {
		$id = absint( $request->get_param( 'id' ) );
		$consumer = $this->repository->find( $id );
		if ( ! $consumer ) return $this->error( 'not_found', 'Consumer not found', 404 );
		return $this->success( $consumer->toArray() );
	}

	public function create_item( $request ) {
		$consumer = new Consumer();
		$this->update_from_request( $consumer, $request );
		$id = $this->repository->save( $consumer );
		return $this->success( $this->repository->find( $id )->toArray(), 'Created', 201 );
	}

	public function update_item( $request ) {
		$id = absint( $request->get_param( 'id' ) );
		$consumer = $this->repository->find( $id );
		if ( ! $consumer ) return $this->error( 'not_found', 'Consumer not found', 404 );
		$this->update_from_request( $consumer, $request );
		$this->repository->save( $consumer );
		return $this->success( $consumer->toArray(), 'Updated' );
	}

	public function delete_item( $request ) {
		$id = absint( $request->get_param( 'id' ) );
		$this->repository->delete( $id );
		return $this->success( null, 'Deleted' );
	}

	private function update_from_request( Consumer $consumer, WP_REST_Request $request ) {
		$params = $request->get_params();
		if ( isset( $params['name'] ) ) $consumer->setName( sanitize_text_field( $params['name'] ) );
		if ( isset( $params['source_url'] ) ) $consumer->setSourceUrl( esc_url_raw( $params['source_url'] ) );
		if ( isset( $params['http_method'] ) ) $consumer->setHttpMethod( sanitize_text_field( $params['http_method'] ) );
		if ( isset( $params['headers'] ) ) $consumer->setHeaders( (array) $params['headers'] );
		if ( isset( $params['schedule'] ) ) $consumer->setSchedule( sanitize_text_field( $params['schedule'] ) );
		if ( isset( $params['actions'] ) ) $consumer->setActions( (array) $params['actions'] );
		if ( isset( $params['is_active'] ) ) $consumer->setIsActive( (bool) $params['is_active'] );
	}
}
