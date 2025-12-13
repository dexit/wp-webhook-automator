<?php
/**
 * Webhook Entity
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Core;

class Webhook {

	/**
	 * Webhook ID.
	 *
	 * @var int
	 */
	private int $id = 0;

	/**
	 * Webhook name.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * Webhook description.
	 *
	 * @var string
	 */
	private string $description = '';

	/**
	 * Trigger type.
	 *
	 * @var string
	 */
	private string $triggerType = '';

	/**
	 * Trigger configuration.
	 *
	 * @var array
	 */
	private array $triggerConfig = [];

	/**
	 * Endpoint URL.
	 *
	 * @var string
	 */
	private string $endpointUrl = '';

	/**
	 * HTTP method.
	 *
	 * @var string
	 */
	private string $httpMethod = 'POST';

	/**
	 * Custom headers.
	 *
	 * @var array
	 */
	private array $headers = [];

	/**
	 * Payload format (json or form).
	 *
	 * @var string
	 */
	private string $payloadFormat = 'json';

	/**
	 * Payload template.
	 *
	 * @var array
	 */
	private array $payloadTemplate = [];

	/**
	 * Secret key for signing.
	 *
	 * @var string|null
	 */
	private ?string $secretKey = null;

	/**
	 * Whether webhook is active.
	 *
	 * @var bool
	 */
	private bool $isActive = true;

	/**
	 * Number of retry attempts.
	 *
	 * @var int
	 */
	private int $retryCount = 3;

	/**
	 * Delay between retries in seconds.
	 *
	 * @var int
	 */
	private int $retryDelay = 60;

	/**
	 * Created timestamp.
	 *
	 * @var string|null
	 */
	private ?string $createdAt = null;

	/**
	 * Updated timestamp.
	 *
	 * @var string|null
	 */
	private ?string $updatedAt = null;

	/**
	 * User ID who created the webhook.
	 *
	 * @var int|null
	 */
	private ?int $createdBy = null;

	/**
	 * Constructor.
	 *
	 * @param array $data Optional data to populate the entity.
	 */
	public function __construct( array $data = [] ) {
		if ( ! empty( $data ) ) {
			$this->fromArray( $data );
		}
	}

	/**
	 * Get ID.
	 *
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * Get trigger type.
	 *
	 * @return string
	 */
	public function getTriggerType(): string {
		return $this->triggerType;
	}

	/**
	 * Get trigger configuration.
	 *
	 * @return array
	 */
	public function getTriggerConfig(): array {
		return $this->triggerConfig;
	}

	/**
	 * Get endpoint URL.
	 *
	 * @return string
	 */
	public function getEndpointUrl(): string {
		return $this->endpointUrl;
	}

	/**
	 * Get HTTP method.
	 *
	 * @return string
	 */
	public function getHttpMethod(): string {
		return $this->httpMethod;
	}

	/**
	 * Get custom headers.
	 *
	 * @return array
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Get payload format.
	 *
	 * @return string
	 */
	public function getPayloadFormat(): string {
		return $this->payloadFormat;
	}

	/**
	 * Get payload template.
	 *
	 * @return array
	 */
	public function getPayloadTemplate(): array {
		return $this->payloadTemplate;
	}

	/**
	 * Get secret key.
	 *
	 * @return string|null
	 */
	public function getSecretKey(): ?string {
		return $this->secretKey;
	}

	/**
	 * Check if webhook is active.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return $this->isActive;
	}

	/**
	 * Get retry count.
	 *
	 * @return int
	 */
	public function getRetryCount(): int {
		return $this->retryCount;
	}

	/**
	 * Get retry delay.
	 *
	 * @return int
	 */
	public function getRetryDelay(): int {
		return $this->retryDelay;
	}

	/**
	 * Get created timestamp.
	 *
	 * @return string|null
	 */
	public function getCreatedAt(): ?string {
		return $this->createdAt;
	}

	/**
	 * Get updated timestamp.
	 *
	 * @return string|null
	 */
	public function getUpdatedAt(): ?string {
		return $this->updatedAt;
	}

	/**
	 * Get creator user ID.
	 *
	 * @return int|null
	 */
	public function getCreatedBy(): ?int {
		return $this->createdBy;
	}

	/**
	 * Set ID.
	 *
	 * @param int $id The webhook ID.
	 * @return self
	 */
	public function setId( int $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set name.
	 *
	 * @param string $name The webhook name.
	 * @return self
	 */
	public function setName( string $name ): self {
		$this->name = $name;
		return $this;
	}

	/**
	 * Set description.
	 *
	 * @param string $description The webhook description.
	 * @return self
	 */
	public function setDescription( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Set trigger type.
	 *
	 * @param string $triggerType The trigger type.
	 * @return self
	 */
	public function setTriggerType( string $triggerType ): self {
		$this->triggerType = $triggerType;
		return $this;
	}

	/**
	 * Set trigger configuration.
	 *
	 * @param array $triggerConfig The trigger configuration.
	 * @return self
	 */
	public function setTriggerConfig( array $triggerConfig ): self {
		$this->triggerConfig = $triggerConfig;
		return $this;
	}

	/**
	 * Set endpoint URL.
	 *
	 * @param string $endpointUrl The endpoint URL.
	 * @return self
	 */
	public function setEndpointUrl( string $endpointUrl ): self {
		$this->endpointUrl = $endpointUrl;
		return $this;
	}

	/**
	 * Set HTTP method.
	 *
	 * @param string $httpMethod The HTTP method.
	 * @return self
	 */
	public function setHttpMethod( string $httpMethod ): self {
		$this->httpMethod = $httpMethod;
		return $this;
	}

	/**
	 * Set custom headers.
	 *
	 * @param array $headers The custom headers.
	 * @return self
	 */
	public function setHeaders( array $headers ): self {
		$this->headers = $headers;
		return $this;
	}

	/**
	 * Set payload format.
	 *
	 * @param string $payloadFormat The payload format.
	 * @return self
	 */
	public function setPayloadFormat( string $payloadFormat ): self {
		$this->payloadFormat = $payloadFormat;
		return $this;
	}

	/**
	 * Set payload template.
	 *
	 * @param array $payloadTemplate The payload template.
	 * @return self
	 */
	public function setPayloadTemplate( array $payloadTemplate ): self {
		$this->payloadTemplate = $payloadTemplate;
		return $this;
	}

	/**
	 * Set secret key.
	 *
	 * @param string|null $secretKey The secret key.
	 * @return self
	 */
	public function setSecretKey( ?string $secretKey ): self {
		$this->secretKey = $secretKey;
		return $this;
	}

	/**
	 * Set active status.
	 *
	 * @param bool $isActive Whether webhook is active.
	 * @return self
	 */
	public function setIsActive( bool $isActive ): self {
		$this->isActive = $isActive;
		return $this;
	}

	/**
	 * Set retry count.
	 *
	 * @param int $retryCount The retry count.
	 * @return self
	 */
	public function setRetryCount( int $retryCount ): self {
		$this->retryCount = $retryCount;
		return $this;
	}

	/**
	 * Set retry delay.
	 *
	 * @param int $retryDelay The retry delay in seconds.
	 * @return self
	 */
	public function setRetryDelay( int $retryDelay ): self {
		$this->retryDelay = $retryDelay;
		return $this;
	}

	/**
	 * Set creator user ID.
	 *
	 * @param int|null $createdBy The user ID.
	 * @return self
	 */
	public function setCreatedBy( ?int $createdBy ): self {
		$this->createdBy = $createdBy;
		return $this;
	}

	/**
	 * Convert entity to array.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return [
			'id'               => $this->id,
			'name'             => $this->name,
			'description'      => $this->description,
			'trigger_type'     => $this->triggerType,
			'trigger_config'   => $this->triggerConfig,
			'endpoint_url'     => $this->endpointUrl,
			'http_method'      => $this->httpMethod,
			'headers'          => $this->headers,
			'payload_format'   => $this->payloadFormat,
			'payload_template' => $this->payloadTemplate,
			'secret_key'       => $this->secretKey,
			'is_active'        => $this->isActive,
			'retry_count'      => $this->retryCount,
			'retry_delay'      => $this->retryDelay,
			'created_at'       => $this->createdAt,
			'updated_at'       => $this->updatedAt,
			'created_by'       => $this->createdBy,
		];
	}

	/**
	 * Populate entity from array.
	 *
	 * @param array $data The data array.
	 * @return self
	 */
	public function fromArray( array $data ): self {
		$this->id              = (int) ( $data['id'] ?? 0 );
		$this->name            = $data['name'] ?? '';
		$this->description     = $data['description'] ?? '';
		$this->triggerType     = $data['trigger_type'] ?? '';
		$this->triggerConfig   = $this->decodeJson( $data['trigger_config'] ?? [] );
		$this->endpointUrl     = $data['endpoint_url'] ?? '';
		$this->httpMethod      = $data['http_method'] ?? 'POST';
		$this->headers         = $this->decodeJson( $data['headers'] ?? [] );
		$this->payloadFormat   = $data['payload_format'] ?? 'json';
		$this->payloadTemplate = $this->decodeJson( $data['payload_template'] ?? [] );
		$this->secretKey       = $data['secret_key'] ?? null;
		$this->isActive        = (bool) ( $data['is_active'] ?? true );
		$this->retryCount      = (int) ( $data['retry_count'] ?? 3 );
		$this->retryDelay      = (int) ( $data['retry_delay'] ?? 60 );
		$this->createdAt       = $data['created_at'] ?? null;
		$this->updatedAt       = $data['updated_at'] ?? null;
		$this->createdBy       = isset( $data['created_by'] ) ? (int) $data['created_by'] : null;

		return $this;
	}

	/**
	 * Decode JSON string to array.
	 *
	 * @param mixed $value The value to decode.
	 * @return array
	 */
	private function decodeJson( mixed $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && ! empty( $value ) ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : [];
		}

		return [];
	}
}
