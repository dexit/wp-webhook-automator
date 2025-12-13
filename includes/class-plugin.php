<?php
/**
 * Main Plugin Class
 *
 * @package WP_Webhook_Automator
 */

if (!defined('ABSPATH')) {
    exit;
}

use WWA\Admin\Admin;
use WWA\Core\WebhookRepository;
use WWA\Core\WebhookDispatcher;
use WWA\Core\Logger;
use WWA\Triggers\TriggerRegistry;
use WWA\Rest\WebhooksController;
use WWA\Rest\LogsController;
use WWA\Rest\TriggersController;

class WWA_Plugin {

    /**
     * Plugin instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Webhook repository.
     *
     * @var WebhookRepository|null
     */
    private ?WebhookRepository $repository = null;

    /**
     * Webhook dispatcher.
     *
     * @var WebhookDispatcher|null
     */
    private ?WebhookDispatcher $dispatcher = null;

    /**
     * Trigger registry.
     *
     * @var TriggerRegistry|null
     */
    private ?TriggerRegistry $triggerRegistry = null;

    /**
     * Get plugin instance.
     *
     * @return self
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->loadDependencies();
        $this->registerTriggers();
        $this->registerHooks();
        $this->initAdmin();
    }

    /**
     * Load dependencies.
     *
     * @return void
     */
    private function loadDependencies(): void {
        // Core dependencies are loaded via Composer autoload
        // Initialize core services lazily to avoid errors if classes don't exist yet
    }

    /**
     * Get the webhook repository.
     *
     * @return WebhookRepository
     */
    public function getRepository(): WebhookRepository {
        if ($this->repository === null) {
            $this->repository = new WebhookRepository();
        }
        return $this->repository;
    }

    /**
     * Get the webhook dispatcher.
     *
     * @return WebhookDispatcher
     */
    public function getDispatcher(): WebhookDispatcher {
        if ($this->dispatcher === null) {
            $this->dispatcher = new WebhookDispatcher();
        }
        return $this->dispatcher;
    }

    /**
     * Get the trigger registry.
     *
     * @return TriggerRegistry
     */
    public function getTriggerRegistry(): TriggerRegistry {
        if ($this->triggerRegistry === null) {
            $this->triggerRegistry = TriggerRegistry::getInstance();
        }
        return $this->triggerRegistry;
    }

    /**
     * Register triggers.
     *
     * @return void
     */
    private function registerTriggers(): void {
        // Check if trigger classes exist (via Composer autoload)
        if (!class_exists('WWA\Triggers\TriggerRegistry')) {
            return;
        }

        $registry = $this->getTriggerRegistry();

        // Post triggers
        $postTriggers = [
            'WWA\Triggers\PostPublishedTrigger',
            'WWA\Triggers\PostUpdatedTrigger',
            'WWA\Triggers\PostDeletedTrigger',
            'WWA\Triggers\PostTrashedTrigger',
        ];

        foreach ($postTriggers as $triggerClass) {
            if (class_exists($triggerClass)) {
                $registry->register(new $triggerClass());
            }
        }

        // User triggers
        $userTriggers = [
            'WWA\Triggers\UserRegisteredTrigger',
            'WWA\Triggers\UserUpdatedTrigger',
            'WWA\Triggers\UserDeletedTrigger',
            'WWA\Triggers\UserLoginTrigger',
            'WWA\Triggers\UserLogoutTrigger',
        ];

        foreach ($userTriggers as $triggerClass) {
            if (class_exists($triggerClass)) {
                $registry->register(new $triggerClass());
            }
        }

        // Comment triggers
        $commentTriggers = [
            'WWA\Triggers\CommentCreatedTrigger',
            'WWA\Triggers\CommentApprovedTrigger',
            'WWA\Triggers\CommentSpamTrigger',
            'WWA\Triggers\CommentReplyTrigger',
        ];

        foreach ($commentTriggers as $triggerClass) {
            if (class_exists($triggerClass)) {
                $registry->register(new $triggerClass());
            }
        }

        // Allow third-party triggers
        do_action('wwa_register_triggers', $registry);
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    private function registerHooks(): void {
        // Register all active triggers
        if (class_exists('WWA\Triggers\TriggerRegistry')) {
            foreach ($this->getTriggerRegistry()->getAll() as $trigger) {
                $trigger->register([$this, 'handleTrigger']);
            }
        }

        // Cron events
        add_action('wwa_dispatch_webhook', [$this, 'handleAsyncDispatch'], 10, 2);
        add_action('wwa_retry_webhook', [$this, 'handleRetry']);
        add_action('wwa_cleanup_logs', [$this, 'cleanupLogs']);

        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function registerRestRoutes(): void {
        // Webhooks endpoints
        if (class_exists('WWA\Rest\WebhooksController')) {
            $webhooks_controller = new WebhooksController();
            $webhooks_controller->register_routes();
        }

        // Logs endpoints
        if (class_exists('WWA\Rest\LogsController')) {
            $logs_controller = new LogsController();
            $logs_controller->register_routes();
        }

        // Triggers endpoints
        if (class_exists('WWA\Rest\TriggersController')) {
            $triggers_controller = new TriggersController();
            $triggers_controller->register_routes();
        }
    }

    /**
     * Initialize admin interface.
     *
     * @return void
     */
    private function initAdmin(): void {
        if (is_admin() && class_exists('WWA\Admin\Admin')) {
            new Admin();
        }
    }

    /**
     * Handle a trigger event.
     *
     * @param string $triggerKey The trigger key.
     * @param array  $eventData  The event data.
     * @return void
     */
    public function handleTrigger(string $triggerKey, array $eventData): void {
        $webhooks = $this->getRepository()->findActiveByTrigger($triggerKey);
        $trigger = $this->getTriggerRegistry()->get($triggerKey);

        foreach ($webhooks as $webhook) {
            // Check if event matches webhook config
            if ($trigger && !$trigger->matchesConfig($eventData, $webhook->getTriggerConfig())) {
                continue;
            }

            // Dispatch (async if enabled)
            if (get_option('wwa_enable_async', true)) {
                $this->getDispatcher()->dispatchAsync($webhook, $eventData);
            } else {
                $this->getDispatcher()->dispatch($webhook, $eventData);
            }
        }
    }

    /**
     * Handle async webhook dispatch.
     *
     * @param int   $webhookId The webhook ID.
     * @param array $eventData The event data.
     * @return void
     */
    public function handleAsyncDispatch(int $webhookId, array $eventData): void {
        $webhook = $this->getRepository()->find($webhookId);
        if ($webhook && $webhook->isActive()) {
            $this->getDispatcher()->dispatch($webhook, $eventData);
        }
    }

    /**
     * Handle webhook retry.
     *
     * @param int $logId The log entry ID.
     * @return void
     */
    public function handleRetry(int $logId): void {
        $this->getDispatcher()->retry($logId);
    }

    /**
     * Clean up old logs.
     *
     * @return void
     */
    public function cleanupLogs(): void {
        $days = (int) get_option('wwa_log_retention_days', 30);
        $logger = new Logger();
        $logger->cleanup($days);
    }
}
