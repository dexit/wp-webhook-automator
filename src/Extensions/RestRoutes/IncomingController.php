<?php
/**
 * Incoming REST Routes Controller
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\RestRoutes;

use Hookly\Rest\RestController;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class IncomingController extends RestController {

	/**
	 * Handle incoming request.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @param RestRoute       $route   The route entity.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_request( WP_REST_Request $request, RestRoute $route ) {
		// Validate secret key if set
		$secret = $route->getSecretKey();
		if ( $secret ) {
			$provided_secret = $request->get_header( 'X-Hookly-Secret' ) ?: $request->get_param( 'secret' );
			if ( $provided_secret !== $secret ) {
				return $this->error( 'rest_forbidden', __( 'Invalid secret key.', 'hookly-webhook-automator' ), 403 );
			}
		}

		$data = [
			'body'    => $request->get_json_params() ?: $request->get_body_params(),
			'query'   => $request->get_query_params(),
			'headers' => $request->get_headers(),
			'params'  => $request->get_params(),
		];

		// Trigger webhook event (dispatching)
		do_action( 'hookly_rest_route_received', $route, $data );

		// Process actions (consuming)
		if ( $route->isAsync() ) {
			wp_schedule_single_event( time(), 'hookly_process_rest_route_action', [ $route->getId(), $data ] );
			return $this->success( null, __( 'Request received and queued for processing.', 'hookly-webhook-automator' ), 202 );
		}

		$processor = new ActionProcessor();
		$results   = $processor->process( $route, $data );

		// Check if any errors occurred in batch processing
		$has_errors = false;
		foreach ( $results as $result ) {
			if ( is_wp_error( $result ) ) {
				$has_errors = true;
				break;
			}
		}

		if ( $has_errors && count( $results ) === 1 ) {
			return $results[0];
		}

		return $this->success(
			[
				'results' => $results,
				'batch'   => count( $results ) > 1,
			],
			__( 'Request processed successfully.', 'hookly-webhook-automator' )
		);
	}
}
