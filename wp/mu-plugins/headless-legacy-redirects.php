<?php
/**
 * Plugin Name: WCHS Legacy Redirects
 * Description: Server-side 301s for old Shopify/Woo category paths into canonical WCHS routes.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_loaded', 'wchs_legacy_redirects_maybe_redirect', 0 );

function wchs_legacy_redirects_maybe_redirect(): void {
	$path = wchs_legacy_redirects_request_path();
	if ( '' === $path ) {
		return;
	}

	if ( preg_match( '#^/collections/([^/]+)/products/([^/]+)/?$#i', $path, $m ) ) {
		$product_target = wchs_legacy_redirects_product_target_path( $m[2] );
		if ( '' !== $product_target ) {
			wchs_legacy_redirects_send( $product_target );
		}
		$target = wchs_legacy_redirects_collection_target_path( $m[1] );
		wchs_legacy_redirects_send( $target );
	}

	if ( preg_match( '#^/products/?$#i', $path ) ) {
		wchs_legacy_redirects_send( '/shop' );
	}

	if ( preg_match( '#^/products/([^/]+)/?$#i', $path, $m ) ) {
		$product_target = wchs_legacy_redirects_product_target_path( $m[1] );
		wchs_legacy_redirects_send( '' !== $product_target ? $product_target : '/shop' );
	}

	if ( preg_match( '#^/product/([^/]+)/?$#i', $path, $m ) ) {
		$slug = sanitize_title( rawurldecode( $m[1] ) );
		$spa_route = wchs_legacy_redirects_spa_route_for_mistaken_product_slug( $slug );
		if ( '' !== $spa_route ) {
			wchs_legacy_redirects_send( $spa_route );
		}
		$alias_target = wchs_legacy_redirects_product_alias_target_path( $m[1] );
		if ( '' !== $alias_target ) {
			wchs_legacy_redirects_send( $alias_target );
		}
	}

	if ( preg_match( '#^/collections/?$#i', $path ) ) {
		wchs_legacy_redirects_send( '/shop' );
	}

	if ( preg_match( '#^/collections/([^/]+)/?$#i', $path, $m ) ) {
		wchs_legacy_redirects_send( wchs_legacy_redirects_collection_target_path( $m[1] ) );
	}

	if ( preg_match( '#^/product-category/([^/]+)/?$#i', $path, $m ) ) {
		$slug = sanitize_title( rawurldecode( $m[1] ) );
		$target = wchs_legacy_redirects_category_exists( $slug )
			? '/shop/' . $slug
			: '/shop';
		wchs_legacy_redirects_send( $target );
	}

	if ( preg_match( '#^/([^/]+)/?$#i', $path, $m ) ) {
		$slug = sanitize_title( rawurldecode( $m[1] ) );
		if ( wchs_legacy_redirects_root_alias_allowed( $slug ) ) {
			$alias_target = wchs_legacy_redirects_product_alias_target_path( $slug );
			if ( '' !== $alias_target ) {
				wchs_legacy_redirects_send( $alias_target );
			}
		}
	}
}

function wchs_legacy_redirects_request_path(): string {
	$raw = $_SERVER['REQUEST_URI'] ?? '';
	if ( ! is_string( $raw ) || '' === $raw ) {
		return '';
	}
	$path = wp_parse_url( $raw, PHP_URL_PATH );
	return is_string( $path ) ? '/' . ltrim( $path, '/' ) : '';
}

function wchs_legacy_redirects_collection_target_path( string $handle ): string {
	$handle = sanitize_title( rawurldecode( $handle ) );
	if ( '' === $handle ) {
		return '/shop';
	}

	$aliases = apply_filters(
		'wchs_legacy_collection_redirects',
		[
			'all' => '',
		]
	);
	if ( ! is_array( $aliases ) ) {
		$aliases = [];
	}

	if ( array_key_exists( $handle, $aliases ) ) {
		$target = sanitize_title( (string) $aliases[ $handle ] );
		if ( '' === $target ) {
			return '/shop';
		}
		return wchs_legacy_redirects_category_exists( $target ) ? '/shop/' . $target : '/shop';
	}

	if ( wchs_legacy_redirects_category_exists( $handle ) ) {
		return '/shop/' . $handle;
	}

	return '/shop';
}

function wchs_legacy_redirects_product_target_path( string $handle ): string {
	$slug = sanitize_title( rawurldecode( $handle ) );
	if ( '' === $slug ) {
		return '';
	}

	$alias_target = wchs_legacy_redirects_product_alias_target_slug( $slug );
	if ( '' !== $alias_target ) {
		return '/product/' . $alias_target;
	}

	if ( wchs_legacy_redirects_product_exists( $slug ) ) {
		return '/product/' . $slug;
	}

	return '';
}

function wchs_legacy_redirects_product_alias_target_path( string $handle ): string {
	$slug = sanitize_title( rawurldecode( $handle ) );
	if ( '' === $slug ) {
		return '';
	}

	$target_slug = wchs_legacy_redirects_product_alias_target_slug( $slug );
	return '' !== $target_slug ? '/product/' . $target_slug : '';
}

function wchs_legacy_redirects_product_alias_target_slug( string $slug ): string {
	$aliases = wchs_legacy_redirects_product_aliases();
	if ( ! array_key_exists( $slug, $aliases ) ) {
		return '';
	}

	$target = $aliases[ $slug ];
	if ( $target === $slug || ! wchs_legacy_redirects_product_exists( $target ) ) {
		return '';
	}

	return $target;
}

/**
 * SPA routes that must not be loaded as /product/{slug} (common nav typo).
 *
 * @return array<string, string> mistaken slug => canonical SPA path
 */
