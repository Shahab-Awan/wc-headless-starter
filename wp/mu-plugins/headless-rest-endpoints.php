<?php
/**
 * Plugin Name: Headless REST Endpoints
 * Description: Custom REST routes for things the Store API does not cover — product reviews and current-user order history.
 * Version:     0.1.0
 * Author:      WCHS Contributors
*
 * Routes
 *   GET    /wp-json/wchs/v1/config                     — SPA config payload
 *   GET    /wp-json/wchs/v1/reviews/{product_id}      — sanitized reviews list
 *   POST   /wp-json/wchs/v1/reviews/{product_id}      — create review
 *   GET    /wp-json/wchs/v1/reviews/aggregate         — review slider aggregate
 *   GET    /wp-json/wchs/v1/session                   — current auth/session shape
 *   DELETE /wp-json/wchs/v1/session                   — logout
 *   GET    /wp-json/wchs/v1/my-orders                 — current user's orders (cookie auth)
 *   POST   /wp-json/wchs/v1/newsletter                — newsletter signup
 *   POST   /wp-json/wchs/v1/contact                   — contact form submit
 *   GET    /wp-json/wchs/v1/order-payment/{id}?key=   — thank-you/payment details
 *   GET    /wp-json/wchs/v1/coa-library               — products with COA PDFs attached
 *
 * Security posture
 *   - Reviews: public, read-only, capped at 20 per request, only "approved"
 *     comments, only author name / rating / date / content (no email, no IP).
 *   - My-orders: requires `is_user_logged_in()` from the existing WP
 *     cookie session. Does NOT accept user_id params — always uses
 *     get_current_user_id(). No admin-visible fields are exposed.
 *   - Both routes are rate-limit-friendly: simple transient-based limiter
 *     (10 req/min/IP) prevents obvious scraping. Real rate limiting
 *     belongs at nginx; this is belt-and-suspenders.
 */

defined( 'ABSPATH' ) || exit;

/**
 * When a split_value module has no hero image, use the first published
 * product's featured (or first gallery) image from the catalog.
 *
 * @param array<int, array<string, mixed>> $mods Homepage/shop/page modules.
 * @return array<int, array<string, mixed>>
 */
function wchs_enrich_split_value_module_images( array $mods ): array {
	static $fallback = null;
	$out             = [];
	foreach ( $mods as $m ) {
		if ( ! is_array( $m ) ) {
			continue;
		}
		if ( ( $m['type'] ?? '' ) !== 'split_value' ) {
			$out[] = $m;
			continue;
		}
		$cfg = is_array( $m['config'] ?? null ) ? $m['config'] : [];
		if ( ! empty( $cfg['image'] ) ) {
			$out[] = $m;
			continue;
		}
		if ( null === $fallback ) {
			$fallback = [ 'src' => '', 'alt' => '' ];
			$uploads  = wp_upload_dir();
			if ( empty( $uploads['error'] ) ) {
				$rel = '2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp';
				$abs = trailingslashit( $uploads['basedir'] ) . $rel;
				if ( file_exists( $abs ) ) {
					$fallback = [
						'src' => trailingslashit( $uploads['baseurl'] ) . $rel,
						'alt' => 'Research-grade peptides — product lineup',
					];
				}
			}
			if ( '' === $fallback['src'] && function_exists( 'wc_get_products' ) ) {
				$products = wc_get_products(
					[
						'status'  => 'publish',
						'limit'   => 1,
						'orderby' => 'menu_order',
						'order'   => 'ASC',
					]
				);
				$p = ( is_array( $products ) && isset( $products[0] ) && $products[0] instanceof \WC_Product )
					? $products[0]
					: null;
				if ( $p ) {
					$img_id = (int) $p->get_image_id();
					$src    = '';
					if ( $img_id ) {
						$src = (string) ( wp_get_attachment_image_url( $img_id, 'woocommerce_single' )
							?: wp_get_attachment_image_url( $img_id, 'large' ) );
					}
					if ( '' === $src ) {
						$gids = $p->get_gallery_image_ids();
						if ( ! empty( $gids[0] ) ) {
							$src = (string) ( wp_get_attachment_image_url( (int) $gids[0], 'large' ) ?: '' );
						}
					}
					if ( '' !== $src ) {
						$fallback = [
							'src' => $src,
							'alt' => (string) $p->get_name(),
						];
					}
				}
			}
		}
		if ( ! empty( $fallback['src'] ) ) {
			$cfg['image'] = $fallback['src'];
			if ( ! isset( $cfg['image_alt'] ) || ! is_string( $cfg['image_alt'] ) || '' === trim( $cfg['image_alt'] ) ) {
				$cfg['image_alt'] = $fallback['alt'];
			}
		}
		$m['config'] = $cfg;
		$out[]       = $m;
	}
	return $out;
}

/**
 * Insert default feature_highlights before the catalog slider when the saved
 * homepage predates that module. Allows legacy trust_bar rows between
 * split_value and product_slider (the storefront hides trust_bar but it stays in JSON).
 *
 * @param array<int, array<string, mixed>> $mods
 * @return array<int, array<string, mixed>>
 */
function wchs_homepage_feature_highlights_insert_index( array $mods ): int {
	$n = count( $mods );
	for ( $i = 0; $i < $n; $i++ ) {
		$m = $mods[ $i ] ?? null;
		if ( ! is_array( $m ) || ( $m['type'] ?? '' ) !== 'split_value' ) {
			continue;
		}
		$j = $i + 1;
		while ( $j < $n && is_array( $mods[ $j ] ?? null ) ) {
			$gap_type = $mods[ $j ]['type'] ?? '';
			if ( 'trust_bar' === $gap_type || 'spacer' === $gap_type ) {
				$j++;
				continue;
			}
			break;
		}
		if ( $j < $n && is_array( $mods[ $j ] ?? null ) && ( $mods[ $j ]['type'] ?? '' ) === 'product_slider' ) {
			return $j;
		}
	}
	for ( $i = 0; $i < $n; $i++ ) {
		if ( is_array( $mods[ $i ] ?? null ) && ( $mods[ $i ]['type'] ?? '' ) === 'product_slider' ) {
			return $i;
		}
	}
	return -1;
}

function wchs_homepage_ensure_feature_highlights_module( array $mods ): array {
	foreach ( $mods as $m ) {
		if ( is_array( $m ) && ( $m['type'] ?? '' ) === 'feature_highlights' ) {
			return $mods;
		}
	}
	if ( ! class_exists( '\\WCHS\\Admin\\AdminPage' ) ) {
		return $mods;
	}
	$j = wchs_homepage_feature_highlights_insert_index( $mods );
	if ( $j < 0 ) {
		return $mods;
	}
	$defaults = \WCHS\Admin\AdminPage::homepage_defaults();
	$seed_row = null;
	foreach ( (array) ( $defaults['modules'] ?? [] ) as $dm ) {
		if ( is_array( $dm ) && ( $dm['type'] ?? '' ) === 'feature_highlights' ) {
			$seed_row = $dm;
			break;
		}
	}
	if ( ! $seed_row ) {
		return $mods;
	}
	$seed = json_decode( wp_json_encode( $seed_row ), true );
	if ( ! is_array( $seed ) ) {
		return $mods;
	}
	if ( empty( $seed['id'] ) || ! preg_match( '/^[a-z0-9]{8}$/', (string) $seed['id'] ) ) {
		$seed['id'] = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 );
	}
	array_splice( $mods, $j, 0, [ $seed ] );
	return $mods;
}

/**
 * Insert default order_handling immediately before the first accordion module.
 *
 * @param array<int, array<string, mixed>> $mods
 * @return array<int, array<string, mixed>>
 */
