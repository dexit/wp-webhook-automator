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
