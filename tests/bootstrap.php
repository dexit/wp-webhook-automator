<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for Webhook Automator.
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants needed by the plugin
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('HOOKLY_VERSION')) {
    define('HOOKLY_VERSION', '1.0.0');
}

if (!defined('HOOKLY_PLUGIN_DIR')) {
    define('HOOKLY_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('HOOKLY_PLUGIN_URL')) {
    define('HOOKLY_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-webhook-automator/');
}

if (!defined('HOOKLY_PLUGIN_BASENAME')) {
    define('HOOKLY_PLUGIN_BASENAME', 'wp-webhook-automator/wp-webhook-automator.php');
}

if (!defined('HOOKLY_DB_VERSION')) {
    define('HOOKLY_DB_VERSION', '1.0.0');
}

if (!defined('HOOKLY_TESTING')) {
    define('HOOKLY_TESTING', true);
}

/**
 * Mock WordPress classes for unit testing
 */
if (!class_exists('WP_REST_Controller')) {
    class WP_REST_Controller {
        public function get_endpoint_args_for_item_schema( $method = 'GET' ) { return []; }
        public function add_additional_fields_schema( $schema ) { return $schema; }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;
        private $headers = [];
        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
        public function header($key, $value) { $this->headers[$key] = $value; }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE = 'POST, PUT, PATCH';
        const DELETABLE = 'DELETE';
        const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}

/**
 * Mock WP_Error class for testing.
 */
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private array $errors = [];
        private array $error_data = [];

        public function __construct(string $code = '', string $message = '', $data = '')
        {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code(): string
        {
            $codes = array_keys($this->errors);
            return $codes[0] ?? '';
        }

        public function get_error_message(string $code = ''): string
        {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_codes(): array
        {
            return array_keys($this->errors);
        }
    }
}
