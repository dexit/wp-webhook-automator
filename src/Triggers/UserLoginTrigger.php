<?php
/**
 * User Login Trigger
 *
 * Fires when a user logs in.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class UserLoginTrigger extends UserRegisteredTrigger {

    /**
     * @var string
     */
    protected string $key = 'user_login';

    /**
     * @var string
     */
    protected string $name = 'User Login';

    /**
     * @var string
     */
    protected string $description = 'Fires when a user logs in';

    /**
     * @var string
     */
    protected string $hook = 'wp_login';

    /**
     * @var int
     */
    protected int $acceptedArgs = 2;

    /**
     * Prepare event data.
     *
     * @param array $args Hook arguments.
     * @return array|null
     */
    protected function prepareEventData(array $args): ?array {
        $userLogin = $args[0];
        $user = $args[1] ?? null;

        if (!$user instanceof \WP_User) {
            $user = get_user_by('login', $userLogin);
        }

        if (!$user) {
            return null;
        }

        $data = $this->buildUserData($user);

        // Add login-specific data
        $data['login'] = [
            'timestamp'  => current_time('mysql'),
            'ip_address' => $this->getClientIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
        ];

        return $data;
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private function getClientIp(): string {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }
}
