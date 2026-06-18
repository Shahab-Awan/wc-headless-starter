<?php
/**
 * Plugin Name: Headless FunnelKit Cart
 * Description: Optional FunnelKit Cart slide drawer on the SPA via classic-cart sync and a WP-hosted shell iframe.
 * Version:     0.3.0
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array<string, mixed>
 */
function wchs_funnelkit_cart_site_settings(): array {
	if ( class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return \WCHS\Admin\AdminPage::get_site_settings();
	}
	return [];
}

/**
 * Admin toggle: use FunnelKit Cart on the storefront instead of SlideCart.svelte.
 */
function wchs_use_funnelkit_cart(): bool {
	$settings = wchs_funnelkit_cart_site_settings();
	return (bool) ( $settings['use_funnelkit_cart'] ?? false );
}

/**
 * FunnelKit Cart plugin (cart-for-woocommerce) is present and booted.
 */
function wchs_funnelkit_cart_plugin_active(): bool {
	if (
		defined( 'FKCART_VERSION' )
		|| defined( 'FKCART_FILE' )
		|| defined( 'FKCART_PLUGIN_FILE' )
		|| function_exists( 'fkcart' )
		|| class_exists( 'FKCart\Main' )
		|| class_exists( 'FKCart\Plugin' )
		|| class_exists( 'FKCart\Includes\Front' )
	) {
		return true;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'cart-for-woocommerce/cart-for-woocommerce.php' )
		|| is_plugin_active( 'cart-for-woocommerce/plugin.php' )
		|| file_exists( WP_PLUGIN_DIR . '/cart-for-woocommerce/cart-for-woocommerce.php' );
}

/**
 * REST + SPA config payload for funnelkit_cart.
 *
 * @return array<string, mixed>
 */
function wchs_build_funnelkit_cart_config(): array {
	$requested = wchs_use_funnelkit_cart();
	$plugin    = wchs_funnelkit_cart_plugin_active();
	$enabled   = $requested && $plugin;
	$shell     = wchs_funnelkit_cart_shell_url();

	return [
		'requested'     => $requested,
		'enabled'       => $enabled,
		'shell_url'     => $enabled ? $shell : '',
		'sync_url'      => $enabled ? rest_url( 'wchs/v1/cart/sync-classic' ) : '',
		'open_class'    => 'fkcart-mini-open',
		'cart_selector' => '.site-header__cart',
		'plugin_active' => $plugin,
	];
}

/**
 * WordPress route that always loads FunnelKit Cart assets (see .htaccess cart prefix).
 */
function wchs_funnelkit_cart_shell_url(): string {
	$spa = function_exists( 'wchs_spa_origin' ) ? wchs_spa_origin() : home_url();
	return add_query_arg(
		[
			'wchs_fk_cart_shell' => '1',
			'spa_origin'         => untrailingslashit( $spa ),
		],
		home_url( '/cart/' )
	);
}

/**
 * Prepare WooCommerce + session so FunnelKit Cart assets render on the shell page.
 */
function wchs_funnelkit_cart_bootstrap_shell(): void {
	if ( ! function_exists( 'wc' ) ) {
		return;
	}

	if ( null === WC()->cart ) {
		wc_load_cart();
	}

	if ( WC()->session && ! WC()->session->has_session() ) {
		WC()->session->set_customer_session_cookie( true );
	}

	if ( WC()->cart ) {
		WC()->cart->get_cart();
	}

	add_filter( 'woocommerce_is_cart', '__return_true', 9999 );
}

/**
 * Minimal WP page that only runs wp_head/wp_footer so FunnelKit Cart can render in an iframe.
 */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['wchs_fk_cart_shell'] ) ) {
			return;
		}
		if ( ! wchs_use_funnelkit_cart() || ! wchs_funnelkit_cart_plugin_active() ) {
			status_header( 404 );
			exit;
		}

		wchs_funnelkit_cart_bootstrap_shell();

		status_header( 200 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		$spa_origin = isset( $_GET['spa_origin'] ) ? esc_url_raw( wp_unslash( (string) $_GET['spa_origin'] ) ) : '';
		if ( $spa_origin === '' && function_exists( 'wchs_spa_origin' ) ) {
			$spa_origin = wchs_spa_origin();
		}
		$spa_origin = untrailingslashit( $spa_origin );

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php wp_head(); ?>
<style>
	html, body { margin: 0; padding: 0; background: transparent; overflow: hidden; }
	/* Shortcode icon lives in the shell for FK init; SPA header is the visible trigger. */
	.wchs-fk-cart-shell-mount {
		position: fixed;
		top: 0;
		left: 0;
		width: 1px;
		height: 1px;
		overflow: hidden;
		opacity: 0;
		z-index: -1;
	}
	#wchs-fk-remote-trigger {
		position: fixed;
		top: 0;
		left: 0;
		width: 1px;
		height: 1px;
		padding: 0;
		border: 0;
		opacity: 0;
	}
