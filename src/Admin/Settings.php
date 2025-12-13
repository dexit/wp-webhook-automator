<?php
/**
 * Settings Page
 *
 * Plugin settings and configuration.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Admin;

class Settings {

    /**
     * Settings fields.
     *
     * @var array
     */
    private array $fields = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->fields = $this->getFields();
    }

    /**
     * Get settings fields.
     *
     * @return array
     */
    private function getFields(): array {
        return [
            'general' => [
                'title'  => __('General Settings', 'wp-webhook-automator'),
                'fields' => [
                    'wwa_enable_async' => [
                        'label'       => __('Async Delivery', 'wp-webhook-automator'),
                        'type'        => 'checkbox',
                        'description' => __('Send webhooks asynchronously in the background. Recommended for better performance.', 'wp-webhook-automator'),
                        'default'     => true,
                    ],
                    'wwa_default_timeout' => [
                        'label'       => __('Request Timeout', 'wp-webhook-automator'),
                        'type'        => 'number',
                        'description' => __('Maximum time in seconds to wait for a webhook response.', 'wp-webhook-automator'),
                        'default'     => 30,
                        'min'         => 5,
                        'max'         => 120,
                        'suffix'      => __('seconds', 'wp-webhook-automator'),
                    ],
                    'wwa_rate_limit' => [
                        'label'       => __('Rate Limit', 'wp-webhook-automator'),
                        'type'        => 'number',
                        'description' => __('Maximum number of webhooks to send per minute. Set to 0 for unlimited.', 'wp-webhook-automator'),
                        'default'     => 100,
                        'min'         => 0,
                        'max'         => 1000,
                        'suffix'      => __('per minute', 'wp-webhook-automator'),
                    ],
                ],
            ],
            'logging' => [
                'title'  => __('Logging Settings', 'wp-webhook-automator'),
                'fields' => [
                    'wwa_log_retention_days' => [
                        'label'       => __('Log Retention', 'wp-webhook-automator'),
                        'type'        => 'number',
                        'description' => __('Number of days to keep webhook logs. Older logs will be automatically deleted.', 'wp-webhook-automator'),
                        'default'     => 30,
                        'min'         => 1,
                        'max'         => 365,
                        'suffix'      => __('days', 'wp-webhook-automator'),
                    ],
                    'wwa_max_log_entries' => [
                        'label'       => __('Maximum Log Entries', 'wp-webhook-automator'),
                        'type'        => 'number',
                        'description' => __('Maximum number of log entries to keep. Set to 0 for unlimited (uses retention days only).', 'wp-webhook-automator'),
                        'default'     => 1000,
                        'min'         => 0,
                        'max'         => 100000,
                        'suffix'      => __('entries', 'wp-webhook-automator'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render(): void {
        ?>
        <div class="wrap wwa-wrap">
            <div class="wwa-header">
                <h1><?php esc_html_e('Settings', 'wp-webhook-automator'); ?></h1>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('wwa_admin', 'wwa_nonce'); ?>
                <input type="hidden" name="wwa_action" value="save_settings">

                <?php foreach ($this->fields as $sectionKey => $section) : ?>
                    <div class="wwa-card" style="margin-bottom: 20px;">
                        <div class="wwa-card-header">
                            <h2><?php echo esc_html($section['title']); ?></h2>
                        </div>
                        <div class="wwa-card-body">
                            <table class="form-table">
                                <?php foreach ($section['fields'] as $key => $field) : ?>
                                    <tr>
                                        <th scope="row">
                                            <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label>
                                        </th>
                                        <td>
                                            <?php $this->renderField($key, $field); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Info Section -->
                <div class="wwa-card" style="margin-bottom: 20px;">
                    <div class="wwa-card-header">
                        <h2><?php esc_html_e('System Information', 'wp-webhook-automator'); ?></h2>
                    </div>
                    <div class="wwa-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Plugin Version', 'wp-webhook-automator'); ?></th>
                                <td><code><?php echo esc_html(WWA_VERSION); ?></code></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Database Version', 'wp-webhook-automator'); ?></th>
                                <td><code><?php echo esc_html(get_option('wwa_db_version', '1.0.0')); ?></code></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('PHP Version', 'wp-webhook-automator'); ?></th>
                                <td><code><?php echo esc_html(PHP_VERSION); ?></code></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('WordPress Version', 'wp-webhook-automator'); ?></th>
                                <td><code><?php echo esc_html(get_bloginfo('version')); ?></code></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('WP Cron', 'wp-webhook-automator'); ?></th>
                                <td>
                                    <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) : ?>
                                        <span class="wwa-badge wwa-badge-warning"><?php esc_html_e('Disabled', 'wp-webhook-automator'); ?></span>
                                        <p class="description"><?php esc_html_e('WP Cron is disabled. Async delivery and retries may not work correctly.', 'wp-webhook-automator'); ?></p>
                                    <?php else : ?>
                                        <span class="wwa-badge wwa-badge-success"><?php esc_html_e('Enabled', 'wp-webhook-automator'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Next Log Cleanup', 'wp-webhook-automator'); ?></th>
                                <td>
                                    <?php
                                    $nextRun = wp_next_scheduled('wwa_cleanup_logs');
                                    if ($nextRun) {
                                        echo esc_html(wwa_format_datetime(gmdate('Y-m-d H:i:s', $nextRun)));
                                    } else {
                                        echo '<span class="wwa-text-muted">' . esc_html__('Not scheduled', 'wp-webhook-automator') . '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'wp-webhook-automator'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render a settings field.
     *
     * @param string $key   Field key.
     * @param array  $field Field configuration.
     * @return void
     */
    private function renderField(string $key, array $field): void {
        $value = get_option($key, $field['default'] ?? '');

        switch ($field['type']) {
            case 'checkbox':
                ?>
                <label>
                    <input type="checkbox" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($value); ?>>
                    <?php echo esc_html($field['description']); ?>
                </label>
                <?php
                break;

            case 'number':
                ?>
                <input type="number" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" class="small-text" min="<?php echo esc_attr($field['min'] ?? 0); ?>" max="<?php echo esc_attr($field['max'] ?? 99999); ?>">
                <?php if (!empty($field['suffix'])) : ?>
                    <span><?php echo esc_html($field['suffix']); ?></span>
                <?php endif; ?>
                <?php if (!empty($field['description'])) : ?>
                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                <?php endif; ?>
                <?php
                break;

            case 'text':
            default:
                ?>
                <input type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
                <?php if (!empty($field['description'])) : ?>
                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                <?php endif; ?>
                <?php
        }
    }

    /**
     * Handle settings form submission.
     *
     * @param array $data Form data.
     * @return array Result with success status and message.
     */
    public function handleSubmit(array $data): array {
        foreach ($this->fields as $section) {
            foreach ($section['fields'] as $key => $field) {
                switch ($field['type']) {
                    case 'checkbox':
                        $value = !empty($data[$key]);
                        break;
                    case 'number':
                        $value = (int) ($data[$key] ?? $field['default']);
                        if (isset($field['min'])) {
                            $value = max($field['min'], $value);
                        }
                        if (isset($field['max'])) {
                            $value = min($field['max'], $value);
                        }
                        break;
                    default:
                        $value = sanitize_text_field($data[$key] ?? $field['default']);
                }

                update_option($key, $value);
            }
        }

        return [
            'success' => true,
            'message' => __('Settings saved successfully.', 'wp-webhook-automator'),
        ];
    }
}
