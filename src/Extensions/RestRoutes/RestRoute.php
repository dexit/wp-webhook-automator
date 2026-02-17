<?php
/**
 * Rest Route Entity
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\RestRoutes;

class RestRoute {

	/**
	 * Route ID.
	 *
	 * @var int
	 */
	private int $id = 0;

	/**
	 * Route name.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * Route path.
	 *
	 * @var string
	 */
	private string $routePath = '';

	/**
	 * HTTP methods.
	 *
	 * @var array
	 */
	private array $methods = [ 'POST' ];

	/**
	 * Action type (php_code, wp_action, create_cpt, update_cpt).
	 *
	 * @var string
	 */
	private string $actionType = 'wp_action';

	/**
	 * Action configuration.
	 *
	 * @var array
	 */
	private array $actionConfig = [];

	/**
	 * Whether route is active.
	 *
	 * @var bool
	 */
	private bool $isActive = true;

	/**
	 * Whether actions should be processed asynchronously.
	 *
	 * @var bool
	 */
	private bool $isAsync = false;

	/**
	 * Secret key for validation.
	 *
	 * @var string|null
	 */
	private ?string $secretKey = null;

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
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * Get name.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get route path.
	 */
	public function getRoutePath(): string {
		return $this->routePath;
	}

	/**
	 * Get methods.
	 */
	public function getMethods(): array {
		return $this->methods;
	}

	/**
	 * Get action type.
	 */
	public function getActionType(): string {
		return $this->actionType;
	}

	/**
	 * Get action config.
	 */
	public function getActionConfig(): array {
		return $this->actionConfig;
	}

	/**
	 * Is active.
	 */
	public function isActive(): bool {
		return $this->isActive;
	}

	/**
	 * Is async.
	 */
	public function isAsync(): bool {
		return $this->isAsync;
	}

	/**
	 * Get secret key.
	 */
	public function getSecretKey(): ?string {
		return $this->secretKey;
	}

	/**
	 * Set ID.
	 */
	public function setId( int $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set name.
	 */
	public function setName( string $name ): self {
		$this->name = $name;
		return $this;
	}

	/**
	 * Set route path.
	 */
	public function setRoutePath( string $routePath ): self {
		$this->routePath = $routePath;
		return $this;
	}

	/**
	 * Set methods.
	 */
	public function setMethods( array $methods ): self {
		$this->methods = $methods;
		return $this;
	}

	/**
	 * Set action type.
	 */
	public function setActionType( string $actionType ): self {
		$this->actionType = $actionType;
		return $this;
	}

	/**
	 * Set action config.
	 */
	public function setActionConfig( array $actionConfig ): self {
		$this->actionConfig = $actionConfig;
		return $this;
	}

	/**
	 * Set is active.
	 */
	public function setIsActive( bool $isActive ): self {
		$this->isActive = $isActive;
		return $this;
	}

	/**
	 * Set is async.
	 */
	public function setIsAsync( bool $isAsync ): self {
		$this->isAsync = $isAsync;
		return $this;
	}

	/**
	 * Set secret key.
	 */
	public function setSecretKey( ?string $secretKey ): self {
		$this->secretKey = $secretKey;
		return $this;
	}

	/**
	 * Convert entity to array.
	 */
	public function toArray(): array {
		return [
			'id'            => $this->id,
			'name'          => $this->name,
			'route_path'    => $this->routePath,
			'methods'       => $this->methods,
			'action_type'   => $this->actionType,
			'action_config' => $this->actionConfig,
			'is_active'     => $this->isActive,
			'is_async'      => $this->isAsync,
			'secret_key'    => $this->secretKey,
			'created_at'    => $this->createdAt,
			'updated_at'    => $this->updatedAt,
		];
	}

	/**
	 * Populate entity from array.
	 */
	public function fromArray( array $data ): self {
		$this->id           = (int) ( $data['id'] ?? 0 );
		$this->name         = $data['name'] ?? '';
		$this->routePath    = $data['route_path'] ?? '';
		$this->methods      = $this->decodeJson( $data['methods'] ?? [ 'POST' ] );
		$this->actionType   = $data['action_type'] ?? 'wp_action';
		$this->actionConfig = $this->decodeJson( $data['action_config'] ?? [] );
		$this->isActive     = (bool) ( $data['is_active'] ?? true );
		$this->isAsync      = (bool) ( $data['is_async'] ?? false );
		$this->secretKey    = $data['secret_key'] ?? null;
		$this->createdAt    = $data['created_at'] ?? null;
		$this->updatedAt    = $data['updated_at'] ?? null;

		return $this;
	}

	/**
	 * Decode JSON string to array.
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
