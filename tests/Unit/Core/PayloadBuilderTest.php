<?php
/**
 * PayloadBuilder Tests
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Tests\Unit\Core;

use WWA\Tests\TestCase;
use WWA\Core\PayloadBuilder;

class PayloadBuilderTest extends TestCase
{
    private PayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PayloadBuilder();
    }

    /**
     * Test building default payload when template is empty.
     */
    public function testBuildDefaultPayload(): void
    {
        $eventData = [
            'post' => [
                'id' => 123,
                'title' => 'Test Post',
            ],
        ];

        $result = $this->builder->build([], $eventData);

        $this->assertArrayHasKey('site', $result);
        $this->assertArrayHasKey('name', $result['site']);
        $this->assertArrayHasKey('url', $result['site']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('timestamp_iso', $result);
        $this->assertArrayHasKey('event', $result);
        $this->assertSame($eventData, $result['event']);
    }

    /**
     * Test building payload with simple template.
     */
    public function testBuildWithSimpleTemplate(): void
    {
        $template = [
            'title' => '{{post.title}}',
            'id' => '{{post.id}}',
            'static' => 'constant value',
        ];

        $eventData = [
            'post' => [
                'id' => 456,
                'title' => 'My Post Title',
            ],
        ];

        $result = $this->builder->build($template, $eventData);

        $this->assertSame('My Post Title', $result['title']);
        $this->assertSame('456', $result['id']);
        $this->assertSame('constant value', $result['static']);
    }

    /**
     * Test building payload with nested template.
     */
    public function testBuildWithNestedTemplate(): void
    {
        $template = [
            'post' => [
                'title' => '{{post.title}}',
                'author' => [
                    'name' => '{{post.author.name}}',
                    'email' => '{{post.author.email}}',
                ],
            ],
        ];

        $eventData = [
            'post' => [
                'title' => 'Nested Test',
                'author' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ],
        ];

        $result = $this->builder->build($template, $eventData);

        $this->assertSame('Nested Test', $result['post']['title']);
        $this->assertSame('John Doe', $result['post']['author']['name']);
        $this->assertSame('john@example.com', $result['post']['author']['email']);
    }

    /**
     * Test merge tag replacement in string.
     */
    public function testReplaceMergeTags(): void
    {
        $content = 'Post "{{post.title}}" by {{post.author.name}}';
        $data = [
            'post' => [
                'title' => 'Hello World',
                'author' => [
                    'name' => 'Jane',
                ],
            ],
        ];

        $result = $this->builder->replaceMergeTags($content, $data);

        $this->assertSame('Post "Hello World" by Jane', $result);
    }

    /**
     * Test merge tag replacement with missing data keeps original.
     */
    public function testReplaceMergeTagsWithMissingData(): void
    {
        $content = 'Value: {{missing.path}}';
        $data = ['other' => 'data'];

        $result = $this->builder->replaceMergeTags($content, $data);

        $this->assertSame('Value: {{missing.path}}', $result);
    }

    /**
     * Test merge tag replacement with array value converts to JSON.
     */
    public function testReplaceMergeTagsWithArrayValue(): void
    {
        $content = 'Items: {{items}}';
        $data = [
            'items' => ['apple', 'banana', 'cherry'],
        ];

        $result = $this->builder->replaceMergeTags($content, $data);

        $this->assertSame('Items: ["apple","banana","cherry"]', $result);
    }

    /**
     * Test buildJson returns valid JSON string.
     */
    public function testBuildJson(): void
    {
        $template = [
            'message' => '{{message}}',
            'count' => 42,
        ];

        $eventData = [
            'message' => 'Hello',
        ];

        $result = $this->builder->buildJson($template, $eventData);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertSame('Hello', $decoded['message']);
        $this->assertSame(42, $decoded['count']);
    }

    /**
     * Test buildFormData returns URL-encoded string.
     */
    public function testBuildFormData(): void
    {
        $template = [
            'name' => '{{user.name}}',
            'email' => '{{user.email}}',
        ];

        $eventData = [
            'user' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ],
        ];

        $result = $this->builder->buildFormData($template, $eventData);

        $this->assertStringContainsString('name=Test+User', $result);
        $this->assertStringContainsString('email=test%40example.com', $result);
    }

    /**
     * Test buildFormData with nested data.
     */
    public function testBuildFormDataWithNestedData(): void
    {
        $template = [
            'user' => [
                'name' => '{{user.name}}',
            ],
        ];

        $eventData = [
            'user' => [
                'name' => 'Nested',
            ],
        ];

        $result = $this->builder->buildFormData($template, $eventData);

        $this->assertStringContainsString('user[name]=Nested', urldecode($result));
    }

    /**
     * Test getGlobalData returns expected structure.
     */
    public function testGetGlobalData(): void
    {
        $result = $this->builder->getGlobalData();

        $this->assertArrayHasKey('site', $result);
        $this->assertArrayHasKey('name', $result['site']);
        $this->assertArrayHasKey('url', $result['site']);
        $this->assertArrayHasKey('admin_email', $result['site']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('timestamp_iso', $result);
        $this->assertIsInt($result['timestamp']);
        $this->assertIsString($result['timestamp_iso']);
    }

    /**
     * Test getAvailableTags returns global tags.
     */
    public function testGetAvailableTagsGlobal(): void
    {
        $tags = $this->builder->getAvailableTags('custom');

        $this->assertArrayHasKey('site.name', $tags);
        $this->assertArrayHasKey('site.url', $tags);
        $this->assertArrayHasKey('site.admin_email', $tags);
        $this->assertArrayHasKey('timestamp', $tags);
        $this->assertArrayHasKey('timestamp_iso', $tags);
        $this->assertArrayHasKey('webhook.name', $tags);
        $this->assertArrayHasKey('webhook.id', $tags);
    }

    /**
     * Test getAvailableTags returns post-specific tags.
     */
    public function testGetAvailableTagsForPostTrigger(): void
    {
        $tags = $this->builder->getAvailableTags('post_published');

        $this->assertArrayHasKey('post.id', $tags);
        $this->assertArrayHasKey('post.title', $tags);
        $this->assertArrayHasKey('post.content', $tags);
        $this->assertArrayHasKey('post.author.name', $tags);
        $this->assertArrayHasKey('post.url', $tags);
    }

    /**
     * Test getAvailableTags returns user-specific tags.
     */
    public function testGetAvailableTagsForUserTrigger(): void
    {
        $tags = $this->builder->getAvailableTags('user_registered');

        $this->assertArrayHasKey('user.id', $tags);
        $this->assertArrayHasKey('user.login', $tags);
        $this->assertArrayHasKey('user.email', $tags);
        $this->assertArrayHasKey('user.display_name', $tags);
    }

    /**
     * Test getAvailableTags returns comment-specific tags.
     */
    public function testGetAvailableTagsForCommentTrigger(): void
    {
        $tags = $this->builder->getAvailableTags('comment_created');

        $this->assertArrayHasKey('comment.id', $tags);
        $this->assertArrayHasKey('comment.content', $tags);
        $this->assertArrayHasKey('comment.author_name', $tags);
        $this->assertArrayHasKey('comment.post.id', $tags);
    }

    /**
     * Test parseTemplate with valid JSON.
     */
    public function testParseTemplateWithValidJson(): void
    {
        $json = '{"title": "{{post.title}}", "count": 5}';

        $result = $this->builder->parseTemplate($json);

        $this->assertIsArray($result);
        $this->assertSame('{{post.title}}', $result['title']);
        $this->assertSame(5, $result['count']);
    }

    /**
     * Test parseTemplate with invalid JSON returns empty array.
     */
    public function testParseTemplateWithInvalidJson(): void
    {
        $invalid = 'not valid json {';

        $result = $this->builder->parseTemplate($invalid);

        $this->assertSame([], $result);
    }

    /**
     * Test parseTemplate with empty string returns empty array.
     */
    public function testParseTemplateWithEmptyString(): void
    {
        $result = $this->builder->parseTemplate('');

        $this->assertSame([], $result);
    }

    /**
     * Test validateTemplate with valid template.
     */
    public function testValidateTemplateValid(): void
    {
        $template = [
            'simple' => 'value',
            'nested' => [
                'key' => 'value',
            ],
        ];

        $this->assertTrue($this->builder->validateTemplate($template));
    }

    /**
     * Test template with non-string values.
     */
    public function testBuildWithMixedValues(): void
    {
        $template = [
            'string' => '{{value}}',
            'number' => 42,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
        ];

        $eventData = ['value' => 'replaced'];

        $result = $this->builder->build($template, $eventData);

        $this->assertSame('replaced', $result['string']);
        $this->assertSame(42, $result['number']);
        $this->assertTrue($result['boolean']);
        $this->assertNull($result['null']);
        $this->assertSame([1, 2, 3], $result['array']);
    }

    /**
     * Test merge tags with special characters in values.
     */
    public function testMergeTagsWithSpecialCharacters(): void
    {
        $content = 'Title: {{post.title}}';
        $data = [
            'post' => [
                'title' => 'Test <script>alert("xss")</script>',
            ],
        ];

        $result = $this->builder->replaceMergeTags($content, $data);

        // The replacement should work, but note this is raw -
        // escaping would happen at output time
        $this->assertStringContainsString('<script>', $result);
    }

    /**
     * Test deeply nested merge tags.
     */
    public function testDeeplyNestedMergeTags(): void
    {
        $content = 'Value: {{level1.level2.level3.value}}';
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep',
                    ],
                ],
            ],
        ];

        $result = $this->builder->replaceMergeTags($content, $data);

        $this->assertSame('Value: deep', $result);
    }

    /**
     * Test multiple merge tags in same string.
     */
    public function testMultipleMergeTags(): void
    {
        $content = '{{user.name}} ({{user.email}}) - {{user.role}}';
        $data = [
            'user' => [
                'name' => 'John',
                'email' => 'john@test.com',
                'role' => 'admin',
            ],
        ];

        $result = $this->builder->replaceMergeTags($content, $data);

        $this->assertSame('John (john@test.com) - admin', $result);
    }

    /**
     * Test merge tag with numeric keys.
     */
    public function testMergeTagWithNumericKeys(): void
    {
        $content = 'First item: {{items.0}}';
        $data = [
            'items' => ['apple', 'banana', 'cherry'],
        ];

        $result = $this->builder->replaceMergeTags($content, $data);

        $this->assertSame('First item: apple', $result);
    }
}
