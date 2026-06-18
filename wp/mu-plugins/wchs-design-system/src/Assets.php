<?php
/**
 * WCHS\DesignSystem\Assets — enqueues shared design tokens + WC widget
 * overrides on every front-end page and the wp-login page. Dequeues
 * WooCommerce's own stylesheets FIRST so our tokens win the cascade.
 *
 * Dequeue order matters: we hook wp_enqueue_scripts at priority 999,
 * which fires after WC's default priority 10 enqueue. We rip out their
 * styles then add ours.
 *
 * Never use !important in wc-overrides.css (except the documented
 * Select2 exception) — our high priority + token-based cascade should
 * win without it.
 */

declare( strict_types = 1 );

namespace WCHS\DesignSystem;

defined( 'ABSPATH' ) || exit;

class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_front' ],    999 );
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_login' ],    999 );
	}

	public function enqueue_front(): void {
		$skip_wc_overrides = function_exists( 'wchs_use_wchs_checkout_ui' )
			&& ! wchs_use_wchs_checkout_ui()
			&& function_exists( 'wchs_funnelkit_is_checkout_request' )
			&& wchs_funnelkit_is_checkout_request();

		if ( apply_filters( 'wchs_design_system_skip_wc_overrides', false ) ) {
			$skip_wc_overrides = true;
		}

		if ( ! $skip_wc_overrides ) {
			$this->dequeue_wc_styles();
		}

		// Inter from Bunny (Runway single-typeface commitment)
		wp_enqueue_style(
			'wchs-ds-fonts',
			'https://fonts.bunny.net/css?family=inter:400,450,500,600&display=swap',
			[],
			null
		);

		// Shared tokens (also consumed by the SPA via symlink)
		wp_enqueue_style(
			'wchs-ds-tokens',
			WCHS_DS_URL . '/assets/tokens.css',
			[ 'wchs-ds-fonts' ],
			WCHS_DS_VERSION
		);

		if ( ! $skip_wc_overrides ) {
			wp_enqueue_style(
				'wchs-ds-wc-overrides',
				WCHS_DS_URL . '/assets/wc-overrides.css',
				[ 'wchs-ds-tokens' ],
				WCHS_DS_VERSION
			);
		}

		if ( $this->is_checkout_surface() ) {
			wp_enqueue_style(
				'wchs-ds-hide-stray-payment-terms',
				WCHS_DS_URL . '/assets/checkout-hide-stray-payment-terms.css',
				[ 'wchs-ds-tokens' ],
				WCHS_DS_VERSION
			);
		}

		// Shared header CSS — single source for both SPA and WP.
		// The SPA imports the same file via symlink.
		wp_enqueue_style(
			'wchs-ds-header',
			WCHS_DS_URL . '/assets/header.css',
			[ 'wchs-ds-tokens' ],
			WCHS_DS_VERSION
		);

		// Inject admin-configured accent color as CSS variable override.
		// The SPA does this via JS in +layout.svelte; WP pages need it inline.
		if ( class_exists( '\WCHS\Admin\AdminPage' ) ) {
			$settings = \WCHS\Admin\AdminPage::get_site_settings();
			$accent   = $settings['accent_color'] ?? null;
			if ( $accent && is_string( $accent ) ) {
				$accent_fg = \WCHS\Admin\AdminPage::get_accent_fg( $accent ) ?? '#ffffff';
				// Must override all specificity levels in tokens.css:
				// :root (0,0,1), [data-theme='dark'] (0,1,0),
				// and @media(prefers-color-scheme:dark) :root:not([data-theme='light']) (0,2,1)
				$css  = ":root, [data-theme='light'], [data-theme='dark'] { --accent: {$accent} !important; --accent-fg: {$accent_fg} !important; }";
				$css .= " @media (prefers-color-scheme: dark) { :root:not([data-theme='light']) { --accent: {$accent} !important; --accent-fg: {$accent_fg} !important; } }";
				wp_add_inline_style( 'wchs-ds-tokens', $css );
			}
		}
	}

	public function enqueue_login(): void {
		// Strip WP's default login stylesheets so our tokens win.
		// Keep `dashicons` — wp-login uses it for the caps-lock indicator
		// and the password show-toggle glyph, and we style them further
		// down in wc-overrides.css.
		foreach ( [ 'login', 'wp-admin', 'colors', 'buttons', 'forms' ] as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}

		wp_enqueue_style(
			'wchs-ds-login-fonts',
			'https://fonts.bunny.net/css?family=inter:400,450,500,600&display=swap',
			[],
			null
		);
		wp_enqueue_style(
			'wchs-ds-login-tokens',
			WCHS_DS_URL . '/assets/tokens.css',
			[ 'wchs-ds-login-fonts' ],
			WCHS_DS_VERSION
		);
		wp_enqueue_style(
			'wchs-ds-login-overrides',
			WCHS_DS_URL . '/assets/wc-overrides.css',
			[ 'wchs-ds-login-tokens' ],
			WCHS_DS_VERSION
		);
		wp_enqueue_style(
			'wchs-ds-login-header',
			WCHS_DS_URL . '/assets/header.css',
			[ 'wchs-ds-login-tokens' ],
			WCHS_DS_VERSION
		);
	}

	/**
	 * Native /checkout and FunnelKit store checkout (wc-overrides may be skipped there).
	 */
	private function is_checkout_surface(): bool {
		if ( function_exists( 'wchs_funnelkit_is_checkout_request' ) && wchs_funnelkit_is_checkout_request() ) {
			return true;
		}
		return function_exists( 'is_checkout' )
			&& is_checkout()
			&& ! is_wc_endpoint_url( 'order-received' );
	}

	/**
	 * Strip WooCommerce's own stylesheets so our overrides don't fight
	 * their high-specificity rules. We keep select2 (needed for the
	 * dropdown logic), the blocks CSS (unused but cheap), and any
	 * third-party plugin styles that aren't WC core.
	 */
	private function dequeue_wc_styles(): void {
		$handles = [
			'woocommerce-general',
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'wc-blocks-style',
			'wc-blocks-vendors-style',
		];
		foreach ( $handles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}
}
