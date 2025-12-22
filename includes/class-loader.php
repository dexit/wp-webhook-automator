<?php
/**
 * Hook Loader
 *
 * Maintains a list of all hooks that are registered throughout
 * the plugin, and handles their registration with the WordPress API.
 *
 * @package WP_Webhook_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hookly_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @var array
	 */
	protected array $actions = [];

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @var array
	 */
	protected array $filters = [];

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @param string $hook          The name of the WordPress action.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the function defined on the $component.
	 * @param int    $priority      Optional. The priority at which the function should be fired.
	 * @param int    $accepted_args Optional. The number of arguments passed to the callback.
	 * @return void
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @param string $hook          The name of the WordPress filter.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the function defined on the $component.
	 * @param int    $priority      Optional. The priority at which the function should be fired.
	 * @param int    $accepted_args Optional. The number of arguments passed to the callback.
	 * @return void
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function that is used to register the actions and hooks.
	 *
	 * @param array  $hooks         The collection of hooks.
	 * @param string $hook          The name of the WordPress hook.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the function defined on the $component.
	 * @param int    $priority      The priority at which the function should be fired.
	 * @param int    $accepted_args The number of arguments passed to the callback.
	 * @return array
	 */
	private function add(
		array $hooks,
		string $hook,
		object $component,
		string $callback,
		int $priority,
		int $accepted_args
	): array {
		$hooks[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];

		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
