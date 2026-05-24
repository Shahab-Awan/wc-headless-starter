<?php
/**
 * Plugin Name: Headless FunnelKit Cart
 * Description: FunnelKit Cart on the SPA via [fk_cart_menu], classic-cart sync, and direct script load (no iframe).
 * Version:     0.4.1
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
 * @return string Plugin root URL or empty.
 */
function wchs_funnelkit_cart_plugin_url(): string {
	if ( defined( 'FKCART_FILE' ) ) {
		return plugins_url( '/', FKCART_FILE );
	}
	if ( defined( 'FKCART_PLUGIN_FILE' ) ) {
		return plugins_url( '/', FKCART_PLUGIN_FILE );
	}
	return '';
}

/**
 * @param string $src Script or style src from WP enqueue.
 */
function wchs_funnelkit_cart_normalize_asset_url( string $src ): string {
	$src = trim( $src );
	if ( $src === '' ) {
		return '';
	}
	if ( str_starts_with( $src, '//' ) ) {
		return 'https:' . $src;
	}
	if ( preg_match( '#^https?://#i', $src ) ) {
		return $src;
	}
	return home_url( $src );
}

/**
 * Discover FK cart JS/CSS when enqueue hooks did not register handles (REST context).
 *
 * @return array{scripts: array<int, array{handle: string, src: string}>, styles: array<int, array{handle: string, src: string}>}
 */
function wchs_funnelkit_cart_asset_fallback(): array {
	$base = wchs_funnelkit_cart_plugin_url();
	if ( $base === '' ) {
		return [ 'scripts' => [], 'styles' => [] ];
	}

	$scripts = [
		[ 'handle' => 'jquery', 'src' => wchs_funnelkit_cart_normalize_asset_url( includes_url( 'jquery/jquery.min.js' ) ) ],
	];
	$styles  = [];

	$file = '';
	if ( defined( 'FKCART_FILE' ) ) {
		$file = FKCART_FILE;
	} elseif ( defined( 'FKCART_PLUGIN_FILE' ) ) {
		$file = FKCART_PLUGIN_FILE;
	}
	if ( $file !== '' ) {
		$dir = plugin_dir_path( $file );
		foreach ( glob( $dir . 'assets/**/*.js' ) ?: [] as $path ) {
			if ( str_contains( $path, 'admin' ) ) {
				continue;
			}
			$rel = str_replace( '\\', '/', substr( $path, strlen( $dir ) ) );
			$handle = 'fkcart-' . sanitize_title( basename( $path, '.js' ) );
			$deps   = [ 'jquery' ];
			if ( str_contains( $path, 'cart.min.js' ) ) {
				$deps[] = 'fkcart-embla-carousel-min';
			}
			$scripts[] = [
				'handle' => $handle,
				'src'    => wchs_funnelkit_cart_normalize_asset_url( $base . $rel ),
				'deps'   => $deps,
			];
		}
		foreach ( glob( $dir . 'assets/**/*.css' ) ?: [] as $path ) {
			if ( str_contains( $path, 'admin' ) ) {
				continue;
			}
			$rel = str_replace( '\\', '/', substr( $path, strlen( $dir ) ) );
			$styles[] = [
				'handle' => 'fkcart-' . sanitize_title( basename( $path, '.css' ) ),
				'src'    => wchs_funnelkit_cart_normalize_asset_url( $base . $rel ),
			];
		}
	}

	return [ 'scripts' => $scripts, 'styles' => $styles ];
}

/**
 * Capture FK cart scripts/styles registered for the storefront.
 *
 * @return array{scripts: array<int, array{handle: string, src: string}>, styles: array<int, array{handle: string, src: string}>}
 */
