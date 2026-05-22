<?php
/**
 * Headless Shim theme — minimal template fallback only.
 *
 * Responsibilities in this file (kept small on purpose):
 *   - Provide WooCommerce theme support so native WC pages render
 *   - Redirect any unexpected front-end request to the SPA
 *   - Strip WP's default block/editor bloat on the native pages
 *
 * Responsibilities moved OUT of this file into the mu-plugin at
 * wp/mu-plugins/wchs-design-system/:
 *   - All CSS enqueueing (tokens + wc-overrides)
 *   - Theme sync JS + floating toggle button
 *   - WC behavior hooks (classic cart/checkout force, breadcrumb)
 *
 * Why: a mu-plugin survives theme swaps and can dequeue WC's own
 * stylesheets at priority 999 without fighting the enqueue order that
 * child themes are stuck with.
 */

defined( 'ABSPATH' ) || exit;

/** SPA origin — dev default. Override via `WCHS_SPA_URL` wp-config constant. */
function wchs_spa_url(): string {
	if ( function_exists( 'wchs_spa_origin' ) ) {
		return wchs_spa_origin();
	}
	if ( defined( 'WCHS_SPA_URL' ) ) {
		return rtrim( WCHS_SPA_URL, '/' );
	}
	return untrailingslashit( home_url( '/' ) );
}

/** Paths we keep native — everything else redirects to the SPA. */
function wchs_is_native_page(): bool {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return true;
	}
	// Elementor / FunnelKit editor iframe must stay on WordPress (never bounce to SPA).
	if ( function_exists( 'wchs_is_checkout_builder_preview' ) && wchs_is_checkout_builder_preview() ) {
		return true;
	}
	$req = $_SERVER['REQUEST_URI'] ?? '/';
	$native_paths = [
		'/checkout',
		'/my-account',
		'/wp-login.php',
		'/wp-admin',
		'/wc-api',
		// Upsell offer pages render on WP via our custom engine
		// (headless-one-click-upsell.php uses /checkout/order-received/).
	];
	if ( function_exists( 'wchs_checkout_handoff_paths' ) ) {
		foreach ( wchs_checkout_handoff_paths() as $handoff ) {
			$handoff = (string) $handoff;
			if ( $handoff !== '' && $handoff !== '/checkout' && ! in_array( $handoff, $native_paths, true ) ) {
				$native_paths[] = $handoff;
			}
		}
	}
	foreach ( $native_paths as $p ) {
		if ( strpos( $req, $p ) === 0 ) {
			return true;
		}
	}

	$path = wp_parse_url( $req, PHP_URL_PATH ) ?? '/';
	if ( function_exists( 'wchs_is_funnelkit_native_path' ) && wchs_is_funnelkit_native_path( $path ) ) {
		return true;
	}

	return false;
}

/**
 * Redirect front-end requests that aren't on a native page to the SPA.
 * Home, category, single, shop — all delegated to SvelteKit.
 */
add_action(
	'template_redirect',
	function () {
		$req  = $_SERVER['REQUEST_URI'] ?? '/';
		$path = wp_parse_url( $req, PHP_URL_PATH ) ?? '/';
		if ( preg_match( '#^/cart/?$#', $path ) ) {
			wp_redirect( add_query_arg( 'open_cart', '1', wchs_spa_url() . '/shop' ), 302 );
			exit;
		}

		if ( wchs_is_native_page() ) {
			return;
		}
		wp_redirect( wchs_spa_url() . $req, 302 );
		exit;
	}
);

/**
 * Strip WP's own block/editor bloat on the native pages — the
 * mu-plugin takes over token + override enqueueing, but WP's default
 * block-library styles still load and need to go.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	},
	100
);

/**
 * Theme supports — WooCommerce, title tag, thumbnails.
 */
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
	}
);

/**
 * Siteground compatibility: NGINX 403s bare `/wp-admin/` directory requests
 * at the edge before Apache/mod_rewrite sees them. DirectoryIndex never
 * fires and users see Siteground's 404/403 page instead of the dashboard.
 *
 * Fix: rewrite every admin_url() output that ends with `/wp-admin/` to
 * `/wp-admin/index.php` so PHP handles the request directly. Also handle
 * the login-success redirect target since `wp_safe_redirect(admin_url())`
 * produces the same broken URL.
 *
 * Safe on non-Siteground hosts because `/wp-admin/index.php` is the canonical
 * target DirectoryIndex would have resolved anyway.
 */
add_filter(
	'admin_url',
	function ( $url ) {
		if ( is_string( $url ) && preg_match( '~/wp-admin/?(\?.*)?$~', $url ) ) {
			$url = preg_replace( '~/wp-admin/?(\?|$)~', '/wp-admin/index.php$1', $url, 1 );
		}
		return $url;
	},
	10,
	1
);
add_filter(
	'login_redirect',
	function ( $redirect_to ) {
		if ( is_string( $redirect_to ) && preg_match( '~/wp-admin/?(\?.*)?$~', $redirect_to ) ) {
			$redirect_to = preg_replace( '~/wp-admin/?(\?|$)~', '/wp-admin/index.php$1', $redirect_to, 1 );
		}
		return $redirect_to;
	},
	10,
	1
);
