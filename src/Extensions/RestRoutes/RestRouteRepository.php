<?php
/**
 * Rest Route Repository
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\RestRoutes;

class RestRouteRepository {

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
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'hookly_rest_routes';
	}

	/**
	 * Find a route by ID.
	 */
	public function find( int $id ): ?RestRoute {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? new RestRoute( $row ) : null;
	}

	/**
	 * Find a route by path.
	 */
	public function findByPath( string $path ): ?RestRoute {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE route_path = %s AND is_active = 1", $path ),
			ARRAY_A
		);

		return $row ? new RestRoute( $row ) : null;
	}

	/**
	 * Find all routes.
	 */
	public function findAll( array $criteria = [] ): array {
		$sql  = "SELECT * FROM {$this->table}";
		$rows = $this->db->get_results( $sql, ARRAY_A );

		return array_map( fn( $row ) => new RestRoute( $row ), $rows ?: [] );
	}

	/**
	 * Save a route.
	 */
	public function save( RestRoute $route ): int {
		$data = [
			'name'       => $route->getName(),
			'route_path' => $route->getRoutePath(),
			'methods'    => wp_json_encode( $route->getMethods() ),
			'actions'    => wp_json_encode( $route->getActions() ),
			'is_active'  => $route->isActive() ? 1 : 0,
			'is_async'   => $route->isAsync() ? 1 : 0,
			'secret_key' => $route->getSecretKey(),
		];

		if ( $route->getId() > 0 ) {
			$this->db->update( $this->table, $data, [ 'id' => $route->getId() ] );
			return $route->getId();
		}

		$this->db->insert( $this->table, $data );
		return (int) $this->db->insert_id;
	}

	/**
	 * Delete a route.
	 */
	public function delete( int $id ): bool {
		return (bool) $this->db->delete( $this->table, [ 'id' => $id ] );
	}
}
