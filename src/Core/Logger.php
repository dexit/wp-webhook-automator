<?php
/**
 * Webhook Logger
 *
 * Handles logging of webhook requests and responses.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Core;

class Logger {

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
        $this->table = $wpdb->prefix . 'wwa_logs';
    }

    /**
     * Create a log entry.
     *
     * @param int   $webhookId The webhook ID.
     * @param array $data      Log data.
     * @return int The log entry ID.
     */
    public function log(int $webhookId, array $data): int {
        $insertData = [
            'webhook_id'       => $webhookId,
            'trigger_type'     => $data['trigger_type'] ?? '',
            'trigger_data'     => wp_json_encode($data['trigger_data'] ?? []),
            'endpoint_url'     => $data['endpoint_url'] ?? '',
            'request_headers'  => wp_json_encode($data['request_headers'] ?? []),
            'request_payload'  => $data['request_payload'] ?? '',
            'response_code'    => $data['response_code'] ?? null,
            'response_headers' => wp_json_encode($data['response_headers'] ?? []),
            'response_body'    => $data['response_body'] ?? '',
            'duration_ms'      => $data['duration_ms'] ?? null,
            'status'           => $data['status'] ?? 'pending',
            'error_message'    => $data['error_message'] ?? null,
            'attempt_number'   => $data['attempt_number'] ?? 1,
        ];

        $this->db->insert($this->table, $insertData);
        return (int) $this->db->insert_id;
    }

    /**
     * Update an existing log entry.
     *
     * @param int   $logId The log entry ID.
     * @param array $data  Data to update.
     * @return void
     */
    public function updateLog(int $logId, array $data): void {
        $updateData = [];
        $formats = [];

        $allowedFields = [
            'response_code'    => '%d',
            'response_headers' => '%s',
            'response_body'    => '%s',
            'duration_ms'      => '%d',
            'status'           => '%s',
            'error_message'    => '%s',
            'attempt_number'   => '%d',
        ];

        foreach ($allowedFields as $field => $format) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if ($field === 'response_headers' && is_array($value)) {
                    $value = wp_json_encode($value);
                }
                $updateData[$field] = $value;
                $formats[] = $format;
            }
        }

        if (!empty($updateData)) {
            $this->db->update($this->table, $updateData, ['id' => $logId], $formats, ['%d']);
        }
    }

    /**
     * Get log entries with optional filtering.
     *
     * @param array $criteria Filter criteria.
     * @param int   $limit    Maximum number of results.
     * @param int   $offset   Offset for pagination.
     * @return array
     */
    public function getLogs(array $criteria = [], int $limit = 100, int $offset = 0): array {
        $where = $this->buildWhere($criteria);

        $sql = $this->db->prepare(
            "SELECT l.*, w.name as webhook_name
             FROM {$this->table} l
             LEFT JOIN {$this->db->prefix}wwa_webhooks w ON l.webhook_id = w.id
             {$where}
             ORDER BY l.created_at DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        return $this->db->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Get a single log entry.
     *
     * @param int $id The log entry ID.
     * @return array|null
     */
    public function getLog(int $id): ?array {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT l.*, w.name as webhook_name
                 FROM {$this->table} l
                 LEFT JOIN {$this->db->prefix}wwa_webhooks w ON l.webhook_id = w.id
                 WHERE l.id = %d",
                $id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Clean up old log entries.
     *
     * @param int $keepDays Number of days to keep.
     * @return int Number of deleted entries.
     */
    public function cleanup(int $keepDays = 30): int {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$keepDays} days"));

        return (int) $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->table} WHERE created_at < %s",
                $cutoff
            )
        );
    }

    /**
     * Count log entries matching criteria.
     *
     * @param array $criteria Filter criteria.
     * @return int
     */
    public function count(array $criteria = []): int {
        $where = $this->buildWhere($criteria);
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table} l {$where}");
    }

    /**
     * Get log statistics.
     *
     * @return array
     */
    public function getStats(): array {
        $today = gmdate('Y-m-d 00:00:00');

        return [
            'total'         => $this->count(),
            'today'         => $this->count(['date_from' => $today]),
            'success_today' => (int) $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE status = 'success' AND created_at >= %s",
                    $today
                )
            ),
            'failed_today'  => (int) $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE status = 'failed' AND created_at >= %s",
                    $today
                )
            ),
            'pending_today' => (int) $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE status = 'pending' AND created_at >= %s",
                    $today
                )
            ),
            'success_rate'  => $this->calculateSuccessRate($today),
        ];
    }

    /**
     * Get statistics by webhook.
     *
     * @param int $webhookId The webhook ID.
     * @return array
     */
    public function getStatsByWebhook(int $webhookId): array {
        $today = gmdate('Y-m-d 00:00:00');

        return [
            'total'   => $this->count(['webhook_id' => $webhookId]),
            'success' => (int) $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE webhook_id = %d AND status = 'success'",
                    $webhookId
                )
            ),
            'failed'  => (int) $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE webhook_id = %d AND status = 'failed'",
                    $webhookId
                )
            ),
            'last_run' => $this->db->get_var(
                $this->db->prepare(
                    "SELECT created_at FROM {$this->table} WHERE webhook_id = %d ORDER BY created_at DESC LIMIT 1",
                    $webhookId
                )
            ),
            'avg_duration' => (int) $this->db->get_var(
                $this->db->prepare(
                    "SELECT AVG(duration_ms) FROM {$this->table} WHERE webhook_id = %d AND duration_ms IS NOT NULL",
                    $webhookId
                )
            ),
        ];
    }

    /**
     * Calculate success rate since a given date.
     *
     * @param string $since Date string.
     * @return float
     */
    private function calculateSuccessRate(string $since): float {
        $total = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE created_at >= %s AND status != 'pending'",
                $since
            )
        );

        if ($total === 0) {
            return 100.0;
        }

        $success = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE status = 'success' AND created_at >= %s",
                $since
            )
        );

        return round(($success / $total) * 100, 1);
    }

    /**
     * Delete a single log entry.
     *
     * @param int $id The log entry ID.
     * @return bool
     */
    public function deleteLog(int $id): bool {
        return (bool) $this->db->delete($this->table, ['id' => $id], ['%d']);
    }

    /**
     * Delete logs by webhook ID.
     *
     * @param int $webhookId The webhook ID.
     * @return int Number of deleted entries.
     */
    public function deleteByWebhookId(int $webhookId): int {
        return (int) $this->db->delete($this->table, ['webhook_id' => $webhookId], ['%d']);
    }

    /**
     * Clear all logs.
     *
     * @return int Number of deleted entries.
     */
    public function clearAll(): int {
        return (int) $this->db->query("TRUNCATE TABLE {$this->table}");
    }

    /**
     * Get recent logs for dashboard.
     *
     * @param int $limit Number of entries to return.
     * @return array
     */
    public function getRecentLogs(int $limit = 10): array {
        return $this->getLogs([], $limit, 0);
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
            switch ($field) {
                case 'webhook_id':
                    $conditions[] = $this->db->prepare('l.webhook_id = %d', $value);
                    break;
                case 'status':
                    $conditions[] = $this->db->prepare('l.status = %s', $value);
                    break;
                case 'trigger_type':
                    $conditions[] = $this->db->prepare('l.trigger_type = %s', $value);
                    break;
                case 'date_from':
                    $conditions[] = $this->db->prepare('l.created_at >= %s', $value);
                    break;
                case 'date_to':
                    $conditions[] = $this->db->prepare('l.created_at <= %s', $value);
                    break;
                case 'search':
                    $search = '%' . $this->db->esc_like($value) . '%';
                    $conditions[] = $this->db->prepare(
                        '(l.endpoint_url LIKE %s OR l.error_message LIKE %s OR w.name LIKE %s)',
                        $search,
                        $search,
                        $search
                    );
                    break;
            }
        }

        return empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
    }
}
