<?php
/**
 * Main Plugin Class
 *
 * @package WP_Webhook_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Hookly\Admin\Admin;
use Hookly\Core\WebhookRepository;
use Hookly\Core\WebhookDispatcher;
use Hookly\Core\Logger;
use Hookly\Triggers\TriggerRegistry;
use Hookly\Rest\WebhooksController;
use Hookly\Rest\LogsController;
use Hookly\Rest\TriggersController;
use Hookly\Extensions\RestRoutes\RestRouteManager;
use Hookly\Extensions\RestRoutes\RestRoutesController;
use Hookly\Extensions\Consumers\ConsumerManager;
use Hookly\Extensions\Consumers\ConsumersController;

class Hookly_Plugin {

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
		if ( self::$instance === null ) {
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
		$this->initExtensions();
		$this->initAdmin();
	}

	/**
	 * Initialize extensions.
	 */
	private function initExtensions(): void {
		if ( class_exists( 'Hookly\Extensions\RestRoutes\RestRouteManager' ) ) {
			( new RestRouteManager() )->init();
		}
		if ( class_exists( 'Hookly\Extensions\Consumers\ConsumerManager' ) ) {
			( new ConsumerManager() )->init();
		}
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
		if ( $this->repository === null ) {
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
		if ( $this->dispatcher === null ) {
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
		if ( $this->triggerRegistry === null ) {
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
		if ( ! class_exists( 'Hookly\Triggers\TriggerRegistry' ) ) {
			return;
		}

		$registry = $this->getTriggerRegistry();

		// Post triggers
		$postTriggers = [
			'Hookly\Triggers\PostPublishedTrigger',
			'Hookly\Triggers\PostUpdatedTrigger',
			'Hookly\Triggers\PostDeletedTrigger',
			'Hookly\Triggers\PostTrashedTrigger',
		];

		foreach ( $postTriggers as $triggerClass ) {
			if ( class_exists( $triggerClass ) ) {
				$registry->register( new $triggerClass() );
			}
		}

		// User triggers
		$userTriggers = [
			'Hookly\Triggers\UserRegisteredTrigger',
			'Hookly\Triggers\UserUpdatedTrigger',
			'Hookly\Triggers\UserDeletedTrigger',
			'Hookly\Triggers\UserLoginTrigger',
			'Hookly\Triggers\UserLogoutTrigger',
		];

		foreach ( $userTriggers as $triggerClass ) {
			if ( class_exists( $triggerClass ) ) {
				$registry->register( new $triggerClass() );
			}
		}

		// Comment triggers
		$commentTriggers = [
			'Hookly\Triggers\CommentCreatedTrigger',
			'Hookly\Triggers\CommentApprovedTrigger',
			'Hookly\Triggers\CommentSpamTrigger',
			'Hookly\Triggers\CommentReplyTrigger',
		];

		foreach ( $commentTriggers as $triggerClass ) {
			if ( class_exists( $triggerClass ) ) {
				$registry->register( new $triggerClass() );
			}
		}

		// REST Route trigger
		if ( class_exists( 'Hookly\Triggers\RestRouteTrigger' ) ) {
			$registry->register( new \Hookly\Triggers\RestRouteTrigger() );
		}

		// Allow third-party triggers
		do_action( 'hookly_register_triggers', $registry );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function registerHooks(): void {
		// Register all active triggers
		if ( class_exists( 'Hookly\Triggers\TriggerRegistry' ) ) {
			foreach ( $this->getTriggerRegistry()->getAll() as $trigger ) {
				$trigger->register( [ $this, 'handleTrigger' ] );
			}
		}

		// Cron events
		add_action( 'hookly_dispatch_webhook', [ $this, 'handleAsyncDispatch' ], 10, 2 );
		add_action( 'hookly_retry_webhook', [ $this, 'handleRetry' ] );
		add_action( 'hookly_cleanup_logs', [ $this, 'cleanupLogs' ] );

		// REST API
		add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function registerRestRoutes(): void {
		// Webhooks endpoints
		if ( class_exists( 'Hookly\Rest\WebhooksController' ) ) {
			$webhooks_controller = new WebhooksController();
			$webhooks_controller->register_routes();
		}

		// Logs endpoints
		if ( class_exists( 'Hookly\Rest\LogsController' ) ) {
			$logs_controller = new LogsController();
			$logs_controller->register_routes();
		}

		// Triggers endpoints
		if ( class_exists( 'Hookly\Rest\TriggersController' ) ) {
			$triggers_controller = new TriggersController();
			$triggers_controller->register_routes();
		}

		// Rest Routes extension endpoints
		if ( class_exists( 'Hookly\Extensions\RestRoutes\RestRoutesController' ) ) {
			$rest_routes_controller = new RestRoutesController();
			$rest_routes_controller->register_routes();
		}

		// Consumers extension endpoints
		if ( class_exists( 'Hookly\Extensions\Consumers\ConsumersController' ) ) {
			$consumers_controller = new ConsumersController();
			$consumers_controller->register_routes();
		}
	}

	/**
	 * Initialize admin interface.
	 *
	 * @return void
	 */
	private function initAdmin(): void {
		if ( is_admin() && class_exists( 'Hookly\Admin\Admin' ) ) {
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
	public function handleTrigger( string $triggerKey, array $eventData ): void {
		$webhooks = $this->getRepository()->findActiveByTrigger( $triggerKey );
		$trigger  = $this->getTriggerRegistry()->get( $triggerKey );

		foreach ( $webhooks as $webhook ) {
			// Check if event matches webhook config
			if ( $trigger && ! $trigger->matchesConfig( $eventData, $webhook->getTriggerConfig() ) ) {
				continue;
			}

			// Dispatch (async if enabled)
			if ( get_option( 'hookly_enable_async', true ) ) {
				$this->getDispatcher()->dispatchAsync( $webhook, $eventData );
			} else {
				$this->getDispatcher()->dispatch( $webhook, $eventData );
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
	public function handleAsyncDispatch( int $webhookId, array $eventData ): void {
		$webhook = $this->getRepository()->find( $webhookId );
		if ( $webhook && $webhook->isActive() ) {
			$this->getDispatcher()->dispatch( $webhook, $eventData );
		}
	}

	/**
	 * Handle webhook retry.
	 *
	 * @param int $logId The log entry ID.
	 * @return void
	 */
	public function handleRetry( int $logId ): void {
		$this->getDispatcher()->retry( $logId );
	}

	/**
	 * Clean up old logs.
	 *
	 * @return void
	 */
	public function cleanupLogs(): void {
		$days   = (int) get_option( 'hookly_log_retention_days', 30 );
		$logger = new Logger();
		$logger->cleanup( $days );
	}
}
