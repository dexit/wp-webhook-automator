<?php

namespace Hookly\Tests\Unit\Extensions\RestRoutes;

use Hookly\Extensions\RestRoutes\IncomingController;
use Hookly\Extensions\RestRoutes\RestRoute;
use Hookly\Triggers\RestRouteTrigger;
use Hookly\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class IntegrationTest extends TestCase {

	/**
	 * Test the integration between IncomingController and RestRouteTrigger.
	 */
	public function test_incoming_request_triggers_rest_route_webhook() {
		$controller = new IncomingController();
		$route = new RestRoute([
			'id' => 123,
			'name' => 'My Custom API',
			'route_path' => 'my-api',
			'actions' => [
				[ 'type' => 'wp_action', 'config' => [ 'action' => 'custom_hook' ] ]
			],
		]);

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_json_params' )->andReturn( [ 'user_id' => 1 ] );
		$request->shouldReceive( 'get_query_params' )->andReturn( [] );
		$request->shouldReceive( 'get_headers' )->andReturn( [] );
		$request->shouldReceive( 'get_params' )->andReturn( [ 'user_id' => 1 ] );
		$request->shouldReceive( 'get_header' )->andReturn( null );

		// 1. When request hits IncomingController, it should fire 'hookly_rest_route_received'
		Functions\expect( 'do_action' )
			->once()
			->with( 'hookly_rest_route_received', $route, Mockery::subset( [ 'body' => [ 'user_id' => 1 ] ] ) );

		// 2. It also fires the configured WP action
		Functions\expect( 'do_action' )
			->once()
			->with( 'custom_hook', Mockery::subset( [ 'body' => [ 'user_id' => 1 ] ] ) );

		$response = $controller->handle_request( $request, $route );
		$this->assertTrue( $response->get_data()['success'] );

		// 3. Now verify the Trigger would prepare the correct data for Hookly
		$trigger = new RestRouteTrigger();
		$method = new \ReflectionMethod( RestRouteTrigger::class, 'prepareEventData' );
		$method->setAccessible( true );

		$eventData = $method->invoke( $trigger, [ $route, [ 'body' => [ 'user_id' => 1 ] ] ] );

		$this->assertEquals( 'my-api', $eventData['route']['path'] );
		$this->assertEquals( 1, $eventData['request']['body']['user_id'] );
	}
}
