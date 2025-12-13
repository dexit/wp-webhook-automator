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
			'site.name'        => __( 'Site name', 'webhook-automator' ),
			'site.url'         => __( 'Site URL', 'webhook-automator' ),
			'site.admin_email' => __( 'Admin email', 'webhook-automator' ),
			'timestamp'        => __( 'Unix timestamp', 'webhook-automator' ),
			'timestamp_iso'    => __( 'ISO 8601 timestamp', 'webhook-automator' ),
			'webhook.name'     => __( 'Webhook name', 'webhook-automator' ),
			'webhook.id'       => __( 'Webhook ID', 'webhook-automator' ),
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
			'post.id'             => __( 'Post ID', 'webhook-automator' ),
			'post.title'          => __( 'Post title', 'webhook-automator' ),
			'post.content'        => __( 'Post content', 'webhook-automator' ),
			'post.excerpt'        => __( 'Post excerpt', 'webhook-automator' ),
			'post.status'         => __( 'Post status', 'webhook-automator' ),
			'post.type'           => __( 'Post type', 'webhook-automator' ),
			'post.slug'           => __( 'Post slug', 'webhook-automator' ),
			'post.url'            => __( 'Post URL', 'webhook-automator' ),
			'post.author.id'      => __( 'Author ID', 'webhook-automator' ),
			'post.author.name'    => __( 'Author name', 'webhook-automator' ),
			'post.author.email'   => __( 'Author email', 'webhook-automator' ),
			'post.date'           => __( 'Publish date', 'webhook-automator' ),
			'post.modified'       => __( 'Modified date', 'webhook-automator' ),
			'post.categories'     => __( 'Categories (comma-separated)', 'webhook-automator' ),
			'post.tags'           => __( 'Tags (comma-separated)', 'webhook-automator' ),
			'post.featured_image' => __( 'Featured image URL', 'webhook-automator' ),
		];
	}

	/**
	 * Get user-related merge tags.
	 *
	 * @return array
	 */
	private function getUserTags(): array {
		return [
			'user.id'           => __( 'User ID', 'webhook-automator' ),
			'user.login'        => __( 'Username', 'webhook-automator' ),
			'user.email'        => __( 'Email', 'webhook-automator' ),
			'user.first_name'   => __( 'First name', 'webhook-automator' ),
			'user.last_name'    => __( 'Last name', 'webhook-automator' ),
			'user.display_name' => __( 'Display name', 'webhook-automator' ),
			'user.role'         => __( 'User role', 'webhook-automator' ),
			'user.registered'   => __( 'Registration date', 'webhook-automator' ),
			'user.url'          => __( 'User URL', 'webhook-automator' ),
		];
	}

	/**
	 * Get comment-related merge tags.
	 *
	 * @return array
	 */
	private function getCommentTags(): array {
		return [
			'comment.id'           => __( 'Comment ID', 'webhook-automator' ),
			'comment.content'      => __( 'Comment content', 'webhook-automator' ),
			'comment.author_name'  => __( 'Author name', 'webhook-automator' ),
			'comment.author_email' => __( 'Author email', 'webhook-automator' ),
			'comment.author_url'   => __( 'Author URL', 'webhook-automator' ),
			'comment.date'         => __( 'Comment date', 'webhook-automator' ),
			'comment.status'       => __( 'Comment status', 'webhook-automator' ),
			'comment.post.id'      => __( 'Related post ID', 'webhook-automator' ),
			'comment.post.title'   => __( 'Related post title', 'webhook-automator' ),
		];
	}

	/**
	 * Get WooCommerce order merge tags.
	 *
	 * @return array
	 */
	private function getWooOrderTags(): array {
		return [
			'order.id'                  => __( 'Order ID', 'webhook-automator' ),
			'order.number'              => __( 'Order number', 'webhook-automator' ),
			'order.status'              => __( 'Order status', 'webhook-automator' ),
			'order.total'               => __( 'Order total', 'webhook-automator' ),
			'order.subtotal'            => __( 'Subtotal', 'webhook-automator' ),
			'order.tax'                 => __( 'Tax amount', 'webhook-automator' ),
			'order.shipping'            => __( 'Shipping cost', 'webhook-automator' ),
			'order.discount'            => __( 'Discount amount', 'webhook-automator' ),
			'order.currency'            => __( 'Currency', 'webhook-automator' ),
			'order.payment_method'      => __( 'Payment method', 'webhook-automator' ),
			'order.billing.first_name'  => __( 'Billing first name', 'webhook-automator' ),
			'order.billing.last_name'   => __( 'Billing last name', 'webhook-automator' ),
			'order.billing.email'       => __( 'Billing email', 'webhook-automator' ),
			'order.billing.phone'       => __( 'Billing phone', 'webhook-automator' ),
			'order.billing.address_1'   => __( 'Billing address', 'webhook-automator' ),
			'order.billing.city'        => __( 'Billing city', 'webhook-automator' ),
			'order.billing.country'     => __( 'Billing country', 'webhook-automator' ),
			'order.shipping.first_name' => __( 'Shipping first name', 'webhook-automator' ),
			'order.shipping.last_name'  => __( 'Shipping last name', 'webhook-automator' ),
			'order.items'               => __( 'Order items (JSON)', 'webhook-automator' ),
			'order.date_created'        => __( 'Order date', 'webhook-automator' ),
		];
	}

	/**
	 * Get WooCommerce product merge tags.
	 *
	 * @return array
	 */
	private function getWooProductTags(): array {
		return [
			'product.id'             => __( 'Product ID', 'webhook-automator' ),
			'product.name'           => __( 'Product name', 'webhook-automator' ),
			'product.sku'            => __( 'SKU', 'webhook-automator' ),
			'product.price'          => __( 'Price', 'webhook-automator' ),
			'product.regular_price'  => __( 'Regular price', 'webhook-automator' ),
			'product.sale_price'     => __( 'Sale price', 'webhook-automator' ),
			'product.stock_quantity' => __( 'Stock quantity', 'webhook-automator' ),
			'product.stock_status'   => __( 'Stock status', 'webhook-automator' ),
			'product.url'            => __( 'Product URL', 'webhook-automator' ),
			'product.type'           => __( 'Product type', 'webhook-automator' ),
		];
	}

	/**
	 * Get form submission merge tags.
	 *
	 * @return array
	 */
	private function getFormTags(): array {
		return [
			'form.id'           => __( 'Form ID', 'webhook-automator' ),
			'form.name'         => __( 'Form name', 'webhook-automator' ),
			'form.fields'       => __( 'All fields (JSON)', 'webhook-automator' ),
			'form.submitted_at' => __( 'Submission time', 'webhook-automator' ),
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
