<?php
/**
 * Plugin Name: Headless FunnelKit Compat
 * Description: Admin toggle to use WCHS checkout or FunnelKit Store Checkout (Elementor). Aligns SPA cart handoff and skips WCHS checkout CSS when FunnelKit is selected.
 * Version:     0.2.0
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read WCHS site settings (defaults when admin is unavailable).
 *
 * @return array<string, mixed>
 */
function wchs_funnelkit_site_settings(): array {
	if ( class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return \WCHS\Admin\AdminPage::get_site_settings();
	}
	return [];
}

/**
 * When true, WCHS checkout chrome runs on /checkout. When false, FunnelKit (or override path) owns checkout UI.
 */
function wchs_use_wchs_checkout_ui(): bool {
	$settings = wchs_funnelkit_site_settings();
	return (bool) ( $settings['use_wchs_checkout'] ?? true );
}

/**
 * Post ID for the active FunnelKit store checkout page (0 when unknown).
 */
function wchs_funnelkit_store_checkout_post_id(): int {
	if ( function_exists( 'wffn_get_store_checkout_id' ) ) {
		return max( 0, (int) wffn_get_store_checkout_id() );
	}

	if ( class_exists( 'WFFN_Core' ) ) {
		$core = WFFN_Core();
		if ( is_object( $core ) && method_exists( $core, 'get_store_checkout_id' ) ) {
			return max( 0, (int) $core->get_store_checkout_id() );
		}
	}

	$settings = get_option( 'bwf_settings', [] );
	if ( is_array( $settings ) ) {
		foreach ( [ 'store_checkout_page_id', 'global_checkout_id', 'checkout_page_id' ] as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				return max( 0, (int) $settings[ $key ] );
			}
		}
	}

	return 0;
}

/**
 * Whether FunnelKit store checkout is enabled and published.
 */
function wchs_funnelkit_store_checkout_enabled(): bool {
	$post_id = wchs_funnelkit_store_checkout_post_id();
	if ( $post_id <= 0 ) {
		return false;
	}

	$settings = get_option( 'bwf_settings', [] );
	if ( is_array( $settings ) && array_key_exists( 'store_checkout_enabled', $settings ) ) {
		return (bool) $settings['store_checkout_enabled'];
	}

	return 'publish' === get_post_status( $post_id );
}

/**
 * Path from admin override or FunnelKit permalink.
 */
function wchs_funnelkit_resolved_checkout_path(): string {
	$settings = wchs_funnelkit_site_settings();
	$override = trim( (string) ( $settings['funnelkit_checkout_path'] ?? '' ), '/' );
	if ( $override !== '' ) {
		return '/' . $override;
	}

	if ( wchs_funnelkit_store_checkout_enabled() ) {
		$post_id = wchs_funnelkit_store_checkout_post_id();
		if ( $post_id > 0 ) {
			$permalink = get_permalink( $post_id );
			if ( is_string( $permalink ) && $permalink !== '' ) {
				$path = wp_parse_url( $permalink, PHP_URL_PATH );
				if ( is_string( $path ) && $path !== '' ) {
					$path = rtrim( $path, '/' );
					return $path === '' ? '/' : $path;
				}
			}
		}
	}

	return '/checkout';
}

/**
 * URL path (no query) customers use for checkout handoff, e.g. /checkout or /checkouts/alyve.
 */
function wchs_checkout_handoff_path(): string {
	if ( wchs_use_wchs_checkout_ui() ) {
		$path = '/checkout';
	} else {
		$path = wchs_funnelkit_resolved_checkout_path();
	}

	return (string) apply_filters( 'wchs_checkout_handoff_path', $path );
}

/**
 * Paths where the SPA cart JWT bridge may run (exact match after rtrim slash).
 *
 * @return string[]
 */
function wchs_checkout_handoff_paths(): array {
	$paths = [ wchs_checkout_handoff_path() ];
	return array_values( array_unique( apply_filters( 'wchs_checkout_handoff_paths', $paths ) ) );
}

/**
 * @param string $path Request path without trailing slash (except root).
 */
function wchs_is_checkout_handoff_path( string $path ): bool {
	$path = rtrim( $path, '/' );
	if ( $path === '' ) {
		$path = '/';
	}
	foreach ( wchs_checkout_handoff_paths() as $allowed ) {
		$allowed = rtrim( $allowed, '/' );
		if ( $allowed === '' ) {
			$allowed = '/';
		}
		if ( $path === $allowed ) {
			return true;
		}
	}
	return false;
}

/**
 * True when the current front-end request should use builder-owned checkout (no WCHS chrome).
 */
function wchs_funnelkit_is_checkout_request(): bool {
	if ( wchs_use_wchs_checkout_ui() ) {
		return false;
	}

	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '';
	$path = rtrim( $path, '/' );
	if ( wchs_is_checkout_handoff_path( $path ) ) {
		return true;
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		$post_id = wchs_funnelkit_store_checkout_post_id();
		if ( $post_id > 0 && (int) get_queried_object_id() === $post_id ) {
			return true;
		}
	}

	return false;
}

/**
 * Elementor / FunnelKit editor preview must not be bounced by the cart bridge.
 */
function wchs_is_checkout_builder_preview(): bool {
	if ( is_admin() ) {
		return true;
	}

	foreach ( [ 'elementor-preview', 'preview', 'preview_id', 'ver', 'bwf-builder-preview', 'wffn-preview' ] as $key ) {
		if ( ! empty( $_GET[ $key ] ) ) {
			return true;
		}
	}

	if ( class_exists( '\Elementor\Plugin' ) ) {
		try {
			$plugin = \Elementor\Plugin::$instance;
			if ( $plugin && isset( $plugin->preview ) && is_object( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
				return true;
			}
		} catch ( \Throwable $e ) {
			// Preview detection is best-effort only.
		}
	}

	return (bool) apply_filters( 'wchs_is_checkout_builder_preview', false );
}

/**
 * WCHS checkout chrome (timer, sidebar, payment column moves) must not run on FunnelKit pages.
 */
function wchs_use_wchs_checkout_enhancements(): bool {
	if ( ! wchs_use_wchs_checkout_ui() ) {
		return false;
	}
	if ( wchs_funnelkit_is_checkout_request() ) {
		return false;
	}
	return (bool) apply_filters( 'wchs_use_wchs_checkout_enhancements', true );
}
