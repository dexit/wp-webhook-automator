<?php
/**
 * User Registered Trigger
 *
 * Fires when a new user is registered.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class UserRegisteredTrigger extends AbstractTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'user_registered';

	/**
	 * @var string
	 */
	protected string $name = 'User Registered';

	/**
	 * @var string
	 */
	protected string $description = 'Fires when a new user is registered';

	/**
	 * @var string
	 */
	protected string $category = 'Users';

	/**
	 * @var string
	 */
	protected string $hook = 'user_register';

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
		return [ 'user' ];
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function getConfigFields(): array {
		return [
			'roles' => [
				'type'        => 'multiselect',
				'label'       => __( 'User Roles', 'hookly-webhook-automator' ),
				'description' => __( 'Only trigger for users with these roles. Leave empty for all roles.', 'hookly-webhook-automator' ),
				'options'     => $this->getUserRoles(),
				'default'     => [],
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
		if ( ! empty( $config['roles'] ) ) {
			$roles    = is_array( $config['roles'] ) ? $config['roles'] : [ $config['roles'] ];
			$userRole = $eventData['user']['role'] ?? '';

			// User might have multiple roles
			$userRoles = array_map( 'trim', explode( ',', $userRole ) );
			return ! empty( array_intersect( $userRoles, $roles ) );
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
		$userId   = $args[0];
		$userdata = $args[1] ?? [];

		$user = get_userdata( $userId );
		if ( ! $user ) {
			return null;
		}

		return $this->buildUserData( $user );
	}

	/**
	 * Build user data array.
	 *
	 * @param \WP_User $user The user object.
	 * @return array
	 */
	protected function buildUserData( \WP_User $user ): array {
		return [
			'user' => [
				'id'           => $user->ID,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'display_name' => $user->display_name,
				'nickname'     => $user->nickname,
				'role'         => implode( ', ', $user->roles ),
				'roles'        => $user->roles,
				'registered'   => $user->user_registered,
				'url'          => $user->user_url,
				'locale'       => get_user_locale( $user->ID ),
				'meta'         => $this->getUserMeta( $user->ID ),
			],
		];
	}

	/**
	 * Get user meta data.
	 *
	 * @param int $userId The user ID.
	 * @return array
	 */
	protected function getUserMeta( int $userId ): array {
		$meta     = get_user_meta( $userId );
		$filtered = [];

		// List of safe meta keys to include
		$allowedKeys = [
			'description',
			'rich_editing',
			'syntax_highlighting',
			'comment_shortcuts',
			'admin_color',
			'show_admin_bar_front',
		];

		foreach ( $meta as $key => $values ) {
			// Skip private meta keys
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			// Skip capabilities and user level
			if ( in_array( $key, [ 'wp_capabilities', 'wp_user_level' ], true ) ) {
				continue;
			}

			$filtered[ $key ] = count( $values ) === 1 ? $values[0] : $values;
		}

		return $filtered;
	}
}
