<?php
/**
 * Consumer Manager
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Extensions\Consumers;

use Hookly\Extensions\RestRoutes\ActionProcessor;

class ConsumerManager {

	private ConsumerRepository $repository;

	public function __construct() {
		$this->repository = new ConsumerRepository();
	}

	public function init(): void {
		add_action( 'hookly_run_consumer', [ $this, 'run_consumer' ] );
		add_action( 'hookly_schedule_consumers', [ $this, 'schedule_consumers' ] );

		if ( ! wp_next_scheduled( 'hookly_schedule_consumers' ) ) {
			wp_schedule_event( time(), 'hourly', 'hookly_schedule_consumers' );
		}
	}

	public function schedule_consumers(): void {
		$consumers = $this->repository->findAll();
		foreach ( $consumers as $consumer ) {
			if ( ! $consumer->isActive() ) {
				wp_clear_scheduled_hook( 'hookly_run_consumer', [ $consumer->getId() ] );
				continue;
			}

			if ( ! wp_next_scheduled( 'hookly_run_consumer', [ $consumer->getId() ] ) ) {
				wp_schedule_event( time(), $consumer->getSchedule(), 'hookly_run_consumer', [ $consumer->getId() ] );
			}
		}
	}

	public function run_consumer( int $id ): void {
		$consumer = $this->repository->find( $id );
		if ( ! $consumer || ! $consumer->isActive() ) {
			return;
		}

		$response = wp_remote_request( $consumer->getSourceUrl(), [
			'method'  => $consumer->getHttpMethod(),
			'headers' => $consumer->getHeaders(),
			'timeout' => 60,
		]);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true ) ?: [ 'body' => $body ];

		// Wrap in standard data structure for ActionProcessor
		$processor_data = [
			'body'    => $data,
			'headers' => wp_remote_retrieve_headers( $response )->getAll(),
			'query'   => [],
			'params'  => [],
		];

		$processor = new ActionProcessor();
		$processor->process( $consumer, $processor_data );

		$this->repository->updateLastRun( $id );
	}
}
