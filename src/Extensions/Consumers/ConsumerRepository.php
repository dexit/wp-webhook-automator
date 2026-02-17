<?php
/**
 * Consumer Repository
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\Consumers;

class ConsumerRepository {

	private \wpdb $db;
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'hookly_consumers';
	}

	public function find( int $id ): ?Consumer {
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? new Consumer( $row ) : null;
	}

	public function findAll( array $criteria = [] ): array {
		$sql  = "SELECT * FROM {$this->table}";
		$rows = $this->db->get_results( $sql, ARRAY_A );
		return array_map( fn( $row ) => new Consumer( $row ), $rows ?: [] );
	}

	public function save( Consumer $consumer ): int {
		$data = [
			'name'        => $consumer->getName(),
			'source_url'  => $consumer->getSourceUrl(),
			'http_method' => $consumer->getHttpMethod(),
			'headers'     => wp_json_encode( $consumer->getHeaders() ),
			'schedule'    => $consumer->getSchedule(),
			'actions'     => wp_json_encode( $consumer->getActions() ),
			'is_active'   => $consumer->isActive() ? 1 : 0,
		];

		if ( $consumer->getId() > 0 ) {
			$this->db->update( $this->table, $data, [ 'id' => $consumer->getId() ] );
			return $consumer->getId();
		}

		$this->db->insert( $this->table, $data );
		return (int) $this->db->insert_id;
	}

	public function delete( int $id ): bool {
		return (bool) $this->db->delete( $this->table, [ 'id' => $id ] );
	}

	public function updateLastRun( int $id ): void {
		$this->db->update( $this->table, [ 'last_run' => current_time( 'mysql' ) ], [ 'id' => $id ] );
	}
}
