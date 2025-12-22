/**
 * Hookly - Webhook Automator - Admin JavaScript
 *
 * @package WP_Webhook_Automator
 */

(function($) {
    'use strict';

    /**
     * Main admin object
     */
    const HooklyAdmin = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initHeadersRepeater();
            this.initTriggerConfig();
            this.initPayloadBuilder();
        },

        /**
         * Bind global events
         */
        bindEvents: function() {
            // Toggle webhook active status
            $(document).on('click', '.hookly-toggle-status', this.toggleStatus);

            // Delete webhook confirmation
            $(document).on('click', '.hookly-delete-webhook', this.confirmDelete);

            // Test webhook
            $(document).on('click', '.hookly-test-webhook', this.testWebhook);

            // View log details
            $(document).on('click', '.hookly-view-log', this.viewLog);

            // Retry webhook
            $(document).on('click', '.hookly-retry-webhook', this.retryWebhook);

            // Copy to clipboard
            $(document).on('click', '.hookly-copy', this.copyToClipboard);
        },

        /**
         * Initialize headers repeater
         */
        initHeadersRepeater: function() {
            const $container = $('.hookly-headers-list');
            if (!$container.length) return;

            // Add header row
            $(document).on('click', '.hookly-add-header', function(e) {
                e.preventDefault();
                const $row = $(
                    '<div class="hookly-header-row">' +
                    '<input type="text" name="headers[keys][]" placeholder="Header Name" />' +
                    '<input type="text" name="headers[values][]" placeholder="Header Value" />' +
                    '<button type="button" class="button hookly-remove-header">&times;</button>' +
                    '</div>'
                );
                $container.find('.hookly-headers-rows').append($row);
            });

            // Remove header row
            $(document).on('click', '.hookly-remove-header', function(e) {
                e.preventDefault();
                $(this).closest('.hookly-header-row').remove();
            });
        },

        /**
         * Initialize trigger configuration
         */
        initTriggerConfig: function() {
            const $triggerSelect = $('#trigger_type');
            if (!$triggerSelect.length) return;

            $triggerSelect.on('change', function() {
                HooklyAdmin.loadTriggerConfig($(this).val());
            });

            // Load initial config if editing
            if ($triggerSelect.val()) {
                this.loadTriggerConfig($triggerSelect.val());
            }
        },

        /**
         * Load trigger configuration fields
         */
        loadTriggerConfig: function(triggerType) {
            if (!triggerType) {
                $('#hookly-trigger-config').html('');
                $('#hookly-merge-tags').html('');
                return;
            }

            // Load trigger config fields
            $.ajax({
                url: hooklyAdmin.restUrl + 'triggers/' + triggerType + '/config',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', hooklyAdmin.restNonce);
                },
                success: function(response) {
                    const data = response.data || response;
                    const fields = data.fields || {};
                    const html = HooklyAdmin.buildConfigFieldsHtml(fields);
                    $('#hookly-trigger-config').html(html);
                },
                error: function() {
                    $('#hookly-trigger-config').html('');
                }
            });

            // Load merge tags
            $.ajax({
                url: hooklyAdmin.restUrl + 'triggers/' + triggerType + '/merge-tags',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', hooklyAdmin.restNonce);
                },
                success: function(response) {
                    const data = response.data || response;
                    const tags = data.merge_tags || [];
                    const html = HooklyAdmin.buildMergeTagsHtml(tags);
                    $('#hookly-merge-tags').html(html);
                },
                error: function() {
                    $('#hookly-merge-tags').html('');
                }
            });
        },

        /**
         * Build configuration fields HTML
         */
        buildConfigFieldsHtml: function(fields) {
            if (!fields || Object.keys(fields).length === 0) {
                return '<p class="description">No additional configuration needed for this trigger.</p>';
            }

            let html = '';
            for (const [key, config] of Object.entries(fields)) {
                html += '<div class="hookly-config-field">';
                html += '<label for="trigger_config_' + key + '">' + (config.label || key) + '</label>';

                switch (config.type) {
                    case 'multiselect':
                    case 'select':
                        const multiple = config.type === 'multiselect' ? 'multiple' : '';
                        const name = config.type === 'multiselect'
                            ? 'trigger_config[' + key + '][]'
                            : 'trigger_config[' + key + ']';
                        html += '<select id="trigger_config_' + key + '" name="' + name + '" ' + multiple + '>';
                        if (config.options) {
                            for (const [value, label] of Object.entries(config.options)) {
                                const selected = (config.default && config.default.includes(value)) ? 'selected' : '';
                                html += '<option value="' + value + '" ' + selected + '>' + label + '</option>';
                            }
                        }
                        html += '</select>';
                        break;
                    case 'checkbox':
                        const checked = config.default ? 'checked' : '';
                        html += '<input type="checkbox" id="trigger_config_' + key + '" name="trigger_config[' + key + ']" value="1" ' + checked + ' />';
                        break;
                    default:
                        html += '<input type="text" id="trigger_config_' + key + '" name="trigger_config[' + key + ']" value="' + (config.default || '') + '" />';
                }

                if (config.description) {
                    html += '<p class="description">' + config.description + '</p>';
                }
                html += '</div>';
            }
            return html;
        },

        /**
         * Build merge tags HTML
         */
        buildMergeTagsHtml: function(tags) {
            if (!tags || tags.length === 0) {
                return '';
            }

            let html = '<div class="hookly-merge-tags-list">';
            html += '<p class="description">Click a tag to insert it into the payload template:</p>';
            html += '<div class="hookly-tags">';
            for (const tag of tags) {
                html += '<button type="button" class="button hookly-insert-tag" data-tag="' + tag.path + '" title="' + tag.description + '">' + tag.tag + '</button>';
            }
            html += '</div></div>';
            return html;
        },

        /**
         * Initialize payload builder
         */
        initPayloadBuilder: function() {
            const $payloadTemplate = $('#payload_template');
            if (!$payloadTemplate.length) return;

            // Insert merge tag
            $(document).on('click', '.hookly-insert-tag', function(e) {
                e.preventDefault();
                const tag = '{{' + $(this).data('tag') + '}}';
                const cursorPos = $payloadTemplate[0].selectionStart;
                const textBefore = $payloadTemplate.val().substring(0, cursorPos);
                const textAfter = $payloadTemplate.val().substring(cursorPos);
                $payloadTemplate.val(textBefore + tag + textAfter);
                $payloadTemplate.focus();
                $payloadTemplate[0].selectionStart = cursorPos + tag.length;
                $payloadTemplate[0].selectionEnd = cursorPos + tag.length;
            });

            // Validate JSON
            $(document).on('blur', '#payload_template', function() {
                HooklyAdmin.validatePayloadJson($(this));
            });
        },

        /**
         * Validate payload JSON
         */
        validatePayloadJson: function($textarea) {
            const value = $textarea.val().trim();
            if (!value) return;

            try {
                JSON.parse(value);
                $textarea.removeClass('hookly-invalid');
                $('.hookly-json-error').remove();
            } catch (e) {
                $textarea.addClass('hookly-invalid');
                if (!$textarea.next('.hookly-json-error').length) {
                    $textarea.after(
                        '<p class="hookly-json-error" style="color: #d63638;">' +
                        'Invalid JSON: ' + e.message +
                        '</p>'
                    );
                }
            }
        },

        /**
         * Toggle webhook status
         */
        toggleStatus: function(e) {
            e.preventDefault();
            const $toggle = $(this);
            const webhookId = $toggle.data('id');

            $toggle.prop('disabled', true);

            $.ajax({
                url: hooklyAdmin.restUrl + 'webhooks/' + webhookId + '/toggle',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', hooklyAdmin.restNonce);
                },
                success: function(response) {
                    const webhook = response.data || response;
                    if (webhook.is_active) {
                        $toggle.addClass('active').text('Active');
                    } else {
                        $toggle.removeClass('active').text('Inactive');
                    }
                    $toggle.prop('disabled', false);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Failed to update webhook status.';
                    alert(message);
                    $toggle.prop('disabled', false);
                }
            });
        },

        /**
         * Confirm webhook deletion
         */
        confirmDelete: function(e) {
            if (!confirm('Are you sure you want to delete this webhook? This action cannot be undone.')) {
                e.preventDefault();
            }
        },

        /**
         * Test webhook
         */
        testWebhook: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const webhookId = $btn.data('id');

            $btn.prop('disabled', true).text('Testing...');

            $.ajax({
                url: hooklyAdmin.restUrl + 'webhooks/' + webhookId + '/test',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', hooklyAdmin.restNonce);
                },
                success: function(response) {
                    const data = response.data || response;
                    let message = response.message || 'Test webhook sent!';
                    if (data.response_code) {
                        message += '\n\nResponse Code: ' + data.response_code;
                    }
                    if (data.duration_ms) {
                        message += '\nDuration: ' + data.duration_ms + 'ms';
                    }
                    alert(message);
                    $btn.prop('disabled', false).text('Test');
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Failed to send test webhook.';
                    alert(message);
                    $btn.prop('disabled', false).text('Test');
                }
            });
        },

        /**
         * View log details
         */
        viewLog: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const logId = $btn.data('id');
            const $row = $btn.closest('tr');
            const $detailsRow = $row.next('.hookly-log-details-row');

            if ($detailsRow.length) {
                $detailsRow.toggle();
                return;
            }

            $btn.prop('disabled', true);

            $.ajax({
                url: hooklyAdmin.restUrl + 'logs/' + logId,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', hooklyAdmin.restNonce);
                },
                success: function(response) {
                    const log = response.data || response;
                    const html = HooklyAdmin.buildLogDetailsHtml(log);
                    $row.after(
                        '<tr class="hookly-log-details-row"><td colspan="7">' +
                        html +
                        '</td></tr>'
                    );
                    $btn.prop('disabled', false);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Failed to load log details.';
                    alert(message);
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Build log details HTML
         */
        buildLogDetailsHtml: function(log) {
            let html = '<div class="hookly-log-details">';

            html += '<div class="hookly-log-meta">';
            html += '<div class="hookly-log-meta-item"><strong>Endpoint</strong><span>' + log.endpoint_url + '</span></div>';
            html += '<div class="hookly-log-meta-item"><strong>Response Code</strong><span>' + (log.response_code || 'N/A') + '</span></div>';
            html += '<div class="hookly-log-meta-item"><strong>Duration</strong><span>' + (log.duration_ms || 0) + 'ms</span></div>';
            html += '<div class="hookly-log-meta-item"><strong>Attempt</strong><span>#' + log.attempt_number + '</span></div>';
            html += '</div>';

            html += '<h4>Request Headers</h4>';
            html += '<pre>' + HooklyAdmin.formatJson(log.request_headers) + '</pre>';

            html += '<h4>Request Payload</h4>';
            html += '<pre>' + HooklyAdmin.formatJson(log.request_payload) + '</pre>';

            if (log.response_body) {
                html += '<h4>Response Body</h4>';
                html += '<pre>' + HooklyAdmin.formatJson(log.response_body) + '</pre>';
            }

            if (log.error_message) {
                html += '<h4>Error</h4>';
                html += '<pre style="color: #d63638;">' + log.error_message + '</pre>';
            }

            html += '</div>';

            return html;
        },

        /**
         * Format JSON for display
         */
        formatJson: function(data) {
            if (typeof data === 'string') {
                try {
                    data = JSON.parse(data);
                } catch (e) {
                    return data;
                }
            }
            return JSON.stringify(data, null, 2);
        },

        /**
         * Retry webhook
         */
        retryWebhook: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const logId = $btn.data('id');

            $btn.prop('disabled', true).text('Retrying...');

            $.ajax({
                url: hooklyAdmin.restUrl + 'logs/' + logId + '/retry',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', hooklyAdmin.restNonce);
                },
                success: function(response) {
                    const message = response.message || 'Webhook retry completed.';
                    alert(message);
                    location.reload();
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Failed to retry webhook.';
                    alert(message);
                    $btn.prop('disabled', false).text('Retry');
                }
            });
        },

        /**
         * Copy to clipboard
         */
        copyToClipboard: function(e) {
            e.preventDefault();
            const text = $(this).data('copy');

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('Copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                alert('Copied to clipboard!');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        HooklyAdmin.init();
    });

})(jQuery);