function wchs_funnelkit_cart_capture_assets(): array {
	$scripts = [];
	$styles  = [];

	if ( ! function_exists( 'wp_scripts' ) ) {
		return wchs_funnelkit_cart_asset_fallback();
	}

	if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
		define( 'WOOCOMMERCE_CART', true );
	}

	wp_scripts();
	wp_styles();
	wp_enqueue_script( 'jquery' );

	if ( function_exists( 'fkcart' ) ) {
		$inst = fkcart();
		if ( is_object( $inst ) ) {
			foreach ( [ 'frontend', 'public', 'front' ] as $prop ) {
				if ( ! isset( $inst->$prop ) || ! is_object( $inst->$prop ) ) {
					continue;
				}
				foreach ( [ 'enqueue_scripts', 'enqueue_assets', 'load_scripts' ] as $method ) {
					if ( method_exists( $inst->$prop, $method ) ) {
						$inst->$prop->$method();
					}
				}
			}
		}
	}

	do_action( 'wp_enqueue_scripts' );

	global $wp_scripts, $wp_styles;

	if ( $wp_scripts instanceof \WP_Scripts ) {
		foreach ( (array) $wp_scripts->queue as $handle ) {
			if ( ! is_string( $handle ) ) {
				continue;
			}
			if ( ! preg_match( '/fkcart|fk-cart|fkit-cart/i', $handle ) && $handle !== 'jquery' ) {
				continue;
			}
			$reg = $wp_scripts->registered[ $handle ] ?? null;
			if ( ! $reg || empty( $reg->src ) ) {
				continue;
			}
			$scripts[] = [
				'handle' => $handle,
				'src'    => wchs_funnelkit_cart_normalize_asset_url( (string) $reg->src ),
				'deps'   => array_values( array_filter( (array) ( $reg->deps ?? [] ), 'is_string' ) ),
			];
		}
	}

	if ( $wp_styles instanceof \WP_Styles ) {
		foreach ( (array) $wp_styles->queue as $handle ) {
			if ( ! is_string( $handle ) || ! preg_match( '/fkcart|fk-cart|fkit-cart/i', $handle ) ) {
				continue;
			}
			$reg = $wp_styles->registered[ $handle ] ?? null;
			if ( ! $reg || empty( $reg->src ) ) {
				continue;
			}
			$styles[] = [
				'handle' => $handle,
				'src'    => wchs_funnelkit_cart_normalize_asset_url( (string) $reg->src ),
			];
		}
	}

	$has_fk_script = false;
	foreach ( $scripts as $row ) {
		if ( preg_match( '/fkcart|fk-cart/i', (string) ( $row['handle'] ?? '' ) ) ) {
			$has_fk_script = true;
			break;
		}
	}
	if ( ! $has_fk_script ) {
		$fallback = wchs_funnelkit_cart_asset_fallback();
		$scripts  = array_merge( $scripts, $fallback['scripts'] );
		$styles   = array_merge( $styles, $fallback['styles'] );
	}

	$dedupe = static function ( array $rows ): array {
		$seen = [];
		$out  = [];
		foreach ( $rows as $row ) {
			$key = (string) ( $row['src'] ?? '' );
			if ( $key === '' || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $row;
		}
		return $out;
	};

	return [
		'scripts' => $dedupe( $scripts ),
		'styles'  => $dedupe( $styles ),
	];
}

/**
 * Inline script blobs (wp_localize_script) required by FunnelKit on the SPA.
 *
 * @return array<int, array{handle: string, data: string}>
 */
