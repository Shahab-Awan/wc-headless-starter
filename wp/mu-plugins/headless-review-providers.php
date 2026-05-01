<?php
/**
 * Plugin Name: Headless Review Providers
 * Description: Swappable review provider system. The SPA always consumes the same
 *              ReviewData shape from /wchs/v1/reviews/{id} — the backend delegates
 *              to the active provider (WooCommerce native, Yotpo, Stamped, Reviews.io,
 *              or a mock for testing).
 *
 *
 * Author:      WCHS Contributors
 *
 * Provider contract:
 *   get_reviews(product_id, per_page, page) → ['reviews' => [], 'average' => float, 'count' => int, 'distribution' => int[5]]
 *   supports_write() → bool
 *   create_review(product_id, user_id, rating, content) → array|WP_Error
 *
 * Plugins that use wp_comments (Product Reviews Pro, YITH, ReviewX) work
 * automatically with the WooCommerce provider — no adapter needed.
 */

defined( 'ABSPATH' ) || exit;

// ─── Review image cleanup on comment deletion ───────────────────

add_action( 'delete_comment', function ( int $comment_id ) {
	$image_ids = get_comment_meta( $comment_id, '_wchs_review_images', true );
	if ( is_array( $image_ids ) ) {
		foreach ( $image_ids as $img_id ) {
			wp_delete_attachment( (int) $img_id, true );
		}
	}
} );

// ─── Provider Interface ──────────────────────────────────────────

interface WchsReviewProvider {
	/**
	 * Fetch reviews for a product.
	 * @return array{ reviews: array, average: float, count: int, distribution: int[] }
	 */
	public function get_reviews( int $product_id, int $per_page, int $page ): array;

	/** Whether this provider supports creating reviews via our API. */
	public function supports_write(): bool;

	/** Create a review. Only called if supports_write() is true. */
	public function create_review( int $product_id, int $user_id, int $rating, string $content ): array;

	/** Human-readable provider name for error messages. */
	public function name(): string;
}

// ─── Provider Factory ────────────────────────────────────────────

function wchs_get_review_provider(): WchsReviewProvider {
	static $instance = null;
	if ( $instance !== null ) {
		return $instance;
	}

	$settings = class_exists( '\WCHS\Admin\AdminPage' )
		? \WCHS\Admin\AdminPage::get_site_settings()
		: [];
	$provider_id = $settings['review_provider'] ?? 'woocommerce';
	$keys        = $settings['review_provider_keys'] ?? [];

	switch ( $provider_id ) {
		case 'yotpo':
			$instance = new WchsYotpoProvider( $keys['yotpo_app_key'] ?? '' );
			break;
		case 'stamped':
			$instance = new WchsStampedProvider(
				$keys['stamped_api_key'] ?? '',
				$keys['stamped_api_secret'] ?? '',
				$keys['stamped_store_hash'] ?? ''
			);
			break;
		case 'reviewsio':
			$instance = new WchsReviewsIoProvider(
				$keys['reviewsio_store_id'] ?? '',
				$keys['reviewsio_api_key'] ?? ''
			);
			break;
		case 'mock':
			$instance = new WchsMockProvider();
			break;
		default:
			$instance = new WchsWooCommerceProvider();
	}

	return $instance;
}

/**
 * Resolve WC product ID to SKU for third-party providers.
 */
function wchs_product_sku( int $product_id ): string {
	$product = wc_get_product( $product_id );
	return $product ? $product->get_sku() : '';
}

/**
 * Standard empty result.
 */
function wchs_empty_reviews(): array {
	return [
		'reviews'      => [],
		'average'      => 0,
		'count'        => 0,
		'distribution' => [ 0, 0, 0, 0, 0 ],
	];
}

/**
 * Resolve a review-image attachment to a public URL.
 *
 * Imported review providers sometimes leave behind attachment rows whose
 * `_wp_attached_file` points to a missing local file. Prefer the local URL
 * when the file exists, otherwise fall back to the original remote source URL
 * if one was captured at import time.
 */
