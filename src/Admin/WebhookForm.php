<?php
/**
 * Webhook Form Page
 *
 * Add/Edit webhook form.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Admin;

use WWA\Core\Webhook;
use WWA\Core\WebhookRepository;
use WWA\Core\PayloadBuilder;
use WWA\Triggers\TriggerRegistry;

class WebhookForm {

    /**
     * Webhook repository.
     *
     * @var WebhookRepository
     */
    private WebhookRepository $repository;

    /**
     * Trigger registry.
     *
     * @var TriggerRegistry
     */
    private TriggerRegistry $triggerRegistry;

    /**
     * Payload builder.
     *
     * @var PayloadBuilder
     */
    private PayloadBuilder $payloadBuilder;

    /**
     * Current webhook being edited.
     *
     * @var Webhook|null
     */
    private ?Webhook $webhook = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->repository = new WebhookRepository();
        $this->triggerRegistry = TriggerRegistry::getInstance();
        $this->payloadBuilder = new PayloadBuilder();
    }

    /**
     * Render the form.
     *
     * @return void
     */
    public function render(): void {
        // Check if editing
        $webhookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($webhookId) {
            $this->webhook = $this->repository->find($webhookId);
            if (!$this->webhook) {
                wp_die(__('Webhook not found.', 'wp-webhook-automator'));
            }
        }

        $isEdit = $this->webhook !== null;
        $pageTitle = $isEdit
            ? __('Edit Webhook', 'wp-webhook-automator')
            : __('Add New Webhook', 'wp-webhook-automator');
        ?>
        <div class="wrap wwa-wrap">
            <div class="wwa-header">
                <h1><?php echo esc_html($pageTitle); ?></h1>
            </div>

            <form method="post" action="" id="wwa-webhook-form">
                <?php wp_nonce_field('wwa_admin', 'wwa_nonce'); ?>
                <input type="hidden" name="wwa_action" value="save_webhook">
                <?php if ($isEdit) : ?>
                    <input type="hidden" name="webhook_id" value="<?php echo esc_attr($this->webhook->getId()); ?>">
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <!-- Main Form -->
                    <div>
                        <!-- Basic Info -->
                        <div class="wwa-card">
                            <div class="wwa-card-header">
                                <h2><?php esc_html_e('Basic Information', 'wp-webhook-automator'); ?></h2>
                            </div>
                            <div class="wwa-card-body">
                                <div class="wwa-form-row">
                                    <label for="name"><?php esc_html_e('Name', 'wp-webhook-automator'); ?> <span class="required">*</span></label>
                                    <input type="text" id="name" name="name" value="<?php echo esc_attr($this->getValue('name')); ?>" required>
                                    <p class="description"><?php esc_html_e('A descriptive name for this webhook.', 'wp-webhook-automator'); ?></p>
                                </div>

                                <div class="wwa-form-row">
                                    <label for="description"><?php esc_html_e('Description', 'wp-webhook-automator'); ?></label>
                                    <textarea id="description" name="description" rows="3"><?php echo esc_textarea($this->getValue('description')); ?></textarea>
                                </div>

                                <div class="wwa-form-row">
                                    <label for="trigger_type"><?php esc_html_e('Trigger', 'wp-webhook-automator'); ?> <span class="required">*</span></label>
                                    <select id="trigger_type" name="trigger_type" required>
                                        <option value=""><?php esc_html_e('Select a trigger...', 'wp-webhook-automator'); ?></option>
                                        <?php foreach ($this->triggerRegistry->getForSelect() as $category => $triggers) : ?>
                                            <optgroup label="<?php echo esc_attr($category); ?>">
                                                <?php foreach ($triggers as $key => $name) : ?>
                                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($this->getValue('trigger_type'), $key); ?>>
                                                        <?php echo esc_html($name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Trigger Config (loaded dynamically) -->
                                <div id="wwa-trigger-config">
                                    <?php $this->renderTriggerConfig(); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery -->
                        <div class="wwa-card" style="margin-top: 20px;">
                            <div class="wwa-card-header">
                                <h2><?php esc_html_e('Delivery', 'wp-webhook-automator'); ?></h2>
                            </div>
                            <div class="wwa-card-body">
                                <div class="wwa-form-row">
                                    <label for="endpoint_url"><?php esc_html_e('Endpoint URL', 'wp-webhook-automator'); ?> <span class="required">*</span></label>
                                    <input type="url" id="endpoint_url" name="endpoint_url" value="<?php echo esc_url($this->getValue('endpoint_url')); ?>" required placeholder="https://example.com/webhook">
                                    <p class="description"><?php esc_html_e('The URL to send the webhook to.', 'wp-webhook-automator'); ?></p>
                                </div>

                                <div class="wwa-form-row">
                                    <label for="http_method"><?php esc_html_e('HTTP Method', 'wp-webhook-automator'); ?></label>
                                    <select id="http_method" name="http_method">
                                        <?php foreach (wwa_get_http_methods() as $method => $label) : ?>
                                            <option value="<?php echo esc_attr($method); ?>" <?php selected($this->getValue('http_method', 'POST'), $method); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="wwa-form-row">
                                    <label><?php esc_html_e('Custom Headers', 'wp-webhook-automator'); ?></label>
                                    <div class="wwa-headers-list">
                                        <div class="wwa-headers-rows">
                                            <?php
                                            $headers = $this->getValue('headers', []);
                                            if (empty($headers)) {
                                                $headers = ['' => ''];
                                            }
                                            foreach ($headers as $key => $value) :
                                            ?>
                                                <div class="wwa-header-row">
                                                    <input type="text" name="headers[keys][]" value="<?php echo esc_attr($key); ?>" placeholder="<?php esc_attr_e('Header Name', 'wp-webhook-automator'); ?>">
                                                    <input type="text" name="headers[values][]" value="<?php echo esc_attr($value); ?>" placeholder="<?php esc_attr_e('Header Value', 'wp-webhook-automator'); ?>">
                                                    <button type="button" class="button wwa-remove-header">&times;</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button wwa-add-header" style="margin-top: 10px;">
                                            <?php esc_html_e('+ Add Header', 'wp-webhook-automator'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payload -->
                        <div class="wwa-card" style="margin-top: 20px;">
                            <div class="wwa-card-header">
                                <h2><?php esc_html_e('Payload', 'wp-webhook-automator'); ?></h2>
                            </div>
                            <div class="wwa-card-body">
                                <div class="wwa-form-row">
                                    <label for="payload_format"><?php esc_html_e('Format', 'wp-webhook-automator'); ?></label>
                                    <select id="payload_format" name="payload_format">
                                        <?php foreach (wwa_get_payload_formats() as $format => $label) : ?>
                                            <option value="<?php echo esc_attr($format); ?>" <?php selected($this->getValue('payload_format', 'json'), $format); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="wwa-form-row">
                                    <label for="payload_template"><?php esc_html_e('Custom Payload Template', 'wp-webhook-automator'); ?></label>
                                    <textarea id="payload_template" name="payload_template" rows="10" style="font-family: monospace;"><?php
                                        $template = $this->getValue('payload_template', []);
                                        if (!empty($template)) {
                                            echo esc_textarea(wp_json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                        }
                                    ?></textarea>
                                    <p class="description">
                                        <?php esc_html_e('Leave empty to use the default payload. Use merge tags like {{post.title}} to include dynamic data.', 'wp-webhook-automator'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Security -->
                        <div class="wwa-card" style="margin-top: 20px;">
                            <div class="wwa-card-header">
                                <h2><?php esc_html_e('Security', 'wp-webhook-automator'); ?></h2>
                            </div>
                            <div class="wwa-card-body">
                                <div class="wwa-form-row">
                                    <label for="secret_key"><?php esc_html_e('Secret Key', 'wp-webhook-automator'); ?></label>
                                    <div style="display: flex; gap: 10px;">
                                        <input type="text" id="secret_key" name="secret_key" value="<?php echo esc_attr($this->getValue('secret_key')); ?>" style="flex: 1;">
                                        <button type="button" class="button" onclick="document.getElementById('secret_key').value = '<?php echo esc_js(wwa_generate_secret_key()); ?>';">
                                            <?php esc_html_e('Generate', 'wp-webhook-automator'); ?>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('Used to sign the webhook payload. The signature is sent in the X-Webhook-Signature header.', 'wp-webhook-automator'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Retry Settings -->
                        <div class="wwa-card" style="margin-top: 20px;">
                            <div class="wwa-card-header">
                                <h2><?php esc_html_e('Retry Settings', 'wp-webhook-automator'); ?></h2>
                            </div>
                            <div class="wwa-card-body">
                                <div class="wwa-form-row">
                                    <label for="retry_count"><?php esc_html_e('Retry Attempts', 'wp-webhook-automator'); ?></label>
                                    <input type="number" id="retry_count" name="retry_count" min="0" max="10" value="<?php echo esc_attr($this->getValue('retry_count', 3)); ?>">
                                    <p class="description"><?php esc_html_e('Number of retry attempts if the webhook fails.', 'wp-webhook-automator'); ?></p>
                                </div>

                                <div class="wwa-form-row">
                                    <label for="retry_delay"><?php esc_html_e('Retry Delay (seconds)', 'wp-webhook-automator'); ?></label>
                                    <input type="number" id="retry_delay" name="retry_delay" min="10" max="3600" value="<?php echo esc_attr($this->getValue('retry_delay', 60)); ?>">
                                    <p class="description"><?php esc_html_e('Seconds to wait between retry attempts.', 'wp-webhook-automator'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div>
                        <!-- Publish Box -->
                        <div class="wwa-card">
                            <div class="wwa-card-header">
                                <h2><?php esc_html_e('Publish', 'wp-webhook-automator'); ?></h2>
                            </div>
                            <div class="wwa-card-body">
                                <div class="wwa-form-row">
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="is_active" value="1" <?php checked($this->getValue('is_active', true)); ?>>
                                        <?php esc_html_e('Active', 'wp-webhook-automator'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Enable or disable this webhook.', 'wp-webhook-automator'); ?></p>
                                </div>

                                <div style="margin-top: 20px;">
                                    <button type="submit" class="button button-primary button-large" style="width: 100%;">
                                        <?php echo $isEdit ? esc_html__('Update Webhook', 'wp-webhook-automator') : esc_html__('Create Webhook', 'wp-webhook-automator'); ?>
                                    </button>
                                </div>

                                <?php if ($isEdit) : ?>
                                    <div style="margin-top: 10px;">
                                        <button type="button" class="button wwa-test-webhook" data-id="<?php echo esc_attr($this->webhook->getId()); ?>" style="width: 100%;">
                                            <?php esc_html_e('Send Test Webhook', 'wp-webhook-automator'); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Available Tags -->
                        <div class="wwa-card" style="margin-top: 20px;">
                            <div class="wwa-card-header">
                                <h2><?php esc_html_e('Available Merge Tags', 'wp-webhook-automator'); ?></h2>
                            </div>
                            <div class="wwa-card-body" id="wwa-merge-tags">
                                <p class="wwa-text-muted"><?php esc_html_e('Select a trigger to see available merge tags.', 'wp-webhook-automator'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        jQuery(function($) {
            // Update merge tags when trigger changes
            $('#trigger_type').on('change', function() {
                var triggerType = $(this).val();
                if (!triggerType) {
                    $('#wwa-merge-tags').html('<p class="wwa-text-muted"><?php echo esc_js(__('Select a trigger to see available merge tags.', 'wp-webhook-automator')); ?></p>');
                    return;
                }

                var tags = <?php echo wp_json_encode($this->getAllMergeTags()); ?>;
                var html = '<ul style="margin: 0; padding: 0; list-style: none; max-height: 300px; overflow-y: auto;">';

                // Global tags
                $.each(tags.global, function(tag, label) {
                    html += '<li style="padding: 5px 0; border-bottom: 1px solid #eee; font-size: 12px;">';
                    html += '<code class="wwa-insert-tag" data-tag="' + tag + '" style="cursor: pointer;">{{' + tag + '}}</code>';
                    html += '<br><small class="wwa-text-muted">' + label + '</small>';
                    html += '</li>';
                });

                // Trigger-specific tags
                if (tags[triggerType]) {
                    $.each(tags[triggerType], function(tag, label) {
                        html += '<li style="padding: 5px 0; border-bottom: 1px solid #eee; font-size: 12px;">';
                        html += '<code class="wwa-insert-tag" data-tag="' + tag + '" style="cursor: pointer;">{{' + tag + '}}</code>';
                        html += '<br><small class="wwa-text-muted">' + label + '</small>';
                        html += '</li>';
                    });
                }

                html += '</ul>';
                $('#wwa-merge-tags').html(html);
            });

            // Trigger initial load
            if ($('#trigger_type').val()) {
                $('#trigger_type').trigger('change');
            }
        });
        </script>
        <?php
    }

    /**
     * Get a form value.
     *
     * @param string $key     The field key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    private function getValue(string $key, mixed $default = ''): mixed {
        if ($this->webhook) {
            $method = 'get' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this->webhook, $method)) {
                return $this->webhook->$method();
            }

            $data = $this->webhook->toArray();
            return $data[$key] ?? $default;
        }

        return $default;
    }

    /**
     * Render trigger configuration fields.
     *
     * @return void
     */
    private function renderTriggerConfig(): void {
        if (!$this->webhook) {
            return;
        }

        $trigger = $this->triggerRegistry->get($this->webhook->getTriggerType());
        if (!$trigger) {
            return;
        }

        $config = $this->webhook->getTriggerConfig();
        $fields = $trigger->getConfigFields();

        foreach ($fields as $key => $field) {
            $this->renderConfigField($key, $field, $config[$key] ?? $field['default'] ?? null);
        }
    }

    /**
     * Render a configuration field.
     *
     * @param string $key   Field key.
     * @param array  $field Field configuration.
     * @param mixed  $value Current value.
     * @return void
     */
    private function renderConfigField(string $key, array $field, mixed $value): void {
        ?>
        <div class="wwa-form-row">
            <label for="trigger_config_<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label>
            <?php
            switch ($field['type']) {
                case 'multiselect':
                    ?>
                    <select id="trigger_config_<?php echo esc_attr($key); ?>" name="trigger_config[<?php echo esc_attr($key); ?>][]" multiple style="min-height: 100px;">
                        <?php foreach ($field['options'] as $optValue => $optLabel) : ?>
                            <option value="<?php echo esc_attr($optValue); ?>" <?php selected(is_array($value) && in_array($optValue, $value)); ?>>
                                <?php echo esc_html($optLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;
                case 'select':
                    ?>
                    <select id="trigger_config_<?php echo esc_attr($key); ?>" name="trigger_config[<?php echo esc_attr($key); ?>]">
                        <?php foreach ($field['options'] as $optValue => $optLabel) : ?>
                            <option value="<?php echo esc_attr($optValue); ?>" <?php selected($value, $optValue); ?>>
                                <?php echo esc_html($optLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;
                default:
                    ?>
                    <input type="text" id="trigger_config_<?php echo esc_attr($key); ?>" name="trigger_config[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                    <?php
            }

            if (!empty($field['description'])) :
            ?>
                <p class="description"><?php echo esc_html($field['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get all merge tags for JavaScript.
     *
     * @return array
     */
    private function getAllMergeTags(): array {
        $tags = [
            'global' => [
                'site.name'        => __('Site name', 'wp-webhook-automator'),
                'site.url'         => __('Site URL', 'wp-webhook-automator'),
                'site.admin_email' => __('Admin email', 'wp-webhook-automator'),
                'timestamp'        => __('Unix timestamp', 'wp-webhook-automator'),
                'timestamp_iso'    => __('ISO 8601 timestamp', 'wp-webhook-automator'),
                'webhook.name'     => __('Webhook name', 'wp-webhook-automator'),
                'webhook.id'       => __('Webhook ID', 'wp-webhook-automator'),
            ],
        ];

        foreach ($this->triggerRegistry->getAll() as $trigger) {
            $tags[$trigger->getKey()] = $this->payloadBuilder->getAvailableTags($trigger->getKey());
        }

        return $tags;
    }

    /**
     * Handle form submission.
     *
     * @param array $data Form data.
     * @return array Result with success status and message.
     */
    public function handleSubmit(array $data): array {
        // Validate required fields
        if (empty($data['name']) || empty($data['trigger_type']) || empty($data['endpoint_url'])) {
            return [
                'success' => false,
                'message' => __('Please fill in all required fields.', 'wp-webhook-automator'),
            ];
        }

        // Validate URL
        if (!filter_var($data['endpoint_url'], FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => __('Please enter a valid URL.', 'wp-webhook-automator'),
            ];
        }

        // Parse headers
        $headers = [];
        if (!empty($data['headers']['keys'])) {
            foreach ($data['headers']['keys'] as $i => $key) {
                $key = sanitize_text_field($key);
                $value = sanitize_text_field($data['headers']['values'][$i] ?? '');
                if (!empty($key)) {
                    $headers[$key] = $value;
                }
            }
        }

        // Parse payload template
        $payloadTemplate = [];
        if (!empty($data['payload_template'])) {
            $decoded = json_decode(stripslashes($data['payload_template']), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payloadTemplate = $decoded;
            }
        }

        // Create or update webhook
        $webhookId = isset($data['webhook_id']) ? (int) $data['webhook_id'] : 0;
        $webhook = $webhookId ? $this->repository->find($webhookId) : new Webhook();

        if (!$webhook) {
            $webhook = new Webhook();
        }

        $webhook->setName(sanitize_text_field($data['name']))
            ->setDescription(sanitize_textarea_field($data['description'] ?? ''))
            ->setTriggerType(sanitize_text_field($data['trigger_type']))
            ->setTriggerConfig($data['trigger_config'] ?? [])
            ->setEndpointUrl(esc_url_raw($data['endpoint_url']))
            ->setHttpMethod(sanitize_text_field($data['http_method'] ?? 'POST'))
            ->setHeaders($headers)
            ->setPayloadFormat(sanitize_text_field($data['payload_format'] ?? 'json'))
            ->setPayloadTemplate($payloadTemplate)
            ->setSecretKey(sanitize_text_field($data['secret_key'] ?? ''))
            ->setIsActive(!empty($data['is_active']))
            ->setRetryCount((int) ($data['retry_count'] ?? 3))
            ->setRetryDelay((int) ($data['retry_delay'] ?? 60))
            ->setCreatedBy(get_current_user_id());

        $savedId = $this->repository->save($webhook);

        if ($savedId) {
            return [
                'success' => true,
                'message' => $webhookId
                    ? __('Webhook updated successfully.', 'wp-webhook-automator')
                    : __('Webhook created successfully.', 'wp-webhook-automator'),
                'webhook_id' => $savedId,
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to save webhook.', 'wp-webhook-automator'),
        ];
    }
}
