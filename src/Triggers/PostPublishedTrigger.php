<?php
/**
 * Post Published Trigger
 *
 * Fires when a post is published.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Triggers;

class PostPublishedTrigger extends AbstractTrigger {

    /**
     * @var string
     */
    protected string $key = 'post_published';

    /**
     * @var string
     */
    protected string $name = 'Post Published';

    /**
     * @var string
     */
    protected string $description = 'Fires when a post is published';

    /**
     * @var string
     */
    protected string $category = 'Posts';

    /**
     * @var string
     */
    protected string $hook = 'transition_post_status';

    /**
     * @var int
     */
    protected int $acceptedArgs = 3;

    /**
     * Get available data fields.
     *
     * @return array
     */
    public function getAvailableData(): array {
        return ['post', 'post.author'];
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
                'label'       => __('Post Types', 'wp-webhook-automator'),
                'description' => __('Select which post types should trigger this webhook.', 'wp-webhook-automator'),
                'options'     => $this->getPostTypes(),
                'default'     => ['post'],
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
    public function matchesConfig(array $eventData, array $config): bool {
        if (!empty($config['post_types'])) {
            $postTypes = is_array($config['post_types']) ? $config['post_types'] : [$config['post_types']];
            return in_array($eventData['post']['type'] ?? '', $postTypes, true);
        }
        return true;
    }

    /**
     * Prepare event data.
     *
     * @param array $args Hook arguments.
     * @return array|null
     */
    protected function prepareEventData(array $args): ?array {
        [$newStatus, $oldStatus, $post] = $args;

        // Only trigger when publishing (not already published)
        if ($newStatus !== 'publish' || $oldStatus === 'publish') {
            return null;
        }

        // Skip revisions and autosaves
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) {
            return null;
        }

        return $this->buildPostData($post);
    }

    /**
     * Build post data array.
     *
     * @param \WP_Post $post The post object.
     * @return array
     */
    protected function buildPostData(\WP_Post $post): array {
        $author = get_userdata($post->post_author);

        $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
        $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);

        return [
            'post' => [
                'id'             => $post->ID,
                'title'          => $post->post_title,
                'content'        => $post->post_content,
                'excerpt'        => $post->post_excerpt ?: wp_trim_words($post->post_content, 55),
                'status'         => $post->post_status,
                'type'           => $post->post_type,
                'slug'           => $post->post_name,
                'url'            => get_permalink($post->ID),
                'author'         => [
                    'id'    => (int) $post->post_author,
                    'name'  => $author ? $author->display_name : '',
                    'email' => $author ? $author->user_email : '',
                ],
                'date'           => $post->post_date,
                'date_gmt'       => $post->post_date_gmt,
                'modified'       => $post->post_modified,
                'modified_gmt'   => $post->post_modified_gmt,
                'categories'     => implode(', ', $categories ?: []),
                'tags'           => implode(', ', $tags ?: []),
                'featured_image' => get_the_post_thumbnail_url($post->ID, 'full') ?: '',
                'meta'           => $this->getPostMeta($post->ID),
            ],
        ];
    }

    /**
     * Get post meta data.
     *
     * @param int $postId The post ID.
     * @return array
     */
    protected function getPostMeta(int $postId): array {
        $meta = get_post_meta($postId);
        $filtered = [];

        foreach ($meta as $key => $values) {
            // Skip private meta keys (starting with _)
            if (strpos($key, '_') === 0) {
                continue;
            }
            $filtered[$key] = count($values) === 1 ? $values[0] : $values;
        }

        return $filtered;
    }
}