function wchs_homepage_ensure_order_handling_module( array $mods ): array {
	foreach ( $mods as $m ) {
		if ( is_array( $m ) && ( $m['type'] ?? '' ) === 'order_handling' ) {
			return $mods;
		}
	}
	if ( ! class_exists( '\\WCHS\\Admin\\AdminPage' ) ) {
		return $mods;
	}
	$acc_idx = -1;
	for ( $i = 0, $n = count( $mods ); $i < $n; $i++ ) {
		if ( is_array( $mods[ $i ] ?? null ) && ( $mods[ $i ]['type'] ?? '' ) === 'accordion' ) {
			$acc_idx = $i;
			break;
		}
	}
	if ( $acc_idx < 0 ) {
		return $mods;
	}
	$defaults = \WCHS\Admin\AdminPage::homepage_defaults();
	$seed_row = null;
	foreach ( (array) ( $defaults['modules'] ?? [] ) as $dm ) {
		if ( is_array( $dm ) && ( $dm['type'] ?? '' ) === 'order_handling' ) {
			$seed_row = $dm;
			break;
		}
	}
	if ( ! $seed_row ) {
		$seed_row = [
			'type'          => 'order_handling',
			'visibility'    => 'all',
			'spacing_v'     => 'normal',
			'spacing_h'     => 'normal',
			'center_header' => true,
			'config'        => [
				'badge_text'    => 'Our Process',
				'headline'      => 'How Every Order Is Handled',
				'subheadline'   => 'From verification to delivery, we ensure each step meets our highest standards.',
				'steps'         => [
					[
						'variant'     => 'verified',
						'headline'    => 'Verified Batches',
						'description' => 'Every batch undergoes rigorous quality control and verification before release.',
					],
					[
						'variant'     => 'lab',
						'headline'    => '3rd Party Testing',
						'description' => 'Independent laboratory testing ensures purity and consistency you can trust.',
					],
					[
						'variant'     => 'shipping',
						'headline'    => 'Ships Same Day',
						'description' => 'Discreetly packaged and dispatched within 24 hours from our U.S. facility.',
					],
					[
						'variant'     => 'support',
						'headline'    => '24/7 Support',
						'description' => 'Round-the-clock customer service for any questions before or after your order.',
					],
				],
				'metrics_title' => 'Quality Metrics',
				'metrics'       => [
					[ 'value' => '99.8%', 'label' => 'Batch Accuracy' ],
					[ 'value' => '100%', 'label' => 'Verified Testing' ],
					[ 'value' => '24/7', 'label' => 'Support Response' ],
				],
			],
		];
	}
	$seed = json_decode( wp_json_encode( $seed_row ), true );
	if ( ! is_array( $seed ) ) {
		return $mods;
	}
	if ( empty( $seed['id'] ) || ! preg_match( '/^[a-z0-9]{8}$/', (string) $seed['id'] ) ) {
		$seed['id'] = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 );
	}
	array_splice( $mods, $acc_idx, 0, [ $seed ] );
	return $mods;
}

/**
 * Prevent stale domain/origin drift from host-level caches after cutovers.
 *
 * SiteGround's dynamic cache can serve old JSON for GET /wchs/v1/config and
 * Woo Store API product endpoints even after home/siteurl are updated. These
 * routes drive the SPA's origin and product-image URLs, so stale cache creates
 * broken home/shop cards on the new domain. Mark them no-store at the REST
 * layer so future cutovers don't replay old payloads.
 */
add_filter(
	'rest_post_dispatch',
	function ( $result, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $result;
		}

		$method = strtoupper( $request->get_method() );
		if ( 'GET' !== $method ) {
			return $result;
		}

		$route = (string) $request->get_route();
		$match = (
			0 === strpos( $route, '/wchs/v1/config' ) ||
			0 === strpos( $route, '/wchs/v1/session' ) ||
			0 === strpos( $route, '/wc/store/v1/products' )
		);
		if ( ! $match ) {
			return $result;
		}

		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', '0' );
		return $response;
	},
	10,
	3
);

/**
 * Per-IP token-bucket rate limiter backed by transients. Limits are
 * per-bucket so a noisy /session check doesn't starve /my-orders.
 *
 * IMPORTANT FOR PRODUCTION:
 *   1. Set WP_DEBUG=false (rate limiting is disabled when WP_DEBUG is true)
 *   2. Configure real IP forwarding in nginx so REMOTE_ADDR is the real
 *      client IP. Without this, all visitors share one bucket (the proxy IP)
 *      and one bot locks out everyone. See SECURITY.md.
 *   3. This only covers custom /wchs/v1/ endpoints. WooCommerce Store API
 *      and WP REST API have ZERO built-in rate limiting. Add nginx or
 *      Cloudflare rate limits for /wp-json/ in production.
 *
 * Defaults per bucket (requests per 60s window):
 *   config         = 60    — SPA boots once per session, 60 is generous
 *   reviews_read   = 120   — public read, paginated browsing
 *   reviews_write  = 5     — writing reviews is rare; prevent spam
 *   my-orders      = 30    — legit user reloads + pagination
 *   session        = 120   — SPA polls on mount, tab focus, every nav
 *   session_delete = 10    — logout is rare; high ceiling would hide abuse
 */
function wchs_rest_rate_limit( string $bucket ): bool {
	// Skip rate limiting in local dev — all requests share the same proxy
	// IP, so a test suite or rapid browsing burns through the budget and
	// locks out the developer's own browser.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return true;
	}

	// Admin-toggleable bypass — set when an upstream host (Siteground
	// sg-cachepress, Cloudflare, nginx) already provides rate limiting.
	// Defaults to enabled; site owner flips off under Access & Privacy.
	if ( class_exists( '\WCHS\Admin\AdminPage' ) ) {
		$settings = \WCHS\Admin\AdminPage::get_site_settings();
		if ( empty( $settings['internal_rate_limit_enabled'] ) ) {
			return true;
		}
	}

	$limits = [
		'config'         => 60,
		'reviews_read'   => 120,
		'reviews_write'  => 5,
		'my-orders'      => 30,
		'session'        => 120,
		'session_delete' => 10,
		'coa_library'    => 60,
	];
	$max = $limits[ $bucket ] ?? 10;

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key = 'wchs_rl_' . md5( $bucket . '|' . $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= $max ) {
		return false;
	}
	set_transient( $key, $hits + 1, 60 );
	return true;
}

/**
 * Resolve the current user from the logged-in cookie WITHOUT WP REST's
 * mandatory nonce rule.
 *
 * Why: `is_user_logged_in()` / `get_current_user_id()` are zeroed-out in
 * REST context when the request lacks a valid `X-WP-Nonce` (WP's CSRF
 * defense for cookie-authed REST calls). Our SPA is cross-origin and has
 * no way to mint that nonce. We read the HMAC-signed `wordpress_logged_in_*`
 * cookie directly with `wp_validate_auth_cookie()`, which uses WP's secret
 * keys — an attacker cannot forge it.
 *
 * This is safe to use for READ endpoints. For WRITES, require a matching
 * `Origin` header (enforced in CORS layer) so a hostile third-party origin
 * cannot ride the cookie via CSRF.
 *
 * Returns a WP_User on success, or null if not authenticated.
 */
function wchs_current_user_from_cookie(): ?\WP_User {
	$cookie = null;
	foreach ( $_COOKIE as $name => $value ) {
		if ( strpos( (string) $name, 'wordpress_logged_in_' ) === 0 ) {
			$cookie = (string) $value;
			break;
		}
	}
	if ( null === $cookie ) {
		return null;
	}
	$user_id = wp_validate_auth_cookie( $cookie, 'logged_in' );
	if ( ! $user_id ) {
		return null;
	}
	$user = get_userdata( (int) $user_id );
	return $user instanceof \WP_User ? $user : null;
}

/**
 * Require the request's Origin header to be in the allowlist. Used to
 * gate state-changing auth endpoints (logout). Returns true when the
 * Origin is allowed OR when there is no Origin header (same-origin PHP
 * or server-side cURL). Returns false for a cross-origin hostile call.
 */
