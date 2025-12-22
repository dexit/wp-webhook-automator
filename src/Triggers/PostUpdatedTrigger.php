<?php
/**
 * Post Updated Trigger
 *
 * Fires when a published post is updated.
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Triggers;

class PostUpdatedTrigger extends PostPublishedTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'post_updated';

	/**
	 * @var string
	 */
	protected string $name = 'Post Updated';

	/**
	 * @var string
	 */
	protected string $description = 'Fires when a published post is updated';

	/**
	 * @var string
	 */
	protected string $hook = 'post_updated';

	/**
	 * @var int
	 */
	protected int $acceptedArgs = 3;

	/**
	 * Prepare event data.
	 *
	 * @param array $args Hook arguments.
	 * @return array|null
	 */
	protected function prepareEventData( array $args ): ?array {
		[$postId, $postAfter, $postBefore] = $args;

		// Only trigger for published posts
		if ( $postAfter->post_status !== 'publish' ) {
			return null;
		}

		// Skip revisions and autosaves
		if ( wp_is_post_revision( $postId ) || wp_is_post_autosave( $postId ) ) {
			return null;
		}

		// Skip if this is the initial publish
		if ( $postBefore->post_status !== 'publish' ) {
			return null;
		}

		$data = $this->buildPostData( $postAfter );

		// Add previous values for comparison
		$data['post']['previous'] = [
			'title'   => $postBefore->post_title,
			'content' => $postBefore->post_content,
			'excerpt' => $postBefore->post_excerpt,
			'status'  => $postBefore->post_status,
		];

		return $data;
	}
}
