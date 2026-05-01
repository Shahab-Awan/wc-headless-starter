<?php
/**
 * WCHS\DesignSystem\WcOverrides — PHP-side WooCommerce behavior hooks
 * that complement the CSS overrides. Nothing that belongs in a
 * stylesheet lives here.
 *
 * Currently:
 *   - Force classic cart/checkout shortcodes on install
 *     (block-based checkout is harder to restyle; we stick with classic)
 *   - Hide the woocommerce-breadcrumb (we don't use it in the SPA)
 *   - Remove wp-emoji bloat from native pages
 *
 * Previously these hooks lived in wp/themes/headless-shim/functions.php.
 * Consolidating them in the mu-plugin means they keep working even if
 * the theme is swapped.
 */

declare( strict_types = 1 );

namespace WCHS\DesignSystem;

defined( 'ABSPATH' ) || exit;

class WcOverrides {

	public function register(): void {
		// Fire once when WC is first installed — ensures cart + checkout
		// pages use classic shortcodes, not block-based versions.
		add_action( 'woocommerce_installed', [ $this, 'force_classic_shortcodes' ] );

		// Hide the breadcrumb globally.
		add_action( 'init', [ $this, 'remove_wc_breadcrumb' ] );

		// Remove wp-emoji bloat on the native pages.
		add_action( 'init', [ $this, 'remove_emoji_bloat' ] );

		// wp-login.php — strip "Powered by WordPress" + "Go to ..." link.
		add_filter( 'login_headerurl',  [ $this, 'login_header_url' ] );
		add_filter( 'login_headertext', [ $this, 'login_header_text' ] );
		add_action( 'login_footer',     [ $this, 'hide_login_nav' ], 1 );
	}

	public function login_header_url( $url ) {
		// Link the wp-login logo back to the SPA rather than wordpress.org
		if ( function_exists( 'wchs_spa_origin' ) ) {
			return wchs_spa_origin() . '/';
		}
		if ( defined( 'WCHS_SPA_URL' ) && is_string( WCHS_SPA_URL ) ) {
			return WCHS_SPA_URL;
		}
		return home_url( '/' );
	}

	public function login_header_text( $text ) {
		// Replace "Powered by WordPress" with nothing — the h1 gets
		// hidden via CSS and we render no brand text on the login form.
		// The login page is the sign-in form, nothing else. Keep it sparse.
		return '';
	}

	public function hide_login_nav(): void {
		// Hide the "← Go to Site Title" backtoblog link, the h1 logo link,
		// and any Powered-By text WP prints around the login form.
		echo '<style>
			body.login h1, body.login h1 a { display: none !important; }
			body.login #backtoblog { display: none !important; }
		</style>';
	}

	public function force_classic_shortcodes(): void {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}
		$pages = [
			wc_get_page_id( 'cart' )     => '[woocommerce_cart]',
			wc_get_page_id( 'checkout' ) => '[woocommerce_checkout]',
		];
		foreach ( $pages as $id => $shortcode ) {
			if ( $id && $id > 0 ) {
				wp_update_post( [ 'ID' => $id, 'post_content' => $shortcode ] );
			}
		}
	}

	public function remove_wc_breadcrumb(): void {
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		}
	}

	public function remove_emoji_bloat(): void {
		remove_action( 'wp_head',              'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts',  'print_emoji_detection_script' );
		remove_action( 'wp_print_styles',      'print_emoji_styles' );
		remove_action( 'admin_print_styles',   'print_emoji_styles' );
		remove_filter( 'the_content_feed',     'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss',     'wp_staticize_emoji' );
		remove_filter( 'wp_mail',              'wp_staticize_emoji_for_email' );
	}
}
