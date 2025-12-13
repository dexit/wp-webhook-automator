<?php
/**
 * Base REST Controller
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Rest;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

/**
 * Base class for all REST API controllers.
 */
abstract class RestController extends WP_REST_Controller {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wwa/v1';

	/**
	 * Check if the current user has admin permissions.
	 *
	 * @return bool|WP_Error
	 */
	public function admin_permissions_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'wp-webhook-automator' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Create a success response.
	 *
	 * @param mixed  $data    Response data.
	 * @param string $message Success message.
	 * @param int    $status  HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success( $data = null, string $message = '', int $status = 200 ): WP_REST_Response {
		$response = array(
			'success' => true,
		);

		if ( $message ) {
			$response['message'] = $message;
		}

		if ( $data !== null ) {
			$response['data'] = $data;
		}

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Create an error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Create a paginated response.
	 *
	 * @param array $items      Items to return.
	 * @param int   $total      Total number of items.
	 * @param int   $page       Current page.
	 * @param int   $per_page   Items per page.
	 * @return WP_REST_Response
	 */
	protected function paginated_response( array $items, int $total, int $page, int $per_page ): WP_REST_Response {
		$response = new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $items,
				'meta'    => array(
					'total'       => $total,
					'page'        => $page,
					'per_page'    => $per_page,
					'total_pages' => ceil( $total / $per_page ),
				),
			),
			200
		);

		// Add pagination headers.
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Sanitize and validate pagination parameters.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return array
	 */
	protected function get_pagination_params( $request ): array {
		$page     = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 20;

		// Limit per_page to reasonable range.
		$per_page = min( max( $per_page, 1 ), 100 );

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
		);
	}

	/**
	 * Validate required fields in request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param array            $fields  Required fields.
	 * @return true|WP_Error
	 */
	protected function validate_required_fields( $request, array $fields ) {
		$missing = array();

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( empty( $value ) && $value !== '0' && $value !== 0 ) {
				$missing[] = $field;
			}
		}

		if ( ! empty( $missing ) ) {
			return $this->error(
				'missing_required_fields',
				/* translators: %s: comma-separated list of field names */
				sprintf( __( 'Missing required fields: %s', 'wp-webhook-automator' ), implode( ', ', $missing ) ),
				400
			);
		}

		return true;
	}
}
