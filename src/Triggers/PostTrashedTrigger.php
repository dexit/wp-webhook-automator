<?php
/**
 * Post Trashed Trigger
 *
 * Fires when a post is moved to trash.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class PostTrashedTrigger extends PostPublishedTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'post_trashed';

	/**
	 * @var string
	 */
	protected string $name = 'Post Trashed';

	/**
	 * @var string
	 */
	protected string $description = 'Fires when a post is moved to trash';

	/**
	 * @var string
	 */
	protected string $hook = 'trashed_post';

	/**
	 * @var int
	 */
	protected int $acceptedArgs = 1;

	/**
	 * Prepare event data.
	 *
	 * @param array $args Hook arguments.
	 * @return array|null
	 */
	protected function prepareEventData( array $args ): ?array {
		[$postId] = $args;

		$post = get_post( $postId );
		if ( ! $post ) {
			return null;
		}

		// Skip revisions
		if ( wp_is_post_revision( $postId ) ) {
			return null;
		}

		return $this->buildPostData( $post );
	}
}
