<?php
/**
 * User Updated Trigger
 *
 * Fires when a user profile is updated.
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Triggers;

class UserUpdatedTrigger extends UserRegisteredTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'user_updated';

	/**
	 * @var string
	 */
	protected string $name = 'User Updated';

	/**
	 * @var string
	 */
	protected string $description = 'Fires when a user profile is updated';

	/**
	 * @var string
	 */
	protected string $hook = 'profile_update';

	/**
	 * @var int
	 */
	protected int $acceptedArgs = 3;

	/**
	 * Prepare event data.
	 *
	 * @param array $args Hook arguments.
	 * @return array|null
	 */
	protected function prepareEventData( array $args ): ?array {
		$userId      = $args[0];
		$oldUserData = $args[1] ?? null;
		$userdata    = $args[2] ?? [];

		$user = get_userdata( $userId );
		if ( ! $user ) {
			return null;
		}

		$data = $this->buildUserData( $user );

		// Add previous values if available
		if ( $oldUserData instanceof \WP_User ) {
			$data['user']['previous'] = [
				'email'        => $oldUserData->user_email,
				'display_name' => $oldUserData->display_name,
				'first_name'   => $oldUserData->first_name,
				'last_name'    => $oldUserData->last_name,
				'role'         => implode( ', ', $oldUserData->roles ),
			];
		}

		return $data;
	}
}