function wchs_funnelkit_cart_capture_inline_scripts(): array {
	global $wp_scripts;
	$out = [];
	if ( ! $wp_scripts instanceof \WP_Scripts ) {
		return $out;
	}
	foreach ( (array) $wp_scripts->queue as $handle ) {
		if ( ! is_string( $handle ) || ! preg_match( '/fkcart|fk-cart|fkit-cart|jquery/i', $handle ) ) {
			continue;
		}
		$reg = $wp_scripts->registered[ $handle ] ?? null;
		if ( ! $reg ) {
			continue;
		}
		if ( ! empty( $reg->extra['data'] ) && is_string( $reg->extra['data'] ) ) {
			$out[] = [
				'handle' => $handle,
				'data'   => $reg->extra['data'],
			];
		}
		foreach ( (array) ( $reg->extra['before'] ?? [] ) as $i => $code ) {
			if ( is_string( $code ) && $code !== '' ) {
				$out[] = [
					'handle' => $handle . '-before-' . $i,
					'data'   => $code,
				];
			}
		}
		foreach ( (array) ( $reg->extra['after'] ?? [] ) as $i => $code ) {
			if ( is_string( $code ) && $code !== '' ) {
				$out[] = [
					'handle' => $handle . '-after-' . $i,
					'data'   => $code,
				];
			}
		}
	}
	if ( empty( $out ) ) {
		$out = wchs_funnelkit_cart_fallback_inline_scripts();
	}
	return $out;
}

/**
 * Fallback wp_localize_script blobs when REST/bootstrap skips enqueue extras.
 *
 * @return array<int, array{handle: string, data: string}>
 */
function wchs_funnelkit_cart_localize_payload(): array {
	if ( class_exists( 'FKCart\Includes\Front' ) ) {
		$front = FKCart\Includes\Front::get_instance();
		if ( is_object( $front ) && method_exists( $front, 'localize_data' ) ) {
			$data = $front->localize_data();
			if ( is_array( $data ) && $data !== [] ) {
				return $data;
			}
		}
	}

	$checkout = function_exists( 'wchs_checkout_handoff_path' )
		? home_url( untrailingslashit( wchs_checkout_handoff_path() ) . '/' )
		: ( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '' );

	return [
		'ajax_url'     => admin_url( 'admin-ajax.php' ),
		'wc_ajax_url'  => class_exists( 'WC_AJAX' ) ? \WC_AJAX::get_endpoint( '%%endpoint%%' ) : '',
		'checkout_url' => $checkout,
	];
}

/**
 * Fallback wp_localize_script blobs when REST/bootstrap skips enqueue extras.
 *
 * @return array<int, array{handle: string, data: string}>
 */
function wchs_funnelkit_cart_fallback_inline_scripts(): array {
	$json = wp_json_encode( wchs_funnelkit_cart_localize_payload() );
	if ( ! is_string( $json ) ) {
		return [];
	}

	return [
		[
			'handle' => 'wchs-fkcart-inline',
			'data'   => 'var fkcart_app_data = ' . $json . '; var fkcart_data = fkcart_app_data;',
		],
	];
}

/**
 * Call a FKCart Front method and return HTML (empty when method missing).
 */
function wchs_funnelkit_invoke_front_method( string $method ): string {
	if ( ! class_exists( 'FKCart\Includes\Front' ) ) {
		return '';
	}
	$front = FKCart\Includes\Front::get_instance();
	if ( ! is_object( $front ) || ! method_exists( $front, $method ) ) {
		return '';
	}
	ob_start();
	$result = $front->$method();
	$html   = (string) ob_get_clean();
	if ( is_string( $result ) && $result !== '' ) {
		$html = $result . $html;
	}
	return wchs_funnelkit_cart_strip_global_assets( $html );
}

/**
 * Drawer shell — same markup as Front::cart_content() without Data::is_cart_enabled() gates
 * (cart_display "none" and non-WC routes return empty from cart_content on REST/bootstrap).
 */
function wchs_funnelkit_cart_drawer_shell_html(): string {
	if ( ! function_exists( 'fkcart_get_template_part' ) ) {
		return '';
	}

	$upsell_style  = 'style1';
	$icon_position = 'right';
	if ( class_exists( 'FKCart\Includes\Data' ) ) {
		$upsell_style  = (string) ( FKCart\Includes\Data::get_value( 'upsell_style' ) ?: 'style1' );
		$icon_position = (string) ( FKCart\Includes\Data::get_value( 'cart_icon_position' ) ?: 'right' );
	}

	ob_start();
	?>
	<div id="fkcart-modal" class="fkcart-modal" data-upsell-style="<?php echo esc_attr( $upsell_style ); ?>">
		<div class="fkcart-modal-container" data-direction="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>" data-slider-pos="<?php echo esc_attr( $icon_position ); ?>">
			<?php fkcart_get_template_part( 'cart/placeholder' ); ?>
		</div>
	</div>
	<?php
	return wchs_funnelkit_cart_strip_global_assets( (string) ob_get_clean() );
}

