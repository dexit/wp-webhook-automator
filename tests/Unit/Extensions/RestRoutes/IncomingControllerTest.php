<?php

namespace Hookly\Tests\Unit\Extensions\RestRoutes;

use Hookly\Extensions\RestRoutes\IncomingController;
use Hookly\Extensions\RestRoutes\RestRoute;
use Hookly\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class IncomingControllerTest extends TestCase {


	/**
	 * Test handle_request triggers the webhook action.
	 */
	public function test_handle_request_triggers_action() {
		$controller = new IncomingController();
		$route = new RestRoute([
			'id' => 1,
			'name' => 'Test Route',
			'route_path' => 'test-path',
			'action_type' => 'wp_action',
			'action_config' => [ 'action' => 'my_custom_action' ],
		]);

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_json_params' )->andReturn( [ 'foo' => 'bar' ] );
		$request->shouldReceive( 'get_query_params' )->andReturn( [] );
		$request->shouldReceive( 'get_headers' )->andReturn( [] );
		$request->shouldReceive( 'get_params' )->andReturn( [ 'foo' => 'bar' ] );
		$request->shouldReceive( 'get_header' )->andReturn( null );

		// Expect both the internal trigger action AND the configured WP action
		Functions\expect( 'do_action' )
			->once()
			->with( 'hookly_rest_route_received', $route, Mockery::subset( [ 'body' => [ 'foo' => 'bar' ] ] ) );

		Functions\expect( 'do_action' )
			->once()
			->with( 'my_custom_action', Mockery::subset( [ 'body' => [ 'foo' => 'bar' ] ] ) );

		$response = $controller->handle_request( $request, $route );
		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * Test handle_request with async enabled.
	 */
	public function test_handle_request_async() {
		$controller = new IncomingController();
		$route = new RestRoute([
			'id' => 1,
			'is_async' => true,
		]);

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_json_params' )->andReturn( [ 'foo' => 'bar' ] );
		$request->shouldReceive( 'get_query_params' )->andReturn( [] );
		$request->shouldReceive( 'get_headers' )->andReturn( [] );
		$request->shouldReceive( 'get_params' )->andReturn( [ 'foo' => 'bar' ] );
		$request->shouldReceive( 'get_header' )->andReturn( null );

		Functions\expect( 'do_action' )->once();
		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with( Mockery::any(), 'hookly_process_rest_route_action', Mockery::on(function($args){
				return $args[0] === 1 && $args[1]['body']['foo'] === 'bar';
			}) );

		$response = $controller->handle_request( $request, $route );
		$this->assertEquals( 202, $response->get_status() );
	}

	/**
	 * Test handle_request with secret key validation.
	 */
	public function test_handle_request_invalid_secret() {
		$controller = new IncomingController();
		$route = new RestRoute([
			'secret_key' => 'super-secret',
		]);

		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_header' )->with( 'X-Hookly-Secret' )->andReturn( 'wrong-secret' );
		$request->shouldReceive( 'get_param' )->with( 'secret' )->andReturn( null );

		$response = $controller->handle_request( $request, $route );
		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'rest_forbidden', $response->get_error_code() );
	}
}
