<?php
/**
 * Helper Functions
 *
 * @package WP_Webhook_Automator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the plugin instance.
 *
 * @return WWA_Plugin
 */
function wwa(): WWA_Plugin {
    return WWA_Plugin::get_instance();
}

/**
 * Get a webhook by ID.
 *
 * @param int $id The webhook ID.
 * @return WWA\Core\Webhook|null
 */
function wwa_get_webhook(int $id): ?WWA\Core\Webhook {
    $repository = new WWA\Core\WebhookRepository();
    return $repository->find($id);
}

/**
 * Get all active webhooks.
 *
 * @return array
 */
function wwa_get_active_webhooks(): array {
    $repository = new WWA\Core\WebhookRepository();
    return $repository->findActive();
}

/**
 * Log a debug message.
 *
 * @param string $message The message to log.
 * @param array  $context Additional context.
 * @return void
 */
function wwa_log(string $message, array $context = []): void {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $log_message = '[WP Webhook Automator] ' . $message;

    if (!empty($context)) {
        $log_message .= ' | Context: ' . wp_json_encode($context);
    }

    error_log($log_message);
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function wwa_is_woocommerce_active(): bool {
    return class_exists('WooCommerce');
}

/**
 * Check if a plugin is active.
 *
 * @param string $plugin Plugin basename (e.g., 'contact-form-7/wp-contact-form-7.php').
 * @return bool
 */
function wwa_is_plugin_active(string $plugin): bool {
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return is_plugin_active($plugin);
}

/**
 * Sanitize a webhook payload template.
 *
 * @param array $template The template array.
 * @return array
 */
function wwa_sanitize_payload_template(array $template): array {
    $sanitized = [];

    foreach ($template as $key => $value) {
        $key = sanitize_key($key);

        if (is_array($value)) {
            $sanitized[$key] = wwa_sanitize_payload_template($value);
        } elseif (is_string($value)) {
            // Allow merge tags in values
            $sanitized[$key] = wp_kses_post($value);
        } else {
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
}

/**
 * Get available HTTP methods.
 *
 * @return array
 */
function wwa_get_http_methods(): array {
    return [
        'POST'   => __('POST', 'wp-webhook-automator'),
        'GET'    => __('GET', 'wp-webhook-automator'),
        'PUT'    => __('PUT', 'wp-webhook-automator'),
        'PATCH'  => __('PATCH', 'wp-webhook-automator'),
        'DELETE' => __('DELETE', 'wp-webhook-automator'),
    ];
}

/**
 * Get available payload formats.
 *
 * @return array
 */
function wwa_get_payload_formats(): array {
    return [
        'json' => __('JSON', 'wp-webhook-automator'),
        'form' => __('Form Data', 'wp-webhook-automator'),
    ];
}

/**
 * Format a timestamp for display.
 *
 * @param string $timestamp The timestamp string.
 * @return string
 */
function wwa_format_datetime(string $timestamp): string {
    $datetime = strtotime($timestamp);
    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $datetime);
}

/**
 * Format duration in milliseconds for display.
 *
 * @param int $ms Duration in milliseconds.
 * @return string
 */
function wwa_format_duration(int $ms): string {
    if ($ms < 1000) {
        return $ms . 'ms';
    }

    $seconds = round($ms / 1000, 2);
    return $seconds . 's';
}

/**
 * Get status badge HTML.
 *
 * @param string $status The status string.
 * @return string
 */
function wwa_get_status_badge(string $status): string {
    $classes = [
        'success' => 'wwa-badge wwa-badge-success',
        'failed'  => 'wwa-badge wwa-badge-error',
        'pending' => 'wwa-badge wwa-badge-warning',
    ];

    $labels = [
        'success' => __('Success', 'wp-webhook-automator'),
        'failed'  => __('Failed', 'wp-webhook-automator'),
        'pending' => __('Pending', 'wp-webhook-automator'),
    ];

    $class = $classes[$status] ?? 'wwa-badge';
    $label = $labels[$status] ?? ucfirst($status);

    return sprintf('<span class="%s">%s</span>', esc_attr($class), esc_html($label));
}

/**
 * Generate a random secret key.
 *
 * @param int $length The length of the key.
 * @return string
 */
function wwa_generate_secret_key(int $length = 32): string {
    return wp_generate_password($length, false);
}

/**
 * Mask a secret key for display.
 *
 * @param string $key The secret key.
 * @return string
 */
function wwa_mask_secret_key(string $key): string {
    if (strlen($key) <= 8) {
        return str_repeat('*', strlen($key));
    }

    return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
}
