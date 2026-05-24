<?php
/**
 * Plugin Name: Headless FunnelKit Cart
 * Description: Optional FunnelKit Cart slide drawer on the SPA via classic-cart sync and a WP-hosted shell iframe.
 * Version:     0.2.0
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
	return defined( 'FKCART_VERSION' )
		|| defined( 'FKCART_FILE' )
		|| defined( 'FKCART_PLUGIN_FILE' )
		|| function_exists( 'fkcart' )
		|| class_exists( 'FKCart\Main' )
		|| class_exists( 'FKCart\Plugin' );
}

/**
 * REST + SPA config payload for funnelkit_cart.
 *
 * @return array<string, mixed>
 */
function wchs_build_funnelkit_cart_config(): array {
	$enabled = wchs_use_funnelkit_cart() && wchs_funnelkit_cart_plugin_active();
	$shell   = wchs_funnelkit_cart_shell_url();

	return [
		'enabled'       => $enabled,
		'shell_url'     => $enabled ? $shell : '',
		'sync_url'      => $enabled ? rest_url( 'wchs/v1/cart/sync-classic' ) : '',
		'open_class'    => 'fkcart-mini-open',
		'cart_selector' => '#fkcart-floating-toggler',
		'plugin_active' => wchs_funnelkit_cart_plugin_active(),
	];
}

/**
 * WordPress route that always loads FunnelKit Cart assets (see .htaccess cart prefix).
 */
function wchs_funnelkit_cart_shell_url(): string {
	return home_url( '/cart/?wchs_fk_cart_shell=1' );
}

/**
 * Print FunnelKit floating icon + drawer when wp_footer hooks did not (e.g. cart_display "none").
 */
function wchs_funnelkit_cart_shell_render_fk_markup(): void {
	if ( ! class_exists( 'FKCart\Includes\Front' ) ) {
		return;
	}
	$front = FKCart\Includes\Front::get_instance();
	if ( method_exists( $front, 'cart_icon' ) ) {
		$front->cart_icon();
	}
	if ( method_exists( $front, 'cart_content' ) ) {
		$front->cart_content();
	}
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

		status_header( 200 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php wp_head(); ?>
<style>
	html, body { margin: 0; padding: 0; background: transparent; overflow: hidden; }
	#fkcart-floating-toggler {
		position: absolute !important;
		top: 0 !important;
		right: 0 !important;
		bottom: auto !important;
		left: auto !important;
		margin: 0 !important;
		transform: none !important;
		z-index: 2 !important;
	}
</style>
</head>
<body>
<?php
		wp_footer();
		if ( class_exists( 'FKCart\Includes\Data' ) && ! FKCart\Includes\Data::is_cart_enabled() ) {
			wchs_funnelkit_cart_shell_render_fk_markup();
		} elseif ( ! class_exists( 'FKCart\Includes\Data' ) ) {
			wchs_funnelkit_cart_shell_render_fk_markup();
		}
		?>
<script>
(function () {
	var origin = <?php echo wp_json_encode( untrailingslashit( home_url() ) ); ?>;
	window.addEventListener('message', function (e) {
		if (!e.data || e.data.type !== 'wchs-fk-cart-open') return;
		if (e.origin !== origin) return;
		var opened = false;
		if (window.jQuery) {
			try {
				window.jQuery(document.body).trigger('fkcart_open_slider');
				opened = true;
			} catch (err) {}
		}
		if (!opened) {
			var el = document.querySelector('.fkcart-mini-open, [data-fkcart-trigger], .fkcart-cart-open');
			if (el && typeof el.click === 'function') el.click();
		}
	});
	window.parent.postMessage({ type: 'wchs-fk-cart-ready' }, origin);
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
