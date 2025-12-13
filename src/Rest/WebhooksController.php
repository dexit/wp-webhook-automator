<?php
/**
 * Webhooks REST Controller
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Rest;

use WWA\Core\Webhook;
use WWA\Core\WebhookRepository;
use WWA\Core\WebhookDispatcher;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for webhook operations.
 */
class WebhooksController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'webhooks';

	/**
	 * Webhook repository.
	 *
	 * @var WebhookRepository
	 */
	private WebhookRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new WebhookRepository();
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		// GET/POST /webhooks.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_webhook_params(),
				),
			)
		);

		// GET/PUT/DELETE /webhooks/{id}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the webhook.', 'wp-webhook-automator' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_webhook_params( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the webhook.', 'wp-webhook-automator' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
			)
		);

		// POST /webhooks/{id}/toggle.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/toggle',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_item' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'id' => array(
						'description' => __( 'Unique identifier for the webhook.', 'wp-webhook-automator' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
			)
		);

		// POST /webhooks/{id}/test.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_item' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'id' => array(
						'description' => __( 'Unique identifier for the webhook.', 'wp-webhook-automator' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
			)
		);

		// POST /webhooks/{id}/duplicate.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'id' => array(
						'description' => __( 'Unique identifier for the webhook.', 'wp-webhook-automator' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Get a collection of webhooks.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$pagination = $this->get_pagination_params( $request );
		$criteria   = array();

		// Filter by trigger type.
		$trigger_type = $request->get_param( 'trigger_type' );
		if ( $trigger_type ) {
			$criteria['trigger_type'] = sanitize_text_field( $trigger_type );
		}

		// Filter by active status.
		$is_active = $request->get_param( 'is_active' );
		if ( $is_active !== null && $is_active !== '' ) {
			$criteria['is_active'] = (int) $is_active;
		}

		// Get webhooks.
		$webhooks = $this->repository->findAll( $criteria, $pagination['per_page'], $pagination['offset'] );
		$total    = $this->repository->count( $criteria );

		// Format response.
		$items = array_map(
			function ( Webhook $webhook ) {
				return $this->prepare_webhook_for_response( $webhook );
			},
			$webhooks
		);

		return $this->paginated_response( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get a single webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = $this->repository->find( $id );

		if ( ! $webhook ) {
			return $this->error( 'webhook_not_found', __( 'Webhook not found.', 'wp-webhook-automator' ), 404 );
		}

		return $this->success( $this->prepare_webhook_for_response( $webhook ) );
	}

	/**
	 * Create a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		// Validate required fields.
		$validation = $this->validate_required_fields( $request, array( 'name', 'trigger_type', 'endpoint_url' ) );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Create webhook from request.
		$webhook = $this->create_webhook_from_request( $request );
		$webhook->setCreatedBy( get_current_user_id() );

		// Validate endpoint URL.
		$endpoint_url = $webhook->getEndpointUrl();
		if ( ! filter_var( $endpoint_url, FILTER_VALIDATE_URL ) ) {
			return $this->error( 'invalid_endpoint_url', __( 'Invalid endpoint URL.', 'wp-webhook-automator' ), 400 );
		}

		// Save webhook.
		$id = $this->repository->save( $webhook );

		if ( ! $id ) {
			return $this->error( 'webhook_save_failed', __( 'Failed to save webhook.', 'wp-webhook-automator' ), 500 );
		}

		$webhook = $this->repository->find( $id );

		return $this->success(
			$this->prepare_webhook_for_response( $webhook ),
			__( 'Webhook created successfully.', 'wp-webhook-automator' ),
			201
		);
	}

	/**
	 * Update a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = $this->repository->find( $id );

		if ( ! $webhook ) {
			return $this->error( 'webhook_not_found', __( 'Webhook not found.', 'wp-webhook-automator' ), 404 );
		}

		// Update webhook from request.
		$this->update_webhook_from_request( $webhook, $request );

		// Validate endpoint URL if provided.
		$endpoint_url = $webhook->getEndpointUrl();
		if ( $endpoint_url && ! filter_var( $endpoint_url, FILTER_VALIDATE_URL ) ) {
			return $this->error( 'invalid_endpoint_url', __( 'Invalid endpoint URL.', 'wp-webhook-automator' ), 400 );
		}

		// Save webhook.
		$this->repository->save( $webhook );

		// Reload to get updated timestamps.
		$webhook = $this->repository->find( $id );

		return $this->success(
			$this->prepare_webhook_for_response( $webhook ),
			__( 'Webhook updated successfully.', 'wp-webhook-automator' )
		);
	}

	/**
	 * Delete a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = $this->repository->find( $id );

		if ( ! $webhook ) {
			return $this->error( 'webhook_not_found', __( 'Webhook not found.', 'wp-webhook-automator' ), 404 );
		}

		$deleted = $this->repository->delete( $id );

		if ( ! $deleted ) {
			return $this->error( 'webhook_delete_failed', __( 'Failed to delete webhook.', 'wp-webhook-automator' ), 500 );
		}

		return $this->success( null, __( 'Webhook deleted successfully.', 'wp-webhook-automator' ) );
	}

	/**
	 * Toggle webhook active status.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle_item( $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = $this->repository->find( $id );

		if ( ! $webhook ) {
			return $this->error( 'webhook_not_found', __( 'Webhook not found.', 'wp-webhook-automator' ), 404 );
		}

		$this->repository->toggleActive( $id );

		// Reload to get updated status.
		$webhook = $this->repository->find( $id );
		$status  = $webhook->isActive() ? __( 'activated', 'wp-webhook-automator' ) : __( 'deactivated', 'wp-webhook-automator' );

		return $this->success(
			$this->prepare_webhook_for_response( $webhook ),
			/* translators: %s: status (activated/deactivated) */
			sprintf( __( 'Webhook %s successfully.', 'wp-webhook-automator' ), $status )
		);
	}

	/**
	 * Send a test webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_item( $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = $this->repository->find( $id );

		if ( ! $webhook ) {
			return $this->error( 'webhook_not_found', __( 'Webhook not found.', 'wp-webhook-automator' ), 404 );
		}

		// Create test event data.
		$test_data = array(
			'test'      => true,
			'timestamp' => time(),
			'message'   => __( 'This is a test webhook from Webhook Automator.', 'wp-webhook-automator' ),
			'post'      => array(
				'id'       => 1,
				'title'    => __( 'Test Post Title', 'wp-webhook-automator' ),
				'content'  => __( 'Test post content.', 'wp-webhook-automator' ),
				'excerpt'  => __( 'Test post excerpt.', 'wp-webhook-automator' ),
				'status'   => 'publish',
				'type'     => 'post',
				'slug'     => 'test-post',
				'url'      => home_url( '/test-post/' ),
				'author'   => array(
					'id'    => get_current_user_id(),
					'name'  => wp_get_current_user()->display_name,
					'email' => wp_get_current_user()->user_email,
				),
				'date'     => current_time( 'mysql' ),
				'modified' => current_time( 'mysql' ),
			),
			'user'      => array(
				'id'           => get_current_user_id(),
				'login'        => wp_get_current_user()->user_login,
				'email'        => wp_get_current_user()->user_email,
				'display_name' => wp_get_current_user()->display_name,
				'first_name'   => wp_get_current_user()->first_name,
				'last_name'    => wp_get_current_user()->last_name,
			),
		);

		// Dispatch webhook.
		$dispatcher = new WebhookDispatcher();
		$result     = $dispatcher->dispatchTest( $webhook, $test_data );

		if ( $result['success'] ) {
			return $this->success(
				array(
					'response_code' => $result['response_code'],
					'response_body' => $result['response_body'],
					'duration_ms'   => $result['duration_ms'],
				),
				__( 'Test webhook sent successfully.', 'wp-webhook-automator' )
			);
		}

		return $this->error(
			'test_webhook_failed',
			/* translators: %s: error message */
			sprintf( __( 'Test webhook failed: %s', 'wp-webhook-automator' ), $result['error'] ),
			400
		);
	}

	/**
	 * Duplicate a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate_item( $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$webhook = $this->repository->find( $id );

		if ( ! $webhook ) {
			return $this->error( 'webhook_not_found', __( 'Webhook not found.', 'wp-webhook-automator' ), 404 );
		}

		$new_id = $this->repository->duplicate( $id );

		if ( ! $new_id ) {
			return $this->error( 'webhook_duplicate_failed', __( 'Failed to duplicate webhook.', 'wp-webhook-automator' ), 500 );
		}

		$new_webhook = $this->repository->find( $new_id );

		return $this->success(
			$this->prepare_webhook_for_response( $new_webhook ),
			__( 'Webhook duplicated successfully.', 'wp-webhook-automator' ),
			201
		);
	}

	/**
	 * Prepare a webhook for response.
	 *
	 * @param Webhook $webhook Webhook object.
	 * @return array
	 */
	private function prepare_webhook_for_response( Webhook $webhook ): array {
		return array(
			'id'               => $webhook->getId(),
			'name'             => $webhook->getName(),
			'description'      => $webhook->getDescription(),
			'trigger_type'     => $webhook->getTriggerType(),
			'trigger_config'   => $webhook->getTriggerConfig(),
			'endpoint_url'     => $webhook->getEndpointUrl(),
			'http_method'      => $webhook->getHttpMethod(),
			'headers'          => $webhook->getHeaders(),
			'payload_format'   => $webhook->getPayloadFormat(),
			'payload_template' => $webhook->getPayloadTemplate(),
			'secret_key'       => $webhook->getSecretKey() ? '********' : null, // Mask secret.
			'is_active'        => $webhook->isActive(),
			'retry_count'      => $webhook->getRetryCount(),
			'retry_delay'      => $webhook->getRetryDelay(),
			'created_at'       => $webhook->getCreatedAt(),
			'updated_at'       => $webhook->getUpdatedAt(),
			'created_by'       => $webhook->getCreatedBy(),
		);
	}

	/**
	 * Create a webhook object from request data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return Webhook
	 */
	private function create_webhook_from_request( WP_REST_Request $request ): Webhook {
		$webhook = new Webhook();

		$webhook->setName( sanitize_text_field( $request->get_param( 'name' ) ) );
		$webhook->setDescription( sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ) );
		$webhook->setTriggerType( sanitize_text_field( $request->get_param( 'trigger_type' ) ) );
		$webhook->setTriggerConfig( $request->get_param( 'trigger_config' ) ?? array() );
		$webhook->setEndpointUrl( esc_url_raw( $request->get_param( 'endpoint_url' ) ) );
		$webhook->setHttpMethod( sanitize_text_field( $request->get_param( 'http_method' ) ?? 'POST' ) );
		$webhook->setHeaders( $request->get_param( 'headers' ) ?? array() );
		$webhook->setPayloadFormat( sanitize_text_field( $request->get_param( 'payload_format' ) ?? 'json' ) );
		$webhook->setPayloadTemplate( $request->get_param( 'payload_template' ) ?? array() );
		$webhook->setSecretKey( sanitize_text_field( $request->get_param( 'secret_key' ) ?? '' ) ?: null );
		$webhook->setIsActive( (bool) ( $request->get_param( 'is_active' ) ?? true ) );
		$webhook->setRetryCount( absint( $request->get_param( 'retry_count' ) ?? 3 ) );
		$webhook->setRetryDelay( absint( $request->get_param( 'retry_delay' ) ?? 60 ) );

		return $webhook;
	}

	/**
	 * Update a webhook object from request data.
	 *
	 * @param Webhook         $webhook Webhook object.
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_webhook_from_request( Webhook $webhook, WP_REST_Request $request ): void {
		$params = $request->get_params();

		if ( isset( $params['name'] ) ) {
			$webhook->setName( sanitize_text_field( $params['name'] ) );
		}
		if ( isset( $params['description'] ) ) {
			$webhook->setDescription( sanitize_textarea_field( $params['description'] ) );
		}
		if ( isset( $params['trigger_type'] ) ) {
			$webhook->setTriggerType( sanitize_text_field( $params['trigger_type'] ) );
		}
		if ( isset( $params['trigger_config'] ) ) {
			$webhook->setTriggerConfig( $params['trigger_config'] );
		}
		if ( isset( $params['endpoint_url'] ) ) {
			$webhook->setEndpointUrl( esc_url_raw( $params['endpoint_url'] ) );
		}
		if ( isset( $params['http_method'] ) ) {
			$webhook->setHttpMethod( sanitize_text_field( $params['http_method'] ) );
		}
		if ( isset( $params['headers'] ) ) {
			$webhook->setHeaders( $params['headers'] );
		}
		if ( isset( $params['payload_format'] ) ) {
			$webhook->setPayloadFormat( sanitize_text_field( $params['payload_format'] ) );
		}
		if ( isset( $params['payload_template'] ) ) {
			$webhook->setPayloadTemplate( $params['payload_template'] );
		}
		if ( isset( $params['secret_key'] ) ) {
			// Only update if not the masked value.
			if ( $params['secret_key'] !== '********' ) {
				$webhook->setSecretKey( sanitize_text_field( $params['secret_key'] ) ?: null );
			}
		}
		if ( isset( $params['is_active'] ) ) {
			$webhook->setIsActive( (bool) $params['is_active'] );
		}
		if ( isset( $params['retry_count'] ) ) {
			$webhook->setRetryCount( absint( $params['retry_count'] ) );
		}
		if ( isset( $params['retry_delay'] ) ) {
			$webhook->setRetryDelay( absint( $params['retry_delay'] ) );
		}
	}

	/**
	 * Get collection parameters for list endpoint.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return array(
			'page'         => array(
				'description'       => __( 'Current page of the collection.', 'wp-webhook-automator' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'     => array(
				'description'       => __( 'Maximum number of items to return.', 'wp-webhook-automator' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'trigger_type' => array(
				'description'       => __( 'Filter by trigger type.', 'wp-webhook-automator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'is_active'    => array(
				'description' => __( 'Filter by active status.', 'wp-webhook-automator' ),
				'type'        => 'integer',
				'enum'        => array( 0, 1 ),
			),
		);
	}

	/**
	 * Get webhook parameters for create/update endpoints.
	 *
	 * @param bool $required Whether fields are required.
	 * @return array
	 */
	private function get_webhook_params( bool $required = true ): array {
		return array(
			'name'             => array(
				'description'       => __( 'Webhook name.', 'wp-webhook-automator' ),
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'      => array(
				'description'       => __( 'Webhook description.', 'wp-webhook-automator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'trigger_type'     => array(
				'description'       => __( 'Trigger type identifier.', 'wp-webhook-automator' ),
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'trigger_config'   => array(
				'description' => __( 'Trigger configuration.', 'wp-webhook-automator' ),
				'type'        => 'object',
			),
			'endpoint_url'     => array(
				'description'       => __( 'Webhook endpoint URL.', 'wp-webhook-automator' ),
				'type'              => 'string',
				'required'          => $required,
				'format'            => 'uri',
				'sanitize_callback' => 'esc_url_raw',
			),
			'http_method'      => array(
				'description' => __( 'HTTP method.', 'wp-webhook-automator' ),
				'type'        => 'string',
				'default'     => 'POST',
				'enum'        => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
			),
			'headers'          => array(
				'description' => __( 'Custom HTTP headers.', 'wp-webhook-automator' ),
				'type'        => 'object',
			),
			'payload_format'   => array(
				'description' => __( 'Payload format.', 'wp-webhook-automator' ),
				'type'        => 'string',
				'default'     => 'json',
				'enum'        => array( 'json', 'form' ),
			),
			'payload_template' => array(
				'description' => __( 'Payload template.', 'wp-webhook-automator' ),
				'type'        => 'object',
			),
			'secret_key'       => array(
				'description'       => __( 'Secret key for signature.', 'wp-webhook-automator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'is_active'        => array(
				'description' => __( 'Whether webhook is active.', 'wp-webhook-automator' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'retry_count'      => array(
				'description'       => __( 'Number of retry attempts.', 'wp-webhook-automator' ),
				'type'              => 'integer',
				'default'           => 3,
				'minimum'           => 0,
				'maximum'           => 10,
				'sanitize_callback' => 'absint',
			),
			'retry_delay'      => array(
				'description'       => __( 'Delay between retries in seconds.', 'wp-webhook-automator' ),
				'type'              => 'integer',
				'default'           => 60,
				'minimum'           => 10,
				'maximum'           => 3600,
				'sanitize_callback' => 'absint',
			),
		);
	}
}
