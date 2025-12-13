<?php
/**
 * Trigger Registry
 *
 * Manages registration and retrieval of webhook triggers.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class TriggerRegistry {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Registered triggers.
     *
     * @var array<string, TriggerInterface>
     */
    private array $triggers = [];

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton.
     */
    private function __construct() {}

    /**
     * Register a trigger.
     *
     * @param TriggerInterface $trigger The trigger to register.
     * @return void
     */
    public function register(TriggerInterface $trigger): void {
        $this->triggers[$trigger->getKey()] = $trigger;
    }

    /**
     * Unregister a trigger.
     *
     * @param string $key The trigger key.
     * @return void
     */
    public function unregister(string $key): void {
        unset($this->triggers[$key]);
    }

    /**
     * Get a trigger by key.
     *
     * @param string $key The trigger key.
     * @return TriggerInterface|null
     */
    public function get(string $key): ?TriggerInterface {
        return $this->triggers[$key] ?? null;
    }

    /**
     * Check if a trigger exists.
     *
     * @param string $key The trigger key.
     * @return bool
     */
    public function has(string $key): bool {
        return isset($this->triggers[$key]);
    }

    /**
     * Get all registered triggers.
     *
     * @return array<string, TriggerInterface>
     */
    public function getAll(): array {
        return $this->triggers;
    }

    /**
     * Get triggers by category.
     *
     * @param string $category The category name.
     * @return array<string, TriggerInterface>
     */
    public function getByCategory(string $category): array {
        return array_filter(
            $this->triggers,
            fn($trigger) => $trigger->getCategory() === $category
        );
    }

    /**
     * Get all categories with their triggers.
     *
     * @return array<string, TriggerInterface[]>
     */
    public function getCategories(): array {
        $categories = [];

        foreach ($this->triggers as $trigger) {
            $category = $trigger->getCategory();
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $trigger;
        }

        // Sort categories alphabetically
        ksort($categories);

        return $categories;
    }

    /**
     * Get triggers formatted for a select dropdown.
     *
     * @return array
     */
    public function getForSelect(): array {
        $options = [];

        foreach ($this->getCategories() as $category => $triggers) {
            $options[$category] = [];
            foreach ($triggers as $trigger) {
                $options[$category][$trigger->getKey()] = $trigger->getName();
            }
        }

        return $options;
    }

    /**
     * Get trigger information for display.
     *
     * @return array
     */
    public function getTriggerInfo(): array {
        $info = [];

        foreach ($this->triggers as $key => $trigger) {
            $info[$key] = [
                'key'         => $trigger->getKey(),
                'name'        => $trigger->getName(),
                'description' => $trigger->getDescription(),
                'category'    => $trigger->getCategory(),
                'config'      => $trigger->getConfigFields(),
                'data'        => $trigger->getAvailableData(),
            ];
        }

        return $info;
    }

    /**
     * Get configuration fields for a trigger.
     *
     * @param string $key The trigger key.
     * @return array
     */
    public function getConfigFields(string $key): array {
        $trigger = $this->get($key);
        return $trigger ? $trigger->getConfigFields() : [];
    }

    /**
     * Get available data fields for a trigger.
     *
     * @param string $key The trigger key.
     * @return array
     */
    public function getAvailableData(string $key): array {
        $trigger = $this->get($key);
        return $trigger ? $trigger->getAvailableData() : [];
    }

    /**
     * Get trigger count.
     *
     * @return int
     */
    public function count(): int {
        return count($this->triggers);
    }

    /**
     * Get category count.
     *
     * @return int
     */
    public function categoryCount(): int {
        return count($this->getCategories());
    }
}
