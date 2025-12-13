<?php
/**
 * Comment Reply Trigger
 *
 * Fires when a reply is posted to a comment.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class CommentReplyTrigger extends CommentCreatedTrigger {

    /**
     * @var string
     */
    protected string $key = 'comment_reply';

    /**
     * @var string
     */
    protected string $name = 'Comment Reply';

    /**
     * @var string
     */
    protected string $description = 'Fires when a reply is posted to a comment';

    /**
     * @var string
     */
    protected string $hook = 'wp_insert_comment';

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
    protected function prepareEventData(array $args): ?array {
        $commentId = $args[0];
        $comment = $args[1] ?? null;

        if (!$comment instanceof \WP_Comment) {
            $comment = get_comment($commentId);
        }

        if (!$comment) {
            return null;
        }

        // Only trigger for replies (comments with a parent)
        if (empty($comment->comment_parent)) {
            return null;
        }

        // Skip pingbacks and trackbacks
        if (in_array($comment->comment_type, ['pingback', 'trackback'], true)) {
            return null;
        }

        $data = $this->buildCommentData($comment);

        // Add parent comment data
        $parentComment = get_comment($comment->comment_parent);
        if ($parentComment) {
            $data['comment']['parent_comment'] = [
                'id'           => (int) $parentComment->comment_ID,
                'content'      => $parentComment->comment_content,
                'author_name'  => $parentComment->comment_author,
                'author_email' => $parentComment->comment_author_email,
                'date'         => $parentComment->comment_date,
            ];
        }

        return $data;
    }
}