</style>
</head>
<body>
<div class="wchs-fk-cart-shell-mount" aria-hidden="true">
<?php
if ( shortcode_exists( 'fk_cart_menu' ) ) {
	echo do_shortcode( '[fk_cart_menu]' );
}
?>
</div>
<button type="button" id="wchs-fk-remote-trigger" class="fkcart-mini-open" aria-hidden="true" tabindex="-1"></button>
<?php wp_footer(); ?>
<script>
(function () {
	var wpOrigin = <?php echo wp_json_encode( untrailingslashit( home_url() ) ); ?>;
	var spaOrigin = <?php echo wp_json_encode( $spa_origin ); ?>;
	var allowed = [wpOrigin, spaOrigin].filter(function (o, i, a) { return o && a.indexOf(o) === i; });

	function isAllowedOrigin(origin) {
		return allowed.indexOf(origin) !== -1;
	}

	function notifyParent(type, detail) {
		if (window.parent === window) return;
		window.parent.postMessage(Object.assign({ type: type }, detail || {}), '*');
	}

	function refreshSideCart() {
		if (!window.jQuery) return false;
		try {
			window.jQuery(document.body).trigger('fkcart_update_side_cart', [true]);
			return true;
		} catch (err) {
			return false;
		}
	}

	function clickFkTriggers() {
		var clicked = false;
		var remote = document.getElementById('wchs-fk-remote-trigger');
		if (remote) {
			remote.click();
			clicked = true;
		}
		var icon = document.querySelector('.fkcart-shortcode-icon-wrap, .fkcart-shortcode-icon-wrap a, .fkcart-shortcode-icon');
		if (icon) {
			icon.click();
			clicked = true;
		}
		return clicked;
	}

	function openCart() {
		refreshSideCart();
		clickFkTriggers();
		if (window.jQuery) {
			try {
				window.jQuery(document.body).trigger('fkcart_open');
			} catch (err) {}
			try {
				window.jQuery(document.body).trigger('fkcart_open_slider');
			} catch (err2) {}
		}
		if (typeof window.fkcart_open === 'function') {
			try {
				window.fkcart_open();
			} catch (err3) {}
		}
		notifyIfOpen();
		return true;
	}

	function isCartOpen() {
		if (document.body.classList.contains('fkcart-trigger-open')) return true;
		var modal = document.querySelector('#fkcart-modal, .fkcart-modal, .fkcart-drawer, [data-fkcart-modal]');
		if (!modal) return false;
		if (modal.classList.contains('fkcart-show') || modal.classList.contains('is-open')) return true;
		if (modal.getAttribute('aria-hidden') === 'false') return true;
		var style = window.getComputedStyle(modal);
		return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
	}

	function notifyIfOpen() {
		if (isCartOpen()) notifyParent('wchs-fk-cart-opened');
	}

	window.addEventListener('message', function (e) {
		if (!e.data || !isAllowedOrigin(e.origin)) return;
		if (e.data.type === 'wchs-fk-cart-synced') {
			refreshSideCart();
			return;
		}
		if (e.data.type !== 'wchs-fk-cart-open') return;
		openCart();
		setTimeout(openCart, 150);
		setTimeout(openCart, 600);
		var polls = 0;
		var pollId = setInterval(function () {
			polls++;
			if (isCartOpen()) {
				notifyParent('wchs-fk-cart-opened');
				clearInterval(pollId);
				return;
			}
			if (polls >= 25) clearInterval(pollId);
		}, 200);
	});

	function bindFkEvents() {
		if (!window.jQuery) return;
		window.jQuery(document.body)
			.off('fkcart_open.wchs fkcart_open_slider.wchs')
			.on('fkcart_open.wchs fkcart_open_slider.wchs', function () {
				notifyParent('wchs-fk-cart-opened');
			});
		window.jQuery(document.body)
			.off('fkcart_close.wchs fkcart_close_slider.wchs fkcart_closed.wchs')
			.on('fkcart_close.wchs fkcart_close_slider.wchs fkcart_closed.wchs', function () {
				notifyParent('wchs-fk-cart-closed');
			});
	}

	function signalReady() {
		bindFkEvents();
		notifyParent('wchs-fk-cart-ready', {
			hasJquery: !!window.jQuery,
			hasFkModal: !!document.querySelector('#fkcart-modal, .fkcart-modal, [data-fkcart-modal]')
		});
	}

	if (document.readyState === 'complete') {
		signalReady();
	} else {
		window.addEventListener('load', signalReady);
	}
})();
</script>
</body>
</html>
		<?php
		exit;
	},
	0
);

