<?php
/**
 * Triggers REST Controller
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Rest;

use Hookly\Triggers\TriggerRegistry;
use Hookly\Triggers\TriggerInterface;
use Hookly\Core\PayloadBuilder;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for trigger operations.
 */
class TriggersController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'triggers';

	/**
	 * Trigger registry.
	 *
	 * @var TriggerRegistry
	 */
	private TriggerRegistry $registry;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->registry = TriggerRegistry::getInstance();
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		// GET /triggers.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'category' => array(
						'description'       => __( 'Filter by category.', 'hookly-webhook-automator' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /triggers/{key}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'key' => array(
						'description'       => __( 'Trigger key identifier.', 'hookly-webhook-automator' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /triggers/{key}/config.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'key' => array(
						'description'       => __( 'Trigger key identifier.', 'hookly-webhook-automator' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /triggers/{key}/merge-tags.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<key>[a-zA-Z0-9_-]+)/merge-tags',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_merge_tags' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'key' => array(
						'description'       => __( 'Trigger key identifier.', 'hookly-webhook-automator' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /triggers/categories.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '-categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_categories' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
			)
		);
	}

	/**
	 * Get all triggers.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$category = $request->get_param( 'category' );

		if ( $category ) {
			$triggers = $this->registry->getByCategory( $category );
		} else {
			$triggers = $this->registry->getAll();
		}

		$items = array_map(
			function ( TriggerInterface $trigger ) {
				return $this->prepare_trigger_for_response( $trigger );
			},
			$triggers
		);

		// Re-index array to ensure JSON array format.
		$items = array_values( $items );

		return $this->success( $items );
	}

	/**
	 * Get a single trigger.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$key     = sanitize_text_field( $request->get_param( 'key' ) );
		$trigger = $this->registry->get( $key );

		if ( ! $trigger ) {
			return $this->error( 'trigger_not_found', __( 'Trigger not found.', 'hookly-webhook-automator' ), 404 );
		}

		return $this->success( $this->prepare_trigger_for_response( $trigger, true ) );
	}

	/**
	 * Get trigger configuration fields.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_config( $request ) {
		$key     = sanitize_text_field( $request->get_param( 'key' ) );
		$trigger = $this->registry->get( $key );

		if ( ! $trigger ) {
			return $this->error( 'trigger_not_found', __( 'Trigger not found.', 'hookly-webhook-automator' ), 404 );
		}

		$config_fields = $trigger->getConfigFields();

		return $this->success(
			array(
				'key'    => $trigger->getKey(),
				'name'   => $trigger->getName(),
				'fields' => $config_fields,
			)
		);
	}

	/**
	 * Get available merge tags for a trigger.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_merge_tags( $request ) {
		$key     = sanitize_text_field( $request->get_param( 'key' ) );
		$trigger = $this->registry->get( $key );

		if ( ! $trigger ) {
			return $this->error( 'trigger_not_found', __( 'Trigger not found.', 'hookly-webhook-automator' ), 404 );
		}

		$payload_builder = new PayloadBuilder();
		$tags            = $payload_builder->getAvailableTags( $key );

		// Format tags for easier consumption.
		$formatted_tags = array();
		foreach ( $tags as $tag => $description ) {
			$formatted_tags[] = array(
				'tag'         => '{{' . $tag . '}}',
				'path'        => $tag,
				'description' => $description,
			);
		}

		return $this->success(
			array(
				'key'        => $trigger->getKey(),
				'name'       => $trigger->getName(),
				'merge_tags' => $formatted_tags,
			)
		);
	}

	/**
	 * Get trigger categories.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_categories( $request ) {
		$categories    = $this->registry->getCategories();
		$category_list = array();

		foreach ( $categories as $name => $triggers ) {
			$category_list[] = array(
				'name'  => $name,
				'slug'  => sanitize_title( $name ),
				'count' => count( $triggers ),
			);
		}

		return $this->success( $category_list );
	}

	/**
	 * Prepare a trigger for response.
	 *
	 * @param TriggerInterface $trigger      The trigger object.
	 * @param bool             $include_full Whether to include full details.
	 * @return array
	 */
	private function prepare_trigger_for_response( TriggerInterface $trigger, bool $include_full = false ): array {
		$data = array(
			'key'         => $trigger->getKey(),
			'name'        => $trigger->getName(),
			'description' => $trigger->getDescription(),
			'category'    => $trigger->getCategory(),
		);

		if ( $include_full ) {
			$data['hook']           = $trigger->getHook();
			$data['available_data'] = $trigger->getAvailableData();
			$data['config_fields']  = $trigger->getConfigFields();

			// Get merge tags.
			$payload_builder    = new PayloadBuilder();
			$data['merge_tags'] = $payload_builder->getAvailableTags( $trigger->getKey() );
		}

		return $data;
	}
}
