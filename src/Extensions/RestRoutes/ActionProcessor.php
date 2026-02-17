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
	 * @param RestRoute|object $route_or_consumer The entity containing actions.
	 * @param array            $data              The request data.
	 * @return array Results of execution.
	 */
	public function process( $route_or_consumer, array $data ): array {
		$body = $data['body'] ?? [];

		// Handle batch processing
		if ( $this->is_batch( $body ) ) {
			$results = [];
			foreach ( $body as $item ) {
				$item_data         = $data;
				$item_data['body'] = $item;
				$results[]         = $this->execute_chain( $route_or_consumer->getActions(), $item_data );
			}
			return $results;
		}

		return [ $this->execute_chain( $route_or_consumer->getActions(), $data ) ];
	}

	/**
	 * Check if data is a batch of items.
	 */
	private function is_batch( $body ): bool {
		if ( ! is_array( $body ) || empty( $body ) ) {
			return false;
		}
		// Check if it's a numeric array (list)
		return array_keys( $body ) === range( 0, count( $body ) - 1 );
	}

	/**
	 * Execute a sequence of actions.
	 */
	private function execute_chain( array $actions, array $data ): array {
		$current_data = $data;
		$results      = [];

		foreach ( $actions as $action ) {
			$type   = $action['type'] ?? '';
			$config = $action['config'] ?? [];

			$result = $this->execute_single_action( $type, $config, $current_data );

			if ( is_wp_error( $result ) ) {
				$results[] = [
					'type'    => $type,
					'status'  => 'error',
					'message' => $result->get_error_message(),
				];
				// Optionally break on error? For now, we continue.
				continue;
			}

			// If action is a transform, update current_data
			if ( isset( $result['transform'] ) && is_array( $result['transform'] ) ) {
				$current_data = $result['transform'];
			}

			$results[] = array_merge( [ 'type' => $type, 'status' => 'success' ], $result );
		}

		return [
			'final_data' => $current_data,
			'actions'    => $results,
		];
	}

	/**
	 * Execute a single action.
	 */
	private function execute_single_action( string $type, array $config, array $data ) {
		switch ( $type ) {
			case 'php_code':
				return $this->execute_php_code( $config['code'] ?? '', $data );

			case 'wp_action':
				return $this->execute_wp_action( $config['action'] ?? '', $data );

			case 'create_cpt':
			case 'update_cpt':
				return $this->execute_cpt_action( $type, $config, $data );

			case 'http_request':
				return $this->execute_http_request( $config, $data );

			default:
				return new WP_Error( 'invalid_action_type', __( 'Invalid action type.', 'hookly-webhook-automator' ) );
		}
	}

	/**
	 * Execute custom PHP code.
	 * PHP code can modify $data by returning a new array.
	 */
	private function execute_php_code( string $code, array $data ) {
		if ( empty( $code ) ) {
			return new WP_Error( 'missing_code', __( 'PHP code is missing.', 'hookly-webhook-automator' ) );
		}

		try {
			ob_start();
			// Make $data available in scope.
			// The code should return an array if it wants to transform data.
			$transform = eval( '?>' . $code );
			$output    = ob_get_clean();

			$res = [
				'result' => $transform,
				'output' => $output,
			];

			if ( is_array( $transform ) ) {
				$res['transform'] = $transform;
			}

			return $res;
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
	 * Handle CPT operations.
	 */
	private function execute_cpt_action( string $type, array $config, array $data ) {
		$post_data = [];

		if ( $type === 'update_cpt' ) {
			$post_id_template = $config['post_id_template'] ?? '';
			$post_id          = (int) $this->parse_template( $post_id_template, $data );
			if ( ! $post_id ) {
				return new WP_Error( 'missing_post_id', __( 'Post ID not found from template.', 'hookly-webhook-automator' ) );
			}
			$post_data['ID'] = $post_id;
		} else {
			$post_data['post_type']   = $config['post_type'] ?? 'post';
			$post_data['post_status'] = $config['post_status'] ?? 'publish';
		}

		$mapping = $config['mapping'] ?? [];
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

		$post_id = $type === 'update_cpt' ? wp_update_post( $post_data, true ) : wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return [
			'post_id' => $post_id,
		];
	}

	/**
	 * Execute an arbitrary HTTP request (dispatch a webhook).
	 */
	private function execute_http_request( array $config, array $data ) {
		$url    = $this->parse_template( $config['url'] ?? '', $data );
		$method = strtoupper( $config['method'] ?? 'POST' );

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid URL for HTTP request action.', 'hookly-webhook-automator' ) );
		}

		$args = [
			'method'  => $method,
			'headers' => $this->parse_template( $config['headers'] ?? [], $data ),
			'timeout' => 30,
		];

		if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$body = $config['body'] ?? [];
			if ( is_array( $body ) ) {
				$args['body'] = $this->parse_template( $body, $data );
				if ( ( $config['format'] ?? 'json' ) === 'json' ) {
					$args['body']                    = wp_json_encode( $args['body'] );
					$args['headers']['Content-Type'] = 'application/json';
				}
			} else {
				$args['body'] = $this->parse_template( $body, $data );
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return [
			'response_code' => wp_remote_retrieve_response_code( $response ),
			'response_body' => wp_remote_retrieve_body( $response ),
		];
	}

	/**
	 * Parse template with data.
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
