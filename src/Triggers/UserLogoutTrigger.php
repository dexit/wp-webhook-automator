<?php
/**
 * User Logout Trigger
 *
 * Fires when a user logs out.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class UserLogoutTrigger extends UserRegisteredTrigger {

    /**
     * @var string
     */
    protected string $key = 'user_logout';

    /**
     * @var string
     */
    protected string $name = 'User Logout';

    /**
     * @var string
     */
    protected string $description = 'Fires when a user logs out';

    /**
     * @var string
     */
    protected string $hook = 'wp_logout';

    /**
     * @var int
     */
    protected int $acceptedArgs = 1;

    /**
     * Prepare event data.
     *
     * @param array $args Hook arguments.
     * @return array|null
     */
    protected function prepareEventData(array $args): ?array {
        $userId = $args[0] ?? 0;

        if (!$userId) {
            return null;
        }

        $user = get_userdata($userId);
        if (!$user) {
            return [
                'user' => [
                    'id' => $userId,
                ],
                'logout' => [
                    'timestamp' => current_time('mysql'),
                ],
            ];
        }

        $data = $this->buildUserData($user);

        // Add logout-specific data
        $data['logout'] = [
            'timestamp' => current_time('mysql'),
        ];

        return $data;
    }
}