/**
 * Drawer DOM — FK only prints this on wp_footer on real page views; call Front API directly.
 */
function wchs_funnelkit_cart_render_drawer_markup(): string {
	$html = wchs_funnelkit_invoke_front_method( 'cart_content' );
	if ( strlen( trim( wp_strip_all_tags( $html ) ) ) >= 20 ) {
		return $html;
	}

	$html = wchs_funnelkit_cart_drawer_shell_html();
	if ( strlen( trim( wp_strip_all_tags( $html ) ) ) >= 20 ) {
		return $html;
	}

	return wchs_funnelkit_cart_capture_footer_markup();
}

/**
 * Remove tags that would change SPA-wide fonts/layout when injected into the storefront.
 */
function wchs_funnelkit_cart_strip_global_assets( string $html ): string {
	$html = (string) preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html );
	$html = (string) preg_replace( '#<link\b[^>]*>#is', '', $html );
	$html = (string) preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $html );
	return trim( $html );
}

/**
 * Slide-cart drawer markup from wp_footer only (never wp_head — that pulls theme fonts/CSS).
 */
function wchs_funnelkit_cart_capture_footer_markup(): string {
	ob_start();
	wp_footer();
	$html = (string) ob_get_clean();
	$html = wchs_funnelkit_cart_strip_global_assets( $html );

	if ( strlen( trim( wp_strip_all_tags( $html ) ) ) < 20 ) {
		foreach ( [ 'fkcart_slider_on_page', 'fkcart', 'fk_cart', 'fkcart_slider' ] as $tag ) {
			if ( shortcode_exists( $tag ) ) {
				$html .= (string) do_shortcode( '[' . $tag . ']' );
			}
		}
		$html = wchs_funnelkit_cart_strip_global_assets( $html );
	}

	return $html;
}

/**
 * Prime WooCommerce + FunnelKit enqueues the way a storefront page would.
 */
function wchs_funnelkit_cart_prepare_front_context(): void {
	if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
		define( 'WOOCOMMERCE_CART', true );
	}
	if ( function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	} elseif ( function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->get_cart();
	}
	wchs_funnelkit_cart_capture_assets();
}

/**
 * @return array{markup: string, scripts: array, styles: array, inline: array}
 */
function wchs_funnelkit_cart_bootstrap_payload(): array {
	wchs_funnelkit_cart_prepare_front_context();

	$assets = wchs_funnelkit_cart_capture_assets();
	$inline = wchs_funnelkit_cart_capture_inline_scripts();

	return [
		'markup'  => wchs_funnelkit_cart_render_drawer_markup(),
		'scripts' => $assets['scripts'],
		'styles'  => $assets['styles'],
		'inline'  => $inline,
	];
}

/**
 * JSON bootstrap for SPA — full FK drawer DOM + localized script data.
 */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['wchs_fk_cart_bootstrap'] ) ) {
			return;
		}
		if ( ! wchs_use_funnelkit_cart() || ! wchs_funnelkit_cart_plugin_active() ) {
			status_header( 404 );
			exit;
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );

		echo wp_json_encode( wchs_funnelkit_cart_bootstrap_payload() );
		exit;
	},
	999
);

/**
 * Render [fk_cart_menu] for the SPA header.
 */
