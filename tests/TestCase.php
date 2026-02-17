<?php
/**
 * Base Test Case
 *
 * Sets up Brain Monkey for mocking WordPress functions.
 */

namespace Hookly\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Set up Brain Monkey before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Set up common WordPress function mocks
        $this->setUpWordPressFunctions();
    }

    /**
     * Tear down Brain Monkey after each test.
     */
    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Set up common WordPress function mocks.
     */
    protected function setUpWordPressFunctions(): void
    {
        // Common translation functions - return the string as-is
        Monkey\Functions\stubs([
            '__' => function ($text, $domain = 'default') {
                return $text;
            },
            '_e' => function ($text, $domain = 'default') {
                echo $text;
            },
            'esc_html__' => function ($text, $domain = 'default') {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_attr__' => function ($text, $domain = 'default') {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_html' => function ($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_attr' => function ($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_url' => function ($url) {
                return filter_var($url, FILTER_SANITIZE_URL);
            },
            'sanitize_text_field' => function ($str) {
                return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
            },
            'sanitize_url' => function ($url) {
                return filter_var($url, FILTER_SANITIZE_URL);
            },
            'sanitize_key' => function ($key) {
                return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
            },
            'wp_json_encode' => function ($data, $options = 0) {
                return json_encode($data, $options);
            },
            'wp_parse_url' => function ($url, $component = -1) {
                return parse_url($url, $component);
            },
            'absint' => function ($value) {
                return abs((int) $value);
            },
            'get_option' => function ($option, $default = false) {
                return $default;
            },
            'update_option' => function ($option, $value) {
                return true;
            },
            'add_option' => function ($option, $value) {
                return true;
            },
            'delete_option' => function ($option) {
                return true;
            },
            'get_bloginfo' => function ($show) {
                return match ($show) {
                    'name' => 'Test Site',
                    'url' => 'https://example.com',
                    default => '',
                };
            },
            'home_url' => function ($path = '') {
                return 'https://example.com' . $path;
            },
            'admin_url' => function ($path = '') {
                return 'https://example.com/wp-admin/' . $path;
            },
            'plugin_dir_path' => function ($file) {
                return dirname($file) . '/';
            },
            'plugin_dir_url' => function ($file) {
                return 'https://example.com/wp-content/plugins/wp-webhook-automator/';
            },
            'wp_create_nonce' => function ($action) {
                return 'test-nonce-' . $action;
            },
            'wp_verify_nonce' => function ($nonce, $action) {
                return $nonce === 'test-nonce-' . $action ? 1 : false;
            },
            'current_user_can' => function ($capability) {
                return true;
            },
            'get_current_user_id' => function () {
                return 1;
            },
            'is_wp_error' => function ($thing) {
                return $thing instanceof \WP_Error;
            },
            'rest_sanitize_value_from_schema' => function ($value, $schema) {
                return $value;
            },
        ]);
    }

    /**
     * Create a mock wpdb instance.
     *
     * @return \Mockery\MockInterface
     */
    protected function createMockWpdb(): \Mockery\MockInterface
    {
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturnUsing(function ($query, ...$args) {
            // Simple prepare simulation
            $i = 0;
            return preg_replace_callback('/%[sd]/', function ($match) use (&$i, $args) {
                return $args[$i++] ?? $match[0];
            }, $query);
        });

        return $wpdb;
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