function wchs_review_attachment_src( int $attachment_id ): string {
	if ( $attachment_id <= 0 ) {
		return '';
	}

	$file = get_attached_file( $attachment_id );
	if ( is_string( $file ) && '' !== $file && file_exists( $file ) ) {
		$url = wp_get_attachment_url( $attachment_id );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}

	$source_url = get_post_meta( $attachment_id, '_source_url', true );
	if ( is_string( $source_url ) && preg_match( '#^https?://#i', $source_url ) ) {
		return esc_url_raw( $source_url );
	}

	return '';
}

// ─── WooCommerce Native Provider ─────────────────────────────────

class WchsWooCommerceProvider implements WchsReviewProvider {

	public function name(): string {
		return 'WooCommerce';
	}

	public function supports_write(): bool {
		return true;
	}

	public function get_reviews( int $product_id, int $per_page, int $page ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return wchs_empty_reviews();
		}

		$args = [
			'post_id' => $product_id,
			'status'  => 'approve',
			'type'    => 'review',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
		];

		$comments = get_comments( $args );
		$reviews  = [];

		foreach ( $comments as $c ) {
			$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
			// Review images. Primary key is our own `_wchs_review_images`
			// (array of attachment IDs). As a fallback we also read
			// `ivole_review_image2` (single attachment ID), which is the key
			// Customer Reviews for WooCommerce (CR4W) uses on sites we've
			// migrated from. Without this fallback, reviews imported from
			// CR4W-powered sources appear without their photos.
			$images_raw = get_comment_meta( $c->comment_ID, '_wchs_review_images', true );
			$images = [];
			if ( is_array( $images_raw ) ) {
				foreach ( $images_raw as $img_id ) {
					$url = wchs_review_attachment_src( (int) $img_id );
					if ( $url ) {
						$images[] = [ 'id' => (int) $img_id, 'src' => $url ];
					}
				}
			}
			if ( empty( $images ) ) {
				$ivole_id = (int) get_comment_meta( $c->comment_ID, 'ivole_review_image2', true );
				if ( $ivole_id > 0 ) {
					$url = wchs_review_attachment_src( $ivole_id );
					if ( $url ) {
						$images[] = [ 'id' => $ivole_id, 'src' => $url ];
					}
				}
			}

			$reviews[] = [
				'id'       => (int) $c->comment_ID,
				'author'   => sanitize_text_field( $c->comment_author ),
				'date'     => mysql_to_rfc3339( $c->comment_date_gmt ),
				'rating'   => max( 1, min( 5, $rating ) ),
				'content'  => wp_kses_post( $c->comment_content ),
				'verified' => (bool) get_comment_meta( $c->comment_ID, 'verified', true ),
				'images'   => $images,
			];
		}

		// Distribution
		$all = get_comments( [
			'post_id' => $product_id,
			'status'  => 'approve',
			'type'    => 'review',
			'fields'  => 'ids',
		] );
		$dist = [ 0, 0, 0, 0, 0 ];
		foreach ( $all as $cid ) {
			$r = (int) get_comment_meta( $cid, 'rating', true );
			if ( $r >= 1 && $r <= 5 ) {
				$dist[ $r - 1 ]++;
			}
		}

		return [
			'reviews'      => $reviews,
			'average'      => (float) $product->get_average_rating(),
			'count'        => (int) $product->get_review_count(),
			'distribution' => $dist,
		];
	}

	public function create_review( int $product_id, int $user_id, int $rating, string $content ): array {
		$user    = get_user_by( 'id', $user_id );
		$product = wc_get_product( $product_id );
		if ( ! $user || ! $product ) {
			return [ 'error' => 'Invalid user or product' ];
		}

		$comment_id = wp_insert_comment( [
			'comment_post_ID'  => $product_id,
			'comment_author'   => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_content'  => $content,
			'comment_type'     => 'review',
			'comment_approved' => 1,
			'user_id'          => $user_id,
		] );

		if ( ! $comment_id ) {
			return [ 'error' => 'Failed to create review' ];
		}

		$verified = wc_customer_bought_product( $user->user_email, $user_id, $product_id );
		update_comment_meta( $comment_id, 'rating', $rating );
		update_comment_meta( $comment_id, 'verified', $verified ? 1 : 0 );
		\WC_Comments::clear_transients( $comment_id );

		return [
			'id'       => $comment_id,
			'verified' => $verified,
		];
	}
}

