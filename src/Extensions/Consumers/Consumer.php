<?php
/**
 * Consumer Entity
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\Consumers;

class Consumer {

	private int $id = 0;
	private string $name = '';
	private string $sourceUrl = '';
	private string $httpMethod = 'GET';
	private array $headers = [];
	private string $schedule = 'hourly';
	private array $actions = [];
	private bool $isActive = true;
	private ?string $lastRun = null;

	public function __construct( array $data = [] ) {
		if ( ! empty( $data ) ) {
			$this->fromArray( $data );
		}
	}

	public function getId(): int { return $this->id; }
	public function getName(): string { return $this->name; }
	public function getSourceUrl(): string { return $this->sourceUrl; }
	public function getHttpMethod(): string { return $this->httpMethod; }
	public function getHeaders(): array { return $this->headers; }
	public function getSchedule(): string { return $this->schedule; }
	public function getActions(): array { return $this->actions; }
	public function isActive(): bool { return $this->isActive; }
	public function getLastRun(): ?string { return $this->lastRun; }

	public function setId( int $id ): self { $this->id = $id; return $this; }
	public function setName( string $name ): self { $this->name = $name; return $this; }
	public function setSourceUrl( string $url ): self { $this->sourceUrl = $url; return $this; }
	public function setHttpMethod( string $method ): self { $this->httpMethod = $method; return $this; }
	public function setHeaders( array $headers ): self { $this->headers = $headers; return $this; }
	public function setSchedule( string $schedule ): self { $this->schedule = $schedule; return $this; }
	public function setActions( array $actions ): self { $this->actions = $actions; return $this; }
	public function setIsActive( bool $active ): self { $this->isActive = $active; return $this; }

	public function toArray(): array {
		return [
			'id'          => $this->id,
			'name'        => $this->name,
			'source_url'  => $this->sourceUrl,
			'http_method' => $this->httpMethod,
			'headers'     => $this->headers,
			'schedule'    => $this->schedule,
			'actions'     => $this->actions,
			'is_active'   => $this->isActive,
			'last_run'    => $this->lastRun,
		];
	}

	public function fromArray( array $data ): self {
		$this->id         = (int) ( $data['id'] ?? 0 );
		$this->name       = $data['name'] ?? '';
		$this->sourceUrl  = $data['source_url'] ?? '';
		$this->httpMethod = $data['http_method'] ?? 'GET';
		$this->headers    = $this->decodeJson( $data['headers'] ?? [] );
		$this->schedule   = $data['schedule'] ?? 'hourly';
		$this->actions    = $this->decodeJson( $data['actions'] ?? [] );
		$this->isActive   = (bool) ( $data['is_active'] ?? true );
		$this->lastRun    = $data['last_run'] ?? null;
		return $this;
	}

	private function decodeJson( mixed $value ): array {
		if ( is_array( $value ) ) return $value;
		if ( is_string( $value ) && ! empty( $value ) ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : [];
		}
		return [];
	}
}