function wchs_funnelkit_cart_menu_html(): string {
	$html = wchs_funnelkit_invoke_front_method( 'get_mini_cart_toggler' );
	if ( $html !== '' ) {
		return $html;
	}

	foreach ( [ 'fk_cart_menu', 'fkcart_menu', 'fkcart_cart_menu' ] as $tag ) {
		if ( shortcode_exists( $tag ) ) {
			$html = (string) do_shortcode( '[' . $tag . ']' );
			if ( $html !== '' ) {
				return $html;
			}
		}
	}
	foreach ( [ 'cart_menu', 'menu_cart', 'header_cart_menu', 'cart_menu_icon' ] as $method ) {
		$html = wchs_funnelkit_invoke_front_method( $method );
		if ( $html !== '' ) {
			return $html;
		}
	}
	return '';
}

/**
 * REST + SPA config payload for funnelkit_cart.
 *
 * @return array<string, mixed>
 */
function wchs_build_funnelkit_cart_config(): array {
	$plugin_active = wchs_funnelkit_cart_plugin_active();
	$enabled       = wchs_use_funnelkit_cart() && $plugin_active;
	$assets        = $enabled ? wchs_funnelkit_cart_capture_assets() : [ 'scripts' => [], 'styles' => [] ];
	$menu_html     = $enabled ? wchs_funnelkit_cart_menu_html() : '';

	$drawer_markup = $enabled ? wchs_funnelkit_cart_render_drawer_markup() : '';
	$markup_len    = strlen( $drawer_markup );

	return [
		'enabled'         => $enabled,
		'use_setting'     => wchs_use_funnelkit_cart(),
		'plugin_active'   => $plugin_active,
		'bootstrap_ok'    => $enabled && $markup_len > 50,
		'menu_html_empty' => $enabled && $menu_html === '',
		'menu_html'       => $menu_html,
		'bootstrap_url'   => $enabled ? home_url( '/cart/?wchs_fk_cart_bootstrap=1' ) : '',
		'sync_url'        => $enabled ? rest_url( 'wchs/v1/cart/sync-classic' ) : '',
		'scripts'         => $assets['scripts'],
		'styles'          => $assets['styles'],
		'open_class'      => 'fkcart-mini-open',
		'cart_selector'   => '.site-header__fkcart-menu',
		'trigger_selector' => '.site-header__fkcart-menu, .site-header__fkcart-menu .fkcart-mini-open',
		'auto_open_on_add' => true,
	];
}

/**
 * Hide FunnelKit floating launcher on SPA (header menu owns the trigger).
 */
add_action(
	'wp_head',
	function () {
		if ( ! wchs_use_funnelkit_cart() ) {
			return;
		}
		echo "<style id=\"wchs-fk-hide-floater\">#fkcart-mini-toggler,.fkcart-mini-toggler,.fkcart-floating-cart,.fkit-floating-cart,[data-fkcart-trigger=\"floating\"]{display:none!important;visibility:hidden!important}</style>\n";
	},
	999
);

/**
 * FunnelKit checkout button should use the WCHS handoff path.
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

		register_rest_route(
			'wchs/v1',
			'/funnelkit/bootstrap',
			[
				'methods'             => 'GET',
				'callback'            => 'wchs_rest_funnelkit_bootstrap',
				'permission_callback' => '__return_true',
			]
		);
	}
);

/**
 * @return \WP_REST_Response|\WP_Error
 */
function wchs_rest_funnelkit_bootstrap() {
	if ( ! wchs_use_funnelkit_cart() || ! wchs_funnelkit_cart_plugin_active() ) {
		return new \WP_Error( 'wchs_fk_cart_disabled', 'FunnelKit cart integration is disabled.', [ 'status' => 403 ] );
	}
	if ( ! function_exists( 'wchs_rest_rate_limit' ) || ! wchs_rest_rate_limit( 'funnelkit_bootstrap' ) ) {
		return new \WP_Error( 'wchs_rate_limited', 'Too many requests.', [ 'status' => 429 ] );
	}
	return new \WP_REST_Response( wchs_funnelkit_cart_bootstrap_payload(), 200 );
}

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

	$token  = '';
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