// ─── Yotpo Provider ──────────────────────────────────────────────

class WchsYotpoProvider implements WchsReviewProvider {

	private string $app_key;

	public function __construct( string $app_key ) {
		$this->app_key = $app_key;
	}

	public function name(): string {
		return 'Yotpo';
	}

	public function supports_write(): bool {
		return false;
	}

	public function create_review( int $product_id, int $user_id, int $rating, string $content ): array {
		return [ 'error' => 'Reviews are managed by Yotpo' ];
	}

	public function get_reviews( int $product_id, int $per_page, int $page ): array {
		if ( ! $this->app_key ) {
			return wchs_empty_reviews();
		}

		$sku = wchs_product_sku( $product_id );
		if ( ! $sku ) {
			$sku = (string) $product_id;
		}

		// Check cache
		$cache_key = "wchs_yotpo_{$sku}_{$per_page}_{$page}";
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		$url = sprintf(
			'https://api-cdn.yotpo.com/v1/widget/%s/products/%s/reviews.json?per_page=%d&page=%d',
			urlencode( $this->app_key ),
			urlencode( $sku ),
			$per_page,
			$page
		);

		$response = wp_remote_get( $url, [ 'timeout' => 8 ] );
		if ( is_wp_error( $response ) ) {
			error_log( 'WCHS Yotpo API error: ' . $response->get_error_message() );
			return wchs_empty_reviews();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['response'] ) ) {
			return wchs_empty_reviews();
		}

		$resp      = $body['response'];
		$bottomline = $resp['bottomline'] ?? [];
		$raw_reviews = $resp['reviews'] ?? [];

		$reviews = [];
		foreach ( $raw_reviews as $r ) {
			$images = [];
			if ( ! empty( $r['images_data'] ) && is_array( $r['images_data'] ) ) {
				foreach ( $r['images_data'] as $i => $img ) {
					$images[] = [
						'id'  => $i,
						'src' => $img['original_url'] ?? ( $img['image_url'] ?? '' ),
					];
				}
			}

			$reviews[] = [
				'id'       => (int) ( $r['id'] ?? 0 ),
				'author'   => sanitize_text_field( $r['user']['display_name'] ?? ( $r['name'] ?? 'Customer' ) ),
				'date'     => date( 'c', strtotime( $r['created_at'] ?? 'now' ) ),
				'rating'   => max( 1, min( 5, (int) ( $r['score'] ?? 5 ) ) ),
				'content'  => sanitize_textarea_field( $r['content'] ?? '' ),
				'verified' => ! empty( $r['verified_buyer'] ),
				'images'   => $images,
			];
		}

		// Star distribution from bottomline
		$star_dist = $bottomline['star_distribution'] ?? [];
		$dist = [
			(int) ( $star_dist[1] ?? 0 ),
			(int) ( $star_dist[2] ?? 0 ),
			(int) ( $star_dist[3] ?? 0 ),
			(int) ( $star_dist[4] ?? 0 ),
			(int) ( $star_dist[5] ?? 0 ),
		];

		$result = [
			'reviews'      => $reviews,
			'average'      => (float) ( $bottomline['average_score'] ?? 0 ),
			'count'        => (int) ( $bottomline['total_review'] ?? $bottomline['total_reviews'] ?? 0 ),
			'distribution' => $dist,
		];

		set_transient( $cache_key, $result, 300 ); // 5 min cache
		return $result;
	}
}

// ─── Stamped.io Provider ─────────────────────────────────────────

class WchsStampedProvider implements WchsReviewProvider {

	private string $api_key;
	private string $api_secret;
	private string $store_hash;

	public function __construct( string $api_key, string $api_secret, string $store_hash ) {
		$this->api_key    = $api_key;
		$this->api_secret = $api_secret;
		$this->store_hash = $store_hash;
	}

	public function name(): string {
		return 'Stamped.io';
	}

	public function supports_write(): bool {
		return false;
	}

	public function create_review( int $product_id, int $user_id, int $rating, string $content ): array {
		return [ 'error' => 'Reviews are managed by Stamped.io' ];
	}

