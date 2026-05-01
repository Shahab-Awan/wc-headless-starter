<?php
/**
 * Plugin Name: Headless CORS
 * Description: Strict CORS policy for the SvelteKit SPA + Store API responses. Also emits defense-in-depth security headers.
 * Version:     0.3.0
 * Author:      WCHS Contributors
*
 * Security posture:
 *   - Hardcoded origin allowlist. No reflection of attacker-controlled
 *     origins, no wildcard with credentials.
 *   - Preflight OPTIONS requests are explicitly denied when the origin
 *     is not in the allowlist (no silent drop to browser defaults).
 *   - Responses carry X-Frame-Options / X-Content-Type-Options /
 *     Referrer-Policy headers regardless of origin — defense in depth.
 *   - Preflight cache short (60s) so allowlist changes take effect quickly.
 *
 * In local dev the SPA normally runs same-origin through Vite's /wp proxy,
 * so CORS is not exercised. This plugin exists for the prod case (SPA
 * and WP on different subdomains) and for direct cross-port development
 * testing.
 *
 * Same-origin stores follow the site's public URL automatically. Custom
 * split-origin deployments can override the allowlist from WCHS Settings
 * or legacy wp-config constants.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Origins the SPA is allowed to make credentialed requests from.
 * Hardcoded allowlist — DO NOT reflect the Origin header without validation.
 */
function wchs_allowed_origins(): array {
	if ( function_exists( 'wchs_allowed_origin_list' ) ) {
		return wchs_allowed_origin_list();
	}
	if ( defined( 'WCHS_ALLOWED_ORIGINS' ) && is_string( WCHS_ALLOWED_ORIGINS ) ) {
		return array_filter( array_map( 'trim', explode( ',', WCHS_ALLOWED_ORIGINS ) ) );
	}
	return [ untrailingslashit( home_url( '/' ) ) ];
}

/**
 * Check if an origin string is in our allowlist. Strict case-sensitive
 * exact match. No substring comparison, no protocol-relative matching.
 */
function wchs_is_allowed_origin( $origin ): bool {
	if ( ! is_string( $origin ) || $origin === '' ) {
		return false;
	}
	return in_array( $origin, wchs_allowed_origins(), true );
}

/**
 * Send CORS + security headers for REST responses (Store API lives under
 * wp-json). Runs late enough to be the final word on these headers.
 */
add_filter(
	'rest_pre_serve_request',
	function ( $served, $result, $request, $server ) {
		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

		// Security headers — emitted on ALL REST responses, not origin-gated.
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: DENY' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		// CORS only when origin is allowlisted. Otherwise the browser will
		// fail the credentialed fetch on its own — we don't emit any
		// Access-Control-Allow-* headers for unknown origins.
		if ( wchs_is_allowed_origin( $origin ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Cart-Token, Nonce, X-WC-Store-API-Nonce, Content-Type, Authorization' );
			// Expose Cart-Token so the SPA can read it from response.headers.
			// Without this the browser hides it from JS even though it's present.
			header( 'Access-Control-Expose-Headers: Cart-Token, Nonce, X-WC-Store-API-Nonce, Link' );
			header( 'Vary: Origin' );
		}

		return $served;
	},
	10,
	4
);

/**
 * Handle OPTIONS preflight before WP does anything expensive.
 * Strict deny for unknown origins — respond 403 + exit.
 */
add_action(
	'init',
	function () {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'OPTIONS' ) {
			return;
		}
		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

		if ( ! wchs_is_allowed_origin( $origin ) ) {
			// Affirmative deny. No CORS headers. Tell browser it's not welcome.
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: DENY' );
			http_response_code( 403 );
			exit;
		}

		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Cart-Token, Nonce, X-WC-Store-API-Nonce, Content-Type, Authorization' );
		header( 'Access-Control-Expose-Headers: Cart-Token, Nonce, X-WC-Store-API-Nonce, Link' );
		header( 'Access-Control-Max-Age: 60' );
		header( 'Vary: Origin' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: DENY' );
		http_response_code( 204 );
		exit;
	},
	0
);
