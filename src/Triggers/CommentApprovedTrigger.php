<?php
/**
 * Comment Approved Trigger
 *
 * Fires when a comment is approved.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class CommentApprovedTrigger extends CommentCreatedTrigger {

	/**
	 * @var string
	 */
	protected string $key = 'comment_approved';

	/**
	 * @var string
	 */
	protected string $name = 'Comment Approved';

	/**
	 * @var string
	 */
	protected string $description = 'Fires when a comment is approved';

	/**
	 * @var string
	 */
	protected string $hook = 'transition_comment_status';

	/**
	 * @var int
	 */
	protected int $acceptedArgs = 3;

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function getConfigFields(): array {
		return [
			'post_types' => [
				'type'        => 'multiselect',
				'label'       => __( 'Post Types', 'hookly-webhook-automator' ),
				'description' => __( 'Only trigger for comments on these post types.', 'hookly-webhook-automator' ),
				'options'     => $this->getPostTypes(),
				'default'     => [],
			],
		];
	}

	/**
	 * Prepare event data.
	 *
	 * @param array $args Hook arguments.
	 * @return array|null
	 */
	protected function prepareEventData( array $args ): ?array {
		$newStatus = $args[0];
		$oldStatus = $args[1];
		$comment   = $args[2] ?? null;

		// Only trigger when transitioning TO approved
		if ( $newStatus !== 'approved' ) {
			return null;
		}

		// Don't trigger if already approved
		if ( $oldStatus === 'approved' ) {
			return null;
		}

		if ( ! $comment instanceof \WP_Comment ) {
			return null;
		}

		$data = $this->buildCommentData( $comment );

		// Add transition info
		$data['comment']['previous_status'] = $oldStatus;

		return $data;
	}
}
