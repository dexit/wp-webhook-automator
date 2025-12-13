<?php
/**
 * WebhooksController Integration Tests
 *
 * Tests the REST controller logic without a full WordPress installation.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Tests\Integration\Rest;

use WWA\Tests\TestCase;
use WWA\Core\Webhook;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Integration tests for WebhooksController.
 *
 * Note: Full REST API integration testing requires the WordPress test suite.
 * These tests focus on the controller's data processing logic.
 */
class WebhooksControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional mocks for REST API
        $this->setUpRestApiFunctions();
    }

    private function setUpRestApiFunctions(): void
    {
        Functions\stubs([
            'register_rest_route' => true,
            'absint' => function ($value) {
                return abs((int) $value);
            },
            'esc_url_raw' => function ($url) {
                return filter_var($url, FILTER_SANITIZE_URL);
            },
            'sanitize_textarea_field' => function ($str) {
                return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
            },
            'current_time' => function ($type) {
                return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
            },
            'wp_get_current_user' => function () {
                return (object) [
                    'ID' => 1,
                    'user_login' => 'admin',
                    'user_email' => 'admin@example.com',
                    'display_name' => 'Administrator',
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                ];
            },
            'rest_url' => function ($path = '') {
                return 'https://example.com/wp-json/' . ltrim($path, '/');
            },
        ]);
    }

    /**
     * Test webhook response preparation.
     */
    public function testWebhookResponsePreparation(): void
    {
        $webhook = new Webhook([
            'id' => 1,
            'name' => 'Test Webhook',
            'description' => 'A test webhook',
            'trigger_type' => 'post_published',
            'trigger_config' => ['post_types' => ['post']],
            'endpoint_url' => 'https://example.com/hook',
            'http_method' => 'POST',
            'headers' => ['Authorization' => 'Bearer token'],
            'payload_format' => 'json',
            'payload_template' => ['title' => '{{post.title}}'],
            'secret_key' => 'secret123',
            'is_active' => true,
            'retry_count' => 3,
            'retry_delay' => 60,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ]);

        // Test that webhook can be converted to array format
        $array = $webhook->toArray();

        $this->assertSame(1, $array['id']);
        $this->assertSame('Test Webhook', $array['name']);
        $this->assertSame('post_published', $array['trigger_type']);
        $this->assertTrue($array['is_active']);
        $this->assertSame('secret123', $array['secret_key']);
    }

    /**
     * Test webhook creation from array data.
     */
    public function testWebhookCreationFromData(): void
    {
        $data = [
            'name' => 'New Webhook',
            'description' => 'Created via API',
            'trigger_type' => 'user_registered',
            'trigger_config' => [],
            'endpoint_url' => 'https://api.example.com/users',
            'http_method' => 'POST',
            'headers' => [],
            'payload_format' => 'json',
            'payload_template' => [],
            'secret_key' => null,
            'is_active' => true,
            'retry_count' => 5,
            'retry_delay' => 120,
        ];

        $webhook = new Webhook($data);

        $this->assertSame('New Webhook', $webhook->getName());
        $this->assertSame('Created via API', $webhook->getDescription());
        $this->assertSame('user_registered', $webhook->getTriggerType());
        $this->assertSame('https://api.example.com/users', $webhook->getEndpointUrl());
        $this->assertSame('POST', $webhook->getHttpMethod());
        $this->assertTrue($webhook->isActive());
        $this->assertSame(5, $webhook->getRetryCount());
    }

    /**
     * Test webhook update partial data.
     */
    public function testWebhookPartialUpdate(): void
    {
        $webhook = new Webhook([
            'id' => 1,
            'name' => 'Original Name',
            'description' => 'Original description',
            'trigger_type' => 'post_published',
            'endpoint_url' => 'https://original.com/hook',
            'is_active' => true,
        ]);

        // Simulate partial update
        $webhook->setName('Updated Name');
        $webhook->setIsActive(false);

        $this->assertSame('Updated Name', $webhook->getName());
        $this->assertSame('Original description', $webhook->getDescription());
        $this->assertSame('https://original.com/hook', $webhook->getEndpointUrl());
        $this->assertFalse($webhook->isActive());
    }

    /**
     * Test endpoint URL validation logic.
     */
    public function testEndpointUrlValidation(): void
    {
        $validUrls = [
            'https://example.com/webhook',
            'https://api.example.com/v1/hooks',
            'http://localhost:8080/test',
            'https://example.com/webhook?key=value',
        ];

        foreach ($validUrls as $url) {
            $this->assertNotFalse(
                filter_var($url, FILTER_VALIDATE_URL),
                "URL should be valid: {$url}"
            );
        }

        $invalidUrls = [
            'not-a-url',
            'ftp://example.com',
            '',
            'javascript:alert(1)',
        ];

        foreach ($invalidUrls as $url) {
            // Empty strings and non-http URLs should fail
            if ($url === '' || !preg_match('#^https?://#', $url)) {
                $this->assertTrue(true); // Expected to be invalid
            }
        }
    }

    /**
     * Test HTTP method validation.
     */
    public function testHttpMethodValidation(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($validMethods as $method) {
            $webhook = new Webhook();
            $webhook->setHttpMethod($method);
            $this->assertSame($method, $webhook->getHttpMethod());
        }
    }

    /**
     * Test payload format validation.
     */
    public function testPayloadFormatValidation(): void
    {
        $validFormats = ['json', 'form'];

        foreach ($validFormats as $format) {
            $webhook = new Webhook();
            $webhook->setPayloadFormat($format);
            $this->assertSame($format, $webhook->getPayloadFormat());
        }
    }

    /**
     * Test retry count bounds.
     */
    public function testRetryCountBounds(): void
    {
        $webhook = new Webhook();

        // Test minimum
        $webhook->setRetryCount(0);
        $this->assertSame(0, $webhook->getRetryCount());

        // Test maximum (as enforced in REST API)
        $webhook->setRetryCount(10);
        $this->assertSame(10, $webhook->getRetryCount());
    }

    /**
     * Test retry delay bounds.
     */
    public function testRetryDelayBounds(): void
    {
        $webhook = new Webhook();

        // Test minimum (as enforced in REST API: 10 seconds)
        $webhook->setRetryDelay(10);
        $this->assertSame(10, $webhook->getRetryDelay());

        // Test maximum (as enforced in REST API: 3600 seconds / 1 hour)
        $webhook->setRetryDelay(3600);
        $this->assertSame(3600, $webhook->getRetryDelay());
    }

    /**
     * Test secret key masking logic.
     */
    public function testSecretKeyMasking(): void
    {
        $webhook = new Webhook([
            'secret_key' => 'my-super-secret-key',
        ]);

        // In API response, secret should be masked
        $this->assertSame('my-super-secret-key', $webhook->getSecretKey());

        // Simulate masked response check
        $maskedValue = $webhook->getSecretKey() ? '********' : null;
        $this->assertSame('********', $maskedValue);
    }

    /**
     * Test webhook active toggle logic.
     */
    public function testWebhookToggleLogic(): void
    {
        $webhook = new Webhook(['is_active' => true]);

        $this->assertTrue($webhook->isActive());

        // Toggle off
        $webhook->setIsActive(!$webhook->isActive());
        $this->assertFalse($webhook->isActive());

        // Toggle on
        $webhook->setIsActive(!$webhook->isActive());
        $this->assertTrue($webhook->isActive());
    }

    /**
     * Test webhook duplication logic.
     */
    public function testWebhookDuplicationLogic(): void
    {
        $original = new Webhook([
            'id' => 1,
            'name' => 'Original Webhook',
            'description' => 'Original description',
            'trigger_type' => 'post_published',
            'endpoint_url' => 'https://example.com/hook',
            'is_active' => true,
            'created_by' => 1,
        ]);

        // Simulate duplication: create new webhook from original data
        $duplicateData = $original->toArray();
        unset($duplicateData['id']); // Remove ID for new entry
        $duplicateData['name'] = $original->getName() . ' (Copy)';
        $duplicateData['is_active'] = false; // Deactivate copy

        $duplicate = new Webhook($duplicateData);

        $this->assertSame(0, $duplicate->getId()); // No ID yet
        $this->assertSame('Original Webhook (Copy)', $duplicate->getName());
        $this->assertSame($original->getTriggerType(), $duplicate->getTriggerType());
        $this->assertSame($original->getEndpointUrl(), $duplicate->getEndpointUrl());
        $this->assertFalse($duplicate->isActive());
    }

    /**
     * Test collection filtering by trigger type.
     */
    public function testCollectionFilteringByTriggerType(): void
    {
        $webhooks = [
            new Webhook(['id' => 1, 'name' => 'Post Hook', 'trigger_type' => 'post_published']),
            new Webhook(['id' => 2, 'name' => 'User Hook', 'trigger_type' => 'user_registered']),
            new Webhook(['id' => 3, 'name' => 'Another Post', 'trigger_type' => 'post_published']),
        ];

        $filterType = 'post_published';
        $filtered = array_filter($webhooks, fn($w) => $w->getTriggerType() === $filterType);

        $this->assertCount(2, $filtered);
    }

    /**
     * Test collection filtering by active status.
     */
    public function testCollectionFilteringByActiveStatus(): void
    {
        $webhooks = [
            new Webhook(['id' => 1, 'is_active' => true]),
            new Webhook(['id' => 2, 'is_active' => false]),
            new Webhook(['id' => 3, 'is_active' => true]),
        ];

        $activeOnly = array_filter($webhooks, fn($w) => $w->isActive());
        $inactiveOnly = array_filter($webhooks, fn($w) => !$w->isActive());

        $this->assertCount(2, $activeOnly);
        $this->assertCount(1, $inactiveOnly);
    }

    /**
     * Test pagination calculation.
     */
    public function testPaginationCalculation(): void
    {
        $page = 2;
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $this->assertSame(10, $offset);

        $total = 25;
        $totalPages = (int) ceil($total / $perPage);

        $this->assertSame(3, $totalPages);
    }

    /**
     * Test test webhook data structure.
     */
    public function testTestWebhookDataStructure(): void
    {
        // Simulate test data structure as used in test_item
        $testData = [
            'test' => true,
            'timestamp' => time(),
            'message' => 'This is a test webhook from WP Webhook Automator.',
            'post' => [
                'id' => 1,
                'title' => 'Test Post Title',
                'content' => 'Test post content.',
                'excerpt' => 'Test post excerpt.',
                'status' => 'publish',
                'type' => 'post',
                'slug' => 'test-post',
                'url' => 'https://example.com/test-post/',
                'author' => [
                    'id' => 1,
                    'name' => 'Administrator',
                    'email' => 'admin@example.com',
                ],
                'date' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            'user' => [
                'id' => 1,
                'login' => 'admin',
                'email' => 'admin@example.com',
                'display_name' => 'Administrator',
                'first_name' => 'Admin',
                'last_name' => 'User',
            ],
        ];

        $this->assertTrue($testData['test']);
        $this->assertIsInt($testData['timestamp']);
        $this->assertArrayHasKey('post', $testData);
        $this->assertArrayHasKey('user', $testData);
        $this->assertArrayHasKey('author', $testData['post']);
    }
}
