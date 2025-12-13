<?php
/**
 * Logs REST Controller
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Rest;

use WWA\Core\Logger;
use WWA\Core\WebhookDispatcher;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for log operations.
 */
class LogsController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'logs';

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		// GET /logs.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		);

		// GET /logs/{id}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'id' => array(
						'description' => __( 'Unique identifier for the log entry.', 'webhook-automator' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
			)
		);

		// DELETE /logs/{id}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'id' => array(
						'description' => __( 'Unique identifier for the log entry.', 'webhook-automator' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
			)
		);

		// POST /logs/{id}/retry.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/retry',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'retry_item' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'id' => array(
						'description' => __( 'Unique identifier for the log entry.', 'webhook-automator' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
			)
		);

		// DELETE /logs (bulk delete / clear).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/clear',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_logs' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'webhook_id' => array(
						'description' => __( 'Filter by webhook ID.', 'webhook-automator' ),
						'type'        => 'integer',
					),
					'days'       => array(
						'description' => __( 'Delete logs older than X days.', 'webhook-automator' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
			)
		);

		// GET /logs/stats.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
			)
		);
	}

	/**
	 * Get a collection of logs.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$pagination = $this->get_pagination_params( $request );
		$criteria   = array();

		// Filter by webhook ID.
		$webhook_id = $request->get_param( 'webhook_id' );
		if ( $webhook_id ) {
			$criteria['webhook_id'] = absint( $webhook_id );
		}

		// Filter by status.
		$status = $request->get_param( 'status' );
		if ( $status ) {
			$criteria['status'] = sanitize_text_field( $status );
		}

		// Filter by trigger type.
		$trigger_type = $request->get_param( 'trigger_type' );
		if ( $trigger_type ) {
			$criteria['trigger_type'] = sanitize_text_field( $trigger_type );
		}

		// Filter by date range.
		$date_from = $request->get_param( 'date_from' );
		if ( $date_from ) {
			$criteria['date_from'] = sanitize_text_field( $date_from );
		}

		$date_to = $request->get_param( 'date_to' );
		if ( $date_to ) {
			$criteria['date_to'] = sanitize_text_field( $date_to );
		}

		// Get logs.
		$logs  = $this->logger->getLogs( $criteria, $pagination['per_page'], $pagination['offset'] );
		$total = $this->logger->count( $criteria );

		// Format response.
		$items = array_map( array( $this, 'prepare_log_for_response' ), $logs );

		return $this->paginated_response( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get a single log entry.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id  = absint( $request->get_param( 'id' ) );
		$log = $this->logger->getLog( $id );

		if ( ! $log ) {
			return $this->error( 'log_not_found', __( 'Log entry not found.', 'webhook-automator' ), 404 );
		}

		return $this->success( $this->prepare_log_for_response( $log, true ) );
	}

	/**
	 * Delete a log entry.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id  = absint( $request->get_param( 'id' ) );
		$log = $this->logger->getLog( $id );

		if ( ! $log ) {
			return $this->error( 'log_not_found', __( 'Log entry not found.', 'webhook-automator' ), 404 );
		}

		$this->logger->deleteLog( $id );

		return $this->success( null, __( 'Log entry deleted successfully.', 'webhook-automator' ) );
	}

	/**
	 * Retry a failed webhook delivery.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function retry_item( $request ) {
		$id  = absint( $request->get_param( 'id' ) );
		$log = $this->logger->getLog( $id );

		if ( ! $log ) {
			return $this->error( 'log_not_found', __( 'Log entry not found.', 'webhook-automator' ), 404 );
		}

		if ( $log['status'] === 'success' ) {
			return $this->error(
				'already_successful',
				__( 'Cannot retry a successful webhook delivery.', 'webhook-automator' ),
				400
			);
		}

		$dispatcher = new WebhookDispatcher();
		$success    = $dispatcher->retry( $id );

		// Reload log to get updated data.
		$log = $this->logger->getLog( $id );

		if ( $success ) {
			return $this->success(
				$this->prepare_log_for_response( $log ),
				__( 'Webhook retry successful.', 'webhook-automator' )
			);
		}

		return $this->success(
			$this->prepare_log_for_response( $log ),
			__( 'Webhook retry failed. See log for details.', 'webhook-automator' )
		);
	}

	/**
	 * Clear logs.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_logs( $request ) {
		$webhook_id = $request->get_param( 'webhook_id' );
		$days       = $request->get_param( 'days' );

		if ( $webhook_id ) {
			// Delete logs for a specific webhook.
			$deleted = $this->logger->deleteByWebhookId( absint( $webhook_id ) );
			return $this->success(
				array( 'deleted' => $deleted ),
				/* translators: %d: number of deleted logs */
				sprintf( __( 'Deleted %d log entries for webhook.', 'webhook-automator' ), $deleted )
			);
		}

		if ( $days ) {
			// Delete logs older than X days.
			$deleted = $this->logger->cleanup( absint( $days ) );
			return $this->success(
				array( 'deleted' => $deleted ),
				/* translators: %d: number of deleted logs */
				sprintf( __( 'Deleted %d old log entries.', 'webhook-automator' ), $deleted )
			);
		}

		// Clear all logs.
		$this->logger->clearAll();

		return $this->success( null, __( 'All logs cleared successfully.', 'webhook-automator' ) );
	}

	/**
	 * Get log statistics.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_stats( $request ) {
		$stats = $this->logger->getStats();

		return $this->success( $stats );
	}

	/**
	 * Prepare a log entry for response.
	 *
	 * @param array $log          Log entry data.
	 * @param bool  $include_full Whether to include full payload/response.
	 * @return array
	 */
	private function prepare_log_for_response( array $log, bool $include_full = false ): array {
		$prepared = array(
			'id'             => (int) $log['id'],
			'webhook_id'     => (int) $log['webhook_id'],
			'webhook_name'   => $log['webhook_name'] ?? '',
			'trigger_type'   => $log['trigger_type'],
			'endpoint_url'   => $log['endpoint_url'],
			'response_code'  => $log['response_code'] ? (int) $log['response_code'] : null,
			'duration_ms'    => $log['duration_ms'] ? (int) $log['duration_ms'] : null,
			'status'         => $log['status'],
			'error_message'  => $log['error_message'],
			'attempt_number' => (int) $log['attempt_number'],
			'created_at'     => $log['created_at'],
		);

		if ( $include_full ) {
			// Include full payload and response for detail view.
			$prepared['trigger_data']     = json_decode( $log['trigger_data'], true );
			$prepared['request_headers']  = json_decode( $log['request_headers'], true );
			$prepared['request_payload']  = $log['request_payload'];
			$prepared['response_headers'] = json_decode( $log['response_headers'], true );
			$prepared['response_body']    = $log['response_body'];
		}

		return $prepared;
	}

	/**
	 * Get collection parameters for list endpoint.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return array(
			'page'         => array(
				'description'       => __( 'Current page of the collection.', 'webhook-automator' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'     => array(
				'description'       => __( 'Maximum number of items to return.', 'webhook-automator' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'webhook_id'   => array(
				'description'       => __( 'Filter by webhook ID.', 'webhook-automator' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'status'       => array(
				'description' => __( 'Filter by status.', 'webhook-automator' ),
				'type'        => 'string',
				'enum'        => array( 'pending', 'success', 'failed' ),
			),
			'trigger_type' => array(
				'description'       => __( 'Filter by trigger type.', 'webhook-automator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_from'    => array(
				'description'       => __( 'Filter logs from this date (Y-m-d H:i:s format).', 'webhook-automator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to'      => array(
				'description'       => __( 'Filter logs until this date (Y-m-d H:i:s format).', 'webhook-automator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
