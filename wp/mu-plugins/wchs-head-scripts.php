<?php
/**
 * Plugin Name: WCHS Head Scripts
 * Description: Injects curated third-party scripts (Alia, GTM, Omnisend,
 *              Klaviyo, Cookiebot, etc.) on WP-rendered pages (checkout,
 *              my-account). Scripts come from the admin-curated registry
 *              (wchs_script_registry) filtered by per-site toggles
 *              (wchs_site_settings.active_scripts). The SPA renders the
 *              same scripts on its own routes via config.data.active_scripts;
 *              this file handles the WP half only.
 *
 * Scope: shop_manager-gated config (Site Scripts tab). IP surface (the
 * src_template + param schema) is gated to real-administrators via the
 * Script Registry tab. See wp/mu-plugins/wchs-admin/admin-page.php.
 *
 * Version:     0.1.0
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render all enabled registry entries whose 'surfaces' includes 'wp'.
 * Hooked to both wp_head + wp_footer so placement='body_end' entries
 * land at the end of body rather than head.
 *
 * Uses the same wchs_build_active_scripts() resolver the REST endpoint
 * uses, so WP-rendered pages and SPA routes see byte-identical specs.
 */
function wchs_head_scripts_render( string $placement ): void {
	if ( ! function_exists( 'wchs_build_active_scripts' ) ) {
		return; // rest-endpoints mu-plugin not loaded yet; skip silently
	}
	$site_settings = \WCHS\Admin\AdminPage::get_site_settings();
	$scripts       = wchs_build_active_scripts( $site_settings );

	foreach ( $scripts as $s ) {
		if ( ( $s['placement'] ?? 'head' ) !== $placement ) {
			continue;
		}
		if ( ! in_array( 'wp', $s['surfaces'] ?? [ 'wp' ], true ) ) {
			continue;
		}

		$attrs = [
			sprintf( 'src="%s"', esc_url( $s['src'] ) ),
			sprintf( 'data-wchs-id="%s"', esc_attr( $s['id'] ) ),
		];
		if ( ! empty( $s['async'] ) ) $attrs[] = 'async';
		if ( ! empty( $s['defer'] ) ) $attrs[] = 'defer';

		echo "<script " . implode( ' ', $attrs ) . "></script>\n";
	}
}

add_action( 'wp_head',   function () { wchs_head_scripts_render( 'head' ); },     99 );
add_action( 'wp_footer', function () { wchs_head_scripts_render( 'body_end' ); }, 99 );
