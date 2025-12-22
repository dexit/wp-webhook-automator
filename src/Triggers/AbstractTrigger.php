<?php
/**
 * Abstract Trigger
 *
 * Base class for all webhook triggers.
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Triggers;

abstract class AbstractTrigger implements TriggerInterface {

	/**
	 * Unique trigger key.
	 *
	 * @var string
	 */
	protected string $key;

	/**
	 * Trigger display name.
	 *
	 * @var string
	 */
	protected string $name;

	/**
	 * Trigger description.
	 *
	 * @var string
	 */
	protected string $description;

	/**
	 * Trigger category.
	 *
	 * @var string
	 */
	protected string $category;

	/**
	 * WordPress hook name.
	 *
	 * @var string
	 */
	protected string $hook;

	/**
	 * Hook priority.
	 *
	 * @var int
	 */
	protected int $priority = 10;

	/**
	 * Number of arguments accepted by the hook.
	 *
	 * @var int
	 */
	protected int $acceptedArgs = 1;

	/**
	 * Get the trigger key.
	 *
	 * @return string
	 */
	public function getKey(): string {
		return $this->key;
	}

	/**
	 * Get the trigger name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the trigger description.
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * Get the trigger category.
	 *
	 * @return string
	 */
	public function getCategory(): string {
		return $this->category;
	}

	/**
	 * Get the WordPress hook.
	 *
	 * @return string
	 */
	public function getHook(): string {
		return $this->hook;
	}

	/**
	 * Register the trigger with WordPress.
	 *
	 * @param callable $callback The callback to execute.
	 * @return void
	 */
	public function register( callable $callback ): void {
		add_action(
			$this->hook,
			function ( ...$args ) use ( $callback ) {
				$eventData = $this->prepareEventData( $args );
				if ( $eventData !== null ) {
					$callback( $this->key, $eventData );
				}
			},
			$this->priority,
			$this->acceptedArgs
		);
	}

	/**
	 * Get configuration fields for this trigger.
	 *
	 * @return array
	 */
	public function getConfigFields(): array {
		return [];
	}

	/**
	 * Validate trigger configuration.
	 *
	 * @param array $config The configuration to validate.
	 * @return bool
	 */
	public function validateConfig( array $config ): bool {
		return true;
	}

	/**
	 * Check if event data matches configuration.
	 *
	 * @param array $eventData The event data.
	 * @param array $config    The trigger configuration.
	 * @return bool
	 */
	public function matchesConfig( array $eventData, array $config ): bool {
		return true;
	}

	/**
	 * Get available post types.
	 *
	 * @return array
	 */
	protected function getPostTypes(): array {
		$types   = get_post_types( [ 'public' => true ], 'objects' );
		$options = [];

		foreach ( $types as $type ) {
			$options[ $type->name ] = $type->label;
		}

		return $options;
	}

	/**
	 * Get available user roles.
	 *
	 * @return array
	 */
	protected function getUserRoles(): array {
		$roles = wp_roles()->get_names();
		return $roles;
	}

	/**
	 * Get available comment statuses.
	 *
	 * @return array
	 */
	protected function getCommentStatuses(): array {
		return [
			'approved' => __( 'Approved', 'hookly-webhook-automator' ),
			'pending'  => __( 'Pending', 'hookly-webhook-automator' ),
			'spam'     => __( 'Spam', 'hookly-webhook-automator' ),
			'trash'    => __( 'Trash', 'hookly-webhook-automator' ),
		];
	}

	/**
	 * Prepare event data from hook arguments.
	 *
	 * @param array $args The hook arguments.
	 * @return array|null Null to skip triggering.
	 */
	abstract protected function prepareEventData( array $args ): ?array;
}
