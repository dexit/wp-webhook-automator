<?php

namespace Hookly\Tests\Unit\Extensions\RestRoutes;

use Hookly\Extensions\RestRoutes\ActionProcessor;
use Hookly\Extensions\RestRoutes\RestRoute;
use Hookly\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class ActionProcessorTest extends TestCase {

	/**
	 * Test template parsing with nested data.
	 */
	public function test_parse_template() {
		$processor = new ActionProcessor();
		$data = [
			'body' => [ 'user' => [ 'name' => 'John', 'meta' => [ 'role' => 'admin' ] ] ],
		];

		$method = new \ReflectionMethod( ActionProcessor::class, 'parse_template' );
		$method->setAccessible( true );

		$this->assertEquals( 'User: John', $method->invoke( $processor, 'User: {{body.user.name}}', $data ) );
		$this->assertEquals( 'Role: admin', $method->invoke( $processor, 'Role: {{body.user.meta.role}}', $data ) );
		$this->assertEquals( '{"name":"John","meta":{"role":"admin"}}', $method->invoke( $processor, '{{body.user}}', $data ) );
	}

	/**
	 * Test batch processing.
	 */
	public function test_process_batch() {
		$processor = new ActionProcessor();
		$route = new RestRoute([
			'action_type' => 'wp_action',
			'action_config' => [ 'action' => 'batch_action' ],
		]);

		$data = [
			'body' => [
				[ 'id' => 1, 'name' => 'One' ],
				[ 'id' => 2, 'name' => 'Two' ],
			],
		];

		Functions\expect( 'do_action' )->twice();

		$results = $processor->process( $route, $data );
		$this->assertCount( 2, $results );
		$this->assertTrue( $results[0]['success'] );
		$this->assertTrue( $results[1]['success'] );
	}
}
