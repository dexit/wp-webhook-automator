<?php
/**
 * Payload Builder
 *
 * Builds webhook payloads with merge tag support.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Core;

class PayloadBuilder {

	/**
	 * Build payload from template and event data.
	 *
	 * @param array $template  The payload template.
	 * @param array $eventData The event data.
	 * @return array
	 */
	public function build( array $template, array $eventData ): array {
		if ( empty( $template ) ) {
			return $this->buildDefaultPayload( $eventData );
		}

		return $this->processTemplate( $template, $eventData );
	}

	/**
	 * Build JSON string payload.
	 *
	 * @param array $template  The payload template.
	 * @param array $eventData The event data.
	 * @return string
	 */
	public function buildJson( array $template, array $eventData ): string {
		$payload = $this->build( $template, $eventData );
		return wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ?: '{}';
	}

	/**
	 * Build form-encoded payload.
	 *
	 * @param array $template  The payload template.
	 * @param array $eventData The event data.
	 * @return string
	 */
	public function buildFormData( array $template, array $eventData ): string {
		$payload = $this->build( $template, $eventData );
		return http_build_query( $this->flattenArray( $payload ) );
	}

	/**
	 * Process template recursively.
	 *
	 * @param array $template  The template to process.
	 * @param array $eventData The event data.
	 * @return array
	 */
	private function processTemplate( array $template, array $eventData ): array {
		$result = [];

		foreach ( $template as $key => $value ) {
			if ( is_array( $value ) ) {
				$result[ $key ] = $this->processTemplate( $value, $eventData );
			} elseif ( is_string( $value ) ) {
				$result[ $key ] = $this->replaceMergeTags( $value, $eventData );
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Replace merge tags in a string.
	 *
	 * @param string $content The content with merge tags.
	 * @param array  $data    The data for replacement.
	 * @return string
	 */
	public function replaceMergeTags( string $content, array $data ): string {
		// Match {{path.to.value}} patterns
		return preg_replace_callback(
			'/\{\{([^}]+)\}\}/',
			function ( $matches ) use ( $data ) {
				$path  = trim( $matches[1] );
				$value = $this->getNestedValue( $data, $path );

				if ( $value === null ) {
					return $matches[0]; // Return original if not found
				}

				// Convert arrays/objects to JSON string
				if ( is_array( $value ) || is_object( $value ) ) {
					return wp_json_encode( $value );
				}

				return (string) $value;
			},
			$content
		) ?? $content;
	}

	/**
	 * Get a nested value from an array using dot notation.
	 *
	 * @param array  $data The data array.
	 * @param string $path The dot-notation path.
	 * @return mixed
	 */
	private function getNestedValue( array $data, string $path ): mixed {
		$keys  = explode( '.', $path );
		$value = $data;

		foreach ( $keys as $key ) {
			if ( is_array( $value ) && isset( $value[ $key ] ) ) {
				$value = $value[ $key ];
			} elseif ( is_object( $value ) && isset( $value->$key ) ) {
				$value = $value->$key;
			} else {
				return null;
			}
		}

		return $value;
	}

	/**
	 * Build default payload structure.
	 *
	 * @param array $eventData The event data.
	 * @return array
	 */
	private function buildDefaultPayload( array $eventData ): array {
		return array_merge(
			$this->getGlobalData(),
			[ 'event' => $eventData ]
		);
	}

	/**
	 * Get global data available for all webhooks.
	 *
	 * @return array
	 */
	public function getGlobalData(): array {
		return [
			'site'          => [
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url(),
				'admin_email' => get_option( 'admin_email' ),
			],
			'timestamp'     => time(),
			'timestamp_iso' => gmdate( 'c' ),
		];
	}

	/**
	 * Get available merge tags for a trigger type.
	 *
	 * @param string $triggerType The trigger type.
	 * @return array
	 */
	public function getAvailableTags( string $triggerType ): array {
		$global = [
			'site.name'        => __( 'Site name', 'wp-webhook-automator' ),
			'site.url'         => __( 'Site URL', 'wp-webhook-automator' ),
			'site.admin_email' => __( 'Admin email', 'wp-webhook-automator' ),
			'timestamp'        => __( 'Unix timestamp', 'wp-webhook-automator' ),
			'timestamp_iso'    => __( 'ISO 8601 timestamp', 'wp-webhook-automator' ),
			'webhook.name'     => __( 'Webhook name', 'wp-webhook-automator' ),
			'webhook.id'       => __( 'Webhook ID', 'wp-webhook-automator' ),
		];

		$triggerTags = match ( true ) {
			str_starts_with( $triggerType, 'post_' ) => $this->getPostTags(),
			str_starts_with( $triggerType, 'user_' ) => $this->getUserTags(),
			str_starts_with( $triggerType, 'comment_' ) => $this->getCommentTags(),
			str_starts_with( $triggerType, 'wc_order_' ) => $this->getWooOrderTags(),
			str_starts_with( $triggerType, 'wc_product_' ) => $this->getWooProductTags(),
			str_starts_with( $triggerType, 'form_' ) => $this->getFormTags(),
			default => [],
		};

		return array_merge( $global, $triggerTags );
	}

	/**
	 * Get post-related merge tags.
	 *
	 * @return array
	 */
	private function getPostTags(): array {
		return [
			'post.id'             => __( 'Post ID', 'wp-webhook-automator' ),
			'post.title'          => __( 'Post title', 'wp-webhook-automator' ),
			'post.content'        => __( 'Post content', 'wp-webhook-automator' ),
			'post.excerpt'        => __( 'Post excerpt', 'wp-webhook-automator' ),
			'post.status'         => __( 'Post status', 'wp-webhook-automator' ),
			'post.type'           => __( 'Post type', 'wp-webhook-automator' ),
			'post.slug'           => __( 'Post slug', 'wp-webhook-automator' ),
			'post.url'            => __( 'Post URL', 'wp-webhook-automator' ),
			'post.author.id'      => __( 'Author ID', 'wp-webhook-automator' ),
			'post.author.name'    => __( 'Author name', 'wp-webhook-automator' ),
			'post.author.email'   => __( 'Author email', 'wp-webhook-automator' ),
			'post.date'           => __( 'Publish date', 'wp-webhook-automator' ),
			'post.modified'       => __( 'Modified date', 'wp-webhook-automator' ),
			'post.categories'     => __( 'Categories (comma-separated)', 'wp-webhook-automator' ),
			'post.tags'           => __( 'Tags (comma-separated)', 'wp-webhook-automator' ),
			'post.featured_image' => __( 'Featured image URL', 'wp-webhook-automator' ),
		];
	}

	/**
	 * Get user-related merge tags.
	 *
	 * @return array
	 */
	private function getUserTags(): array {
		return [
			'user.id'           => __( 'User ID', 'wp-webhook-automator' ),
			'user.login'        => __( 'Username', 'wp-webhook-automator' ),
			'user.email'        => __( 'Email', 'wp-webhook-automator' ),
			'user.first_name'   => __( 'First name', 'wp-webhook-automator' ),
			'user.last_name'    => __( 'Last name', 'wp-webhook-automator' ),
			'user.display_name' => __( 'Display name', 'wp-webhook-automator' ),
			'user.role'         => __( 'User role', 'wp-webhook-automator' ),
			'user.registered'   => __( 'Registration date', 'wp-webhook-automator' ),
			'user.url'          => __( 'User URL', 'wp-webhook-automator' ),
		];
	}

	/**
	 * Get comment-related merge tags.
	 *
	 * @return array
	 */
	private function getCommentTags(): array {
		return [
			'comment.id'           => __( 'Comment ID', 'wp-webhook-automator' ),
			'comment.content'      => __( 'Comment content', 'wp-webhook-automator' ),
			'comment.author_name'  => __( 'Author name', 'wp-webhook-automator' ),
			'comment.author_email' => __( 'Author email', 'wp-webhook-automator' ),
			'comment.author_url'   => __( 'Author URL', 'wp-webhook-automator' ),
			'comment.date'         => __( 'Comment date', 'wp-webhook-automator' ),
			'comment.status'       => __( 'Comment status', 'wp-webhook-automator' ),
			'comment.post.id'      => __( 'Related post ID', 'wp-webhook-automator' ),
			'comment.post.title'   => __( 'Related post title', 'wp-webhook-automator' ),
		];
	}

	/**
	 * Get WooCommerce order merge tags.
	 *
	 * @return array
	 */
	private function getWooOrderTags(): array {
		return [
			'order.id'                  => __( 'Order ID', 'wp-webhook-automator' ),
			'order.number'              => __( 'Order number', 'wp-webhook-automator' ),
			'order.status'              => __( 'Order status', 'wp-webhook-automator' ),
			'order.total'               => __( 'Order total', 'wp-webhook-automator' ),
			'order.subtotal'            => __( 'Subtotal', 'wp-webhook-automator' ),
			'order.tax'                 => __( 'Tax amount', 'wp-webhook-automator' ),
			'order.shipping'            => __( 'Shipping cost', 'wp-webhook-automator' ),
			'order.discount'            => __( 'Discount amount', 'wp-webhook-automator' ),
			'order.currency'            => __( 'Currency', 'wp-webhook-automator' ),
			'order.payment_method'      => __( 'Payment method', 'wp-webhook-automator' ),
			'order.billing.first_name'  => __( 'Billing first name', 'wp-webhook-automator' ),
			'order.billing.last_name'   => __( 'Billing last name', 'wp-webhook-automator' ),
			'order.billing.email'       => __( 'Billing email', 'wp-webhook-automator' ),
			'order.billing.phone'       => __( 'Billing phone', 'wp-webhook-automator' ),
			'order.billing.address_1'   => __( 'Billing address', 'wp-webhook-automator' ),
			'order.billing.city'        => __( 'Billing city', 'wp-webhook-automator' ),
			'order.billing.country'     => __( 'Billing country', 'wp-webhook-automator' ),
			'order.shipping.first_name' => __( 'Shipping first name', 'wp-webhook-automator' ),
			'order.shipping.last_name'  => __( 'Shipping last name', 'wp-webhook-automator' ),
			'order.items'               => __( 'Order items (JSON)', 'wp-webhook-automator' ),
			'order.date_created'        => __( 'Order date', 'wp-webhook-automator' ),
		];
	}

	/**
	 * Get WooCommerce product merge tags.
	 *
	 * @return array
	 */
	private function getWooProductTags(): array {
		return [
			'product.id'             => __( 'Product ID', 'wp-webhook-automator' ),
			'product.name'           => __( 'Product name', 'wp-webhook-automator' ),
			'product.sku'            => __( 'SKU', 'wp-webhook-automator' ),
			'product.price'          => __( 'Price', 'wp-webhook-automator' ),
			'product.regular_price'  => __( 'Regular price', 'wp-webhook-automator' ),
			'product.sale_price'     => __( 'Sale price', 'wp-webhook-automator' ),
			'product.stock_quantity' => __( 'Stock quantity', 'wp-webhook-automator' ),
			'product.stock_status'   => __( 'Stock status', 'wp-webhook-automator' ),
			'product.url'            => __( 'Product URL', 'wp-webhook-automator' ),
			'product.type'           => __( 'Product type', 'wp-webhook-automator' ),
		];
	}

	/**
	 * Get form submission merge tags.
	 *
	 * @return array
	 */
	private function getFormTags(): array {
		return [
			'form.id'           => __( 'Form ID', 'wp-webhook-automator' ),
			'form.name'         => __( 'Form name', 'wp-webhook-automator' ),
			'form.fields'       => __( 'All fields (JSON)', 'wp-webhook-automator' ),
			'form.submitted_at' => __( 'Submission time', 'wp-webhook-automator' ),
		];
	}

	/**
	 * Flatten a multi-dimensional array.
	 *
	 * @param array  $array  The array to flatten.
	 * @param string $prefix The key prefix.
	 * @return array
	 */
	private function flattenArray( array $array, string $prefix = '' ): array {
		$result = [];

		foreach ( $array as $key => $value ) {
			$newKey = $prefix ? "{$prefix}[{$key}]" : $key;

			if ( is_array( $value ) ) {
				$result = array_merge( $result, $this->flattenArray( $value, $newKey ) );
			} else {
				$result[ $newKey ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Parse a JSON template string to array.
	 *
	 * @param string $jsonString The JSON string.
	 * @return array
	 */
	public function parseTemplate( string $jsonString ): array {
		if ( empty( $jsonString ) ) {
			return [];
		}

		$decoded = json_decode( $jsonString, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Validate a template structure.
	 *
	 * @param array $template The template to validate.
	 * @return bool
	 */
	public function validateTemplate( array $template ): bool {
		// Template must be a valid array
		if ( ! is_array( $template ) ) {
			return false;
		}

		// Check for circular references (simplified check)
		$json = wp_json_encode( $template );
		if ( $json === false ) {
			return false;
		}

		return true;
	}
}
