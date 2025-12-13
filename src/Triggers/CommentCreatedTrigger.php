<?php
/**
 * Comment Created Trigger
 *
 * Fires when a new comment is created.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class CommentCreatedTrigger extends AbstractTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'comment_created';

	/**
	 * @var string
	 */
	protected string $name = 'Comment Created';

	/**
	 * @var string
	 */
	protected string $description = 'Fires when a new comment is created';

	/**
	 * @var string
	 */
	protected string $category = 'Comments';

	/**
	 * @var string
	 */
	protected string $hook = 'wp_insert_comment';

	/**
	 * @var int
	 */
	protected int $acceptedArgs = 2;

	/**
	 * Get available data fields.
	 *
	 * @return array
	 */
	public function getAvailableData(): array {
		return [ 'comment', 'comment.post' ];
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function getConfigFields(): array {
		return [
			'post_types' => [
				'type'        => 'multiselect',
				'label'       => __( 'Post Types', 'webhook-automator' ),
				'description' => __( 'Only trigger for comments on these post types.', 'webhook-automator' ),
				'options'     => $this->getPostTypes(),
				'default'     => [],
			],
			'statuses'   => [
				'type'        => 'multiselect',
				'label'       => __( 'Comment Status', 'webhook-automator' ),
				'description' => __( 'Only trigger for comments with these statuses.', 'webhook-automator' ),
				'options'     => $this->getCommentStatuses(),
				'default'     => [],
			],
		];
	}

	/**
	 * Check if event matches configuration.
	 *
	 * @param array $eventData The event data.
	 * @param array $config    The configuration.
	 * @return bool
	 */
	public function matchesConfig( array $eventData, array $config ): bool {
		// Check post type filter
		if ( ! empty( $config['post_types'] ) ) {
			$postTypes = is_array( $config['post_types'] ) ? $config['post_types'] : [ $config['post_types'] ];
			$postType  = $eventData['comment']['post']['type'] ?? '';
			if ( ! in_array( $postType, $postTypes, true ) ) {
				return false;
			}
		}

		// Check status filter
		if ( ! empty( $config['statuses'] ) ) {
			$statuses = is_array( $config['statuses'] ) ? $config['statuses'] : [ $config['statuses'] ];
			$status   = $eventData['comment']['status'] ?? '';
			if ( ! in_array( $status, $statuses, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Prepare event data.
	 *
	 * @param array $args Hook arguments.
	 * @return array|null
	 */
	protected function prepareEventData( array $args ): ?array {
		$commentId = $args[0];
		$comment   = $args[1] ?? null;

		if ( ! $comment instanceof \WP_Comment ) {
			$comment = get_comment( $commentId );
		}

		if ( ! $comment ) {
			return null;
		}

		// Skip pingbacks and trackbacks if needed
		if ( in_array( $comment->comment_type, [ 'pingback', 'trackback' ], true ) ) {
			return null;
		}

		return $this->buildCommentData( $comment );
	}

	/**
	 * Build comment data array.
	 *
	 * @param \WP_Comment $comment The comment object.
	 * @return array
	 */
	protected function buildCommentData( \WP_Comment $comment ): array {
		$post = get_post( $comment->comment_post_ID );

		// Determine comment status
		$status = match ( $comment->comment_approved ) {
			'1' => 'approved',
			'0' => 'pending',
			'spam' => 'spam',
			'trash' => 'trash',
			default => $comment->comment_approved,
		};

		$data = [
			'comment' => [
				'id'           => (int) $comment->comment_ID,
				'content'      => $comment->comment_content,
				'author_name'  => $comment->comment_author,
				'author_email' => $comment->comment_author_email,
				'author_url'   => $comment->comment_author_url,
				'author_ip'    => $comment->comment_author_IP,
				'date'         => $comment->comment_date,
				'date_gmt'     => $comment->comment_date_gmt,
				'status'       => $status,
				'type'         => $comment->comment_type ?: 'comment',
				'parent'       => (int) $comment->comment_parent,
				'user_id'      => (int) $comment->user_id,
				'post'         => [
					'id'    => (int) $comment->comment_post_ID,
					'title' => $post ? $post->post_title : '',
					'type'  => $post ? $post->post_type : '',
					'url'   => $post ? get_permalink( $post->ID ) : '',
				],
			],
		];

		// Add user data if comment is from a registered user
		if ( $comment->user_id ) {
			$user = get_userdata( $comment->user_id );
			if ( $user ) {
				$data['comment']['user'] = [
					'id'           => $user->ID,
					'login'        => $user->user_login,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
				];
			}
		}

		return $data;
	}
}