	public function get_reviews( int $product_id, int $per_page, int $page ): array {
		if ( ! $this->api_key || ! $this->store_hash ) {
			return wchs_empty_reviews();
		}

		$sku = wchs_product_sku( $product_id );
		if ( ! $sku ) {
			return wchs_empty_reviews();
		}

		$cache_key = "wchs_stamped_{$sku}_{$per_page}_{$page}";
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		$url = sprintf(
			'https://stamped.io/api/v2/%s/dashboard/reviews?productSKU=%s&take=%d&page=%d',
			urlencode( $this->store_hash ),
			urlencode( $sku ),
			$per_page,
			$page
		);

		$response = wp_remote_get( $url, [
			'timeout' => 8,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret ),
			],
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'WCHS Stamped API error: ' . $response->get_error_message() );
			return wchs_empty_reviews();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return wchs_empty_reviews();
		}

		$raw_reviews = $body['data'] ?? $body['results'] ?? $body['reviews'] ?? [];
		$reviews     = [];
		$total_score = 0;

		foreach ( $raw_reviews as $r ) {
			$rating = max( 1, min( 5, (int) ( $r['reviewRating'] ?? $r['rating'] ?? 5 ) ) );
			$total_score += $rating;

			$images = [];
			if ( ! empty( $r['reviewUserPhotos'] ) ) {
				foreach ( (array) $r['reviewUserPhotos'] as $i => $url ) {
					$images[] = [ 'id' => $i, 'src' => $url ];
				}
			}

			$reviews[] = [
				'id'       => (int) ( $r['id'] ?? 0 ),
				'author'   => sanitize_text_field( $r['author'] ?? $r['reviewerName'] ?? 'Customer' ),
				'date'     => date( 'c', strtotime( $r['dateCreated'] ?? $r['created_at'] ?? 'now' ) ),
				'rating'   => $rating,
				'content'  => sanitize_textarea_field( $r['body'] ?? $r['reviewMessage'] ?? '' ),
				'verified' => ! empty( $r['reviewVerifiedType'] ),
				'images'   => $images,
			];
		}

		$count   = (int) ( $body['total'] ?? $body['totalCount'] ?? count( $reviews ) );
		$average = $count > 0 && $total_score > 0 ? round( $total_score / count( $reviews ), 2 ) : 0;

		$result = [
			'reviews'      => $reviews,
			'average'      => $average,
			'count'        => $count,
			'distribution' => [ 0, 0, 0, 0, 0 ], // Stamped doesn't return distribution in list endpoint
		];

		set_transient( $cache_key, $result, 300 );
		return $result;
	}
}

// ─── Reviews.io Provider ─────────────────────────────────────────

class WchsReviewsIoProvider implements WchsReviewProvider {

	private string $store_id;
	private string $api_key;

	public function __construct( string $store_id, string $api_key ) {
		$this->store_id = $store_id;
		$this->api_key  = $api_key;
	}

	public function name(): string {
		return 'Reviews.io';
	}

	public function supports_write(): bool {
		return false;
	}

	public function create_review( int $product_id, int $user_id, int $rating, string $content ): array {
		return [ 'error' => 'Reviews are managed by Reviews.io' ];
	}

