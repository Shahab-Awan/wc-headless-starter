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
		$product_slug = sanitize_title( rawurldecode( $m[2] ) );
		if ( wchs_legacy_redirects_product_exists( $product_slug ) ) {
			wchs_legacy_redirects_send( '/product/' . $product_slug );
		}
		$target = wchs_legacy_redirects_collection_target_path( $m[1] );
		wchs_legacy_redirects_send( $target );
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
