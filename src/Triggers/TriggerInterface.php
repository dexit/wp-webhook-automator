<?php
/**
 * Trigger Interface
 *
 * Defines the contract for all webhook triggers.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

interface TriggerInterface {

	/**
	 * Get the unique trigger key.
	 *
	 * @return string
	 */
	public function getKey(): string;

	/**
	 * Get the trigger display name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get the trigger description.
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Get the trigger category.
	 *
	 * @return string
	 */
	public function getCategory(): string;

	/**
	 * Get the WordPress hook name.
	 *
	 * @return string
	 */
	public function getHook(): string;

	/**
	 * Get available data fields for this trigger.
	 *
	 * @return array
	 */
	public function getAvailableData(): array;

	/**
	 * Register the trigger with WordPress.
	 *
	 * @param callable $callback The callback to execute when triggered.
	 * @return void
	 */
	public function register( callable $callback ): void;

	/**
	 * Get configuration fields for this trigger.
	 *
	 * @return array
	 */
	public function getConfigFields(): array;

	/**
	 * Validate trigger configuration.
	 *
	 * @param array $config The configuration to validate.
	 * @return bool
	 */
	public function validateConfig( array $config ): bool;

	/**
	 * Check if event data matches the trigger configuration.
	 *
	 * @param array $eventData The event data.
	 * @param array $config    The trigger configuration.
	 * @return bool
	 */
	public function matchesConfig( array $eventData, array $config ): bool;
}
