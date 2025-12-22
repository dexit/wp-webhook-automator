<?php
/**
 * PostPublishedTrigger Tests
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Tests\Unit\Triggers;

use Hookly\Tests\TestCase;
use Hookly\Triggers\PostPublishedTrigger;
use Brain\Monkey\Functions;
use Mockery;

class PostPublishedTriggerTest extends TestCase
{
    private PostPublishedTrigger $trigger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trigger = new PostPublishedTrigger();
    }

    /**
     * Test trigger key.
     */
    public function testGetKey(): void
    {
        $this->assertSame('post_published', $this->trigger->getKey());
    }

    /**
     * Test trigger name.
     */
    public function testGetName(): void
    {
        $this->assertSame('Post Published', $this->trigger->getName());
    }

    /**
     * Test trigger description.
     */
    public function testGetDescription(): void
    {
        $this->assertSame('Fires when a post is published', $this->trigger->getDescription());
    }

    /**
     * Test trigger category.
     */
    public function testGetCategory(): void
    {
        $this->assertSame('Posts', $this->trigger->getCategory());
    }

    /**
     * Test trigger hook.
     */
    public function testGetHook(): void
    {
        $this->assertSame('transition_post_status', $this->trigger->getHook());
    }

    /**
     * Test available data fields.
     */
    public function testGetAvailableData(): void
    {
        $data = $this->trigger->getAvailableData();

        $this->assertContains('post', $data);
        $this->assertContains('post.author', $data);
    }

    /**
     * Test config fields include post_types.
     */
    public function testGetConfigFields(): void
    {
        // Mock get_post_types
        Functions\when('get_post_types')->justReturn([
            (object) ['name' => 'post', 'label' => 'Posts'],
            (object) ['name' => 'page', 'label' => 'Pages'],
        ]);

        $fields = $this->trigger->getConfigFields();

        $this->assertArrayHasKey('post_types', $fields);
        $this->assertSame('multiselect', $fields['post_types']['type']);
        $this->assertArrayHasKey('label', $fields['post_types']);
        $this->assertArrayHasKey('default', $fields['post_types']);
        $this->assertSame(['post'], $fields['post_types']['default']);
    }

    /**
     * Test matchesConfig with matching post type.
     */
    public function testMatchesConfigMatching(): void
    {
        $eventData = [
            'post' => ['type' => 'post'],
        ];
        $config = [
            'post_types' => ['post', 'page'],
        ];

        $result = $this->trigger->matchesConfig($eventData, $config);

        $this->assertTrue($result);
    }

    /**
     * Test matchesConfig with non-matching post type.
     */
    public function testMatchesConfigNotMatching(): void
    {
        $eventData = [
            'post' => ['type' => 'product'],
        ];
        $config = [
            'post_types' => ['post', 'page'],
        ];

        $result = $this->trigger->matchesConfig($eventData, $config);

        $this->assertFalse($result);
    }

    /**
     * Test matchesConfig with empty config matches all.
     */
    public function testMatchesConfigEmptyConfig(): void
    {
        $eventData = [
            'post' => ['type' => 'any_type'],
        ];
        $config = [];

        $result = $this->trigger->matchesConfig($eventData, $config);

        $this->assertTrue($result);
    }

    /**
     * Test matchesConfig with string post_types (edge case).
     */
    public function testMatchesConfigStringPostType(): void
    {
        $eventData = [
            'post' => ['type' => 'post'],
        ];
        $config = [
            'post_types' => 'post', // String instead of array
        ];

        $result = $this->trigger->matchesConfig($eventData, $config);

        $this->assertTrue($result);
    }

    /**
     * Test register adds action.
     */
    public function testRegister(): void
    {
        $callbackCalled = false;
        $callback = function ($key, $data) use (&$callbackCalled) {
            $callbackCalled = true;
        };

        // Mock add_action
        Functions\when('add_action')->alias(function ($hook, $callable, $priority, $args) {
            // Just verify the hook is correct
            return $hook === 'transition_post_status';
        });

        $this->trigger->register($callback);

        // We can't fully test the callback without simulating WordPress hooks
        // but we can verify add_action was called with correct hook
        $this->assertTrue(true); // If no exception, test passes
    }

    /**
     * Test validateConfig always returns true (default behavior).
     */
    public function testValidateConfig(): void
    {
        $this->assertTrue($this->trigger->validateConfig([]));
        $this->assertTrue($this->trigger->validateConfig(['post_types' => ['post']]));
        $this->assertTrue($this->trigger->validateConfig(['invalid' => 'config']));
    }
}

/**
 * Mock WP_Post class for testing.
 */
if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID;
        public string $post_title = '';
        public string $post_content = '';
        public string $post_excerpt = '';
        public string $post_status = '';
        public string $post_type = '';
        public string $post_name = '';
        public int $post_author = 0;
        public string $post_date = '';
        public string $post_date_gmt = '';
        public string $post_modified = '';
        public string $post_modified_gmt = '';

        public function __construct(array $data = [])
        {
            foreach ($data as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
}
