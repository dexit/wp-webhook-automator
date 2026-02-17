<?php

namespace Hookly\Tests\Unit\Extensions\RestRoutes;

use Hookly\Extensions\RestRoutes\ActionProcessor;
use Hookly\Extensions\RestRoutes\RestRoute;
use Hookly\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class ActionProcessorTest extends TestCase {

	public function test_execute_chain_with_transform() {
		$processor = new ActionProcessor();
		$route = new RestRoute([
			'actions' => [
				[
					'type' => 'php_code',
					'config' => [ 'code' => '<?php return array_merge($data, ["transformed" => true]); ?>' ]
				],
				[
					'type' => 'wp_action',
					'config' => [ 'action' => 'check_transformed' ]
				]
			]
		]);

		$data = [ 'body' => [ 'foo' => 'bar' ] ];

		Functions\expect( 'do_action' )
			->once()
			->with( 'check_transformed', Mockery::on(function($arg){
				return isset($arg['transformed']) && $arg['transformed'] === true;
			}));

		$results = $processor->process( $route, $data );
		$this->assertEquals( true, $results[0]['final_data']['transformed'] );
	}

	public function test_execute_http_request() {
		$processor = new ActionProcessor();
		$route = new RestRoute([
			'actions' => [
				[
					'type' => 'http_request',
					'config' => [
						'url' => 'https://api.example.com/webhook',
						'method' => 'POST',
						'body' => [ 'id' => '{{body.user_id}}' ]
					]
				]
			]
		]);

		$data = [ 'body' => [ 'user_id' => 123 ] ];

		Functions\expect( 'wp_remote_request' )
			->once()
			->with( 'https://api.example.com/webhook', Mockery::on(function($args){
				$body = json_decode($args['body'], true);
				return $body['id'] == 123;
			}))
			->andReturn( [ 'response' => [ 'code' => 200 ], 'body' => 'OK' ] );

		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->andReturn( 'OK' );

		$processor->process( $route, $data );
	}
}