function wchs_require_allowed_origin(): bool {
	$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
	if ( '' === $origin ) {
		return true; // same-origin or no-CORS request, not a CSRF vector
	}
	if ( function_exists( 'wchs_is_allowed_origin' ) ) {
		return wchs_is_allowed_origin( $origin );
	}
	return false;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wchs/v1',
			'/config',
			[
				'methods'             => 'GET',
				'callback'            => 'wchs_rest_config',
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'wchs/v1',
			'/reviews/(?P<product_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => 'wchs_rest_reviews',
				'permission_callback' => '__return_true',
				'args'                => [
					'product_id' => [
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
						'sanitize_callback' => 'absint',
					],
					'per_page'   => [
						'default'           => 10,
						'sanitize_callback' => function ( $value ) {
							return max( 1, min( 20, (int) $value ) );
						},
					],
					'page'       => [
						'default'           => 1,
						'sanitize_callback' => function ( $value ) {
							return max( 1, (int) $value );
						},
					],
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/reviews/aggregate',
			[
				'methods'             => 'GET',
				'callback'            => 'wchs_rest_reviews_aggregate',
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'wchs/v1',
			'/reviews/(?P<product_id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => 'wchs_rest_create_review',
				'permission_callback' => function () {
					$user = wchs_current_user_from_cookie();
					return $user && ! is_wp_error( $user );
				},
				'args'                => [
					'product_id' => [
						'validate_callback' => function ( $value ) { return is_numeric( $value ) && (int) $value > 0; },
						'sanitize_callback' => 'absint',
					],
					'rating'  => [
						'required' => true,
						'validate_callback' => function ( $v ) { return is_numeric( $v ) && (int) $v >= 1 && (int) $v <= 5; },
						'sanitize_callback' => 'absint',
					],
					'content' => [
						'required' => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					],
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/session',
			[
				[
					'methods'             => 'GET',
					'callback'            => 'wchs_rest_session_get',
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => 'DELETE',
					'callback'            => 'wchs_rest_session_delete',
					'permission_callback' => function () {
						// Logout is state-changing; require a valid auth
						// cookie AND an allowlisted Origin to block CSRF.
						if ( ! wchs_require_allowed_origin() ) {
							return new \WP_Error( 'forbidden_origin', 'Origin not allowed', [ 'status' => 403 ] );
						}
						return wchs_current_user_from_cookie() instanceof \WP_User;
					},
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/my-orders',
			[
				'methods'             => 'GET',
				'callback'            => 'wchs_rest_my_orders',
				'permission_callback' => function () {
					return wchs_current_user_from_cookie() instanceof \WP_User;
				},
				'args'                => [
					'per_page' => [
						'default'           => 10,
						'sanitize_callback' => function ( $value ) {
							return max( 1, min( 50, (int) $value ) );
						},
					],
					'page'     => [
						'default'           => 1,
						'sanitize_callback' => function ( $value ) {
							return max( 1, (int) $value );
						},
					],
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/contact',
			[
				'methods'             => 'POST',
				'callback'            => 'wchs_rest_contact_submit',
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'wchs/v1',
			'/order-payment/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => 'wchs_rest_order_payment',
				'permission_callback' => '__return_true',
				'args'                => [
					'id'  => [ 'required' => true, 'type' => 'integer' ],
					'key' => [ 'required' => true, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/newsletter',
			[
				'methods'             => 'POST',
				'callback'            => 'wchs_rest_newsletter_subscribe',
				'permission_callback' => '__return_true',
				'args'                => [
					'email' => [ 'required' => true, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/coa-library',
			[
				'methods'             => 'GET',
				'callback'            => 'wchs_rest_coa_library',
				'permission_callback' => '__return_true',
			]
		);
	}
);

/**
 * GET /wchs/v1/coa-library — published products/variations with a COA PDF URL.
 *
 * @return \WP_REST_Response|\WP_Error
 */
function wchs_rest_coa_library( \WP_REST_Request $request ) {
	if ( ! wchs_rest_rate_limit( 'coa_library' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests', [ 'status' => 429 ] );
	}

	if ( ! function_exists( 'wc_get_product' ) ) {
		return rest_ensure_response( [ 'products' => [] ] );
	}

	$ids = get_posts(
		[
			'post_type'      => [ 'product', 'product_variation' ],
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_wchs_coa_url',
					'value'   => '',
					'compare' => '!=',
				],
			],
		]
	);

	$groups = [];

	foreach ( $ids as $post_id ) {
		$post_id = (int) $post_id;
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			continue;
		}

		$url = esc_url_raw( (string) get_post_meta( $post_id, '_wchs_coa_url', true ) );
		if ( '' === $url ) {
			continue;
		}

		$parent_id = (int) $product->get_parent_id();
		if ( $parent_id > 0 ) {
			$parent = wc_get_product( $parent_id );
			if ( ! $parent || 'publish' !== $parent->get_status() ) {
				continue;
			}
			$group_id = $parent_id;
			$name     = $parent->get_name();
			$slug     = $parent->get_slug();
		} else {
			$group_id = $post_id;
			$name     = $product->get_name();
			$slug     = $product->get_slug();
		}

		$variation_label = '';
		if ( $parent_id > 0 && function_exists( 'wc_get_formatted_variation' ) ) {
			$variation_label = wc_get_formatted_variation( $product, true, false );
		}

		$post     = get_post( $post_id );
		$modified = $post instanceof \WP_Post ? get_post_modified_time( 'c', false, $post ) : '';

		if ( ! isset( $groups[ $group_id ] ) ) {
			$groups[ $group_id ] = [
				'id'           => $group_id,
				'name'         => $name,
				'slug'         => $slug,
				'certificates' => [],
			];
		}

		$groups[ $group_id ]['certificates'][] = [
			'id'              => $post_id,
			'variation_label' => $variation_label,
			'coa_url'         => $url,
			'batch'           => (string) get_post_meta( $post_id, '_wchs_coa_batch', true ),
			'lab'             => (string) get_post_meta( $post_id, '_wchs_coa_lab', true ),
			'tested'          => $modified,
		];
	}

	$list = array_values( $groups );
	usort(
		$list,
		static function ( array $a, array $b ): int {
			return strcasecmp( (string) $a['name'], (string) $b['name'] );
		}
	);

	return rest_ensure_response( [ 'products' => $list ] );
}

/**
 * POST /wchs/v1/newsletter — footer newsletter signup.
 *
 * Forwards to the Omnisend contacts API if a key is configured; otherwise
 * stores the email in the `wchs_newsletter_signups` option as a fallback
 * buffer (last 500 entries) the admin can drain into their mailing tool.
 */
function wchs_omnisend_api_key(): string {
	if ( defined( 'OMNISEND_API_KEY' ) && is_string( OMNISEND_API_KEY ) ) {
		return trim( OMNISEND_API_KEY );
	}

	foreach ( [ 'omnisend_api_key', 'omnisend-api-key' ] as $option ) {
		$key = trim( (string) get_option( $option, '' ) );
		if ( '' !== $key ) {
			return $key;
		}
	}

	return '';
}

function wchs_omnisend_upsert_contact( string $email, string $status, array $tags = [], array $profile = [], array $custom_properties = [] ): array {
	$api_key = wchs_omnisend_api_key();
	if ( '' === $api_key ) {
		return [ 'ok' => false, 'source' => 'none', 'message' => 'Omnisend API key is not configured.' ];
	}

	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return [ 'ok' => false, 'source' => 'omnisend', 'message' => 'Invalid email.' ];
	}

	$status = in_array( $status, [ 'subscribed', 'nonSubscribed' ], true ) ? $status : 'nonSubscribed';
	$tags   = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $tags ) ) ) );

	$payload = [
		'identifiers' => [
			[
				'type'     => 'email',
				'id'       => $email,
				'channels' => [
					'email' => [
						'status'     => $status,
						'statusDate' => gmdate( DATE_ATOM ),
					],
				],
			],
		],
		'tags'        => array_slice( $tags, 0, 100 ),
	];

	foreach ( [ 'firstName', 'lastName', 'phone' ] as $key ) {
		if ( ! empty( $profile[ $key ] ) ) {
			$payload[ $key ] = sanitize_text_field( (string) $profile[ $key ] );
		}
	}

	if ( ! empty( $custom_properties ) ) {
		$payload['customProperties'] = [];
		foreach ( $custom_properties as $key => $value ) {
			$prop_key = preg_replace( '/[^A-Za-z0-9_]/', '_', (string) $key );
			if ( '' === $prop_key ) {
				continue;
			}
			$payload['customProperties'][ $prop_key ] = is_scalar( $value )
				? sanitize_text_field( (string) $value )
				: wp_json_encode( $value );
		}
	}

	$response = wp_remote_post(
		'https://api.omnisend.com/api/contacts',
		[
			'timeout' => 5,
			'headers' => [
				'Authorization'     => 'Omnisend-API-Key ' . $api_key,
				'Omnisend-Version'  => '2026-03-15',
				'Accept'            => 'application/json',
				'Content-Type'      => 'application/json',
			],
			'body'    => wp_json_encode( $payload ),
		]
	);

	if ( is_wp_error( $response ) ) {
		return [ 'ok' => false, 'source' => 'omnisend', 'message' => $response->get_error_message() ];
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code >= 200 && $code < 300 ) {
		return [ 'ok' => true, 'source' => 'omnisend', 'status' => $code ];
	}

	return [
		'ok'      => false,
		'source'  => 'omnisend',
		'status'  => $code,
		'message' => wp_remote_retrieve_body( $response ),
	];
}

function wchs_rest_newsletter_subscribe( \WP_REST_Request $request ) {
	$ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	$bucket = 'newsletter_' . md5( $ip );
	$count  = (int) get_transient( $bucket );
	if ( $count >= 5 ) {
		return new \WP_REST_Response( [ 'code' => 'rate_limited', 'message' => 'Too many attempts.' ], 429 );
	}
	set_transient( $bucket, $count + 1, 900 );

	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( ! $email || ! is_email( $email ) ) {
		return new \WP_REST_Response( [ 'code' => 'invalid_email', 'message' => 'Provide a valid email.' ], 400 );
	}

	$source = 'fallback';

	$omnisend = wchs_omnisend_upsert_contact(
		$email,
		'subscribed',
		[ 'source: form', 'wchs:newsletter', 'form:footer' ],
		[],
		[
			'wchsSource' => 'footer_newsletter',
			'wchsSite'   => home_url( '/' ),
		]
	);
	if ( ! empty( $omnisend['ok'] ) ) {
		$source = 'omnisend';
	}

	if ( $source === 'fallback' ) {
		// Store in option; trim to last 500 signups so it doesn't grow unbounded
		$list = get_option( 'wchs_newsletter_signups', [] );
		if ( ! is_array( $list ) ) $list = [];
		$list[] = [
			'email' => $email,
			'at'    => time(),
			'ip'    => $ip,
			'error' => $omnisend['message'] ?? 'Omnisend unavailable or not configured.',
		];
		if ( count( $list ) > 500 ) $list = array_slice( $list, -500 );
		update_option( 'wchs_newsletter_signups', $list, false );
	}

	return new \WP_REST_Response( [ 'ok' => true, 'source' => $source ], 200 );
}

/**
 * POST /wchs/v1/contact — contact form submission
 */
function wchs_rest_contact_submit( \WP_REST_Request $request ) {
	// Rate limit: 5 per 15 minutes per IP
	$ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	$bucket = 'contact_' . md5( $ip );
	$count  = (int) get_transient( $bucket );
	if ( $count >= 5 ) {
		return new \WP_REST_Response(
			[ 'code' => 'rate_limited', 'message' => 'Too many submissions. Please try again later.' ],
			429
		);
	}
	set_transient( $bucket, $count + 1, 900 ); // 15 min window

	$body   = $request->get_json_params();
	$fields = $body['fields'] ?? [];
	$to     = sanitize_email( $body['recipient_email'] ?? '' );
	$prefix = sanitize_text_field( $body['subject_prefix'] ?? '' );
	$token  = sanitize_text_field( $body['turnstile_token'] ?? '' );

	// Verify Turnstile
	if ( function_exists( 'wchs_verify_turnstile' ) && ! wchs_verify_turnstile( $token ) ) {
		return new \WP_REST_Response(
			[ 'code' => 'bot_check_failed', 'message' => 'Bot verification failed. Please try again.' ],
			403
		);
	}

	if ( ! $to || ! is_email( $to ) ) {
		return new \WP_REST_Response(
			[ 'code' => 'invalid_config', 'message' => 'Contact form is not configured correctly.' ],
			500
		);
	}

	if ( empty( $fields ) || ! is_array( $fields ) ) {
		return new \WP_REST_Response(
			[ 'code' => 'empty_submission', 'message' => 'No form data received.' ],
			400
		);
	}

	// Build email
	$lines   = [];
	$reply_to = '';
	$profile = [];
	foreach ( $fields as $key => $value ) {
		$safe_key   = sanitize_text_field( $key );
		$raw_value  = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		$safe_value = sanitize_textarea_field( $raw_value );
		$lines[]    = ucfirst( str_replace( '_', ' ', $safe_key ) ) . ': ' . $safe_value;
		$normalized_key = strtolower( preg_replace( '/[^a-z0-9]+/i', '_', $safe_key ) );
		if ( in_array( $normalized_key, [ 'email', 'email_address' ], true ) && is_email( $safe_value ) ) {
			$reply_to = $safe_value;
		}
		if ( in_array( $normalized_key, [ 'first_name', 'firstname' ], true ) && '' !== $safe_value ) {
			$profile['firstName'] = $safe_value;
		}
		if ( in_array( $normalized_key, [ 'last_name', 'lastname' ], true ) && '' !== $safe_value ) {
			$profile['lastName'] = $safe_value;
		}
		if ( in_array( $normalized_key, [ 'phone', 'phone_number', 'telephone' ], true ) && '' !== $safe_value ) {
			$profile['phone'] = $safe_value;
		}
		if ( 'name' === $normalized_key && '' !== $safe_value && empty( $profile['firstName'] ) && empty( $profile['lastName'] ) ) {
			$name_parts = preg_split( '/\s+/', trim( $safe_value ), 2 );
			$profile['firstName'] = $name_parts[0] ?? '';
			$profile['lastName']  = $name_parts[1] ?? '';
		}
	}

	$subject = ( $prefix ? $prefix . ' ' : '' ) . 'New contact form submission';
	$message = implode( "\n\n", $lines );
	$message .= "\n\n---\nSubmitted from: " . esc_url( wp_get_referer() ?: home_url() );
	$message .= "\nIP: " . $ip;
	$message .= "\nTime: " . current_time( 'mysql' );

	$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
	if ( $reply_to ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}

	$sent = wp_mail( $to, $subject, $message, $headers );

	if ( ! $sent ) {
		return new \WP_REST_Response(
			[ 'code' => 'mail_failed', 'message' => 'Failed to send message. Please try again later.' ],
			500
		);
	}

	$marketing = [ 'ok' => false, 'source' => 'none' ];
	if ( $reply_to ) {
		$marketing = wchs_omnisend_upsert_contact(
			$reply_to,
			'nonSubscribed',
			[ 'source: form', 'wchs:contact_form' ],
			$profile,
			[
				'wchsSource'        => 'contact_form',
				'wchsLastContactAt' => gmdate( DATE_ATOM ),
				'wchsSubjectPrefix' => $prefix,
				'wchsSite'          => home_url( '/' ),
			]
		);
	}

	return [
		'success'          => true,
		'marketing_source' => ! empty( $marketing['ok'] ) ? 'omnisend' : ( $marketing['source'] ?? 'none' ),
	];
}

/**
 * GET /wchs/v1/config
 *
 * Public config blob the SPA fetches on boot. Contains everything the
 * frontend needs to know about *this specific site*: WP origin, allowed
 * SPA origin, brand name, currency, and feature flags.
 *
 * Per-site configuration defaults to the site's own public origin
 * (`home_url()`). Split-origin or local-dev setups can opt into custom
 * values from WCHS Settings or legacy wp-config.php constants:
 *   define('WCHS_SPA_URL',         'https://shop.example.com');
 *   define('WCHS_ALLOWED_ORIGINS', 'https://shop.example.com');
 *   define('WCHS_RETURN_ORIGINS',  'https://shop.example.com');
 *   define('WCHS_BRAND_NAME',      'Example Shop');
 *
 * The SPA calls GET /wp/wp-json/wchs/v1/config once on boot, caches the
 * result, and every other piece of code reads origins from that store.
 * This lets one SPA build serve many sites — origin is per-deploy, not
 * baked into the bundle.
 */
function wchs_rest_config( \WP_REST_Request $request ) {
	$wp_origin  = function_exists( 'wchs_public_origin' ) ? wchs_public_origin() : untrailingslashit( home_url( '/' ) );
	$allowed    = function_exists( 'wchs_allowed_origin_list' ) ? wchs_allowed_origin_list() : wchs_allowed_origins();
	$returns    = function_exists( 'wchs_return_origin_list' ) ? wchs_return_origin_list() : wchs_allowed_return_origins();
	$spa_origin = function_exists( 'wchs_spa_origin' ) ? wchs_spa_origin() : ( $allowed[0] ?? $wp_origin );
	$mode       = function_exists( 'wchs_origin_mode' ) ? wchs_origin_mode() : 'custom';

	$currency_code = function_exists( 'get_woocommerce_currency' )
		? get_woocommerce_currency()
		: 'USD';
	$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
		? html_entity_decode( get_woocommerce_currency_symbol( $currency_code ), ENT_QUOTES, 'UTF-8' )
		: '$';

	$brand_name = defined( 'WCHS_BRAND_NAME' ) && is_string( WCHS_BRAND_NAME )
		? WCHS_BRAND_NAME
		: get_bloginfo( 'name' );

	$logo_id  = (int) get_theme_mod( 'custom_logo', 0 );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : null;
	$logo_full_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : null;

	$site_settings  = \WCHS\Admin\AdminPage::get_site_settings();

	$dark_logo_id  = (int) ( $site_settings['logo_dark_id'] ?? 0 );
	$dark_logo_url = $dark_logo_id ? wp_get_attachment_image_url( $dark_logo_id, 'medium' ) : null;
	$dark_logo_full_url = $dark_logo_id ? wp_get_attachment_image_url( $dark_logo_id, 'full' ) : null;
	$homepage       = \WCHS\Admin\AdminPage::get_homepage_config();

	// Migrate legacy edge_to_edge → spacing_h on modules
	$_migrate_mods = function ( array $mods ): array {
		foreach ( $mods as &$m ) {
			if ( ! isset( $m['spacing_h'] ) ) {
				$m['spacing_h'] = ! empty( $m['edge_to_edge'] ) ? 'compact' : 'normal';
			}
			if ( ! isset( $m['spacing_v'] ) ) {
				$m['spacing_v'] = 'normal';
			}
			unset( $m['edge_to_edge'] );
		}
		return $mods;
	};
	$homepage['modules'] = $_migrate_mods( $homepage['modules'] ?? [] );
	$homepage['modules'] = wchs_homepage_ensure_feature_highlights_module( $homepage['modules'] );
	$homepage['modules'] = wchs_homepage_ensure_order_handling_module( $homepage['modules'] );

	// Merge site defaults + per-module overrides into a `resolved` block on
	// each module. SPA components read module.resolved instead of
	// reaching into site settings for every token, so overriding one
	// module's accent or font just works.
	$_resolve_mods = function ( array $mods ) use ( $site_settings ): array {
		if ( ! class_exists( '\\WCHS\\Admin\\ResolverService' ) ) {
			return $mods;
		}
		return \WCHS\Admin\ResolverService::resolve_modules( $mods, $site_settings );
	};
	$homepage['modules'] = wchs_enrich_split_value_module_images( $_resolve_mods( $homepage['modules'] ) );

	// Auto-detect free-shipping threshold from WC shipping zones. The
	// cart uses this to render an "Add $X more for FREE shipping" bar.
	// Returns 0 when no free_shipping method is configured or when all
	// configured ones have a 0/absent min_amount. First match wins — if
	// the store has multiple zones with different thresholds we pick the
	// lowest positive one (most generous to the shopper).
	$shipping_free_threshold = 0.0;
	if ( function_exists( 'WC' ) && class_exists( 'WC_Shipping_Zones' ) ) {
		$zones = WC_Shipping_Zones::get_zones();
		$rest  = WC_Shipping_Zones::get_zone( 0 ); // rest-of-world
		if ( $rest ) {
			$zones[] = [ 'shipping_methods' => $rest->get_shipping_methods() ];
		}
		$min = 0.0;
		foreach ( $zones as $z ) {
			foreach ( ( $z['shipping_methods'] ?? [] ) as $m ) {
				if ( $m->id !== 'free_shipping' ) continue;
				$amt = (float) ( $m->min_amount ?? 0 );
				if ( $amt > 0 && ( $min === 0.0 || $amt < $min ) ) {
					$min = $amt;
				}
			}
		}
		$shipping_free_threshold = $min;
	}
	$pdp            = \WCHS\Admin\AdminPage::get_pdp_config();
	$pdp['modules'] = $_resolve_mods( $_migrate_mods( $pdp['modules'] ?? [] ) );
	if ( function_exists( 'wchs_cro_cart_cross_sell_default_exclude_slugs' ) ) {
		$slide = is_array( $pdp['slide_cart'] ?? null ) ? $pdp['slide_cart'] : [];
		$slide['cross_sell_exclude_slugs'] = array_values(
			array_unique(
				array_merge(
					wchs_cro_cart_cross_sell_default_exclude_slugs(),
					(array) ( $slide['cross_sell_exclude_slugs'] ?? [] )
				)
			)
		);
		if ( function_exists( 'wchs_cro_cart_cross_sell_excluded_product_ids' ) ) {
			$slide['cross_sell_exclude_product_ids'] = array_values(
				array_unique(
					array_map(
						'intval',
						array_merge(
							(array) ( $slide['cross_sell_exclude_product_ids'] ?? [] ),
							wchs_cro_cart_cross_sell_excluded_product_ids()
						)
					)
				)
			);
		}
		$pdp['slide_cart'] = $slide;
	}
	$shop_cfg       = \WCHS\Admin\AdminPage::get_shop_config();
	$shop_cfg['modules'] = $_resolve_mods( $_migrate_mods( $shop_cfg['modules'] ?? [] ) );
	if ( ! isset( $shop_cfg['spacing_h'] ) && isset( $shop_cfg['edge_to_edge'] ) ) {
		$shop_cfg['spacing_h'] = $shop_cfg['edge_to_edge'] ? 'compact' : 'normal';
	}
	unset( $shop_cfg['edge_to_edge'] );
	$pages_cfg = \WCHS\Admin\AdminPage::get_pages_config();
	if ( ! empty( $pages_cfg['pages'] ) && is_array( $pages_cfg['pages'] ) ) {
		foreach ( $pages_cfg['pages'] as $pi => $pg ) {
			$pages_cfg['pages'][ $pi ]['modules'] = $_resolve_mods( $_migrate_mods( $pg['modules'] ?? [] ) );
		}
	}
	$accent         = $site_settings['accent_color'] ?? null;
	if ( ! is_string( $accent ) ) $accent = null;

	return [
		'wp_origin'       => $wp_origin,
		'spa_origin'      => $spa_origin,
		'origin_mode'            => $mode,
		'allowed_origins'        => $allowed,
		'return_origins'         => $returns,
		'brand_name'              => $brand_name,
		'static_seo_title'        => $site_settings['static_seo_title'] ?? '',
		'static_seo_description'  => $site_settings['static_seo_description'] ?? '',
		'logo_url'                => $logo_url,
		'logo_dark_url'           => $dark_logo_url,
		'logo_full_url'           => $logo_full_url,
		'logo_dark_full_url'      => $dark_logo_full_url,
		'currency_code'           => $currency_code,
		'currency_symbol'         => $currency_symbol,
		'shipping_free_threshold' => $shipping_free_threshold,
		'features'        => [
			'guest_checkout' => (bool) ( 'yes' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ) ),
			'dark_mode'      => false,
			'pretext'        => true,
		],
		'version'         => '0.1.0',
		'access_mode'     => (int) ( $site_settings['access_mode'] ?? 3 ),
		'gtm_id'          => $site_settings['gtm_id'] ?? '',
		'omnisend_brand_id'           => $site_settings['omnisend_brand_id'] ?? '',
		'klaviyo_public_key'          => $site_settings['klaviyo_public_key'] ?? '',
		'meta_pixel_id'               => $site_settings['meta_pixel_id'] ?? '',
		'tiktok_pixel_id'             => $site_settings['tiktok_pixel_id'] ?? '',
		'pinterest_tag_id'            => $site_settings['pinterest_tag_id'] ?? '',
		'clarity_project_id'          => $site_settings['clarity_project_id'] ?? '',
		'hotjar_site_id'              => $site_settings['hotjar_site_id'] ?? '',
		'google_ads_conversion_id'    => $site_settings['google_ads_conversion_id'] ?? '',
		'google_ads_conversion_label' => $site_settings['google_ads_conversion_label'] ?? '',
		'accent_color'    => $accent,
		'accent_fg'       => \WCHS\Admin\AdminPage::get_accent_fg( $accent ),
		'review_write_enabled' => function_exists( 'wchs_get_review_provider' ) ? wchs_get_review_provider()->supports_write() : true,
		'turnstile_site_key' => ! empty( $site_settings['anti_bot_enabled'] ) ? ( $site_settings['turnstile_site_key'] ?? '' ) : '',
		'internal_rate_limit_enabled' => (bool) ( $site_settings['internal_rate_limit_enabled'] ?? true ),
		'announcement_bar_enabled' => (bool) ( $site_settings['announcement_bar_enabled'] ?? true ),
		'announcement_bar_items'   => array_values(
			array_filter(
				array_map(
					'strval',
					is_array( $site_settings['announcement_bar_items'] ?? null )
						? $site_settings['announcement_bar_items']
						: []
				)
			)
		),
		'header_links'    => $site_settings['header_links'] ?? [
			[ 'label' => 'Shop', 'url' => '/shop', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
			[ 'label' => 'COA Library', 'url' => '/coa-library', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
			[ 'label' => 'Account', 'url' => '/account', 'display' => 'icon', 'icon' => 'user', 'accent' => true, 'mobile_pin' => false ],
		],
		'header_toggle_accent'     => $site_settings['header_toggle_accent'] ?? true,
		'header_cart_accent'       => $site_settings['header_cart_accent'] ?? true,
		'header_inverted'          => $site_settings['header_inverted'] ?? false,
		'header_borderless'        => $site_settings['header_borderless'] ?? false,
		'mobile_hamburger_side'    => $site_settings['mobile_hamburger_side'] ?? 'right',
		'header_show_toggle'       => $site_settings['header_show_toggle'] ?? true,
		'header_toggle_mobile_pin' => $site_settings['header_toggle_mobile_pin'] ?? false,
		'header_cart_mobile_pin'   => $site_settings['header_cart_mobile_pin'] ?? true,
		'theme_default'            => in_array( $site_settings['theme_default'] ?? 'system', [ 'system', 'light', 'dark' ], true ) ? ( $site_settings['theme_default'] ?? 'system' ) : 'system',
		'logo_invert_on_dark'      => (bool) ( $site_settings['logo_invert_on_dark'] ?? true ),
		'logo_size'                => in_array( $site_settings['logo_size'] ?? 'standard', [ 'compact', 'standard', 'prominent', 'xl' ], true ) ? ( $site_settings['logo_size'] ?? 'standard' ) : 'standard',
		'brand_position'           => in_array( $site_settings['brand_position'] ?? 'left', [ 'left', 'center', 'nav-center' ], true ) ? ( $site_settings['brand_position'] ?? 'left' ) : 'left',
		'typography'               => [
			'heading_font'   => $site_settings['typography_heading_font'] ?? 'inter',
			'body_font'      => $site_settings['typography_body_font'] ?? 'inter',
			'heading_weight' => $site_settings['typography_heading_weight'] ?? 'semibold',
			'body_size'      => $site_settings['typography_body_size'] ?? 'm',
		],
		'product_card'             => array_merge(
			[
				'media_aspect_ratio'       => '1:1',
				'corner_radius'            => 'square',
				'border'                   => 'full',
				'hover_effect'             => 'lift',
				'button_style'             => 'outline',
				'badge_position'           => 'top-right',
				'badge_style'              => 'filled',
				'show_bulk_badge'          => true,
				'show_tier_hint'           => true,
				'show_oos_cards'           => true,
				'oos_treatment'            => 'grayscale',
				'title_lines'              => 'auto',
				'secondary_image_on_hover' => false,
				'sale_badge_text'          => 'Sale',
			],
			(array) ( $site_settings['product_card'] ?? [] )
		),
		'tokens'                  => [
			'radius'             => is_int( $site_settings['tokens']['radius']             ?? null ) ? (int) $site_settings['tokens']['radius']             : null,
			'spacing_v_compact'  => is_int( $site_settings['tokens']['spacing_v_compact']  ?? null ) ? (int) $site_settings['tokens']['spacing_v_compact']  : null,
			'spacing_v_normal'   => is_int( $site_settings['tokens']['spacing_v_normal']   ?? null ) ? (int) $site_settings['tokens']['spacing_v_normal']   : null,
			'spacing_v_spacious' => is_int( $site_settings['tokens']['spacing_v_spacious'] ?? null ) ? (int) $site_settings['tokens']['spacing_v_spacious'] : null,
		],
		'seo_nosnippet_products' => $site_settings['seo_nosnippet_products'] ?? false,
		'homepage'        => $homepage,
		'pdp'             => $pdp,
		'shop'            => $shop_cfg,
		'pages'           => $pages_cfg['pages'],
		'footer'          => array_merge( [ 'columns' => [], 'tagline' => '' ], (array) ( $site_settings['footer'] ?? [] ) ),
		'social_links'    => array_values( array_filter(
			(array) ( $site_settings['social_links'] ?? [] ),
			fn( $l ) => is_array( $l ) && ! empty( $l['platform'] ) && ! empty( $l['url'] )
		) ),
		'gate_modal'      => [
			'enabled'      => (bool) ( $site_settings['gate_modal']['enabled'] ?? false ),
			'strict'       => (bool) ( $site_settings['gate_modal']['strict'] ?? false ),
			'title'        => (string) ( $site_settings['gate_modal']['title'] ?? '' ),
			'content'      => (string) ( $site_settings['gate_modal']['content'] ?? '' ),
			'confirm_text' => (string) ( $site_settings['gate_modal']['confirm_text'] ?? 'Enter Site' ),
			'decline_text' => (string) ( $site_settings['gate_modal']['decline_text'] ?? '' ),
			'decline_url'  => (string) ( $site_settings['gate_modal']['decline_url'] ?? '' ),
			'version'      => (int) ( $site_settings['gate_modal']['version'] ?? 1 ),
		],
		'active_scripts'  => wchs_build_active_scripts( $site_settings ),
	];
}

/**
 * Joins wchs_site_settings[active_scripts] with the admin-curated
 * wchs_script_registry and returns a list of fully-assembled script specs
 * ready for the SPA (surfaces='spa' entries) to render.
 *
 * Skipped:
 *   - disabled entries
 *   - entries whose id isn't in the registry (post-delete stale state)
 *   - entries missing any required param
 *   - entries whose dedicated_setting_key is already populated in the
 *     site options — prevents double-firing with existing pixel mu-plugins
 *     (e.g. if gtm_id is set under Integrations, we skip active_scripts[gtm]).
 *
 * Returned shape:
 *   [ { id, name, src, async, defer, placement, surfaces, category, mark, inline? }, ... ]
 */
function wchs_build_active_scripts( array $site_settings ): array {
	$registry = \WCHS\Admin\AdminPage::get_script_registry();
	$active   = $site_settings['active_scripts'] ?? [];
	if ( ! is_array( $active ) ) {
		return [];
	}

	$out = [];
	foreach ( $active as $row ) {
		if ( ! is_array( $row ) || empty( $row['enabled'] ) ) {
			continue;
		}
		$id = $row['id'] ?? '';
		if ( ! $id || ! isset( $registry[ $id ] ) ) {
			continue;
		}
		$entry  = $registry[ $id ];
		$params = (array) ( $row['params'] ?? [] );

		// Dedicated-setting short-circuit.
		$dkey = $entry['dedicated_setting_key'] ?? '';
		if ( $dkey && ! empty( $site_settings[ $dkey ] ) ) {
			continue;
		}

		// Enforce all required params are present (otherwise the script
		// will hit a broken URL and log errors).
		$missing_required = false;
		foreach ( ( $entry['params'] ?? [] ) as $p ) {
			if ( ! empty( $p['required'] ) && empty( $params[ $p['key'] ] ) ) {
				$missing_required = true;
				break;
			}
		}
		if ( $missing_required ) {
			continue;
		}

		// Build final src. Only registered param keys end up in the URL —
		// extra keys from the saved option are ignored.
		$query = [];
		foreach ( ( $entry['params'] ?? [] ) as $p ) {
			$k = $p['key'];
			if ( isset( $params[ $k ] ) && $params[ $k ] !== '' ) {
				$query[ $k ] = $params[ $k ];
			}
		}
		$inline_only = ! empty( $entry['inline_only'] );
		$inline      = isset( $entry['inline'] ) && is_string( $entry['inline'] ) ? $entry['inline'] : '';
		$src         = $inline_only ? '' : esc_url_raw( add_query_arg( $query, $entry['src_template'] ) );
		if ( $inline_only && $inline === '' ) {
			continue;
		}
		if ( ! $inline_only && $src === '' ) {
			continue;
		}

		$allowed_categories = [ 'analytics', 'pixel', 'marketing', 'consent', 'chat', 'other' ];
		$category = ( is_string( $entry['category'] ?? null ) && in_array( $entry['category'], $allowed_categories, true ) )
			? $entry['category'] : 'other';
		$mark = is_string( $entry['mark'] ?? null ) && $entry['mark'] !== ''
			? strtoupper( substr( $entry['mark'], 0, 3 ) )
			: strtoupper( substr( (string) ( $entry['name'] ?? $id ), 0, 2 ) );

		$out_row = [
			'id'        => $id,
			'name'      => $entry['name'] ?? $id,
			'src'       => $src,
			'async'     => ! empty( $entry['attributes']['async'] ),
			'defer'     => ! empty( $entry['attributes']['defer'] ),
			'placement' => in_array( $entry['placement'] ?? 'head', [ 'head', 'body_end' ], true ) ? $entry['placement'] : 'head',
			'surfaces'  => array_values( array_filter(
				(array) ( $entry['surfaces'] ?? [ 'spa', 'wp' ] ),
				fn( $s ) => in_array( $s, [ 'spa', 'wp' ], true )
			) ),
			'category'  => $category,
			'mark'      => $mark,
		];
		if ( $inline !== '' ) {
			$out_row['inline'] = $inline;
		}
		$out[] = $out_row;
	}

	return $out;
}

/**
 * GET /wchs/v1/reviews/{product_id}
 */
function wchs_rest_reviews( \WP_REST_Request $request ) {
	if ( ! wchs_rest_rate_limit( 'reviews_read' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests', [ 'status' => 429 ] );
	}

	$product_id = (int) $request->get_param( 'product_id' );
	$per_page   = (int) $request->get_param( 'per_page' );
	$page       = (int) $request->get_param( 'page' );

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return new \WP_Error( 'not_found', 'Product not found', [ 'status' => 404 ] );
	}

	$provider = wchs_get_review_provider();
	$result   = $provider->get_reviews( $product_id, $per_page, $page );

	return [
		'product_id'   => $product_id,
		'average'      => $result['average'],
		'count'        => $result['count'],
		'distribution' => $result['distribution'],
		'reviews'      => $result['reviews'],
		'page'         => $page,
		'per_page'     => $per_page,
	];
}

/**
 * GET /wchs/v1/reviews/aggregate
 *
 * Sitewide review totals. The ReviewSlider component uses this to label
 * itself "Based on N reviews" regardless of which products its cards are
 * scoped to. We intentionally don't scope this by product_ids — the count
 * should be the same everywhere the slider appears.
 *
 *   total        — all approved review rows in wp_comments
 *   with_content — subset that has non-empty comment_content (the number
 *                  the slider could actually render as carousel cards if
 *                  it wanted to show every review)
 *   average      — mean rating across the `rating` comment meta, 0 if none
 */
function wchs_rest_reviews_aggregate( \WP_REST_Request $request ) {
	if ( ! wchs_rest_rate_limit( 'reviews_read' ) ) {
		return wchs_rate_limited_response();
	}

	global $wpdb;

	$total = (int) get_comments( [
		'type'   => 'review',
		'status' => 'approve',
		'count'  => true,
	] );

	$ids = get_comments( [
		'type'   => 'review',
		'status' => 'approve',
		'fields' => 'ids',
	] );

	$with_content = 0;
	$sum          = 0;
	$n            = 0;
	foreach ( $ids as $cid ) {
		$c = get_comment( $cid );
		if ( $c && trim( (string) $c->comment_content ) !== '' ) {
			$with_content++;
		}
		$r = (int) get_comment_meta( $cid, 'rating', true );
		if ( $r >= 1 && $r <= 5 ) {
			$sum += $r;
			$n++;
		}
	}
	$average = $n > 0 ? round( $sum / $n, 2 ) : 0.0;

	$top_reviewed_ids = [];
	$top_reviewed_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT comment_post_ID AS product_id, COUNT(*) AS review_count
			FROM {$wpdb->comments}
			WHERE comment_type = %s
			  AND comment_approved = %s
			GROUP BY comment_post_ID
			ORDER BY review_count DESC, comment_post_ID DESC
			LIMIT 12",
			'review',
			'1'
		)
	);
	if ( is_array( $top_reviewed_rows ) ) {
		foreach ( $top_reviewed_rows as $row ) {
			$product_id = isset( $row->product_id ) ? (int) $row->product_id : 0;
			if ( $product_id <= 0 ) {
				continue;
			}
			$product = wc_get_product( $product_id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			$top_reviewed_ids[] = $product_id;
			if ( count( $top_reviewed_ids ) >= 4 ) {
				break;
			}
		}
	}

	return new \WP_REST_Response( [
		'total'        => $total,
		'with_content' => $with_content,
		'average'      => (float) $average,
		'product_ids'  => $top_reviewed_ids,
	], 200 );
}

/**
 * POST /wchs/v1/reviews/{product_id}
 */
function wchs_rest_create_review( \WP_REST_Request $request ) {
	if ( ! wchs_rest_rate_limit( 'reviews_write' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests', [ 'status' => 429 ] );
	}

	$user = wchs_current_user_from_cookie();
	if ( ! $user ) {
		return new \WP_Error( 'unauthorized', 'Must be logged in', [ 'status' => 401 ] );
	}

	// Check if active provider supports review creation
	$provider = wchs_get_review_provider();
	if ( ! $provider->supports_write() ) {
		return new \WP_Error(
			'write_not_supported',
			'Reviews are managed by ' . $provider->name() . '. Submit reviews through their platform.',
			[ 'status' => 405 ]
		);
	}

	// Verify Turnstile token (bot protection on write endpoint)
	$turnstile_token = sanitize_text_field( $request->get_param( 'turnstile_token' ) ?? '' );
	if ( function_exists( 'wchs_verify_turnstile' ) && ! wchs_verify_turnstile( $turnstile_token ) ) {
		return new \WP_Error( 'bot_check_failed', 'Bot verification failed. Please try again.', [ 'status' => 403 ] );
	}

	$product_id = (int) $request->get_param( 'product_id' );
	$product    = wc_get_product( $product_id );
	if ( ! $product ) {
		return new \WP_Error( 'not_found', 'Product not found', [ 'status' => 404 ] );
	}

	$rating  = (int) $request->get_param( 'rating' );
	$content = $request->get_param( 'content' );

	// Check for verified purchase
	$verified = wc_customer_bought_product( $user->user_email, $user->ID, $product_id );

	// Prevent duplicate reviews from the same user
	$existing = get_comments( [
		'post_id' => $product_id,
		'user_id' => $user->ID,
		'type'    => 'review',
		'count'   => true,
	] );
	if ( $existing > 0 ) {
		return new \WP_Error( 'duplicate', 'You have already reviewed this product', [ 'status' => 409 ] );
	}

	$comment_id = wp_insert_comment( [
		'comment_post_ID'      => $product_id,
		'comment_author'       => $user->display_name,
		'comment_author_email' => $user->user_email,
		'comment_content'      => $content,
		'comment_type'         => 'review',
		'comment_approved'     => 1,
		'user_id'              => $user->ID,
	] );

	if ( ! $comment_id ) {
		return new \WP_Error( 'failed', 'Failed to create review', [ 'status' => 500 ] );
	}

	update_comment_meta( $comment_id, 'rating', $rating );
	update_comment_meta( $comment_id, 'verified', $verified ? 1 : 0 );

	// Handle image uploads (multipart)
	$files     = $request->get_file_params();
	$image_ids = [];
	if ( ! empty( $files['images'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$images = $files['images'];
		// Normalize single file to array
		if ( ! is_array( $images['name'] ) ) {
			$images = [
				'name'     => [ $images['name'] ],
				'type'     => [ $images['type'] ],
				'tmp_name' => [ $images['tmp_name'] ],
				'error'    => [ $images['error'] ],
				'size'     => [ $images['size'] ],
			];
		}

		$max_images = 4;
		for ( $i = 0; $i < min( count( $images['name'] ), $max_images ); $i++ ) {
			$_FILES['review_image'] = [
				'name'     => $images['name'][ $i ],
				'type'     => $images['type'][ $i ],
				'tmp_name' => $images['tmp_name'][ $i ],
				'error'    => $images['error'][ $i ],
				'size'     => $images['size'][ $i ],
			];
			$attach_id = media_handle_upload( 'review_image', $product_id );
			if ( ! is_wp_error( $attach_id ) ) {
				$image_ids[] = $attach_id;
			}
		}

		if ( ! empty( $image_ids ) ) {
			update_comment_meta( $comment_id, '_wchs_review_images', $image_ids );
		}
	}

	// Force WC to recalculate average rating
	\WC_Comments::clear_transients( $product_id );

	return [
		'id'       => $comment_id,
		'verified' => $verified,
		'images'   => count( $image_ids ),
	];
}

/**
 * GET /wchs/v1/session
 *
 * Stateless "am I signed in?" probe for the headless SPA. Reads the
 * wordpress_logged_in_* cookie directly (bypassing WP REST's mandatory
 * nonce rule, see wchs_current_user_from_cookie). Read-only, safe.
 *
 * Shape:
 *   { authenticated: false }                              — guest
 *   { authenticated: true, user: {...}, logout_url: ... } — signed in
 *
 * The logout_url is a relative path to our own DELETE endpoint — the SPA
 * does not need to mint WP nonces.
 */
function wchs_rest_session_get( \WP_REST_Request $request ) {
	if ( ! wchs_rest_rate_limit( 'session' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests', [ 'status' => 429 ] );
	}

	$user = wchs_current_user_from_cookie();
	if ( ! $user ) {
		return [ 'authenticated' => false ];
	}

	$first = (string) get_user_meta( $user->ID, 'first_name', true );
	$last  = (string) get_user_meta( $user->ID, 'last_name', true );

	// Server time included so the SPA can detect clock skew when diffing
	// against its own Date.now() for session-age heuristics.
	$roles = (array) $user->roles;

	return [
		'authenticated'  => true,
		'email_verified' => function_exists( 'wchs_is_email_verified' ) ? wchs_is_email_verified( $user->ID ) : true,
		'user'           => [
			'id'           => (int) $user->ID,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => $first,
			'last_name'    => $last,
			'role'         => $roles[0] ?? 'subscriber',
		],
		'server_time'    => time(),
	];
}

/**
 * DELETE /wchs/v1/session
 *
 * Logs the current user out. CSRF defense: requires both a valid auth
 * cookie (enforced in permission_callback) AND an allowlisted Origin
 * (also in permission_callback). Idempotent — calling twice is fine.
 */
function wchs_rest_session_delete( \WP_REST_Request $request ) {
	if ( ! wchs_rest_rate_limit( 'session_delete' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests', [ 'status' => 429 ] );
	}

	// Clearing auth cookies is what actually logs the user out. We also
	// fire wp_logout() so any "user logged out" hooks run.
	$user = wchs_current_user_from_cookie();
	if ( $user ) {
		wp_set_current_user( $user->ID );
		wp_logout(); // clears cookies + fires wp_logout action
	} else {
		wp_clear_auth_cookie();
	}

	return [ 'ok' => true ];
}

/**
 * GET /wchs/v1/my-orders
 */
function wchs_rest_my_orders( \WP_REST_Request $request ) {
	if ( ! wchs_rest_rate_limit( 'my-orders' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests', [ 'status' => 429 ] );
	}

	$user = wchs_current_user_from_cookie();
	if ( ! $user ) {
		return new \WP_Error( 'unauthorized', 'Must be logged in', [ 'status' => 401 ] );
	}
	$user_id = (int) $user->ID;

	$per_page = (int) $request->get_param( 'per_page' );
	$page     = (int) $request->get_param( 'page' );

	$orders = wc_get_orders(
		[
			'customer_id' => $user_id,
			'limit'       => $per_page,
			'page'        => $page,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'paginate'    => true,
		]
	);

	$out = [];
	foreach ( $orders->orders as $order ) {
		if ( ! ( $order instanceof \WC_Order ) ) {
			continue;
		}
		$out[] = [
			'id'             => (int) $order->get_id(),
			'number'         => $order->get_order_number(),
			'status'         => $order->get_status(),
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( \DateTimeInterface::ATOM ) : null,
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'item_count'     => $order->get_item_count(),
			'order_key'      => $order->get_order_key(), // Stable per-order token the user already holds
			'billing_email'  => $order->get_billing_email(), // Current user's own email — fine to return
		];
	}

	return [
		'orders'       => $out,
		'page'         => $page,
		'per_page'     => $per_page,
		'total_pages'  => (int) $orders->max_num_pages,
		'total_orders' => (int) $orders->total,
	];
}

/**
 * GET /wchs/v1/order-payment/{id}?key=...
 *
 * Returns payment method info and instructions for an order.
 * Authenticated by order key (same as Store API /order/{id}).
 * Used by the SPA thank-you page to show payment instructions
 * for offline gateways, BACS, COD, etc.
 */
function wchs_rest_order_payment( \WP_REST_Request $request ) {
	$order_id = (int) $request->get_param( 'id' );
	$key      = sanitize_text_field( $request->get_param( 'key' ) );

	$order = wc_get_order( $order_id );
	if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
		return new \WP_Error( 'invalid_order', 'Invalid order', [ 'status' => 403 ] );
	}

	$method       = $order->get_payment_method();
	$method_title = $order->get_payment_method_title();

	// Collect fee line items (gateway surcharges, etc.)
	$fees = [];
	foreach ( $order->get_fees() as $fee_item ) {
		$fees[] = [
			'name'  => $fee_item->get_name(),
			'total' => $fee_item->get_total(),
		];
	}

	$result = [
		'method'       => $method,
		'method_title' => $method_title,
		'status'       => $order->get_status(),
		'fees'         => $fees,
		'instructions' => null,
	];

	// BACS: bank account details
	if ( 'bacs' === $method ) {
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['bacs'] ) ) {
			$bacs = $gateways['bacs'];
			$result['instructions'] = [
				'type'    => 'bacs',
				'message' => $bacs->get_option( 'instructions', '' ),
				'accounts' => $bacs->get_option( 'account_details', [] ),
			];
		}
	}

	// COD: simple message
	if ( 'cod' === $method ) {
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['cod'] ) ) {
			$result['instructions'] = [
				'type'    => 'cod',
				'message' => $gateways['cod']->get_option( 'instructions', 'Pay with cash upon delivery.' ),
			];
		}
	}

	// Our custom offline gateways: handle, link, QR
	if ( str_starts_with( $method, 'wchs_offline_' ) ) {
		$details = function_exists( 'wchs_get_offline_gateway_order_details' )
			? wchs_get_offline_gateway_order_details( $order )
			: null;

		if ( $details ) {
			$result['instructions'] = [
				'type'    => 'offline',
				'message' => '' !== (string) ( $details['instructions'] ?? '' ) ? (string) $details['instructions'] : null,
				'handle'  => '' !== (string) ( $details['handle'] ?? '' ) ? (string) $details['handle'] : null,
				'link'    => '' !== (string) ( $details['link'] ?? '' ) ? (string) $details['link'] : null,
				'show_qr' => ! empty( $details['show_qr'] ),
				'total'   => $order->get_total(),
			];
		}
	}

	return $result;
}