/**
 * Skip WCHS WC overrides on the FK shell — they can interfere with FunnelKit cart CSS.
 */
add_filter(
	'wchs_design_system_skip_wc_overrides',
	function ( $skip ) {
		if ( ! empty( $_GET['wchs_fk_cart_shell'] ) && wchs_use_funnelkit_cart() ) {
			return true;
		}
		return $skip;
	}
);

/**
 * FunnelKit checkout button inside the iframe should use the WCHS handoff path.
 */
add_filter(
	'woocommerce_get_checkout_url',
	function ( $url ) {
		if ( ! wchs_use_funnelkit_cart() || ! function_exists( 'wchs_checkout_handoff_path' ) ) {
			return $url;
		}
		$path = wchs_checkout_handoff_path();
		return home_url( untrailingslashit( $path ) . '/' );
	},
	20
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wchs/v1',
			'/cart/sync-classic',
			[
				'methods'             => [ 'POST', 'OPTIONS' ],
				'callback'            => 'wchs_rest_cart_sync_classic',
				'permission_callback' => '__return_true',
			]
		);
	}
);

/**
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response|\WP_Error
 */
function wchs_rest_cart_sync_classic( \WP_REST_Request $request ) {
	if ( ! wchs_use_funnelkit_cart() || ! wchs_funnelkit_cart_plugin_active() ) {
		return new \WP_Error( 'wchs_fk_cart_disabled', 'FunnelKit cart integration is disabled.', [ 'status' => 403 ] );
	}

	if ( ! function_exists( 'wchs_rest_rate_limit' ) || ! wchs_rest_rate_limit( 'cart_sync_classic' ) ) {
		return new \WP_Error( 'wchs_rate_limited', 'Too many requests.', [ 'status' => 429 ] );
	}

	if ( ! function_exists( 'wc' ) || ! WC()->session ) {
		return new \WP_Error( 'wchs_wc_unavailable', 'WooCommerce is not available.', [ 'status' => 503 ] );
	}

	$token = '';
	$header = $request->get_header( 'cart-token' );
	if ( is_string( $header ) && $header !== '' ) {
		$token = sanitize_text_field( $header );
	}
	if ( $token === '' ) {
		$body = $request->get_json_params();
		if ( is_array( $body ) && ! empty( $body['cart'] ) ) {
			$token = sanitize_text_field( (string) $body['cart'] );
		}
	}
	if ( $token === '' && ! empty( $_GET['cart'] ) ) {
		$token = sanitize_text_field( wp_unslash( (string) $_GET['cart'] ) );
	}
	if ( $token === '' ) {
		return new \WP_Error( 'wchs_missing_cart_token', 'Cart token is required.', [ 'status' => 400 ] );
	}

	if ( ! function_exists( 'wchs_import_store_cart_from_token' ) ) {
		return new \WP_Error( 'wchs_bridge_missing', 'Cart bridge is not loaded.', [ 'status' => 500 ] );
	}

	$result = wchs_import_store_cart_from_token( $token );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$count = 0;
	if ( WC()->cart ) {
		$count = WC()->cart->get_cart_contents_count();
	}

	if ( WC()->session && method_exists( WC()->session, 'save_data' ) ) {
		WC()->session->save_data();
	}

	return new \WP_REST_Response(
		[
			'ok'          => true,
			'items_count' => $count,
		],
		200
	);
}
