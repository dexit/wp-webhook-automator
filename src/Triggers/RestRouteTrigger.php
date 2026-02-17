<?php
/**
 * REST Route Trigger
 *
 * Fires when an incoming custom REST route is hit.
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Triggers;

use Hookly\Extensions\RestRoutes\RestRouteRepository;

class RestRouteTrigger extends AbstractTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'rest_route_received';

	/**
	 * @var string
	 */
	protected string $name = 'REST Route Received';

	/**
	 * @var string
	 */
	protected string $description = 'Fires when a custom incoming REST route is hit';

	/**
	 * @var string
	 */
	protected string $category = 'REST Routes';

	/**
	 * @var string
	 */
	protected string $hook = 'hookly_rest_route_received';

	/**
	 * @var int
	 */
	protected int $acceptedArgs = 2;

	/**
	 * Get available data fields.
	 *
	 * @return array
	 */
	public function getAvailableData(): array {
		return [ 'request', 'route' ];
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function getConfigFields(): array {
		$repository = new RestRouteRepository();
		$routes     = $repository->findAll();
		$options    = [];

		foreach ( $routes as $route ) {
			$options[ $route->getRoutePath() ] = $route->getName() . ' (' . $route->getRoutePath() . ')';
		}

		return [
			'routes' => [
				'type'        => 'multiselect',
				'label'       => __( 'Specific Routes', 'hookly-webhook-automator' ),
				'description' => __( 'Select which custom routes should trigger this webhook. Leave empty for all.', 'hookly-webhook-automator' ),
				'options'     => $options,
			],
		];
	}

	/**
	 * Check if event matches configuration.
	 *
	 * @param array $eventData The event data.
	 * @param array $config    The configuration.
	 * @return bool
	 */
	public function matchesConfig( array $eventData, array $config ): bool {
		if ( ! empty( $config['routes'] ) ) {
			$allowedRoutes = (array) $config['routes'];
			return in_array( $eventData['route']['path'] ?? '', $allowedRoutes, true );
		}
		return true;
	}

	/**
	 * Prepare event data.
	 *
	 * @param array $args Hook arguments.
	 * @return array|null
	 */
	protected function prepareEventData( array $args ): ?array {
		[$route, $requestData] = $args;

		return [
			'route'   => [
				'id'   => $route->getId(),
				'name' => $route->getName(),
				'path' => $route->getRoutePath(),
			],
			'request' => $requestData,
		];
	}
}