function wchs_legacy_redirects_spa_mistaken_product_slugs(): array {
	$routes = [
		'coa-library' => '/coa-library',
	];
	return apply_filters( 'wchs_legacy_spa_mistaken_product_slugs', $routes );
}

function wchs_legacy_redirects_spa_route_for_mistaken_product_slug( string $slug ): string {
	$routes = wchs_legacy_redirects_spa_mistaken_product_slugs();
	return isset( $routes[ $slug ] ) ? (string) $routes[ $slug ] : '';
}

function wchs_legacy_redirects_product_aliases(): array {
	$aliases = get_option( 'wchs_legacy_product_redirects', [] );
	if ( is_string( $aliases ) ) {
		$decoded = json_decode( $aliases, true );
		$aliases = is_array( $decoded ) ? $decoded : [];
	}

	$aliases = apply_filters( 'wchs_legacy_product_redirects', $aliases );
	if ( ! is_array( $aliases ) ) {
		return [];
	}

	$normalized = [];
	foreach ( $aliases as $source => $target ) {
		if ( is_array( $target ) ) {
			$source = $target['source'] ?? $target['from'] ?? $source;
			$target = $target['target'] ?? $target['to'] ?? '';
		}

		$source = sanitize_title( (string) $source );
		$target = sanitize_title( (string) $target );
		if ( '' === $source || '' === $target ) {
			continue;
		}

		$normalized[ $source ] = $target;
	}

	return $normalized;
}

function wchs_legacy_redirects_root_alias_allowed( string $slug ): bool {
	if ( '' === $slug ) {
		return false;
	}

	return ! in_array(
		$slug,
		[
			'account',
			'cart',
			'checkout',
			'collections',
			'comments',
			'feed',
			'my-account',
			'order-received',
			'product',
			'product-category',
			'products',
			'shop',
			'wp-admin',
			'wp-content',
			'wp-includes',
			'wp-json',
			'wp-login',
			'wp-sitemap',
		],
		true
	);
}

function wchs_legacy_redirects_product_exists( string $slug ): bool {
	if ( '' === $slug ) {
		return false;
	}
	$product = get_page_by_path( $slug, OBJECT, 'product' );
	return $product instanceof WP_Post && 'publish' === $product->post_status;
}

function wchs_legacy_redirects_category_exists( string $slug ): bool {
	if ( '' === $slug || ! taxonomy_exists( 'product_cat' ) ) {
		return false;
	}
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	return $term instanceof WP_Term && (int) $term->count > 0;
}

function wchs_legacy_redirects_send( string $path ): void {
	$url = home_url( $path, 'https' );
	$query = wchs_legacy_redirects_tracking_query();
	if ( '' !== $query ) {
		$url .= '?' . $query;
	}
	wp_safe_redirect( $url, 301 );
	exit;
}

function wchs_legacy_redirects_tracking_query(): string {
	$raw = $_SERVER['QUERY_STRING'] ?? '';
	if ( ! is_string( $raw ) || '' === $raw ) {
		return '';
	}

	parse_str( $raw, $params );
	$keep = [];
	foreach ( $params as $key => $value ) {
		$key = is_string( $key ) ? $key : '';
		if ( '' === $key ) {
			continue;
		}
		if ( preg_match( '/^utm_/i', $key ) || in_array( strtolower( $key ), [ 'gclid', 'fbclid', 'msclkid' ], true ) ) {
			$keep[ $key ] = $value;
		}
	}

	return $keep ? http_build_query( $keep ) : '';
}
