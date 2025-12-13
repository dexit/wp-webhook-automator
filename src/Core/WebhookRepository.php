<?php
/**
 * Webhook Repository
 *
 * Handles database operations for webhooks.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Core;

class WebhookRepository {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $db;

    /**
     * Table name.
     *
     * @var string
     */
    private string $table;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'wwa_webhooks';
    }

    /**
     * Find a webhook by ID.
     *
     * @param int $id The webhook ID.
     * @return Webhook|null
     */
    public function find(int $id): ?Webhook {
        $row = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? new Webhook($row) : null;
    }

    /**
     * Find all webhooks matching criteria.
     *
     * @param array $criteria Filter criteria.
     * @param int   $limit    Maximum number of results.
     * @param int   $offset   Offset for pagination.
     * @return Webhook[]
     */
    public function findAll(array $criteria = [], int $limit = 0, int $offset = 0): array {
        $where = $this->buildWhere($criteria);
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC";

        if ($limit > 0) {
            $sql .= $this->db->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        $rows = $this->db->get_results($sql, ARRAY_A);

        return array_map(fn($row) => new Webhook($row), $rows ?: []);
    }

    /**
     * Find webhooks by trigger type.
     *
     * @param string $triggerType The trigger type.
     * @return Webhook[]
     */
    public function findByTrigger(string $triggerType): array {
        return $this->findAll(['trigger_type' => $triggerType]);
    }

    /**
     * Find all active webhooks.
     *
     * @return Webhook[]
     */
    public function findActive(): array {
        return $this->findAll(['is_active' => 1]);
    }

    /**
     * Find active webhooks by trigger type.
     *
     * @param string $triggerType The trigger type.
     * @return Webhook[]
     */
    public function findActiveByTrigger(string $triggerType): array {
        return $this->findAll([
            'trigger_type' => $triggerType,
            'is_active'    => 1,
        ]);
    }

    /**
     * Save a webhook (insert or update).
     *
     * @param Webhook $webhook The webhook entity.
     * @return int The webhook ID.
     */
    public function save(Webhook $webhook): int {
        $data = [
            'name'             => $webhook->getName(),
            'description'      => $webhook->getDescription(),
            'trigger_type'     => $webhook->getTriggerType(),
            'trigger_config'   => wp_json_encode($webhook->getTriggerConfig()),
            'endpoint_url'     => $webhook->getEndpointUrl(),
            'http_method'      => $webhook->getHttpMethod(),
            'headers'          => wp_json_encode($webhook->getHeaders()),
            'payload_format'   => $webhook->getPayloadFormat(),
            'payload_template' => wp_json_encode($webhook->getPayloadTemplate()),
            'secret_key'       => $webhook->getSecretKey(),
            'is_active'        => $webhook->isActive() ? 1 : 0,
            'retry_count'      => $webhook->getRetryCount(),
            'retry_delay'      => $webhook->getRetryDelay(),
            'created_by'       => $webhook->getCreatedBy(),
        ];

        $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d'];

        if ($webhook->getId() > 0) {
            // Update existing webhook
            $this->db->update(
                $this->table,
                $data,
                ['id' => $webhook->getId()],
                $formats,
                ['%d']
            );
            return $webhook->getId();
        }

        // Insert new webhook
        $this->db->insert($this->table, $data, $formats);
        return (int) $this->db->insert_id;
    }

    /**
     * Delete a webhook.
     *
     * @param int $id The webhook ID.
     * @return bool
     */
    public function delete(int $id): bool {
        // Also delete associated logs
        $logsTable = $this->db->prefix . 'wwa_logs';
        $this->db->delete($logsTable, ['webhook_id' => $id], ['%d']);

        return (bool) $this->db->delete($this->table, ['id' => $id], ['%d']);
    }

    /**
     * Count webhooks matching criteria.
     *
     * @param array $criteria Filter criteria.
     * @return int
     */
    public function count(array $criteria = []): int {
        $where = $this->buildWhere($criteria);
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table} {$where}");
    }

    /**
     * Toggle webhook active status.
     *
     * @param int $id The webhook ID.
     * @return bool
     */
    public function toggleActive(int $id): bool {
        $webhook = $this->find($id);
        if (!$webhook) {
            return false;
        }

        return (bool) $this->db->update(
            $this->table,
            ['is_active' => $webhook->isActive() ? 0 : 1],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Duplicate a webhook.
     *
     * @param int $id The webhook ID to duplicate.
     * @return int|null The new webhook ID or null on failure.
     */
    public function duplicate(int $id): ?int {
        $webhook = $this->find($id);
        if (!$webhook) {
            return null;
        }

        $newWebhook = new Webhook($webhook->toArray());
        $newWebhook->setId(0);
        $newWebhook->setName($webhook->getName() . ' (Copy)');
        $newWebhook->setIsActive(false);

        return $this->save($newWebhook);
    }

    /**
     * Get webhooks for dropdown select.
     *
     * @return array
     */
    public function getForSelect(): array {
        $rows = $this->db->get_results(
            "SELECT id, name FROM {$this->table} ORDER BY name ASC",
            ARRAY_A
        );

        $options = [];
        foreach ($rows ?: [] as $row) {
            $options[$row['id']] = $row['name'];
        }

        return $options;
    }

    /**
     * Build WHERE clause from criteria.
     *
     * @param array $criteria Filter criteria.
     * @return string
     */
    private function buildWhere(array $criteria): string {
        if (empty($criteria)) {
            return '';
        }

        $conditions = [];

        foreach ($criteria as $field => $value) {
            $field = sanitize_key($field);

            if ($field === 'search' && !empty($value)) {
                $search = '%' . $this->db->esc_like($value) . '%';
                $conditions[] = $this->db->prepare(
                    "(name LIKE %s OR description LIKE %s)",
                    $search,
                    $search
                );
                continue;
            }

            if (is_int($value) || is_bool($value)) {
                $conditions[] = $this->db->prepare("{$field} = %d", (int) $value);
            } elseif (is_null($value)) {
                $conditions[] = "{$field} IS NULL";
            } else {
                $conditions[] = $this->db->prepare("{$field} = %s", $value);
            }
        }

        return empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
    }
}
