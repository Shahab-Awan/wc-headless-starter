<?php
/**
 * Plugin Name: Headless Access Control
 * Description: Enforces four store access modes at the API + template level.
 *              Mode 0 (Maintenance): site completely offline except for admins.
 *              Mode 1 (Locked): guests see only homepage + login/register.
 *              Mode 2 (Browse-only): guests can browse but not checkout.
 *              Mode 3 (Open): no restrictions (default).
 *
 *
 * Author:      WCHS Contributors
 *
 * Enforcement layers:
 *   1. Store API: rest_pre_dispatch filter blocks endpoints for guests
 *   2. WP templates: template_redirect blocks native pages for guests
 *   3. SPA: route guards (UX only — real gate is here at the API level)
 *
 * SECURITY: Never trust the SPA route guards alone. This mu-plugin is
 * the real security boundary. API responses return 403 with a clean
 * error shape so the SPA can display appropriate messaging.
 *
 * NOTE: This plugin blocks unauthorized access but does NOT rate-limit.
 * A bot can still flood gated endpoints and receive 403/503 responses
 * all day, burning server resources. Add rate limiting at the nginx or
 * Cloudflare level for production. See SECURITY.md.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'rest_pre_dispatch', 'wchs_access_control_api', 5, 3 );
add_action( 'template_redirect', 'wchs_access_control_template', 5 );

function wchs_access_mode(): int {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return 3;
	}
	$settings = \WCHS\Admin\AdminPage::get_site_settings();
	return (int) ( $settings['access_mode'] ?? 3 );
}

/**
 * API-level gate — the real security boundary.
 */
function wchs_access_control_api( $result, $server, $request ) {
	$mode = wchs_access_mode();
	if ( 3 === $mode ) {
		return $result;
	}

	$route = $request->get_route();

	// Always allowed regardless of mode
	$always_open = [
		'/wchs/v1/config',
		'/wchs/v1/session',
	];
	foreach ( $always_open as $open ) {
		if ( 0 === strpos( $route, $open ) ) {
			return $result;
		}
	}

	// Mode 0 — Maintenance: only admins pass, all other users blocked.
	// wp_get_current_user() reads the auth cookie directly even in REST
	// context (before the nonce-based auth fires). current_user_can()
	// may fail if WP REST hasn't set the user from the nonce yet.
	if ( 0 === $mode ) {
		$user = wp_get_current_user();
		if ( $user->exists() && $user->has_cap( 'manage_options' ) ) {
			return $result;
		}
		return new \WP_Error(
			'wchs_maintenance',
			'This site is currently under maintenance.',
			[ 'status' => 503, 'access_mode' => 0 ]
		);
	}

	// Check if the user is authenticated AND email-verified.
	// Unverified users are treated as guests for access mode purposes.
	$is_authed = is_user_logged_in() && ( ! function_exists( 'wchs_is_email_verified' ) || wchs_is_email_verified() );
	if ( $is_authed ) {
		return $result;
	}

	// Mode 1 — Locked: block ALL Store API + product endpoints for guests
	if ( 1 === $mode ) {
		$locked_prefixes = [
			'/wc/store/v1/products',
			'/wc/store/v1/cart',
			'/wc/store/v1/checkout',
			'/wc/store/v1/order',
			'/wchs/v1/reviews',
			'/wchs/v1/my-orders',
			'/wchs/v1/contact',
			'/wchs/v1/order-payment',
		];
		foreach ( $locked_prefixes as $prefix ) {
			if ( 0 === strpos( $route, $prefix ) ) {
				return new \WP_Error(
					'wchs_access_denied',
					'This store requires membership. Please sign in or create an account.',
					[ 'status' => 403, 'access_mode' => 1 ]
				);
			}
		}
	}

	// Mode 2 — Browse-only: block cart/checkout mutations for guests
	if ( 2 === $mode ) {
		$method = $request->get_method();
		$blocked = false;

		// Block all cart writes
		if ( 0 === strpos( $route, '/wc/store/v1/cart' ) && 'GET' !== $method ) {
			$blocked = true;
		}
		// Block checkout entirely
		if ( 0 === strpos( $route, '/wc/store/v1/checkout' ) ) {
			$blocked = true;
		}

		if ( $blocked ) {
			return new \WP_Error(
				'wchs_access_denied',
				'Please sign in to add items to your cart and checkout.',
				[ 'status' => 403, 'access_mode' => 2 ]
			);
		}
	}

	return $result;
}

/**
 * Template-level gate — for native WP pages (checkout, my-account).
 * The SPA handles its own route guards via config.data.access_mode.
 */
function wchs_access_control_template(): void {
	$mode = wchs_access_mode();
	$is_authed = is_user_logged_in() && ( ! function_exists( 'wchs_is_email_verified' ) || wchs_is_email_verified() );
	if ( 3 === $mode || $is_authed ) {
		return;
	}

	// Mode 0 — Maintenance: block all non-admin access
	if ( 0 === $mode ) {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		status_header( 503 );
		header( 'Retry-After: 3600' );
		wp_die(
			'This site is currently under maintenance. Please check back later.',
			'Maintenance',
			[ 'response' => 503 ]
		);
	}

	if ( 1 === $mode ) {
		// Locked: only allow login + registration pages
		$allowed = [
			'/wp-login.php',
			'/my-account/',
		];
		$req = $_SERVER['REQUEST_URI'] ?? '/';
		foreach ( $allowed as $path ) {
			if ( false !== strpos( $req, $path ) ) {
				return;
			}
		}
		// Block everything else — redirect to login
		if ( function_exists( 'is_wc_endpoint_url' ) && ! is_wc_endpoint_url( 'order-received' ) ) {
			wp_safe_redirect( home_url( '/my-account/' ) );
			exit;
		}
	}

	if ( 2 === $mode ) {
		// Browse-only: block checkout for guests (not while admins preview in Elementor).
		if ( function_exists( 'wchs_allow_bare_checkout_handoff' ) && wchs_allow_bare_checkout_handoff() ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			wp_safe_redirect( home_url( '/my-account/' ) );
			exit;
		}
	}
}

// ─── SEO: nosnippet meta on WP product pages ───────────────────

add_action( 'wp_head', function () {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) return;
	if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
	$settings = \WCHS\Admin\AdminPage::get_site_settings();
	if ( ! empty( $settings['seo_nosnippet_products'] ) ) {
		echo '<meta name="googlebot" content="nosnippet, noimageindex" />' . "\n";
	}
}, 1 );

// ─── SEO: robots.txt cart/checkout blocking ─────────────────────

add_filter( 'robots_txt', function ( string $output, bool $public ): string {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return $output;
	}
	$settings = \WCHS\Admin\AdminPage::get_site_settings();
	if ( ! empty( $settings['seo_block_cart_checkout'] ) ) {
		$output .= "\n# WCHS: Block cart and checkout from crawlers\n";
		$output .= "Disallow: /cart/\n";
		$output .= "Disallow: /checkout/\n";
	}
	return $output;
}, 10, 2 );
