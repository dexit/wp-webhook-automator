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

	public function handle_request( WP_REST_Request $request, RestRoute $route ) {
		// Validate secret key
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

		// 1. Dispatcher logic: Fire action for existing outbound webhooks
		do_action( 'hookly_rest_route_received', $route, $data );

		// 2. Consumer logic: Execute action chain (Transforms, CPTs, Custom Requests)
		if ( $route->isAsync() ) {
			wp_schedule_single_event( time(), 'hookly_process_rest_route_action', [ $route->getId(), $data ] );
			return $this->success( null, __( 'Request accepted for background processing.', 'hookly-webhook-automator' ), 202 );
		}

		$processor = new ActionProcessor();
		$results   = $processor->process( $route, $data );

		return $this->success(
			[
				'results' => $results,
				'count'   => count( $results ),
			],
			__( 'Request processed.', 'hookly-webhook-automator' )
		);
	}
}
