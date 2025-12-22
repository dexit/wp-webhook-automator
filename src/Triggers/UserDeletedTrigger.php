<?php
/**
 * User Deleted Trigger
 *
 * Fires when a user is deleted.
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Triggers;

class UserDeletedTrigger extends UserRegisteredTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'user_deleted';

	/**
	 * @var string
	 */
	protected string $name = 'User Deleted';

	/**
	 * @var string
	 */
	protected string $description = 'Fires when a user is deleted';

	/**
	 * @var string
	 */
	protected string $hook = 'delete_user';

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
		$userId     = $args[0];
		$reassignId = $args[1] ?? null;
		$user       = $args[2] ?? null;

		// Try to get user object from args or database
		if ( ! $user instanceof \WP_User ) {
			$user = get_userdata( $userId );
		}

		if ( ! $user ) {
			// User already deleted, return minimal data
			return [
				'user' => [
					'id'          => $userId,
					'reassign_to' => $reassignId,
				],
			];
		}

		$data                        = $this->buildUserData( $user );
		$data['user']['reassign_to'] = $reassignId;

		return $data;
	}
}
