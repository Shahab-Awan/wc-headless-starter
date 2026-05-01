<?php
/**
 * WCHS\DesignSystem\ThemeSync — enqueues a tiny IIFE that reads
 * localStorage.wchs_theme, sets [data-theme] on <html>, and wires the
 * floating toggle button's click handler.
 *
 * Mirrors the SPA's theme store exactly. Same storage key (wchs_theme),
 * same attribute name (data-theme), same values (light | dark). This
 * keeps the native WP pages in sync with the SPA without any PHP cookie
 * gymnastics.
 *
 * Rendered inline in the head with data-no-opt so ad blockers / page
 * builders don't strip it. Inline is a deliberate choice: it runs
 * synchronously before any paint, preventing FOUC where the page
 * briefly shows the wrong theme before JS loads.
 */

declare( strict_types = 1 );

namespace WCHS\DesignSystem;

defined( 'ABSPATH' ) || exit;

class ThemeSync {

	public function register(): void {
		add_action( 'wp_head',    [ $this, 'print_inline_sync' ], 1 );
		add_action( 'login_head', [ $this, 'print_inline_sync' ], 1 );
		add_action( 'wp_head',    [ $this, 'print_gtm_head' ], 2 );
		add_action( 'login_head', [ $this, 'print_gtm_head' ], 2 );
		add_action( 'wp_body_open', [ $this, 'print_gtm_body' ], 1 );
		add_action( 'login_header', [ $this, 'print_gtm_body' ], 0 );
		add_action( 'wp_footer',    [ $this, 'enqueue_footer_script' ], 5 );
		add_action( 'login_footer', [ $this, 'enqueue_footer_script' ], 5 );
	}

	private function get_gtm_id(): string {
		if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
			return '';
		}
		$settings = \WCHS\Admin\AdminPage::get_site_settings();
		return $settings['gtm_id'] ?? '';
	}

	public function print_gtm_head(): void {
		$id = $this->get_gtm_id();
		if ( ! $id ) return;
		?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js( $id ); ?>');</script>
<!-- End Google Tag Manager -->
		<?php
	}

	public function print_gtm_body(): void {
		$id = $this->get_gtm_id();
		if ( ! $id ) return;
		?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $id ); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
		<?php
	}

	/**
	 * Synchronous inline head script — runs BEFORE any CSS paints so
	 * there's no flash of unthemed content. Only the minimum needed to
	 * pick a theme and slap it on <html>. The rest (toggle wiring, cross
	 * tab sync) is in the deferred footer script.
	 */
	public function print_inline_sync(): void {
		?>
<script data-no-optimize="1">
(function(){
try{
	var k='wchs_theme',s=null;
	// Cookie first — shared across ports on same hostname (localhost:5175 ↔ localhost:8099)
	var m=document.cookie.match(/(?:^|; )wchs_theme=([^;]*)/);
	if(m)s=decodeURIComponent(m[1]);
	// Fall back to localStorage (same-origin only)
	if(s!=='light'&&s!=='dark'){try{s=localStorage.getItem(k);}catch(e){}}
	var t=(s==='light'||s==='dark')?s:(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
	document.documentElement.setAttribute('data-theme',t);
	document.documentElement.style.colorScheme=t;
}catch(e){}
})();
</script>
		<?php
	}

	/**
	 * Non-critical theme wiring — toggle click, cross-tab storage sync.
	 * Ok to defer.
	 */
	public function enqueue_footer_script(): void {
		wp_enqueue_script(
			'wchs-ds-theme-sync',
			WCHS_DS_URL . '/assets/theme-sync.js',
			[],
			WCHS_DS_VERSION,
			[ 'in_footer' => true, 'strategy' => 'defer' ]
		);
	}
}
