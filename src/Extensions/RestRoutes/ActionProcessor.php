<?php
/**
 * REST Route Action Processor
 *
 * Handles the execution of actions defined in REST Routes.
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\RestRoutes;

use WP_Error;

class ActionProcessor {

	/**
	 * Process a route action with data.
	 *
	 * @param RestRoute $route The route entity.
	 * @param array     $data  The request data.
	 * @return array Results of execution.
	 */
	public function process( RestRoute $route, array $data ): array {
		$body = $data['body'] ?? [];

		// Handle batch processing (Data Importer feel)
		if ( $this->is_batch( $body ) ) {
			$results = [];
			foreach ( $body as $item ) {
				$item_data         = $data;
				$item_data['body'] = $item;
				$results[]         = $this->execute_single( $route, $item_data );
			}
			return $results;
		}

		return [ $this->execute_single( $route, $data ) ];
	}

	/**
	 * Check if data is a batch of items.
	 */
	private function is_batch( $body ): bool {
		if ( ! is_array( $body ) || empty( $body ) ) {
			return false;
		}
		return array_keys( $body ) === range( 0, count( $body ) - 1 );
	}

	/**
	 * Execute action for a single item.
	 */
	private function execute_single( RestRoute $route, array $data ) {
		$action_type   = $route->getActionType();
		$action_config = $route->getActionConfig();

		switch ( $action_type ) {
			case 'php_code':
				return $this->execute_php_code( $action_config['code'] ?? '', $data );

			case 'wp_action':
				return $this->execute_wp_action( $action_config['action'] ?? '', $data );

			case 'create_cpt':
				return $this->execute_create_cpt( $action_config, $data );

			case 'update_cpt':
				return $this->execute_update_cpt( $action_config, $data );

			default:
				return new WP_Error( 'invalid_action_type', __( 'Invalid action type.', 'hookly-webhook-automator' ) );
		}
	}

	/**
	 * Execute custom PHP code.
	 */
	private function execute_php_code( string $code, array $data ) {
		if ( empty( $code ) ) {
			return new WP_Error( 'missing_code', __( 'PHP code is missing.', 'hookly-webhook-automator' ) );
		}

		try {
			ob_start();
			$result = eval( '?>' . $code );
			$output = ob_get_clean();

			return [
				'success' => true,
				'result'  => $result,
				'output'  => $output,
			];
		} catch ( \Throwable $e ) {
			if ( ob_get_length() ) {
				ob_end_clean();
			}
			return new WP_Error( 'php_execution_failed', $e->getMessage() );
		}
	}

	/**
	 * Execute WordPress action.
	 */
	private function execute_wp_action( string $action, array $data ) {
		if ( empty( $action ) ) {
			return new WP_Error( 'missing_action', __( 'WP action name is missing.', 'hookly-webhook-automator' ) );
		}

		do_action( $action, $data );

		return [ 'success' => true ];
	}

	/**
	 * Create a Custom Post Type entry.
	 */
	private function execute_create_cpt( array $config, array $data ) {
		$post_type = $config['post_type'] ?? 'post';
		$mapping   = $config['mapping'] ?? [];

		$post_data = [
			'post_type'   => $post_type,
			'post_status' => $config['post_status'] ?? 'publish',
		];

		foreach ( $mapping as $post_field => $template ) {
			if ( $post_field === 'meta_input' && is_array( $template ) ) {
				$post_data['meta_input'] = [];
				foreach ( $template as $meta_key => $meta_template ) {
					$post_data['meta_input'][ $meta_key ] = $this->parse_template( $meta_template, $data );
				}
			} else {
				$post_data[ $post_field ] = $this->parse_template( $template, $data );
			}
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return [
			'success' => true,
			'post_id' => $post_id,
		];
	}

	/**
	 * Update an existing entry.
	 */
	private function execute_update_cpt( array $config, array $data ) {
		$post_id_template = $config['post_id_template'] ?? '';
		$post_id          = (int) $this->parse_template( $post_id_template, $data );

		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', __( 'Post ID not found from template.', 'hookly-webhook-automator' ) );
		}

		$mapping   = $config['mapping'] ?? [];
		$post_data = [
			'ID' => $post_id,
		];

		foreach ( $mapping as $post_field => $template ) {
			if ( $post_field === 'meta_input' && is_array( $template ) ) {
				$post_data['meta_input'] = [];
				foreach ( $template as $meta_key => $meta_template ) {
					$post_data['meta_input'][ $meta_key ] = $this->parse_template( $meta_template, $data );
				}
			} else {
				$post_data[ $post_field ] = $this->parse_template( $template, $data );
			}
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'success' => true,
			'post_id' => $post_id,
		];
	}

	/**
	 * Parse template with data.
	 * Supports dot notation and nested structures.
	 */
	private function parse_template( mixed $template, array $data ): mixed {
		if ( is_array( $template ) ) {
			$result = [];
			foreach ( $template as $key => $value ) {
				$result[ $key ] = $this->parse_template( $value, $data );
			}
			return $result;
		}

		if ( ! is_string( $template ) ) {
			return $template;
		}

		return preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9._-]+)\s*\}\}/',
			function ( $matches ) use ( $data ) {
				$path  = explode( '.', $matches[1] );
				$value = $data;
				foreach ( $path as $key ) {
					if ( isset( $value[ $key ] ) ) {
						$value = $value[ $key ];
					} else {
						return $matches[0];
					}
				}

				if ( is_array( $value ) || is_object( $value ) ) {
					return wp_json_encode( $value );
				}

				return (string) $value;
			},
			$template
		);
	}
}
