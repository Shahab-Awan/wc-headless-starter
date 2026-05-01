<?php
/**
 * Plugin Name: Headless Login Return
 * Description: Honor ?return=<spa-origin> on native WP login/register pages so users return to the SPA after auth. Strict origin allowlist, origin-only (no path/query/fragment pass-through).
 * Version:     0.3.0
 * Author:      WCHS Contributors
*
 * Security posture (v0.3.0):
 *   - Return URL must parse into a scheme + host + (optional) port that
 *     EXACTLY matches an entry in wchs_allowed_return_origins(). No
 *     substring matching, no case-insensitive matching, no wildcards.
 *   - We intentionally DROP the path, query, and fragment from the input
 *     and return only the origin + '/'. This prevents an attacker from
 *     injecting arbitrary SPA routes / tracking params / fragments via
 *     the login redirect.
 *   - CR/LF characters in the input are rejected to block header injection
 *     on the Location header of the 302.
 *   - Rejected inputs return null, which causes WP's default redirect
 *     behavior to kick in (wp-admin). No attacker influence.
 *
 * The allowlist defaults to the site's own public origin. Custom split-origin
 * setups can opt into explicit overrides from WCHS Settings or legacy
 * wp-config constants.
 */

defined( 'ABSPATH' ) || exit;

function wchs_allowed_return_origins(): array {
	if ( function_exists( 'wchs_return_origin_list' ) ) {
		return wchs_return_origin_list();
	}
	if ( defined( 'WCHS_RETURN_ORIGINS' ) && is_string( WCHS_RETURN_ORIGINS ) ) {
		return array_filter( array_map( 'trim', explode( ',', WCHS_RETURN_ORIGINS ) ) );
	}
	return [ untrailingslashit( home_url( '/' ) ) ];
}

/**
 * Strict allowlist of SPA paths that may be preserved on the login
 * redirect. Anything not in this list collapses to `/`. Exact match only.
 * Intentionally narrow — do NOT include arbitrary product or order pages.
 */
function wchs_allowed_return_paths(): array {
	return [
		'/',
		'/account',
		'/account/',
		'/account/orders',
		'/account/orders/',
		'/shop',
		'/shop/',
	];
}

/**
 * Resolve the `return` query param into a safe SPA URL, or null if
 * anything fails. Returns ORIGIN + '/' on success — the path is discarded.
 */
function wchs_resolve_return_url(): ?string {
	$raw = $_REQUEST['return'] ?? $_REQUEST['redirect_to'] ?? '';
	if ( ! is_string( $raw ) || $raw === '' ) {
		return null;
	}

	// Reject any CR/LF to prevent header injection on the Location header.
	if ( strpbrk( $raw, "\r\n" ) !== false ) {
		return null;
	}

	// wp_unslash handles WP's magic-quotes legacy. We do NOT run esc_url_raw
	// because it silently mutates input (normalizes, strips, re-encodes) and
	// those mutations have historically caused bypasses. We validate the
	// raw string ourselves after unslash.
	$raw = wp_unslash( $raw );

	// Must start with http:// or https:// explicitly. No protocol-relative
	// (//evil.com), no javascript:, no data:, no mailto:, etc.
	if ( ! preg_match( '#^https?://#i', $raw ) ) {
		return null;
	}

	$parts = wp_parse_url( $raw );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return null;
	}

	// Reject any userinfo (user:pass@host). This blocks the
	// http://localhost:5175@evil.com/ bypass where the attacker-controlled
	// host is after an @.
	if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return null;
	}

	// Normalize the scheme to lowercase so we match the allowlist.
	$scheme = strtolower( $parts['scheme'] );
	$host   = strtolower( $parts['host'] );
	$origin = $scheme . '://' . $host;
	if ( isset( $parts['port'] ) ) {
		$origin .= ':' . (int) $parts['port'];
	}

	if ( ! in_array( $origin, wchs_allowed_return_origins(), true ) ) {
		return null;
	}

	// Path allowlist — only a fixed set of SPA routes may survive the
	// redirect. Anything else collapses to '/'. The query and fragment
	// are always discarded so an attacker cannot inject tracking params
	// or tamper with SPA state via the redirect.
	$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
	if ( ! in_array( $path, wchs_allowed_return_paths(), true ) ) {
		$path = '/';
	}

	return $origin . $path;
}

/**
 * wp-login.php and wp_login form.
 */
add_filter(
	'login_redirect',
	function ( $redirect_to, $requested, $user ) {
		$resolved = wchs_resolve_return_url();
		return $resolved ?? $redirect_to;
	},
	10,
	3
);

/**
 * WooCommerce customer login form (/my-account).
 */
add_filter(
	'woocommerce_login_redirect',
	function ( $redirect, $user ) {
		$resolved = wchs_resolve_return_url();
		return $resolved ?? $redirect;
	},
	10,
	2
);

/**
 * WooCommerce new-customer registration.
 */
add_filter(
	'woocommerce_registration_redirect',
	function ( $redirect ) {
		$resolved = wchs_resolve_return_url();
		return $resolved ?? $redirect;
	}
);

/**
 * WooCommerce logout redirect.
 */
add_filter(
	'woocommerce_logout_default_redirect_url',
	function ( $redirect ) {
		$resolved = wchs_resolve_return_url();
		return $resolved ?? $redirect;
	}
);

/**
 * Pass ?return through the login form as a hidden field so it survives POST.
 */
add_action(
	'login_form',
	function () {
		$resolved = wchs_resolve_return_url();
		if ( $resolved ) {
			printf( '<input type="hidden" name="return" value="%s" />', esc_attr( $resolved ) );
		}
	}
);
