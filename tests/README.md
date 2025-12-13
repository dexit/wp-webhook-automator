# WP Webhook Automator Tests

This directory contains the test suite for the WP Webhook Automator plugin.

## Test Structure

```
tests/
├── bootstrap.php          # PHPUnit bootstrap file
├── TestCase.php           # Base test case with Brain Monkey setup
├── Unit/                  # Unit tests
│   ├── Core/              # Core class tests
│   │   ├── WebhookTest.php
│   │   ├── PayloadBuilderTest.php
│   │   └── SignatureGeneratorTest.php
│   └── Triggers/          # Trigger tests
│       ├── TriggerRegistryTest.php
│       └── PostPublishedTriggerTest.php
└── Integration/           # Integration tests
    └── Rest/              # REST API tests
        └── WebhooksControllerTest.php
```

## Running Tests

### Prerequisites

Install dev dependencies:

```bash
composer install
```

### Run All Tests

```bash
composer test
# or
./vendor/bin/phpunit
```

### Run Unit Tests Only

```bash
composer test:unit
# or
./vendor/bin/phpunit --testsuite=unit
```

### Run Integration Tests Only

```bash
composer test:integration
# or
./vendor/bin/phpunit --testsuite=integration
```

### Generate Code Coverage Report

Requires Xdebug or PCOV:

```bash
composer test:coverage
```

Coverage report will be generated in `coverage/html/`.

## Test Types

### Unit Tests

Located in `tests/Unit/`, these tests focus on individual classes in isolation:

- **WebhookTest**: Tests the Webhook entity class (getters, setters, serialization)
- **PayloadBuilderTest**: Tests payload building and merge tag replacement
- **SignatureGeneratorTest**: Tests HMAC signature generation and verification
- **TriggerRegistryTest**: Tests trigger registration and lookup
- **PostPublishedTriggerTest**: Tests the post published trigger

Unit tests use Brain Monkey to mock WordPress functions, allowing them to run without a WordPress installation.

### Integration Tests

Located in `tests/Integration/`, these tests focus on component interactions:

- **WebhooksControllerTest**: Tests REST controller data processing logic

Note: Full WordPress REST API integration testing requires the WordPress test suite.

## Writing Tests

### Creating a New Unit Test

1. Create a test file in the appropriate `tests/Unit/` subdirectory
2. Extend `WWA\Tests\TestCase` to get Brain Monkey setup
3. Use Brain Monkey functions stubs for WordPress functions

Example:

```php
<?php

namespace WWA\Tests\Unit\MyComponent;

use WWA\Tests\TestCase;
use Brain\Monkey\Functions;

class MyClassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Additional setup
    }

    public function testSomething(): void
    {
        // Mock WordPress function if needed
        Functions\when('get_option')->justReturn('value');

        // Your test
        $this->assertTrue(true);
    }
}
```

### Mocking WordPress Functions

Brain Monkey provides several ways to mock WordPress functions:

```php
// Return a fixed value
Functions\when('get_option')->justReturn('default');

// Use a callback
Functions\when('sanitize_text_field')->alias(function ($str) {
    return strip_tags($str);
});

// Expect specific arguments
Functions\expect('update_option')
    ->once()
    ->with('my_option', 'my_value')
    ->andReturn(true);
```

### Common Stubs

The base `TestCase` class provides stubs for common WordPress functions:

- `__()`, `_e()` - Translation functions
- `esc_html()`, `esc_attr()`, `esc_url()` - Escaping
- `sanitize_text_field()`, `sanitize_url()` - Sanitization
- `wp_json_encode()`, `wp_parse_url()` - Utilities
- `get_option()`, `update_option()`, `add_option()`, `delete_option()` - Options API
- `get_bloginfo()`, `home_url()`, `admin_url()` - Site info
- `wp_create_nonce()`, `wp_verify_nonce()` - Nonces
- `current_user_can()`, `get_current_user_id()` - User capabilities

## Continuous Integration

The tests are designed to run in CI environments without WordPress. For full integration testing in CI, consider using:

- [WP Browser](https://wpbrowser.wptestkit.dev/) for WordPress integration testing
- [WordPress PHPUnit Test Suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)

## Code Coverage

When running with code coverage, the following directories are included:

- `src/` - Main plugin source code

Excluded from coverage:

- `vendor/` - Composer dependencies
- `tests/` - Test files themselves
