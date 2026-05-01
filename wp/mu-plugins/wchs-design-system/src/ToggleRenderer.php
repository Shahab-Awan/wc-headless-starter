<?php
/**
 * WCHS\DesignSystem\ToggleRenderer — outputs a floating theme toggle
 * button bottom-right on every native WP page. Styled via the .wchs-
 * theme-toggle class in wc-overrides.css. Click wiring happens in
 * assets/theme-sync.js.
 *
 * The button holds both sun and moon SVG icons. CSS in wc-overrides
 * shows only the appropriate one based on [data-theme]. No JS needed
 * to swap icons.
 *
 * Intentionally not rendered on /wp-admin/ — we don't want to mess
 * with the WP admin UI. Only front-end + wp-login.
 */

declare( strict_types = 1 );

namespace WCHS\DesignSystem;

defined( 'ABSPATH' ) || exit;

class ToggleRenderer {

	public function register(): void {
		add_action( 'wp_footer',    [ $this, 'render' ], 99 );
		add_action( 'login_footer', [ $this, 'render' ], 99 );
	}

	public function render(): void {
		if ( is_admin() ) {
			return;
		}
		?>
<button type="button" id="wchs-theme-toggle" class="wchs-theme-toggle" aria-label="Toggle dark mode" title="Toggle theme">
	<svg class="wchs-theme-toggle__sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<circle cx="12" cy="12" r="4"/>
		<path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
	</svg>
	<svg class="wchs-theme-toggle__moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
	</svg>
</button>
		<?php
	}
}
