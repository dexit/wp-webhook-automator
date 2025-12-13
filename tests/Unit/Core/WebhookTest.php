<?php
/**
 * Webhook Entity Tests
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WWA\Core\Webhook;

class WebhookTest extends TestCase
{
    /**
     * Test default values on empty constructor.
     */
    public function testDefaultValues(): void
    {
        $webhook = new Webhook();

        $this->assertSame(0, $webhook->getId());
        $this->assertSame('', $webhook->getName());
        $this->assertSame('', $webhook->getDescription());
        $this->assertSame('', $webhook->getTriggerType());
        $this->assertSame([], $webhook->getTriggerConfig());
        $this->assertSame('', $webhook->getEndpointUrl());
        $this->assertSame('POST', $webhook->getHttpMethod());
        $this->assertSame([], $webhook->getHeaders());
        $this->assertSame('json', $webhook->getPayloadFormat());
        $this->assertSame([], $webhook->getPayloadTemplate());
        $this->assertNull($webhook->getSecretKey());
        $this->assertTrue($webhook->isActive());
        $this->assertSame(3, $webhook->getRetryCount());
        $this->assertSame(60, $webhook->getRetryDelay());
        $this->assertNull($webhook->getCreatedAt());
        $this->assertNull($webhook->getUpdatedAt());
        $this->assertNull($webhook->getCreatedBy());
    }

    /**
     * Test constructor with data array.
     */
    public function testConstructorWithData(): void
    {
        $data = [
            'id' => 42,
            'name' => 'Test Webhook',
            'description' => 'A test webhook',
            'trigger_type' => 'post_published',
            'trigger_config' => ['post_types' => ['post', 'page']],
            'endpoint_url' => 'https://example.com/webhook',
            'http_method' => 'PUT',
            'headers' => ['Authorization' => 'Bearer token123'],
            'payload_format' => 'form',
            'payload_template' => ['title' => '{{post.title}}'],
            'secret_key' => 'secret123',
            'is_active' => false,
            'retry_count' => 5,
            'retry_delay' => 120,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
            'created_by' => 1,
        ];

        $webhook = new Webhook($data);

        $this->assertSame(42, $webhook->getId());
        $this->assertSame('Test Webhook', $webhook->getName());
        $this->assertSame('A test webhook', $webhook->getDescription());
        $this->assertSame('post_published', $webhook->getTriggerType());
        $this->assertSame(['post_types' => ['post', 'page']], $webhook->getTriggerConfig());
        $this->assertSame('https://example.com/webhook', $webhook->getEndpointUrl());
        $this->assertSame('PUT', $webhook->getHttpMethod());
        $this->assertSame(['Authorization' => 'Bearer token123'], $webhook->getHeaders());
        $this->assertSame('form', $webhook->getPayloadFormat());
        $this->assertSame(['title' => '{{post.title}}'], $webhook->getPayloadTemplate());
        $this->assertSame('secret123', $webhook->getSecretKey());
        $this->assertFalse($webhook->isActive());
        $this->assertSame(5, $webhook->getRetryCount());
        $this->assertSame(120, $webhook->getRetryDelay());
        $this->assertSame('2024-01-01 00:00:00', $webhook->getCreatedAt());
        $this->assertSame('2024-01-02 00:00:00', $webhook->getUpdatedAt());
        $this->assertSame(1, $webhook->getCreatedBy());
    }

    /**
     * Test fluent setters.
     */
    public function testFluentSetters(): void
    {
        $webhook = new Webhook();

        $result = $webhook
            ->setId(1)
            ->setName('Fluent Webhook')
            ->setDescription('Testing fluent interface')
            ->setTriggerType('user_registered')
            ->setEndpointUrl('https://example.com/hook')
            ->setHttpMethod('POST')
            ->setIsActive(true);

        $this->assertSame($webhook, $result);
        $this->assertSame(1, $webhook->getId());
        $this->assertSame('Fluent Webhook', $webhook->getName());
        $this->assertSame('Testing fluent interface', $webhook->getDescription());
        $this->assertSame('user_registered', $webhook->getTriggerType());
        $this->assertSame('https://example.com/hook', $webhook->getEndpointUrl());
        $this->assertSame('POST', $webhook->getHttpMethod());
        $this->assertTrue($webhook->isActive());
    }

    /**
     * Test toArray method.
     */
    public function testToArray(): void
    {
        $webhook = new Webhook();
        $webhook
            ->setId(1)
            ->setName('Array Webhook')
            ->setDescription('Test description')
            ->setTriggerType('post_published')
            ->setTriggerConfig(['post_types' => ['post']])
            ->setEndpointUrl('https://example.com/webhook')
            ->setHttpMethod('POST')
            ->setHeaders(['X-Custom' => 'header'])
            ->setPayloadFormat('json')
            ->setPayloadTemplate(['key' => 'value'])
            ->setSecretKey('secret')
            ->setIsActive(true)
            ->setRetryCount(3)
            ->setRetryDelay(60)
            ->setCreatedBy(1);

        $array = $webhook->toArray();

        $this->assertIsArray($array);
        $this->assertSame(1, $array['id']);
        $this->assertSame('Array Webhook', $array['name']);
        $this->assertSame('Test description', $array['description']);
        $this->assertSame('post_published', $array['trigger_type']);
        $this->assertSame(['post_types' => ['post']], $array['trigger_config']);
        $this->assertSame('https://example.com/webhook', $array['endpoint_url']);
        $this->assertSame('POST', $array['http_method']);
        $this->assertSame(['X-Custom' => 'header'], $array['headers']);
        $this->assertSame('json', $array['payload_format']);
        $this->assertSame(['key' => 'value'], $array['payload_template']);
        $this->assertSame('secret', $array['secret_key']);
        $this->assertTrue($array['is_active']);
        $this->assertSame(3, $array['retry_count']);
        $this->assertSame(60, $array['retry_delay']);
        $this->assertSame(1, $array['created_by']);
    }

    /**
     * Test fromArray method.
     */
    public function testFromArray(): void
    {
        $data = [
            'id' => '10', // String should be cast to int
            'name' => 'From Array',
            'trigger_type' => 'comment_created',
            'endpoint_url' => 'https://example.com/comments',
            'is_active' => '1', // String should be cast to bool
            'retry_count' => '5', // String should be cast to int
        ];

        $webhook = new Webhook();
        $result = $webhook->fromArray($data);

        $this->assertSame($webhook, $result);
        $this->assertSame(10, $webhook->getId());
        $this->assertSame('From Array', $webhook->getName());
        $this->assertSame('comment_created', $webhook->getTriggerType());
        $this->assertTrue($webhook->isActive());
        $this->assertSame(5, $webhook->getRetryCount());
    }

    /**
     * Test fromArray with JSON strings for arrays.
     */
    public function testFromArrayWithJsonStrings(): void
    {
        $data = [
            'id' => 1,
            'name' => 'JSON Test',
            'trigger_config' => '{"post_types":["post","page"]}',
            'headers' => '{"Authorization":"Bearer token"}',
            'payload_template' => '{"title":"{{post.title}}"}',
            'endpoint_url' => 'https://example.com/hook',
        ];

        $webhook = new Webhook($data);

        $this->assertSame(['post_types' => ['post', 'page']], $webhook->getTriggerConfig());
        $this->assertSame(['Authorization' => 'Bearer token'], $webhook->getHeaders());
        $this->assertSame(['title' => '{{post.title}}'], $webhook->getPayloadTemplate());
    }

    /**
     * Test fromArray with invalid JSON returns empty array.
     */
    public function testFromArrayWithInvalidJson(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Invalid JSON Test',
            'trigger_config' => 'not valid json',
            'headers' => '{broken',
            'endpoint_url' => 'https://example.com/hook',
        ];

        $webhook = new Webhook($data);

        $this->assertSame([], $webhook->getTriggerConfig());
        $this->assertSame([], $webhook->getHeaders());
    }

    /**
     * Test fromArray with missing data uses defaults.
     */
    public function testFromArrayWithMissingData(): void
    {
        $webhook = new Webhook([
            'name' => 'Minimal',
            'endpoint_url' => 'https://example.com',
        ]);

        $this->assertSame(0, $webhook->getId());
        $this->assertSame('Minimal', $webhook->getName());
        $this->assertSame('', $webhook->getDescription());
        $this->assertSame('POST', $webhook->getHttpMethod());
        $this->assertSame('json', $webhook->getPayloadFormat());
        $this->assertTrue($webhook->isActive());
        $this->assertSame(3, $webhook->getRetryCount());
        $this->assertSame(60, $webhook->getRetryDelay());
    }

    /**
     * Test setting null values.
     */
    public function testNullableValues(): void
    {
        $webhook = new Webhook();

        $webhook->setSecretKey('secret');
        $this->assertSame('secret', $webhook->getSecretKey());

        $webhook->setSecretKey(null);
        $this->assertNull($webhook->getSecretKey());

        $webhook->setCreatedBy(1);
        $this->assertSame(1, $webhook->getCreatedBy());

        $webhook->setCreatedBy(null);
        $this->assertNull($webhook->getCreatedBy());
    }

    /**
     * Test all HTTP methods are accepted.
     */
    public function testHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $webhook = new Webhook();

        foreach ($methods as $method) {
            $webhook->setHttpMethod($method);
            $this->assertSame($method, $webhook->getHttpMethod());
        }
    }

    /**
     * Test all payload formats are accepted.
     */
    public function testPayloadFormats(): void
    {
        $formats = ['json', 'form'];
        $webhook = new Webhook();

        foreach ($formats as $format) {
            $webhook->setPayloadFormat($format);
            $this->assertSame($format, $webhook->getPayloadFormat());
        }
    }

    /**
     * Test round-trip: toArray -> fromArray preserves data.
     */
    public function testRoundTrip(): void
    {
        $original = new Webhook();
        $original
            ->setId(999)
            ->setName('Round Trip')
            ->setDescription('Testing round trip')
            ->setTriggerType('post_updated')
            ->setTriggerConfig(['post_types' => ['post']])
            ->setEndpointUrl('https://example.com/rt')
            ->setHttpMethod('PATCH')
            ->setHeaders(['X-Test' => 'value'])
            ->setPayloadFormat('json')
            ->setPayloadTemplate(['a' => 'b'])
            ->setSecretKey('roundtripsecret')
            ->setIsActive(false)
            ->setRetryCount(2)
            ->setRetryDelay(30)
            ->setCreatedBy(5);

        $array = $original->toArray();
        $restored = new Webhook($array);

        $this->assertSame($original->getId(), $restored->getId());
        $this->assertSame($original->getName(), $restored->getName());
        $this->assertSame($original->getDescription(), $restored->getDescription());
        $this->assertSame($original->getTriggerType(), $restored->getTriggerType());
        $this->assertSame($original->getTriggerConfig(), $restored->getTriggerConfig());
        $this->assertSame($original->getEndpointUrl(), $restored->getEndpointUrl());
        $this->assertSame($original->getHttpMethod(), $restored->getHttpMethod());
        $this->assertSame($original->getHeaders(), $restored->getHeaders());
        $this->assertSame($original->getPayloadFormat(), $restored->getPayloadFormat());
        $this->assertSame($original->getPayloadTemplate(), $restored->getPayloadTemplate());
        $this->assertSame($original->getSecretKey(), $restored->getSecretKey());
        $this->assertSame($original->isActive(), $restored->isActive());
        $this->assertSame($original->getRetryCount(), $restored->getRetryCount());
        $this->assertSame($original->getRetryDelay(), $restored->getRetryDelay());
        $this->assertSame($original->getCreatedBy(), $restored->getCreatedBy());
    }
}
