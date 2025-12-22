<?php
/**
 * TriggerRegistry Tests
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Tests\Unit\Triggers;

use Hookly\Tests\TestCase;
use Hookly\Triggers\TriggerRegistry;
use Hookly\Triggers\TriggerInterface;
use Mockery;

class TriggerRegistryTest extends TestCase
{
    private TriggerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        // Get the singleton instance
        $this->registry = TriggerRegistry::getInstance();

        // Clear any existing triggers using reflection
        $reflection = new \ReflectionClass($this->registry);
        $property = $reflection->getProperty('triggers');
        $property->setAccessible(true);
        $property->setValue($this->registry, []);
    }

    /**
     * Create a mock trigger.
     */
    private function createMockTrigger(
        string $key,
        string $name = 'Test Trigger',
        string $category = 'Test Category'
    ): TriggerInterface {
        $trigger = Mockery::mock(TriggerInterface::class);
        $trigger->shouldReceive('getKey')->andReturn($key);
        $trigger->shouldReceive('getName')->andReturn($name);
        $trigger->shouldReceive('getDescription')->andReturn("Description for {$key}");
        $trigger->shouldReceive('getCategory')->andReturn($category);
        $trigger->shouldReceive('getConfigFields')->andReturn([]);
        $trigger->shouldReceive('getAvailableData')->andReturn([]);

        return $trigger;
    }

    /**
     * Test singleton pattern.
     */
    public function testGetInstance(): void
    {
        $instance1 = TriggerRegistry::getInstance();
        $instance2 = TriggerRegistry::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test registering a trigger.
     */
    public function testRegister(): void
    {
        $trigger = $this->createMockTrigger('test_trigger');

        $this->registry->register($trigger);

        $this->assertTrue($this->registry->has('test_trigger'));
        $this->assertSame($trigger, $this->registry->get('test_trigger'));
    }

    /**
     * Test unregistering a trigger.
     */
    public function testUnregister(): void
    {
        $trigger = $this->createMockTrigger('removable');
        $this->registry->register($trigger);

        $this->assertTrue($this->registry->has('removable'));

        $this->registry->unregister('removable');

        $this->assertFalse($this->registry->has('removable'));
        $this->assertNull($this->registry->get('removable'));
    }

    /**
     * Test getting a non-existent trigger.
     */
    public function testGetNonExistent(): void
    {
        $result = $this->registry->get('nonexistent');

        $this->assertNull($result);
    }

    /**
     * Test has method with existing trigger.
     */
    public function testHasExisting(): void
    {
        $trigger = $this->createMockTrigger('exists');
        $this->registry->register($trigger);

        $this->assertTrue($this->registry->has('exists'));
    }

    /**
     * Test has method with non-existing trigger.
     */
    public function testHasNonExisting(): void
    {
        $this->assertFalse($this->registry->has('does_not_exist'));
    }

    /**
     * Test getAll returns all triggers.
     */
    public function testGetAll(): void
    {
        $trigger1 = $this->createMockTrigger('trigger_1');
        $trigger2 = $this->createMockTrigger('trigger_2');

        $this->registry->register($trigger1);
        $this->registry->register($trigger2);

        $all = $this->registry->getAll();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('trigger_1', $all);
        $this->assertArrayHasKey('trigger_2', $all);
    }

    /**
     * Test getByCategory filters correctly.
     */
    public function testGetByCategory(): void
    {
        $trigger1 = $this->createMockTrigger('post_1', 'Post Trigger 1', 'Posts');
        $trigger2 = $this->createMockTrigger('post_2', 'Post Trigger 2', 'Posts');
        $trigger3 = $this->createMockTrigger('user_1', 'User Trigger', 'Users');

        $this->registry->register($trigger1);
        $this->registry->register($trigger2);
        $this->registry->register($trigger3);

        $postTriggers = $this->registry->getByCategory('Posts');
        $userTriggers = $this->registry->getByCategory('Users');

        $this->assertCount(2, $postTriggers);
        $this->assertCount(1, $userTriggers);
        $this->assertArrayHasKey('post_1', $postTriggers);
        $this->assertArrayHasKey('post_2', $postTriggers);
        $this->assertArrayHasKey('user_1', $userTriggers);
    }

    /**
     * Test getByCategory with non-existent category.
     */
    public function testGetByCategoryEmpty(): void
    {
        $trigger = $this->createMockTrigger('trigger', 'Trigger', 'SomeCategory');
        $this->registry->register($trigger);

        $result = $this->registry->getByCategory('NonExistent');

        $this->assertEmpty($result);
    }

    /**
     * Test getCategories groups triggers correctly.
     */
    public function testGetCategories(): void
    {
        $trigger1 = $this->createMockTrigger('a_trigger', 'A Trigger', 'Alpha');
        $trigger2 = $this->createMockTrigger('b_trigger', 'B Trigger', 'Beta');
        $trigger3 = $this->createMockTrigger('a_trigger_2', 'A Trigger 2', 'Alpha');

        $this->registry->register($trigger1);
        $this->registry->register($trigger2);
        $this->registry->register($trigger3);

        $categories = $this->registry->getCategories();

        $this->assertArrayHasKey('Alpha', $categories);
        $this->assertArrayHasKey('Beta', $categories);
        $this->assertCount(2, $categories['Alpha']);
        $this->assertCount(1, $categories['Beta']);
    }

    /**
     * Test getCategories sorts alphabetically.
     */
    public function testGetCategoriesSorted(): void
    {
        $trigger1 = $this->createMockTrigger('z_trigger', 'Z', 'Zeta');
        $trigger2 = $this->createMockTrigger('a_trigger', 'A', 'Alpha');
        $trigger3 = $this->createMockTrigger('m_trigger', 'M', 'Mu');

        $this->registry->register($trigger1);
        $this->registry->register($trigger2);
        $this->registry->register($trigger3);

        $categories = $this->registry->getCategories();
        $categoryNames = array_keys($categories);

        $this->assertSame(['Alpha', 'Mu', 'Zeta'], $categoryNames);
    }

    /**
     * Test getForSelect format.
     */
    public function testGetForSelect(): void
    {
        $trigger1 = $this->createMockTrigger('post_published', 'Post Published', 'Posts');
        $trigger2 = $this->createMockTrigger('user_registered', 'User Registered', 'Users');

        $this->registry->register($trigger1);
        $this->registry->register($trigger2);

        $options = $this->registry->getForSelect();

        $this->assertArrayHasKey('Posts', $options);
        $this->assertArrayHasKey('Users', $options);
        $this->assertSame('Post Published', $options['Posts']['post_published']);
        $this->assertSame('User Registered', $options['Users']['user_registered']);
    }

    /**
     * Test getTriggerInfo.
     */
    public function testGetTriggerInfo(): void
    {
        $trigger = $this->createMockTrigger('info_test', 'Info Test', 'Test');
        $this->registry->register($trigger);

        $info = $this->registry->getTriggerInfo();

        $this->assertArrayHasKey('info_test', $info);
        $this->assertSame('info_test', $info['info_test']['key']);
        $this->assertSame('Info Test', $info['info_test']['name']);
        $this->assertSame('Description for info_test', $info['info_test']['description']);
        $this->assertSame('Test', $info['info_test']['category']);
    }

    /**
     * Test getConfigFields for a trigger.
     */
    public function testGetConfigFields(): void
    {
        $trigger = Mockery::mock(TriggerInterface::class);
        $trigger->shouldReceive('getKey')->andReturn('configurable');
        $trigger->shouldReceive('getConfigFields')->andReturn([
            'option' => ['type' => 'select', 'label' => 'Option'],
        ]);

        $this->registry->register($trigger);

        $fields = $this->registry->getConfigFields('configurable');

        $this->assertArrayHasKey('option', $fields);
        $this->assertSame('select', $fields['option']['type']);
    }

    /**
     * Test getConfigFields for non-existent trigger.
     */
    public function testGetConfigFieldsNonExistent(): void
    {
        $fields = $this->registry->getConfigFields('nonexistent');

        $this->assertEmpty($fields);
    }

    /**
     * Test getAvailableData for a trigger.
     */
    public function testGetAvailableData(): void
    {
        $trigger = Mockery::mock(TriggerInterface::class);
        $trigger->shouldReceive('getKey')->andReturn('data_trigger');
        $trigger->shouldReceive('getAvailableData')->andReturn(['post', 'user']);

        $this->registry->register($trigger);

        $data = $this->registry->getAvailableData('data_trigger');

        $this->assertSame(['post', 'user'], $data);
    }

    /**
     * Test getAvailableData for non-existent trigger.
     */
    public function testGetAvailableDataNonExistent(): void
    {
        $data = $this->registry->getAvailableData('nonexistent');

        $this->assertEmpty($data);
    }

    /**
     * Test count method.
     */
    public function testCount(): void
    {
        $this->assertSame(0, $this->registry->count());

        $this->registry->register($this->createMockTrigger('t1'));
        $this->assertSame(1, $this->registry->count());

        $this->registry->register($this->createMockTrigger('t2'));
        $this->assertSame(2, $this->registry->count());

        $this->registry->unregister('t1');
        $this->assertSame(1, $this->registry->count());
    }

    /**
     * Test categoryCount method.
     */
    public function testCategoryCount(): void
    {
        $this->assertSame(0, $this->registry->categoryCount());

        $this->registry->register($this->createMockTrigger('t1', 'T1', 'Cat1'));
        $this->assertSame(1, $this->registry->categoryCount());

        $this->registry->register($this->createMockTrigger('t2', 'T2', 'Cat1'));
        $this->assertSame(1, $this->registry->categoryCount()); // Still 1 category

        $this->registry->register($this->createMockTrigger('t3', 'T3', 'Cat2'));
        $this->assertSame(2, $this->registry->categoryCount());
    }

    /**
     * Test registering replaces existing trigger with same key.
     */
    public function testRegisterReplacesExisting(): void
    {
        $trigger1 = $this->createMockTrigger('same_key', 'First', 'Cat1');
        $trigger2 = $this->createMockTrigger('same_key', 'Second', 'Cat2');

        $this->registry->register($trigger1);
        $this->registry->register($trigger2);

        $this->assertSame(1, $this->registry->count());
        $this->assertSame('Second', $this->registry->get('same_key')->getName());
    }
}
