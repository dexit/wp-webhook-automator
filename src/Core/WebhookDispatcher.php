<?php
/**
 * Webhook Dispatcher
 *
 * Handles sending webhook requests.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Core;

class WebhookDispatcher {

	/**
	 * Payload builder instance.
	 *
	 * @var PayloadBuilder
	 */
	private PayloadBuilder $payloadBuilder;

	/**
	 * Signature generator instance.
	 *
	 * @var SignatureGenerator
	 */
	private SignatureGenerator $signatureGenerator;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->payloadBuilder     = new PayloadBuilder();
		$this->signatureGenerator = new SignatureGenerator();
		$this->logger             = new Logger();
	}

	/**
	 * Dispatch a webhook synchronously.
	 *
	 * @param Webhook $webhook   The webhook to dispatch.
	 * @param array   $eventData The event data.
	 * @return void
	 */
	public function dispatch( Webhook $webhook, array $eventData ): void {
		// Build payload
		$globalData            = $this->payloadBuilder->getGlobalData();
		$globalData['webhook'] = [
			'id'   => $webhook->getId(),
			'name' => $webhook->getName(),
		];

		$mergedData = array_merge( $globalData, $eventData );

		if ( $webhook->getPayloadFormat() === 'json' ) {
			$payload     = $this->payloadBuilder->buildJson(
				$webhook->getPayloadTemplate(),
				$mergedData
			);
			$contentType = 'application/json';
		} else {
			$payload     = $this->payloadBuilder->buildFormData(
				$webhook->getPayloadTemplate(),
				$mergedData
			);
			$contentType = 'application/x-www-form-urlencoded';
		}

		// Prepare headers
		$headers = array_merge(
			[
				'Content-Type' => $contentType,
				'User-Agent'   => 'WP-Webhook-Automator/' . WWA_VERSION,
			],
			$webhook->getHeaders()
		);

		// Add signature if secret key is set
		if ( $webhook->getSecretKey() ) {
			$headers['X-Webhook-Signature'] = $this->signatureGenerator->getHeader(
				$payload,
				$webhook->getSecretKey()
			);
		}

		// Create log entry
		$logId = $this->logger->log(
			$webhook->getId(),
			[
				'trigger_type'    => $webhook->getTriggerType(),
				'trigger_data'    => $eventData,
				'endpoint_url'    => $webhook->getEndpointUrl(),
				'request_headers' => $headers,
				'request_payload' => $payload,
				'status'          => 'pending',
			]
		);

		// Send request
		$startTime = microtime( true );
		$response  = $this->sendRequest(
			$webhook->getEndpointUrl(),
			$webhook->getHttpMethod(),
			$headers,
			$payload
		);
		$duration  = (int) ( ( microtime( true ) - $startTime ) * 1000 );

		// Determine status
		$status = $this->isSuccessResponse( $response['code'] ) ? 'success' : 'failed';

		// Update log with response
		$this->logger->updateLog(
			$logId,
			[
				'response_code'    => $response['code'],
				'response_headers' => $response['headers'],
				'response_body'    => $this->truncateBody( $response['body'] ),
				'duration_ms'      => $duration,
				'status'           => $status,
				'error_message'    => $response['error'] ?? null,
			]
		);

		// Schedule retry if failed
		if ( $status === 'failed' && $webhook->getRetryCount() > 0 ) {
			$this->scheduleRetry( $logId, $webhook->getRetryDelay(), 1, $webhook->getRetryCount() );
		}

		/**
		 * Fires after a webhook is dispatched.
		 *
		 * @param Webhook $webhook  The webhook that was dispatched.
		 * @param array   $response The response data.
		 * @param string  $status   The dispatch status.
		 */
		do_action( 'wwa_webhook_dispatched', $webhook, $response, $status );
	}

	/**
	 * Dispatch a webhook asynchronously.
	 *
	 * @param Webhook $webhook   The webhook to dispatch.
	 * @param array   $eventData The event data.
	 * @return void
	 */
	public function dispatchAsync( Webhook $webhook, array $eventData ): void {
		wp_schedule_single_event(
			time(),
			'wwa_dispatch_webhook',
			[ $webhook->getId(), $eventData ]
		);
	}

	/**
	 * Retry a failed webhook.
	 *
	 * @param int $logId The log entry ID.
	 * @return bool
	 */
	public function retry( int $logId ): bool {
		$log = $this->logger->getLog( $logId );

		if ( ! $log ) {
			return false;
		}

		$repository = new WebhookRepository();
		$webhook    = $repository->find( (int) $log['webhook_id'] );

		if ( ! $webhook ) {
			return false;
		}

		$attemptNumber = ( (int) $log['attempt_number'] ) + 1;
		$payload       = $log['request_payload'];
		$headers       = json_decode( $log['request_headers'], true ) ?? [];

		// Update signature for retry
		if ( $webhook->getSecretKey() ) {
			$headers['X-Webhook-Signature'] = $this->signatureGenerator->getHeader(
				$payload,
				$webhook->getSecretKey()
			);
		}

		// Send request
		$startTime = microtime( true );
		$response  = $this->sendRequest(
			$webhook->getEndpointUrl(),
			$webhook->getHttpMethod(),
			$headers,
			$payload
		);
		$duration  = (int) ( ( microtime( true ) - $startTime ) * 1000 );

		$status = $this->isSuccessResponse( $response['code'] ) ? 'success' : 'failed';

		// Update log
		$this->logger->updateLog(
			$logId,
			[
				'response_code'    => $response['code'],
				'response_headers' => $response['headers'],
				'response_body'    => $this->truncateBody( $response['body'] ),
				'duration_ms'      => $duration,
				'status'           => $status,
				'error_message'    => $response['error'] ?? null,
				'attempt_number'   => $attemptNumber,
			]
		);

		// Schedule another retry if still failing
		if ( $status === 'failed' && $attemptNumber < $webhook->getRetryCount() ) {
			$this->scheduleRetry( $logId, $webhook->getRetryDelay(), $attemptNumber, $webhook->getRetryCount() );
		}

		return $status === 'success';
	}

	/**
	 * Send a test webhook.
	 *
	 * @param Webhook $webhook The webhook to test.
	 * @return array
	 */
	public function test( Webhook $webhook ): array {
		$testData = $this->getTestData( $webhook->getTriggerType() );

		$globalData            = $this->payloadBuilder->getGlobalData();
		$globalData['webhook'] = [
			'id'   => $webhook->getId(),
			'name' => $webhook->getName(),
		];
		$globalData['test']    = true;

		$mergedData = array_merge( $globalData, $testData );

		if ( $webhook->getPayloadFormat() === 'json' ) {
			$payload     = $this->payloadBuilder->buildJson(
				$webhook->getPayloadTemplate(),
				$mergedData
			);
			$contentType = 'application/json';
		} else {
			$payload     = $this->payloadBuilder->buildFormData(
				$webhook->getPayloadTemplate(),
				$mergedData
			);
			$contentType = 'application/x-www-form-urlencoded';
		}

		$headers = array_merge(
			[
				'Content-Type' => $contentType,
				'User-Agent'   => 'WP-Webhook-Automator/' . WWA_VERSION,
			],
			$webhook->getHeaders()
		);

		if ( $webhook->getSecretKey() ) {
			$headers['X-Webhook-Signature'] = $this->signatureGenerator->getHeader(
				$payload,
				$webhook->getSecretKey()
			);
		}

		// Log the test
		$logId = $this->logger->log(
			$webhook->getId(),
			[
				'trigger_type'    => $webhook->getTriggerType(),
				'trigger_data'    => array_merge( $testData, [ '_test' => true ] ),
				'endpoint_url'    => $webhook->getEndpointUrl(),
				'request_headers' => $headers,
				'request_payload' => $payload,
				'status'          => 'pending',
			]
		);

		$startTime = microtime( true );
		$response  = $this->sendRequest(
			$webhook->getEndpointUrl(),
			$webhook->getHttpMethod(),
			$headers,
			$payload
		);
		$duration  = (int) ( ( microtime( true ) - $startTime ) * 1000 );

		$status = $this->isSuccessResponse( $response['code'] ) ? 'success' : 'failed';

		$this->logger->updateLog(
			$logId,
			[
				'response_code'    => $response['code'],
				'response_headers' => $response['headers'],
				'response_body'    => $this->truncateBody( $response['body'] ),
				'duration_ms'      => $duration,
				'status'           => $status,
				'error_message'    => $response['error'] ?? null,
			]
		);

		return [
			'success'  => $status === 'success',
			'log_id'   => $logId,
			'response' => $response,
			'duration' => $duration,
		];
	}

	/**
	 * Send an HTTP request.
	 *
	 * @param string $url     The URL to send to.
	 * @param string $method  The HTTP method.
	 * @param array  $headers The request headers.
	 * @param string $body    The request body.
	 * @return array
	 */
	private function sendRequest( string $url, string $method, array $headers, string $body ): array {
		// Validate URL - block internal/private IPs
		if ( ! $this->isValidExternalUrl( $url ) ) {
			return [
				'code'    => 0,
				'headers' => [],
				'body'    => '',
				'error'   => __( 'Invalid or blocked URL. Internal/private IP addresses are not allowed.', 'wp-webhook-automator' ),
			];
		}

		$args = [
			'method'      => strtoupper( $method ),
			'headers'     => $headers,
			'body'        => in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ? $body : null,
			'timeout'     => (int) get_option( 'wwa_default_timeout', 30 ),
			'redirection' => 5,
			'sslverify'   => true,
		];

		/**
		 * Filter the request arguments before sending.
		 *
		 * @param array  $args The request arguments.
		 * @param string $url  The URL being requested.
		 */
		$args = apply_filters( 'wwa_request_args', $args, $url );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'code'    => 0,
				'headers' => [],
				'body'    => '',
				'error'   => $response->get_error_message(),
			];
		}

		return [
			'code'    => wp_remote_retrieve_response_code( $response ),
			'headers' => wp_remote_retrieve_headers( $response )->getAll(),
			'body'    => wp_remote_retrieve_body( $response ),
			'error'   => null,
		];
	}

	/**
	 * Check if a URL is valid and external.
	 *
	 * @param string $url The URL to validate.
	 * @return bool
	 */
	private function isValidExternalUrl( string $url ): bool {
		$parsed = wp_parse_url( $url );

		if ( ! $parsed || ! isset( $parsed['host'] ) ) {
			return false;
		}

		$host = $parsed['host'];

		// Block localhost and common local hostnames
		$blockedHosts = [ 'localhost', '127.0.0.1', '0.0.0.0', '::1' ];
		if ( in_array( $host, $blockedHosts, true ) ) {
			return false;
		}

		// Check for private IP ranges
		$ip = gethostbyname( $host );
		if ( $ip !== $host ) {
			$privateRanges = [
				'10.0.0.0/8',
				'172.16.0.0/12',
				'192.168.0.0/16',
				'127.0.0.0/8',
				'169.254.0.0/16',
				'0.0.0.0/8',
			];

			foreach ( $privateRanges as $range ) {
				if ( $this->ipInRange( $ip, $range ) ) {
					return false;
				}
			}
		}

		/**
		 * Filter whether a URL is valid for webhook delivery.
		 *
		 * @param bool   $valid Whether the URL is valid.
		 * @param string $url   The URL being validated.
		 */
		return apply_filters( 'wwa_is_valid_url', true, $url );
	}

	/**
	 * Check if an IP is within a CIDR range.
	 *
	 * @param string $ip    The IP address.
	 * @param string $range The CIDR range.
	 * @return bool
	 */
	private function ipInRange( string $ip, string $range ): bool {
		[$subnet, $bits] = explode( '/', $range );
		$ip              = ip2long( $ip );
		$subnet          = ip2long( $subnet );

		if ( $ip === false || $subnet === false ) {
			return false;
		}

		$mask = -1 << ( 32 - (int) $bits );

		return ( $ip & $mask ) === ( $subnet & $mask );
	}

	/**
	 * Check if a response code indicates success.
	 *
	 * @param int|null $code The response code.
	 * @return bool
	 */
	private function isSuccessResponse( ?int $code ): bool {
		return $code !== null && $code >= 200 && $code < 300;
	}

	/**
	 * Schedule a retry for a failed webhook.
	 *
	 * @param int $logId       The log entry ID.
	 * @param int $delay       The delay in seconds.
	 * @param int $attempt     The current attempt number.
	 * @param int $maxAttempts The maximum number of attempts.
	 * @return void
	 */
	private function scheduleRetry( int $logId, int $delay, int $attempt, int $maxAttempts ): void {
		if ( $attempt >= $maxAttempts ) {
			return;
		}

		wp_schedule_single_event(
			time() + $delay,
			'wwa_retry_webhook',
			[ $logId ]
		);
	}

	/**
	 * Truncate response body to prevent database bloat.
	 *
	 * @param string $body The response body.
	 * @param int    $maxLength Maximum length.
	 * @return string
	 */
	private function truncateBody( string $body, int $maxLength = 65535 ): string {
		if ( strlen( $body ) <= $maxLength ) {
			return $body;
		}

		return substr( $body, 0, $maxLength - 20 ) . '... [truncated]';
	}

	/**
	 * Dispatch a test webhook (for REST API).
	 *
	 * @param Webhook $webhook  The webhook to test.
	 * @param array   $testData Optional custom test data.
	 * @return array
	 */
	public function dispatchTest( Webhook $webhook, array $testData = [] ): array {
		if ( empty( $testData ) ) {
			$testData = $this->getTestData( $webhook->getTriggerType() );
		}

		$globalData            = $this->payloadBuilder->getGlobalData();
		$globalData['webhook'] = [
			'id'   => $webhook->getId(),
			'name' => $webhook->getName(),
		];
		$globalData['test']    = true;

		$mergedData = array_merge( $globalData, $testData );

		if ( $webhook->getPayloadFormat() === 'json' ) {
			$payload     = $this->payloadBuilder->buildJson(
				$webhook->getPayloadTemplate(),
				$mergedData
			);
			$contentType = 'application/json';
		} else {
			$payload     = $this->payloadBuilder->buildFormData(
				$webhook->getPayloadTemplate(),
				$mergedData
			);
			$contentType = 'application/x-www-form-urlencoded';
		}

		$headers = array_merge(
			[
				'Content-Type' => $contentType,
				'User-Agent'   => 'WP-Webhook-Automator/' . WWA_VERSION,
			],
			$webhook->getHeaders()
		);

		if ( $webhook->getSecretKey() ) {
			$headers['X-Webhook-Signature'] = $this->signatureGenerator->getHeader(
				$payload,
				$webhook->getSecretKey()
			);
		}

		// Log the test
		$logId = $this->logger->log(
			$webhook->getId(),
			[
				'trigger_type'    => $webhook->getTriggerType(),
				'trigger_data'    => array_merge( $testData, [ '_test' => true ] ),
				'endpoint_url'    => $webhook->getEndpointUrl(),
				'request_headers' => $headers,
				'request_payload' => $payload,
				'status'          => 'pending',
			]
		);

		$startTime = microtime( true );
		$response  = $this->sendRequest(
			$webhook->getEndpointUrl(),
			$webhook->getHttpMethod(),
			$headers,
			$payload
		);
		$duration  = (int) ( ( microtime( true ) - $startTime ) * 1000 );

		$status = $this->isSuccessResponse( $response['code'] ) ? 'success' : 'failed';

		$this->logger->updateLog(
			$logId,
			[
				'response_code'    => $response['code'],
				'response_headers' => $response['headers'],
				'response_body'    => $this->truncateBody( $response['body'] ),
				'duration_ms'      => $duration,
				'status'           => $status,
				'error_message'    => $response['error'] ?? null,
			]
		);

		return [
			'success'       => $status === 'success',
			'response_code' => $response['code'],
			'response_body' => $this->truncateBody( $response['body'], 1000 ),
			'duration_ms'   => $duration,
			'error'         => $response['error'],
			'log_id'        => $logId,
		];
	}

	/**
	 * Get test data for a trigger type.
	 *
	 * @param string $triggerType The trigger type.
	 * @return array
	 */
	private function getTestData( string $triggerType ): array {
		$currentUser = wp_get_current_user();

		return match ( true ) {
			str_starts_with( $triggerType, 'post_' ) => [
				'post' => [
					'id'             => 1,
					'title'          => 'Test Post Title',
					'content'        => 'This is test post content.',
					'excerpt'        => 'Test excerpt.',
					'status'         => 'publish',
					'type'           => 'post',
					'slug'           => 'test-post',
					'url'            => home_url( '/test-post/' ),
					'author'         => [
						'id'    => $currentUser->ID,
						'name'  => $currentUser->display_name,
						'email' => $currentUser->user_email,
					],
					'date'           => current_time( 'mysql' ),
					'modified'       => current_time( 'mysql' ),
					'categories'     => 'Uncategorized',
					'tags'           => 'test, sample',
					'featured_image' => '',
				],
			],
			str_starts_with( $triggerType, 'user_' ) => [
				'user' => [
					'id'           => $currentUser->ID,
					'login'        => $currentUser->user_login,
					'email'        => $currentUser->user_email,
					'first_name'   => $currentUser->first_name ?: 'John',
					'last_name'    => $currentUser->last_name ?: 'Doe',
					'display_name' => $currentUser->display_name,
					'role'         => implode( ', ', $currentUser->roles ),
					'registered'   => $currentUser->user_registered,
				],
			],
			str_starts_with( $triggerType, 'comment_' ) => [
				'comment' => [
					'id'           => 1,
					'content'      => 'This is a test comment.',
					'author_name'  => 'Test Commenter',
					'author_email' => 'test@example.com',
					'author_url'   => 'https://example.com',
					'date'         => current_time( 'mysql' ),
					'status'       => 'approved',
					'post'         => [
						'id'    => 1,
						'title' => 'Test Post',
					],
				],
			],
			str_starts_with( $triggerType, 'wc_order_' ) => [
				'order' => [
					'id'             => 1001,
					'number'         => '1001',
					'status'         => 'processing',
					'total'          => '99.99',
					'subtotal'       => '89.99',
					'tax'            => '5.00',
					'shipping'       => '5.00',
					'discount'       => '0.00',
					'currency'       => 'USD',
					'payment_method' => 'stripe',
					'billing'        => [
						'first_name' => 'John',
						'last_name'  => 'Doe',
						'email'      => 'john@example.com',
						'phone'      => '555-1234',
					],
					'items'          => [
						[
							'name'     => 'Test Product',
							'quantity' => 1,
							'total'    => '89.99',
						],
					],
					'date_created'   => current_time( 'mysql' ),
				],
			],
			default => [
				'test'    => true,
				'message' => 'This is a test webhook payload.',
				'trigger' => $triggerType,
			],
		};
	}
}
