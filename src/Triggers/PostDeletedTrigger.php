<?php
/**
 * Post Deleted Trigger
 *
 * Fires before a post is deleted.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class PostDeletedTrigger extends PostPublishedTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'post_deleted';

	/**
	 * @var string
	 */
	protected string $name = 'Post Deleted';

	/**
	 * @var string
	 */
	protected string $description = 'Fires before a post is deleted';

	/**
	 * @var string
	 */
	protected string $hook = 'before_delete_post';

	/**
	 * @var int
	 */
	protected int $acceptedArgs = 2;

	/**
	 * Prepare event data.
	 *
	 * @param array $args Hook arguments.
	 * @return array|null
	 */
	protected function prepareEventData( array $args ): ?array {
		[$postId, $post] = $args;

		// Skip revisions
		if ( wp_is_post_revision( $postId ) ) {
			return null;
		}

		// Skip auto-drafts
		if ( $post->post_status === 'auto-draft' ) {
			return null;
		}

		return $this->buildPostData( $post );
	}
}