	public function get_reviews( int $product_id, int $per_page, int $page ): array {
		if ( ! $this->store_id || ! $this->api_key ) {
			return wchs_empty_reviews();
		}

		$sku = wchs_product_sku( $product_id );
		if ( ! $sku ) {
			return wchs_empty_reviews();
		}

		$cache_key = "wchs_reviewsio_{$sku}_{$per_page}_{$page}";
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		$offset = ( $page - 1 ) * $per_page;
		$url    = sprintf(
			'https://api.reviews.io/product/review?store=%s&sku=%s&per_page=%d&offset=%d',
			urlencode( $this->store_id ),
			urlencode( $sku ),
			$per_page,
			$offset
		);

		$response = wp_remote_get( $url, [
			'timeout' => 8,
			'headers' => [
				'apikey' => $this->api_key,
			],
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'WCHS Reviews.io API error: ' . $response->get_error_message() );
			return wchs_empty_reviews();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return wchs_empty_reviews();
		}

		$raw_reviews = $body['reviews'] ?? [];
		$reviews     = [];

		foreach ( $raw_reviews as $r ) {
			$reviews[] = [
				'id'       => (int) ( $r['review_id'] ?? $r['id'] ?? 0 ),
				'author'   => sanitize_text_field( $r['reviewer'] ?? $r['author'] ?? 'Customer' ),
				'date'     => date( 'c', strtotime( $r['date'] ?? $r['datetime'] ?? 'now' ) ),
				'rating'   => max( 1, min( 5, (int) ( $r['rating'] ?? 5 ) ) ),
				'content'  => sanitize_textarea_field( $r['comments'] ?? $r['text'] ?? '' ),
				'verified' => ! empty( $r['verified_buyer'] ),
				'images'   => [], // Reviews.io doesn't return images in standard endpoint
			];
		}

		$totals  = $body['stats'] ?? $body['totals'] ?? [];
		$average = (float) ( $totals['average'] ?? $totals['average_rating'] ?? 0 );
		$count   = (int) ( $totals['count'] ?? $totals['review_count'] ?? $body['total'] ?? count( $reviews ) );

		$result = [
			'reviews'      => $reviews,
			'average'      => $average,
			'count'        => $count,
			'distribution' => [ 0, 0, 0, 0, 0 ],
		];

		set_transient( $cache_key, $result, 300 );
		return $result;
	}
}

// ─── Mock Provider (Testing) ─────────────────────────────────────

class WchsMockProvider implements WchsReviewProvider {

	public function name(): string {
		return 'Mock (Testing)';
	}

	public function supports_write(): bool {
		return false;
	}

	public function create_review( int $product_id, int $user_id, int $rating, string $content ): array {
		return [ 'error' => 'Mock provider does not accept writes' ];
	}

	public function get_reviews( int $product_id, int $per_page, int $page ): array {
		if ( $page > 1 ) {
			return wchs_empty_reviews();
		}

		$reviews = [
			[
				'id'       => 9001,
				'author'   => 'Sarah M.',
				'date'     => date( 'c', strtotime( '-3 days' ) ),
				'rating'   => 5,
				'content'  => 'Excellent quality product. Arrived quickly, was well-packaged, and matched the description.',
				'verified' => true,
				'images'   => [],
			],
			[
				'id'       => 9002,
				'author'   => 'James K.',
				'date'     => date( 'c', strtotime( '-1 week' ) ),
				'rating'   => 4,
				'content'  => 'Good product overall. Documentation was thorough and support answered my questions quickly.',
				'verified' => true,
				'images'   => [],
			],
			[
				'id'       => 9003,
				'author'   => 'Jordan P.',
				'date'     => date( 'c', strtotime( '-2 weeks' ) ),
				'rating'   => 5,
				'content'  => 'We have been ordering for six months now. Quality has been consistent, and reorders have been easy.',
				'verified' => false,
				'images'   => [],
			],
			[
				'id'       => 9004,
				'author'   => 'Mike T.',
				'date'     => date( 'c', strtotime( '-3 weeks' ) ),
				'rating'   => 4,
				'content'  => 'Solid product. Will definitely be reordering. Customer support was also very responsive when I had questions.',
				'verified' => true,
				'images'   => [],
			],
			[
				'id'       => 9005,
				'author'   => 'Anna L.',
				'date'     => date( 'c', strtotime( '-1 month' ) ),
				'rating'   => 5,
				'content'  => 'Third order from here. Never disappointed. The attention to detail in packaging and documentation sets this apart from other suppliers.',
				'verified' => true,
				'images'   => [],
			],
		];

		$sliced = array_slice( $reviews, 0, $per_page );
		$total  = 0;
		$sum    = 0;
		$dist   = [ 0, 0, 0, 0, 0 ];
		foreach ( $reviews as $r ) {
			$total++;
			$sum += $r['rating'];
			$dist[ $r['rating'] - 1 ]++;
		}

		return [
			'reviews'      => $sliced,
			'average'      => $total > 0 ? round( $sum / $total, 2 ) : 0,
			'count'        => $total,
			'distribution' => $dist,
		];
	}
}
