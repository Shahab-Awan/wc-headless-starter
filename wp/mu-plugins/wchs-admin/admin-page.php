<?php
declare( strict_types = 1 );

namespace WCHS\Admin;

defined( 'ABSPATH' ) || exit;

class AdminPage {

	private const SITE_OPTION     = 'wchs_site_settings';
	private const HOMEPAGE_OPTION = 'wchs_homepage_config';
	private const PDP_OPTION      = 'wchs_pdp_config';
	private const PAGES_OPTION    = 'wchs_pages_config';
	private const SHOP_OPTION     = 'wchs_shop_config';
	private const GATEWAYS_OPTION = 'wchs_offline_gateways';
	private const REGISTRY_OPTION = 'wchs_script_registry';

	/**
	 * Script Registry — the admin-curated whitelist of external scripts that
	 * shop_managers can activate per-site via the Site Scripts tab.
	 *
	 * Shape of each entry:
	 *   id           unique slug (alnum + dash/underscore). Stable — referenced by active_scripts.
	 *   name         human label shown in admin
	 *   description  one-liner for the Site Scripts tab
	 *   src_template external URL WITHOUT query string. Params are appended server-side.
	 *   params[]     [{ key, label, required:bool, type:'text'|'url'|'hex', example }]
	 *   attributes   ['async' => bool, 'defer' => bool]
	 *   placement    'head' | 'body_end'
	 *   surfaces     subset of ['spa', 'wp'] — which page rendering contexts inject it
	 *   dedicated_setting_key  OPTIONAL — if the WCHS admin already has a dedicated
	 *                          setting for this integration (e.g. 'gtm_id' or
	 *                          'omnisend_brand_id'), set this so the renderer can
	 *                          skip the generic active_scripts injection when the
	 *                          dedicated path is populated (avoids double-firing).
	 *   category     one of ['analytics','pixel','marketing','consent','chat','other'].
	 *                Drives the tracker-chip color in the canvas preview.
	 *   mark         1–3 char display mark for the tracker chip (e.g. 'GTM', 'K', 'CB').
	 *
	 * Shop_manager can never edit these fields. They can only set
	 * wchs_site_settings['active_scripts'][*].enabled + params.
	 */
	private const REGISTRY_SEEDS = [
		[
			'id'           => 'alia',
			'name'         => 'Alia AI Shopping Assistant',
			'description'  => 'Shopify-backed AI chat widget for product recommendations and support. Storefront-only by default — does not render on /checkout or /my-account so the chat bubble doesn\'t distract mid-purchase. Override per-site via Script Registry if you want full coverage.',
			'src_template' => 'https://backend.alia-prod.com/public/embed.js',
			'params'       => [
				[ 'key' => 'shop', 'label' => 'Shopify shop handle', 'required' => true, 'type' => 'text', 'example' => 'yourstore.myshopify.com' ],
			],
			'attributes'   => [ 'async' => true, 'defer' => false ],
			'placement'    => 'head',
			'surfaces'     => [ 'spa' ],
			'dedicated_setting_key' => '',
			'category'     => 'chat',
			'mark'         => 'AL',
		],
		[
			'id'           => 'gtm',
			'name'         => 'Google Tag Manager',
			'description'  => 'Loads a GTM container. Prefer the dedicated GTM field under Integrations; this entry exists for parity.',
			'src_template' => 'https://www.googletagmanager.com/gtm.js',
			'params'       => [
				[ 'key' => 'id', 'label' => 'Container ID', 'required' => true, 'type' => 'text', 'example' => 'GTM-XXXXXXX' ],
			],
			'attributes'   => [ 'async' => true, 'defer' => false ],
			'placement'    => 'head',
			'surfaces'     => [ 'spa', 'wp' ],
			'dedicated_setting_key' => 'gtm_id',
			'category'     => 'analytics',
			'mark'         => 'GTM',
		],
		[
			'id'           => 'omnisend',
			'name'         => 'Omnisend Launcher',
			'description'  => 'Omnisend forms + tracking. Prefer the dedicated brand-id field under Integrations; this entry exists for parity.',
			'src_template' => 'https://omnisnippet1.com/inshop/launcher-v2.js',
			'params'       => [
				[ 'key' => 'brand_id', 'label' => 'Brand ID (hex)', 'required' => true, 'type' => 'hex', 'example' => '000000000000000000000000' ],
			],
			'attributes'   => [ 'async' => true, 'defer' => false ],
			'placement'    => 'head',
			'surfaces'     => [ 'spa', 'wp' ],
			'dedicated_setting_key' => 'omnisend_brand_id',
			'category'     => 'marketing',
			'mark'         => 'O',
		],
		[
			'id'           => 'klaviyo',
			'name'         => 'Klaviyo Onsite',
			'description'  => 'Klaviyo forms + tracking loader.',
			'src_template' => 'https://static.klaviyo.com/onsite/js/klaviyo.js',
			'params'       => [
				[ 'key' => 'company_id', 'label' => 'Public API Key', 'required' => true, 'type' => 'text', 'example' => 'PUBLIC_KEY' ],
			],
			'attributes'   => [ 'async' => true, 'defer' => false ],
			'placement'    => 'head',
			'surfaces'     => [ 'spa', 'wp' ],
			'dedicated_setting_key' => 'klaviyo_public_key',
			'category'     => 'marketing',
			'mark'         => 'K',
		],
		[
			'id'           => 'cookiebot',
			'name'         => 'Cookiebot Consent Banner',
			'description'  => 'GDPR consent banner. Loads before other marketing scripts.',
			'src_template' => 'https://consent.cookiebot.com/uc.js',
			'params'       => [
				[ 'key' => 'cbid',    'label' => 'Cookiebot ID',     'required' => true,  'type' => 'text', 'example' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' ],
				[ 'key' => 'culture', 'label' => 'Culture (locale)', 'required' => false, 'type' => 'text', 'example' => 'en' ],
			],
			'attributes'   => [ 'async' => true, 'defer' => false ],
			'placement'    => 'head',
			'surfaces'     => [ 'spa', 'wp' ],
			'dedicated_setting_key' => '',
			'category'     => 'consent',
			'mark'         => 'CB',
		],
		[
			'id'           => 'customerlabs',
			'name'         => 'CustomerLabs 1PD Ops',
			'description'  => 'CustomerLabs loader (inline bootstrap + CDN). Replace inline + src_template in REGISTRY_SEEDS with the snippet from your CustomerLabs workspace if this is not your account.',
			'src_template' => 'https://cdn.js.customerlabs.co/cl852373hycz6u.js',
			'params'       => [],
			'attributes'   => [ 'async' => true, 'defer' => false ],
			'placement'    => 'head',
			'surfaces'     => [ 'spa', 'wp' ],
			'dedicated_setting_key' => '',
			'category'     => 'analytics',
			'mark'         => 'CL',
			'inline_only'  => true,
			'inline'       => '!function(t,e,r,c,a,n,s){t.ClAnalyticsObject=a,t[a]=t[a]||[],t[a].methods=["trackSubmit","trackClick","pageview","identify","track", "trackConsent"],t[a].factory=function(e){return function(){var r=Array.prototype.slice.call(arguments);return r.unshift(e),t[a].push(r),t[a]}};for(var i=0;i<t[a].methods.length;i++){var o=t[a].methods[i];t[a][o]=t[a].factory(o)};n=e.createElement(r),s=e.getElementsByTagName(r)[0],n.async=1,n.crossOrigin="anonymous",n.src=c,s.parentNode.insertBefore(n,s)}(window,document,"script","https://cdn.js.customerlabs.co/cl852373hycz6u.js","_cl");_cl.SNIPPET_VERSION="2.0.0"',
		],
	];

	private const REGISTRY_ALLOWED_PLACEMENTS  = [ 'head', 'body_end' ];
	private const REGISTRY_ALLOWED_SURFACES    = [ 'spa', 'wp' ];
	private const REGISTRY_ALLOWED_PARAM_TYPES = [ 'text', 'url', 'hex' ];
	private const REGISTRY_ALLOWED_CATEGORIES  = [ 'analytics', 'pixel', 'marketing', 'consent', 'chat', 'other' ];

	private const ACCENT_PALETTE = [
		'#2563eb', // blue
		'#059669', // emerald
		'#dc2626', // red
		'#ea580c', // orange
		'#7c3aed', // violet
		'#ffdd24', // gold
		'#d4a24b', // dark gold
		'#06b6d4', // cyan
	];

	private const ACCENT_FG_MAP = [
		'#2563eb' => '#ffffff',
		'#059669' => '#ffffff',
		'#dc2626' => '#ffffff',
		'#ea580c' => '#ffffff',
		'#7c3aed' => '#ffffff',
		'#ffdd24' => '#ffffff',
		'#d4a24b' => '#000000',
		'#06b6d4' => '#ffffff',
	];

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_post_wchs_save_settings', [ $this, 'handle_save' ] );
		add_action( 'wp_ajax_wchs_product_search', [ $this, 'ajax_product_search' ] );
		add_action( 'wp_ajax_wchs_product_variations', [ $this, 'ajax_product_variations' ] );
	}

	public function ajax_product_search(): void {
		check_ajax_referer( 'wchs_product_search', '_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		$q       = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		$results = [];
		if ( strlen( $q ) >= 2 ) {
			$products = wc_get_products( [
				'limit'  => 10,
				's'      => $q,
				'status' => 'publish',
			] );
			foreach ( $products as $p ) {
				$img = wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' );
				if ( $p->is_type( 'variable' ) ) {
					$prices = $p->get_variation_prices( true );
					$min = min( $prices['price'] );
					$max = max( $prices['price'] );
					$price_str = html_entity_decode( strip_tags( wc_price( $min ) ), ENT_QUOTES, 'UTF-8' );
					if ( $min !== $max ) {
						$price_str .= ' – ' . html_entity_decode( strip_tags( wc_price( $max ) ), ENT_QUOTES, 'UTF-8' );
					}
				} else {
					$price_str = html_entity_decode( strip_tags( wc_price( $p->get_price() ) ), ENT_QUOTES, 'UTF-8' );
				}
				$results[] = [
					'id'    => $p->get_id(),
					'name'  => $p->get_name(),
					'price' => $price_str,
					'image' => $img ?: '',
					'type'  => $p->get_type(),
				];
			}
		}
		wp_send_json_success( $results );
	}

	public function ajax_product_variations(): void {
		check_ajax_referer( 'wchs_product_search', '_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		$pid = absint( $_GET['product_id'] ?? 0 );
		$product = wc_get_product( $pid );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			wp_send_json_error( 'Not a variable product' );
		}

		$attributes = [];
		foreach ( $product->get_variation_attributes() as $attr_name => $values ) {
			$attributes[] = [
				'name'   => wc_attribute_label( $attr_name, $product ),
				'slug'   => strtolower( $attr_name ),
				'values' => array_values( $values ),
			];
		}

		$variations = [];
		foreach ( $product->get_available_variations() as $v ) {
			$var_product = wc_get_product( $v['variation_id'] );
			$variations[] = [
				'id'         => $v['variation_id'],
				'attributes' => $v['attributes'],
				'price'      => html_entity_decode( strip_tags( wc_price( $v['display_price'] ) ), ENT_QUOTES, 'UTF-8' ),
				'in_stock'   => $v['is_in_stock'],
				'image'      => $v['image']['thumb_src'] ?? '',
			];
		}

		wp_send_json_success( [ 'attributes' => $attributes, 'variations' => $variations ] );
	}

	public function add_menu(): void {
		$hook = add_menu_page(
			'WCHS Settings',
			'WCHS',
			'manage_woocommerce',
			'wchs-settings',
			[ $this, 'render' ],
			'dashicons-layout',
			3
		);
		add_action( "admin_print_styles-{$hook}", [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets(): void {
		wp_enqueue_media();
		wp_enqueue_editor(); // Load TinyMCE for WYSIWYG fields in module modals
		// Version-bust by content hash — filemtime() silently fails when Docker
		// volume mounts preserve host mtime across atomic edits (same second),
		// so two consecutive edits can produce the same ver= and the browser
		// keeps stale JS. Hashing the bytes is cheap and always correct.
		$css_ver = self::asset_version( WCHS_ADMIN_DIR . '/assets/admin.css' );
		$js_ver  = self::asset_version( WCHS_ADMIN_DIR . '/assets/admin.js' );
		wp_enqueue_style( 'wchs-devices', WCHS_ADMIN_URL . '/assets/devices.min.css', [], '1.0.0' );
		wp_enqueue_style( 'wchs-admin', WCHS_ADMIN_URL . '/assets/admin.css', [ 'wchs-devices' ], $css_ver );
		wp_enqueue_script( 'wchs-panzoom', WCHS_ADMIN_URL . '/assets/panzoom.min.js', [], '4.5.1', true );
		wp_enqueue_script( 'wchs-admin', WCHS_ADMIN_URL . '/assets/admin.js', [ 'jquery', 'editor', 'wchs-panzoom' ], $js_ver, true );

		// Resolve SPA origin for the live preview iframe.
		$spa_origin = \function_exists( 'wchs_spa_origin' ) ? \wchs_spa_origin() : untrailingslashit( home_url( '/' ) );

		wp_localize_script( 'wchs-admin', 'wchsAdmin', [
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'productSearchNonce' => wp_create_nonce( 'wchs_product_search' ),
			'adminEmail'         => get_option( 'admin_email' ),
			'siteName'           => get_bloginfo( 'name' ),
			'spaOrigin'          => $spa_origin,
		] );
	}

	/**
	 * Content-hashed cache-bust version for static assets.
	 * SHA1-based so any byte change produces a different `ver=` param.
	 * Falls back to a fixed string if the file is unreadable.
	 */
	private static function asset_version( string $path ): string {
		if ( ! is_readable( $path ) ) {
			return '0';
		}
		$hash = @sha1_file( $path );
		return $hash ? substr( $hash, 0, 8 ) : '0';
	}

	// ─── Data access (used by config endpoint too) ───────────────

	public static function get_site_settings(): array {
		$defaults = [
			'access_mode'                 => 3,
			'accent_color'                => null,
			'gtm_id'                      => '',
			'omnisend_brand_id'           => '',
			'klaviyo_public_key'          => '',
			'meta_pixel_id'               => '',
			'tiktok_pixel_id'             => '',
			'pinterest_tag_id'            => '',
			'clarity_project_id'          => '',
			'hotjar_site_id'              => '',
			'google_ads_conversion_id'    => '',
			'google_ads_conversion_label' => '',
			'domain_origin_mode'          => '',
			'custom_spa_origin'           => '',
			'custom_allowed_origins'      => [],
			'custom_return_origins'       => [],
			'bump_variation_id'           => 0,
			'upsell_enabled'              => false,
			'bump_product_id'             => 0,
			'google_maps_api_key'         => '',
			'easypost_api_key'            => '',
			'address_validation_enabled'  => false,
			'address_validation_mode'     => 'moderate',
			'abandoned_cart_enabled'      => true,
			'review_provider'             => 'woocommerce',
			'review_provider_keys'        => [],
			'anti_bot_enabled'            => false,
			'turnstile_site_key'          => '',
			'turnstile_secret_key'        => '',
			'internal_rate_limit_enabled' => true,
			'announcement_bar_enabled'    => true,
			'announcement_bar_items'    => [
				'UP TO 40% OFF TODAY',
				'Fast & Discreet Shipping',
				'Third-Party Tested',
				'Fulfilled in the USA',
			],
			'header_links'                => [
				[ 'label' => 'Home', 'url' => '/', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'Shop', 'url' => '/shop', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'About', 'url' => '/about', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'COA Library', 'url' => '/coa-library', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'Contact', 'url' => '/contact', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'Account', 'url' => '/account', 'display' => 'icon', 'icon' => 'user', 'accent' => true, 'mobile_pin' => false ],
			],
			'header_toggle_accent'        => true,
			'header_cart_accent'          => true,
			'header_inverted'             => false,
			'header_borderless'           => false,
			'mobile_hamburger_side'       => 'right',
			'header_show_toggle'          => false,
			'header_toggle_mobile_pin'    => false,
			'header_cart_mobile_pin'      => true,
			'seo_nosnippet_products'      => false,
			'seo_block_cart_checkout'     => false,
			'reg_require_email_verify'    => false,
			'reg_require_address'         => false,
			'reg_require_name'            => false,
			'reg_require_phone'           => false,
			'gate_modal'                  => [
				'enabled'      => false,
				'strict'       => false,
				'title'        => '',
				'content'      => '',
				'confirm_text' => 'Enter Site',
				'decline_text' => '',
				'decline_url'  => '',
				'version'      => 1,
			],
			'footer'                      => [ 'columns' => [], 'tagline' => '' ],
			'social_links'                => [], // [{ platform: 'instagram'|'facebook'|'x'|'youtube'|'linkedin'|'tiktok'|'pinterest', url: string }]
			'theme_default'               => 'light', // 'system' | 'light' | 'dark' — what first-time visitors see
			'logo_invert_on_dark'         => true,      // auto-invert the header logo in dark mode
			'logo_dark_id'                => 0,         // optional WP attachment ID; when set, wins over invert in dark mode
			'logo_size'                   => 'standard', // 'compact' | 'standard' | 'prominent' | 'xl' — desktop only; mobile stays constrained
			'brand_position'              => 'nav-center', // 'left' | 'center' | 'nav-center' — desktop only
			'active_scripts'              => [],         // [{ id, enabled, params:{} }] — shop_manager toggles what's active from the admin-curated script registry
			'typography_heading_font'     => 'inter',     // 'inter' | 'barlow' | 'bebas' | 'playfair' | 'space_grotesk' | 'archivo' | 'oswald'
			'typography_body_font'        => 'inter',
			'typography_heading_weight'   => 'semibold',   // 'light' | 'regular' | 'medium' | 'semibold' | 'bold' | 'extrabold' | 'black'
			'typography_body_size'        => 'm',          // 's' (14px) | 'm' (15px) | 'l' (16px)
			'product_card'                => [
				'media_aspect_ratio'        => '1:1',          // '1:1' | '4:5' | '3:4' | '16:9' (1:1 = square)
				'corner_radius'             => 'round',       // 'square' | 'soft' | 'round' | 'pill'
				'border'                    => 'none',        // 'full' | 'bottom-only' | 'none' | 'hover-only'
				'hover_effect'              => 'shadow',      // 'lift' | 'shadow' | 'border' | 'none'
				'button_style'              => 'solid',       // 'outline' | 'solid' | 'icon-only'
				'badge_position'            => 'top-right',   // 'top-left' | 'top-right' (defaulted to top-right per 2025 CRO research)
				'badge_style'               => 'filled',      // 'filled' | 'outline' | 'minimal'
				'show_bulk_badge'           => true,
				'show_tier_hint'            => true,
				'show_oos_cards'            => true,          // false → filter out-of-stock products from grids
				'oos_treatment'             => 'grayscale',   // 'grayscale' | 'dim' | 'hidden-price'
				'title_lines'               => 'auto',        // 'auto' (pretext) | '1' | '2' | '3'
				'secondary_image_on_hover'  => false,
				'sale_badge_text'           => 'Sale',        // supports {percent} placeholder
			],
			'tokens'                      => [
				// null = component fallback (no change from hardcoded). Setting
				// any value cascades through the SPA via CSS vars applied on
				// document root at boot. Opt-in per token.
				'radius'             => null,  // px, 0–32
				'spacing_v_compact'  => null,  // px, 0–48
				'spacing_v_normal'   => null,  // px, 16–96
				'spacing_v_spacious' => null,  // px, 48–160
			],
			'cutover_checklist'           => [
				'domain'     => '',
				'items'      => [],
				'updated_at' => '',
			],
			'cutover_candidate_domain'    => '',
			'last_cutover_from_domain'    => '',
			'last_cutover_to_domain'      => '',
			'last_cutover_at'             => '',
		];
		$saved = get_option( self::SITE_OPTION, [] );
		$result = wp_parse_args( is_array( $saved ) ? $saved : [], $defaults );
		// Type-safety: accent_color must be a string or null
		if ( isset( $result['accent_color'] ) && ! is_string( $result['accent_color'] ) ) {
			$result['accent_color'] = null;
		}
		$result['domain_origin_mode'] = in_array( $result['domain_origin_mode'] ?? '', [ 'same-origin', 'custom' ], true )
			? (string) $result['domain_origin_mode']
			: '';
		$result['custom_spa_origin']      = \function_exists( 'wchs_normalize_origin' ) ? \wchs_normalize_origin( $result['custom_spa_origin'] ?? '' ) : '';
		$result['custom_allowed_origins'] = \function_exists( 'wchs_parse_origin_list' ) ? \wchs_parse_origin_list( $result['custom_allowed_origins'] ?? [] ) : [];
		$result['custom_return_origins']  = \function_exists( 'wchs_parse_origin_list' ) ? \wchs_parse_origin_list( $result['custom_return_origins'] ?? [] ) : [];
		$result['cutover_candidate_domain'] = self::normalize_cutover_domain( (string) ( $result['cutover_candidate_domain'] ?? '' ) );
		$result['last_cutover_from_domain'] = self::normalize_cutover_domain( (string) ( $result['last_cutover_from_domain'] ?? '' ) );
		$result['last_cutover_to_domain']   = self::normalize_cutover_domain( (string) ( $result['last_cutover_to_domain'] ?? '' ) );
		$result['last_cutover_at']          = (string) ( $result['last_cutover_at'] ?? '' );
		if ( ! is_array( $result['cutover_checklist'] ?? null ) ) {
			$result['cutover_checklist'] = $defaults['cutover_checklist'];
		} else {
			$result['cutover_checklist'] = wp_parse_args( $result['cutover_checklist'], $defaults['cutover_checklist'] );
			if ( ! is_array( $result['cutover_checklist']['items'] ?? null ) ) {
				$result['cutover_checklist']['items'] = [];
			}
		}
		return $result;
	}

	public static function get_homepage_config(): array {
		$defaults = self::homepage_defaults();
		$saved    = get_option( self::HOMEPAGE_OPTION, [] );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $defaults;
		}
		$saved['hero'] = wp_parse_args(
			$saved['hero'] ?? [],
			$defaults['hero']
		);
		$h = $saved['hero'];
		if (
			( $h['headline'] ?? '' ) === 'Welcome'
			&& ( $h['variant'] ?? '' ) === 'webgl-noise'
			&& false !== strpos( (string) ( $h['subheadline'] ?? '' ), 'Browse products, review your cart' )
		) {
			$saved['hero'] = array_merge( $h, $defaults['hero'] );
		}
		if ( empty( $saved['modules'] ) || ! is_array( $saved['modules'] ) ) {
			$saved['modules'] = $defaults['modules'];
		}
		return $saved;
	}

	public static function get_pdp_config(): array {
		$defaults = [
			'show_reviews'        => true,
			'cross_sell_mode'     => 'simple',
			'modules'             => [],
			'coa_library_url'     => '',
			'verified_label'      => 'VERIFIED',
			'show_ships_banner'   => true,
			'show_payment_icons'  => true,
			'image_disclaimer'    => 'FOR RESEARCH PURPOSES ONLY',
			'features'            => [
				[ 'icon' => 'lab', 'label' => 'Manufactured in US' ],
				[ 'icon' => 'zap', 'label' => 'Fastest in Trend' ],
				[ 'icon' => 'shield', 'label' => 'Independently Tested' ],
				[ 'icon' => 'shipping', 'label' => 'Same Day Shipping' ],
			],
			'trust_badges'        => [
				[ 'icon' => 'shipping', 'label' => 'Faster shipping' ],
				[ 'icon' => 'shield', 'label' => '60-day guarantee' ],
				[ 'icon' => 'lock', 'label' => 'Secure checkout' ],
			],
			'bundle_bogo'         => [
				'enabled'     => true,
				'savings_pct' => 50,
				'presets'     => [
					[ 'paid_qty' => 1, 'free_qty' => 0, 'flag' => '' ],
					[ 'paid_qty' => 2, 'free_qty' => 1, 'flag' => 'MOST POPULAR' ],
					[ 'paid_qty' => 3, 'free_qty' => 2, 'flag' => 'BEST VALUE' ],
				],
			],
			'cross_sell'          => [
				'eyebrow'      => 'FREQUENTLY PAIRED',
				'title'        => 'Often ordered with',
				'subtitle'     => 'Researchers commonly add these to their order',
				'view_all_url' => '/shop',
			],
			'slide_cart'          => [
				'cross_sell_exclude_product_ids' => [],
				'cross_sell_exclude_slugs'       => [ 'bac-water-10ml', 'shipping-protection' ],
			],
			'coa_section'         => [
				'enabled'         => true,
				'eyebrow'         => 'TRANSPARENCY',
				'title'           => 'Certificate of Analysis',
				'subtitle'        => 'Every batch independently verified by third-party laboratories.',
				'disclaimer'      => 'Certificates of Analysis are provided for informational purposes. Results apply to the specific batch tested. Products are sold for research use only.',
				'default_lab'     => 'Analytical Laboratories Inc.',
				'default_metrics' => [
					[ 'label' => 'HPLC Purity', 'value' => '≥99.4%' ],
					[ 'label' => 'LC-MS Identity', 'value' => 'Confirmed' ],
					[ 'label' => 'Sterility', 'value' => 'PASS' ],
					[ 'label' => 'Contaminants', 'value' => 'ND' ],
					[ 'label' => 'Heavy Metals', 'value' => '<20 ppb' ],
					[ 'label' => 'TAMC / TYMC', 'value' => 'PASS' ],
				],
			],
		];
		$saved = get_option( self::PDP_OPTION, [] );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $defaults;
		}
		return wp_parse_args( $saved, $defaults );
	}

	public static function get_shop_config(): array {
		$defaults = [
			'modules'      => [],
			'cols_min'     => 2,
			'cols_max'     => 4,
			'spacing_h' => 'normal',
		];
		$saved = get_option( self::SHOP_OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		$merged = array_merge( $defaults, $saved );
		if ( ! is_array( $merged['modules'] ) ) {
			$merged['modules'] = [];
		}
		// Clamp cols to sane bounds; ensure min <= max.
		$merged['cols_min'] = max( 1, min( 8, (int) $merged['cols_min'] ) );
		$merged['cols_max'] = max( 1, min( 8, (int) $merged['cols_max'] ) );
		if ( $merged['cols_min'] > $merged['cols_max'] ) {
			$merged['cols_min'] = $merged['cols_max'];
		}
		$valid_spacing = [ 'compact', 'normal', 'spacious' ];
		if ( ! in_array( $merged['spacing_h'] ?? 'normal', $valid_spacing, true ) ) {
			$merged['spacing_h'] = 'normal';
		}
		return $merged;
	}

	public static function get_pages_config(): array {
		$saved = get_option( self::PAGES_OPTION, [] );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return [ 'pages' => [] ];
		}
		if ( ! isset( $saved['pages'] ) || ! is_array( $saved['pages'] ) ) {
			$saved['pages'] = [];
		}
		return $saved;
	}

	public static function get_accent_fg( ?string $accent ): ?string {
		if ( ! $accent ) {
			return null;
		}
		return self::ACCENT_FG_MAP[ $accent ] ?? '#ffffff';
	}

	/**
	 * Returns the merged script registry — saved `wchs_script_registry` overrides
	 * the seeds. Returns `[ id => entry ]` keyed by id for O(1) lookup.
	 */
	public static function get_script_registry(): array {
		$seeds = [];
		foreach ( self::REGISTRY_SEEDS as $entry ) {
			$seeds[ $entry['id'] ] = $entry;
		}
		$saved = get_option( self::REGISTRY_OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		// Merge saved overrides into seeds field-by-field. This way new seed
		// fields (e.g. category/mark added later) still flow through even if
		// an older saved registry dump exists in wp_options.
		foreach ( $saved as $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['id'] ) ) {
				$id = $entry['id'];
				$seeds[ $id ] = isset( $seeds[ $id ] )
					? array_merge( $seeds[ $id ], $entry )
					: $entry;
			}
		}
		return $seeds;
	}

	private static function current_site_domain(): string {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	private static function current_request_domain(): string {
		$host = wp_unslash( $_SERVER['HTTP_HOST'] ?? '' );
		return self::normalize_cutover_domain( $host );
	}

	private static function normalize_cutover_domain( string $value ): string {
		$value = trim( strtolower( $value ) );
		if ( '' === $value ) {
			return '';
		}

		if ( ! str_contains( $value, '://' ) ) {
			$value = 'https://' . $value;
		}

		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$host = trim( strtolower( (string) $parts['host'] ), '.' );
		if ( '' === $host ) {
			return '';
		}
		if ( ! preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
			return '';
		}

		return $host;
	}

	private static function current_robots_sitemap_url(): string {
		$robots_path = trailingslashit( ABSPATH ) . 'robots.txt';
		if ( is_readable( $robots_path ) ) {
			$lines = @file( $robots_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			if ( is_array( $lines ) ) {
				foreach ( $lines as $line ) {
					if ( preg_match( '/^Sitemap:\s*(.+)$/i', trim( $line ), $matches ) ) {
						return trim( $matches[1] );
					}
				}
			}
		}

		return home_url( '/wp-sitemap.xml' );
	}

	private static function cutover_notice_key(): string {
		return 'wchs_cutover_notice_' . get_current_user_id();
	}

	private static function stash_cutover_notice( array $notice ): void {
		if ( get_current_user_id() <= 0 ) {
			return;
		}

		set_transient( self::cutover_notice_key(), $notice, 10 * MINUTE_IN_SECONDS );
	}

	private static function pull_cutover_notice(): ?array {
		if ( get_current_user_id() <= 0 ) {
			return null;
		}

		$key    = self::cutover_notice_key();
		$notice = get_transient( $key );
		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	private static function candidate_domain_for_cutover( array $settings ): string {
		$request = self::current_request_domain();
		$current = self::current_site_domain();
		if ( '' !== $request && $request !== $current ) {
			return $request;
		}

		return self::normalize_cutover_domain( (string) ( $settings['cutover_candidate_domain'] ?? '' ) );
	}

	private static function probe_cutover_origin( string $candidate_origin ): array {
		$response = wp_remote_get(
			trailingslashit( $candidate_origin ),
			[
				'timeout'     => 8,
				'redirection' => 0,
				'sslverify'   => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'ok'     => false,
				'status' => 0,
				'detail' => $response->get_error_message(),
			];
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$ok     = $status >= 200 && $status < 500;

		return [
			'ok'     => $ok,
			'status' => $status,
			'detail' => $ok ? sprintf( 'HTTPS responded with %d.', $status ) : sprintf( 'HTTPS responded with %d.', $status ),
		];
	}

	private static function guided_cutover_report( string $candidate_domain ): array {
		$current_domain = self::current_site_domain();
		$request_domain = self::current_request_domain();
		$origin_report  = \function_exists( 'wchs_origin_report' ) ? \wchs_origin_report() : [];
		$candidate      = self::normalize_cutover_domain( $candidate_domain );
		$candidate_origin = '' !== $candidate ? 'https://' . $candidate : '';
		$checks         = [];
		$errors         = [];
		$warnings       = [];

		if ( '' === $candidate ) {
			$errors[] = 'Enter the final production domain before previewing the cutover.';
			$checks[] = [
				'label'  => 'Candidate domain',
				'status' => 'error',
				'detail' => 'No valid domain was provided.',
			];
		} else {
			$checks[] = [
				'label'  => 'Candidate domain',
				'status' => $candidate !== $current_domain ? 'pass' : 'warning',
				'detail' => $candidate !== $current_domain
					? sprintf( 'Target domain is %s.', $candidate )
					: sprintf( '%s is already the current public domain.', $candidate ),
			];
			if ( $candidate === $current_domain ) {
				$warnings[] = 'The candidate domain already matches the current public domain.';
			}
		}

		$mode = (string) ( $origin_report['mode'] ?? 'same-origin' );
		$mode_ok = 'same-origin' === $mode;
		$checks[] = [
			'label'  => 'Origin mode',
			'status' => $mode_ok ? 'pass' : 'error',
			'detail' => $mode_ok
				? 'Same-origin is active, so WCHS will follow home/siteurl after cutover.'
				: 'Custom origin mode is active. Guided cutover is intentionally disabled for split-origin deployments.',
		];
		if ( ! $mode_ok ) {
			$errors[] = 'Guided cutover only runs when WCHS is in same-origin mode.';
		}

		$runtime_errors = is_array( $origin_report['errors'] ?? null ) ? $origin_report['errors'] : [];
		$checks[] = [
			'label'  => 'Current runtime health',
			'status' => empty( $runtime_errors ) ? 'pass' : 'error',
			'detail' => empty( $runtime_errors )
				? 'Current runtime alignment is clean.'
				: implode( ' ', array_map( 'strval', $runtime_errors ) ),
		];
		if ( ! empty( $runtime_errors ) ) {
			$errors[] = 'Fix the current runtime alignment issues before using guided cutover.';
		}

		if ( '' !== $candidate_origin ) {
			$probe = self::probe_cutover_origin( $candidate_origin );
			$checks[] = [
				'label'  => 'HTTPS reachability',
				'status' => $probe['ok'] ? 'pass' : 'error',
				'detail' => $probe['detail'],
			];
			if ( ! $probe['ok'] ) {
				$errors[] = 'The candidate domain did not answer over HTTPS from WordPress.';
			}
		}

		$checks[] = [
			'label'  => 'Current admin host',
			'status' => ( '' !== $request_domain && '' !== $candidate && $request_domain === $candidate ) ? 'pass' : 'warning',
			'detail' => ( '' !== $request_domain && '' !== $candidate && $request_domain === $candidate )
				? 'You are already viewing wp-admin on the candidate domain.'
				: ( '' !== $request_domain
					? sprintf( 'wp-admin is currently loaded from %s. That is fine; the finalize step will redirect you.', $request_domain )
					: 'Could not determine the current request host.' ),
		];

		return [
			'candidate_domain' => $candidate,
			'candidate_origin' => $candidate_origin,
			'current_domain'   => $current_domain,
			'request_domain'   => $request_domain,
			'ready'            => empty( $errors ) && '' !== $candidate && $candidate !== $current_domain,
			'checks'           => $checks,
			'errors'           => $errors,
			'warnings'         => $warnings,
		];
	}

	private static function maybe_update_cutover_robots_file( string $candidate_origin ): array {
		$robots_path = trailingslashit( ABSPATH ) . 'robots.txt';
		if ( ! file_exists( $robots_path ) ) {
			return [
				'status' => 'skipped',
				'detail' => 'No physical robots.txt file exists. Dynamic robots will follow home_url() automatically.',
			];
		}
		if ( ! is_readable( $robots_path ) || ! is_writable( $robots_path ) ) {
			return [
				'status' => 'warning',
				'detail' => 'robots.txt exists but is not writable, so it was left unchanged.',
			];
		}

		$contents = file_get_contents( $robots_path );
		if ( false === $contents ) {
			return [
				'status' => 'warning',
				'detail' => 'robots.txt could not be read.',
			];
		}

		$sitemap = 'Sitemap: ' . untrailingslashit( $candidate_origin ) . '/sitemap.xml';
		if ( preg_match( '/^Sitemap:\s*.+$/mi', $contents ) ) {
			$updated = (string) preg_replace( '/^Sitemap:\s*.+$/mi', $sitemap, $contents, 1 );
		} else {
			$updated = rtrim( $contents ) . "\n" . $sitemap . "\n";
		}

		if ( $updated === $contents ) {
			return [
				'status' => 'pass',
				'detail' => 'robots.txt already advertised the candidate sitemap.',
			];
		}

		$written = file_put_contents( $robots_path, $updated );
		if ( false === $written ) {
			return [
				'status' => 'warning',
				'detail' => 'robots.txt could not be updated.',
			];
		}

		return [
			'status' => 'pass',
			'detail' => 'robots.txt sitemap was updated to the new domain.',
		];
	}

	private static function purge_cutover_caches(): void {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
		do_action( 'sg_cachepress_purge_everything' );
		do_action( 'sg_cachepress_purge_cache' );
		do_action( 'sgo_purge_everything' );
	}

	private static function run_guided_cutover( string $candidate_domain, array &$settings ): array {
		$report = self::guided_cutover_report( $candidate_domain );
		if ( ! $report['ready'] ) {
			return [
				'status' => 'error',
				'title'  => 'Guided cutover is not ready.',
				'report' => $report,
			];
		}

		$current_domain   = self::current_site_domain();
		$candidate_domain = (string) $report['candidate_domain'];
		$candidate_origin = (string) $report['candidate_origin'];

		update_option( 'siteurl', $candidate_origin );
		update_option( 'home', $candidate_origin );

		$robots = self::maybe_update_cutover_robots_file( $candidate_origin );
		self::purge_cutover_caches();

		$settings['cutover_candidate_domain'] = $candidate_domain;
		$settings['cutover_checklist']        = [
			'domain'     => $candidate_domain,
			'items'      => [],
			'updated_at' => current_time( 'mysql' ),
		];
		$settings['last_cutover_from_domain'] = $current_domain;
		$settings['last_cutover_to_domain']   = $candidate_domain;
		$settings['last_cutover_at']          = current_time( 'mysql' );

		$post_report = \function_exists( 'wchs_origin_report' ) ? \wchs_origin_report() : [];
		$post_public = (string) ( $post_report['public_origin'] ?? '' );
		$post_spa    = (string) ( $post_report['spa_origin'] ?? '' );
		$post_errors = is_array( $post_report['errors'] ?? null ) ? $post_report['errors'] : [];
		$post_checks = [
			[
				'label'  => 'Core URLs',
				'status' => ( wp_parse_url( get_option( 'home' ), PHP_URL_HOST ) === $candidate_domain && wp_parse_url( get_option( 'siteurl' ), PHP_URL_HOST ) === $candidate_domain ) ? 'pass' : 'error',
				'detail' => sprintf( 'home=%s | siteurl=%s', get_option( 'home' ), get_option( 'siteurl' ) ),
			],
			[
				'label'  => 'WCHS runtime',
				'status' => ( $post_public === $candidate_origin && $post_spa === $candidate_origin && empty( $post_errors ) ) ? 'pass' : 'warning',
				'detail' => sprintf( 'public=%s | spa=%s', $post_public ?: '(unknown)', $post_spa ?: '(unknown)' ),
			],
			[
				'label'  => 'robots.txt',
				'status' => (string) ( $robots['status'] ?? 'warning' ),
				'detail' => (string) ( $robots['detail'] ?? '' ),
			],
		];

		return [
			'status'          => empty( $post_errors ) ? 'success' : 'warning',
			'title'           => empty( $post_errors ) ? 'Guided cutover completed.' : 'Guided cutover completed with follow-up checks.',
			'report'          => array_merge( $report, [ 'post_checks' => $post_checks, 'post_errors' => $post_errors ] ),
			'redirect_domain' => $candidate_domain,
		];
	}

	private static function cutover_task_definitions(): array {
		return [
			'dns_ssl_live'        => [
				'label' => 'Domain + SSL are live',
				'help'  => 'DNS points at the live host and the final certificate is active.',
			],
			'wp_runtime_verified' => [
				'label' => 'WCHS runtime verified',
				'help'  => 'Checkout redirects, login returns, config endpoint, and robots/sitemap all match the live domain.',
			],
			'stripe'              => [
				'label' => 'Stripe webhook updated',
				'help'  => 'Create the final webhook endpoint and paste the new signing secret into WooCommerce if Stripe issued one.',
			],
			'omnisend'            => [
				'label' => 'Omnisend store URL confirmed',
				'help'  => 'Update the store URL in Omnisend after the cutover and verify forms/tracking still load.',
			],
			'ga4'                 => [
				'label' => 'GA4 checked',
				'help'  => 'Review the web data stream, Realtime, and unwanted referrals after the domain change.',
			],
			'gtm'                 => [
				'label' => 'GTM preview + publish completed',
				'help'  => 'Run Tag Assistant on the new domain and publish any hardcoded hostname fixes.',
			],
			'search_console'      => [
				'label' => 'Search Console sitemap submitted',
				'help'  => 'Verify the new property and submit the sitemap you want Google to crawl.',
			],
			'checkout_smoke'      => [
				'label' => 'Checkout smoke test passed',
				'help'  => 'Run the golden path: homepage, PDP, cart, checkout, and thank-you redirect on the new domain.',
			],
		];
	}

	private static function cutover_checklist_state( array $settings ): array {
		$definitions   = self::cutover_task_definitions();
		$current_domain = self::current_site_domain();
		$stored         = is_array( $settings['cutover_checklist'] ?? null ) ? $settings['cutover_checklist'] : [];
		$stored_domain  = strtolower( (string) ( $stored['domain'] ?? '' ) );
		$stored_items   = is_array( $stored['items'] ?? null ) ? $stored['items'] : [];
		$items          = [];

		foreach ( $definitions as $key => $_meta ) {
			$items[ $key ] = ( $stored_domain === $current_domain ) && ! empty( $stored_items[ $key ] );
		}

		return [
			'domain'        => $current_domain,
			'stored_domain' => $stored_domain,
			'items'         => $items,
			'updated_at'    => (string) ( $stored['updated_at'] ?? '' ),
		];
	}

	private static function render_cutover_copy_row( string $label, string $value, string $help = '' ): void {
		?>
		<div class="wchs-cutover-copy-row">
			<div class="wchs-cutover-copy-row__meta">
				<strong><?php echo esc_html( $label ); ?></strong>
				<?php if ( $help !== '' ) : ?>
					<span><?php echo esc_html( $help ); ?></span>
				<?php endif; ?>
			</div>
			<code><?php echo esc_html( $value ); ?></code>
			<button type="button" class="wchs-btn wchs-btn--secondary wchs-copy-btn" data-copy-text="<?php echo esc_attr( $value ); ?>">Copy</button>
		</div>
		<?php
	}

	public static function homepage_defaults(): array {
		return [
			'hero' => [
				'headline'               => 'A leading grade provider of research peptides.',
				'content_mode'           => 'text',
				'logo_source'            => 'site_logo',
				'logo_url'               => '',
				'logo_dark_url'          => '',
				'logo_size'              => 'large',
				'headline_size'          => 'l',
				'headline_weight'        => 'medium',
				'headline_font'          => 'inter',
				'text_color_mode'        => 'white',
				'subheadline'            => 'Independently verified. Third-party tested. Every batch held to the highest standard.',
				'subheadline_size'       => 'm',
				'cta_text'               => 'Shop All Peptides',
				'cta_link'               => '/shop',
				'cta_secondary_text'     => 'View COA Library',
				'cta_secondary_link'     => '/coa-library',
				'research_badge'         => '• RESEARCH USE ONLY',
				'research_stats'         => [
					[ 'value' => '≥99%', 'label' => 'VERIFIED PURITY' ],
					[ 'value' => '6-panel', 'label' => 'COA EVERY BATCH' ],
					[ 'value' => '60+', 'label' => 'RESEARCH COMPOUNDS' ],
				],
				'variant'                => 'research-motion',
				'layout'                 => 'center',
				'image_desktop'          => '',
				'image_mobile'           => '',
				'image_position_x'       => 50,
				'image_position_y'       => 50,
				'image_position_mobile_x' => 50,
				'image_position_mobile_y' => 80,
				'image_zoom'             => 100,
				'image_zoom_mobile'      => 100,
				'show_eyebrow'           => false,
				'cta_accent'             => true,
				'show_cta'               => true,
				'show_rating'            => false,
				'rating_text'            => '',
				'trust_items'            => [],
			],
			'modules' => [
				[
					'type'       => 'split_value',
					'visibility' => 'all',
					'spacing_v'  => 'normal',
					'spacing_h'  => 'normal',
					'config'     => [
						'rating_line'         => 'Rated 4.98/5 · 24,987+ reviews',
						'headline_prefix'     => 'A Leading Provider of Research Grade',
						'headline_accent'     => 'Peptides.',
						'accent_underline'    => true,
						'bullets'             => [
							[ 'text' => 'Fast U.S. Shipping' ],
							[ 'text' => '99% Tested Purity' ],
							[ 'text' => 'Made in USA' ],
						],
						'cta_label'           => 'Buy 1 Get 1 Free',
						'cta_href'            => '/shop',
						'trust_note'          => 'Research use only. All major credit/debit cards, PayPal, ACH, BTC, Zelle.',
						'promo_badge_eyebrow' => 'LIMITED TIME',
						'promo_badge_title'   => 'Buy 1 Get 1 Free',
						'image'               => '/wp-content/uploads/2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp',
						'image_alt'           => 'Research-grade peptides — product lineup',
						'stats'               => [
							[ 'value' => '99%', 'label' => 'Purity' ],
							[ 'value' => '24.9K+', 'label' => 'Reviews' ],
							[ 'value' => 'Triple-Tested', 'label' => 'for Quality' ],
						],
					],
				],
				[
					'type'       => 'feature_highlights',
					'visibility' => 'all',
					'spacing_v'  => 'normal',
					'spacing_h'  => 'normal',
					'config'     => [
						'badge_text'      => 'Verified & Trusted',
						'headline_prefix' => 'The Standard for ',
						'headline_accent' => 'Verified Peptides',
						'subheadline'     => 'Independent testing. Full batch documentation. Reliable, tracked delivery.',
						'items'           => [
							[
								'variant'     => 'pin',
								'headline'    => 'USA Manufactured',
								'description' => 'Synthesized and packaged domestically. No overseas sourcing.',
							],
							[
								'variant'     => 'star',
								'headline'    => '5-Star Reviewed',
								'description' => 'Rated 5 stars by verified customers.',
							],
							[
								'variant'     => 'lab',
								'headline'    => 'Third-Party Lab Tested',
								'description' => 'Every batch independently verified before shipping.',
							],
							[
								'variant'     => 'award',
								'headline'    => 'Triple-Tested for Quality',
								'description' => 'Purity, Content, and Endotoxin testing on every product.',
							],
						],
						'cta_label'       => 'Buy 1 Get 1 Free',
						'cta_href'        => '/shop',
					],
				],
				[
					'type'       => 'product_slider',
					'visibility' => 'all',
					'config'     => [
						'title'       => 'Featured',
						'source'      => 'all',
						'category'    => null,
						'product_ids' => [],
					],
				],
			],
		];
	}

	// ─── Save handler ────────────────────────────────────────────

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'wchs_save_settings', 'wchs_nonce' );

		$tab = sanitize_text_field( wp_unslash( $_POST['wchs_tab'] ?? 'homepage' ) );

		// Real-admin-only tabs. Non-admins sending a POST with these tab values
		// get rejected — this is the last defense beyond the admin_init redirect,
		// tab-list filter, and field masking.
		$is_real_admin = function_exists( 'wchs_is_real_admin' ) ? wchs_is_real_admin() : current_user_can( 'install_plugins' );
		if ( ! $is_real_admin && in_array( $tab, [ 'security', 'smtp' ], true ) ) {
			wp_die( 'This settings tab is administrator-only.', 'Forbidden', [ 'response' => 403 ] );
		}

		$redirect = add_query_arg( [
			'page'    => 'wchs-settings',
			'tab'     => $tab,
			'updated' => '1',
		], admin_url( 'admin.php' ) );

		if ( 'design' === $tab ) {
			$this->save_appearance_settings();
		} elseif ( 'checkout' === $tab ) {
			$this->save_checkout_settings();
			$this->save_offline_gateways();
		} elseif ( 'integrations' === $tab ) {
			$this->save_integrations_settings();
			$this->save_active_scripts_settings();
			if ( $is_real_admin ) {
				$this->save_script_registry_settings();
			}
		} elseif ( 'cutover' === $tab ) {
			$redirect = $this->save_cutover_settings() ?: $redirect;
		} elseif ( 'security' === $tab ) {
			$this->save_access_settings();
		} elseif ( 'pdp' === $tab ) {
			$this->save_pdp_config();
		} elseif ( 'pages' === $tab ) {
			$this->save_pages_config();
		} elseif ( 'shop' === $tab ) {
			$this->save_shop_config();
		} else {
			$this->save_homepage_config();
		}

		$redirect_host = wp_parse_url( $redirect, PHP_URL_HOST );
		if ( is_string( $redirect_host ) && '' !== $redirect_host ) {
			add_filter(
				'allowed_redirect_hosts',
				static function ( array $hosts ) use ( $redirect_host ): array {
					$hosts[] = $redirect_host;
					return array_values( array_unique( $hosts ) );
				}
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	// ─── Isolated save handlers ─────────────────────────────────
	// Each handler reads existing settings, updates ONLY its own fields,
	// and writes back. This prevents cross-tab field corruption.

	private function save_appearance_settings(): void {
		$s = self::get_site_settings();

		$accent_color = sanitize_text_field( wp_unslash( $_POST['accent_color'] ?? '' ) );
		if ( $accent_color && ! in_array( $accent_color, self::ACCENT_PALETTE, true ) ) {
			$accent_color = null;
		}
		if ( '' === $accent_color ) {
			$accent_color = null;
		}
		$s['accent_color'] = $accent_color;

		// Header links
		$header_links   = [];
		$raw_hlinks     = $_POST['header_links'] ?? [];
		$valid_displays = [ 'text', 'icon', 'both' ];
		if ( is_array( $raw_hlinks ) ) {
			foreach ( $raw_hlinks as $hl ) {
				if ( ! is_array( $hl ) ) continue;
				$link_label    = sanitize_text_field( wp_unslash( $hl['label'] ?? '' ) );
				$link_url      = sanitize_text_field( wp_unslash( $hl['url'] ?? '' ) );
				$link_display  = sanitize_text_field( $hl['display'] ?? 'text' );
				if ( ! in_array( $link_display, $valid_displays, true ) ) $link_display = 'text';
				$link_icon     = sanitize_key( $hl['icon'] ?? '' );
				$link_accent   = ! empty( $hl['accent'] );
				$link_mobile_pin = ! empty( $hl['mobile_pin'] );
				if ( $link_label && $link_url ) {
					$header_links[] = [
						'label'      => $link_label,
						'url'        => $link_url,
						'display'    => $link_display,
						'icon'       => $link_icon,
						'accent'     => $link_accent,
						'mobile_pin' => $link_mobile_pin,
					];
				}
			}
		}
		if ( empty( $header_links ) ) {
			$header_links = [
				[ 'label' => 'Home', 'url' => '/', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'Shop', 'url' => '/shop', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'About', 'url' => '/about', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'COA Library', 'url' => '/coa-library', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'Contact', 'url' => '/contact', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
				[ 'label' => 'Account', 'url' => '/account', 'display' => 'icon', 'icon' => 'user', 'accent' => true, 'mobile_pin' => false ],
			];
		}
		$s['header_links']         = $header_links;
		$s['announcement_bar_enabled'] = ! empty( $_POST['announcement_bar_enabled'] );
		$raw_ann                       = sanitize_textarea_field( wp_unslash( $_POST['announcement_bar_items'] ?? '' ) );
		$ann_items                     = array_values(
			array_filter(
				array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw_ann ) ?: [] )
			)
		);
		if ( empty( $ann_items ) ) {
			$ann_items = [
				'UP TO 40% OFF TODAY',
				'Fast & Discreet Shipping',
			];
		}
		$s['announcement_bar_items'] = $ann_items;
		$s['header_toggle_accent'] = ! empty( $_POST['header_toggle_accent'] );
		$s['header_cart_accent']   = ! empty( $_POST['header_cart_accent'] );
		$s['header_inverted']      = ! empty( $_POST['header_inverted'] );
		$s['header_borderless']    = ! empty( $_POST['header_borderless'] );

		// Theme default — what first-time visitors see before they've toggled.
		// Keeps the toggle functional in all three modes.
		$theme_default = sanitize_text_field( wp_unslash( $_POST['theme_default'] ?? 'system' ) );
		if ( ! in_array( $theme_default, [ 'system', 'light', 'dark' ], true ) ) {
			$theme_default = 'system';
		}
		$s['theme_default']       = $theme_default;
		$s['logo_invert_on_dark'] = ! empty( $_POST['logo_invert_on_dark'] );
		$s['logo_dark_id']        = absint( $_POST['logo_dark_id'] ?? 0 );
		$logo_size = sanitize_text_field( wp_unslash( $_POST['logo_size'] ?? 'standard' ) );
		if ( ! in_array( $logo_size, [ 'compact', 'standard', 'prominent', 'xl' ], true ) ) {
			$logo_size = 'standard';
		}
		$s['logo_size'] = $logo_size;

		$brand_position = sanitize_text_field( wp_unslash( $_POST['brand_position'] ?? 'left' ) );
		if ( ! in_array( $brand_position, [ 'left', 'center', 'nav-center' ], true ) ) {
			$brand_position = 'left';
		}
		$s['brand_position'] = $brand_position;

		// Mobile hamburger + visibility flags
		$hamburger_side = sanitize_text_field( $_POST['mobile_hamburger_side'] ?? 'right' );
		if ( ! in_array( $hamburger_side, [ 'left', 'right', 'off' ], true ) ) {
			$hamburger_side = 'right';
		}
		$s['mobile_hamburger_side']    = $hamburger_side;
		$s['header_show_toggle']       = ! empty( $_POST['header_show_toggle'] );
		$s['header_toggle_mobile_pin'] = ! empty( $_POST['header_toggle_mobile_pin'] );
		$s['header_cart_mobile_pin']   = ! empty( $_POST['header_cart_mobile_pin'] );

		// Footer columns
		$footer_columns = [];
		$raw_cols = $_POST['footer_columns'] ?? [];
		if ( is_array( $raw_cols ) ) {
			foreach ( $raw_cols as $col ) {
				if ( ! is_array( $col ) ) continue;
				$col_title = sanitize_text_field( wp_unslash( $col['title'] ?? '' ) );
				$col_links = [];
				if ( ! empty( $col['links'] ) && is_array( $col['links'] ) ) {
					foreach ( $col['links'] as $link ) {
						$fl = sanitize_text_field( wp_unslash( $link['label'] ?? '' ) );
						$fu = sanitize_text_field( wp_unslash( $link['url'] ?? '' ) );
						if ( $fl && $fu ) {
							$col_links[] = [ 'label' => $fl, 'url' => $fu ];
						}
					}
				}
				if ( $col_title || count( $col_links ) > 0 ) {
					$footer_columns[] = [ 'title' => $col_title, 'links' => $col_links ];
				}
			}
		}
		$footer_tagline = sanitize_text_field( wp_unslash( $_POST['footer_tagline'] ?? '' ) );
		$s['footer'] = [ 'columns' => $footer_columns, 'tagline' => $footer_tagline ];

		// Social links — array of { platform, url }. Whitelist platform slugs
		// against the icon set in Footer.svelte so unknown keys can't slip in.
		$allowed_platforms = [ 'instagram', 'facebook', 'x', 'twitter', 'youtube', 'linkedin', 'tiktok', 'pinterest' ];
		$social_links = [];
		$raw_social = $_POST['social_links'] ?? [];
		if ( is_array( $raw_social ) ) {
			foreach ( $raw_social as $row ) {
				if ( ! is_array( $row ) ) continue;
				$platform = sanitize_key( $row['platform'] ?? '' );
				$url      = esc_url_raw( wp_unslash( $row['url'] ?? '' ) );
				if ( $platform && $url && in_array( $platform, $allowed_platforms, true ) ) {
					$social_links[] = [ 'platform' => $platform, 'url' => $url ];
				}
			}
		}
		$s['social_links'] = $social_links;

		// Typography
		$valid_fonts   = [ 'inter', 'barlow', 'bebas', 'playfair', 'space_grotesk', 'archivo', 'oswald' ];
		$valid_weights = [ 'light', 'regular', 'medium', 'semibold', 'bold', 'extrabold', 'black' ];
		$valid_sizes   = [ 's', 'm', 'l' ];

		$heading_font = sanitize_text_field( wp_unslash( $_POST['typography_heading_font'] ?? 'inter' ) );
		if ( ! in_array( $heading_font, $valid_fonts, true ) ) $heading_font = 'inter';
		$s['typography_heading_font'] = $heading_font;

		$body_font = sanitize_text_field( wp_unslash( $_POST['typography_body_font'] ?? 'inter' ) );
		if ( ! in_array( $body_font, $valid_fonts, true ) ) $body_font = 'inter';
		$s['typography_body_font'] = $body_font;

		$heading_weight = sanitize_text_field( wp_unslash( $_POST['typography_heading_weight'] ?? 'semibold' ) );
		if ( ! in_array( $heading_weight, $valid_weights, true ) ) $heading_weight = 'semibold';
		$s['typography_heading_weight'] = $heading_weight;

		$body_size = sanitize_text_field( wp_unslash( $_POST['typography_body_size'] ?? 'm' ) );
		if ( ! in_array( $body_size, $valid_sizes, true ) ) $body_size = 'm';
		$s['typography_body_size'] = $body_size;

		// Product card — 14 aesthetic + content options. Enum whitelists
		// guard every field; unknown values silently fall back to defaults
		// so partial POSTs (missing field due to admin UX variation) don't
		// wipe saved state.
		$pc_raw = is_array( $_POST['product_card'] ?? null ) ? $_POST['product_card'] : [];
		$pc_current = is_array( $s['product_card'] ?? null ) ? $s['product_card'] : [];
		$pc_enum = function ( $val, array $allowed, string $fallback ) {
			$val = sanitize_text_field( wp_unslash( $val ?? '' ) );
			return in_array( $val, $allowed, true ) ? $val : $fallback;
		};
		$s['product_card'] = [
			'media_aspect_ratio'       => $pc_enum( $pc_raw['media_aspect_ratio'] ?? ( $pc_current['media_aspect_ratio'] ?? '1:1' ),       [ '1:1', '4:5', '3:4', '16:9' ], '1:1' ),
			'corner_radius'            => $pc_enum( $pc_raw['corner_radius']      ?? ( $pc_current['corner_radius']      ?? 'square' ),   [ 'square', 'soft', 'round', 'pill' ], 'square' ),
			'border'                   => $pc_enum( $pc_raw['border']             ?? ( $pc_current['border']             ?? 'full' ),     [ 'full', 'bottom-only', 'none', 'hover-only' ], 'full' ),
			'hover_effect'             => $pc_enum( $pc_raw['hover_effect']       ?? ( $pc_current['hover_effect']       ?? 'lift' ),     [ 'lift', 'shadow', 'border', 'none' ], 'lift' ),
			'button_style'             => $pc_enum( $pc_raw['button_style']       ?? ( $pc_current['button_style']       ?? 'outline' ),  [ 'outline', 'solid', 'icon-only' ], 'outline' ),
			'badge_position'           => $pc_enum( $pc_raw['badge_position']     ?? ( $pc_current['badge_position']     ?? 'top-right' ), [ 'top-left', 'top-right' ], 'top-right' ),
			'badge_style'              => $pc_enum( $pc_raw['badge_style']        ?? ( $pc_current['badge_style']        ?? 'filled' ),   [ 'filled', 'outline', 'minimal' ], 'filled' ),
			'show_bulk_badge'          => ! empty( $pc_raw['show_bulk_badge'] ),
			'show_tier_hint'           => ! empty( $pc_raw['show_tier_hint'] ),
			'show_oos_cards'           => ! empty( $pc_raw['show_oos_cards'] ),
			'oos_treatment'            => $pc_enum( $pc_raw['oos_treatment']      ?? ( $pc_current['oos_treatment']      ?? 'grayscale' ), [ 'grayscale', 'dim', 'hidden-price' ], 'grayscale' ),
			'title_lines'              => $pc_enum( $pc_raw['title_lines']        ?? ( $pc_current['title_lines']        ?? 'auto' ),     [ 'auto', '1', '2', '3' ], 'auto' ),
			'secondary_image_on_hover' => ! empty( $pc_raw['secondary_image_on_hover'] ),
			'sale_badge_text'          => substr( sanitize_text_field( wp_unslash( $pc_raw['sale_badge_text'] ?? ( $pc_current['sale_badge_text'] ?? 'Sale' ) ) ), 0, 40 ),
		];

		// Design tokens — site-wide CSS variables for radius + spacing scale.
		// Each is clamped to a sensible range; unset → null → SPA uses its
		// per-component fallbacks (no visual change until a merchant opts in).
		$tokens_raw = is_array( $_POST['tokens'] ?? null ) ? $_POST['tokens'] : [];
		$clamp_int = function ( $val, int $min, int $max ): ?int {
			if ( ! is_numeric( $val ) ) return null;
			$n = (int) $val;
			if ( $n < $min ) $n = $min;
			if ( $n > $max ) $n = $max;
			return $n;
		};
		$s['tokens'] = [
			'radius'             => $clamp_int( $tokens_raw['radius']             ?? null, 0,  32 ),
			'spacing_v_compact'  => $clamp_int( $tokens_raw['spacing_v_compact']  ?? null, 0,  48 ),
			'spacing_v_normal'   => $clamp_int( $tokens_raw['spacing_v_normal']   ?? null, 16, 96 ),
			'spacing_v_spacious' => $clamp_int( $tokens_raw['spacing_v_spacious'] ?? null, 48, 160 ),
		];

		update_option( self::SITE_OPTION, $s );
	}

	private function save_checkout_settings(): void {
		$s = self::get_site_settings();
		$is_real_admin = function_exists( 'wchs_is_real_admin' ) ? wchs_is_real_admin() : current_user_can( 'install_plugins' );

		$s['upsell_enabled']             = ! empty( $_POST['upsell_enabled'] );
		$s['bump_product_id']            = absint( $_POST['bump_product_id'] ?? 0 );
		$s['bump_variation_id']          = absint( $_POST['bump_variation_id'] ?? 0 );
		$s['address_validation_enabled'] = ! empty( $_POST['address_validation_enabled'] );

		$addr_mode = sanitize_text_field( wp_unslash( $_POST['address_validation_mode'] ?? 'moderate' ) );
		if ( ! in_array( $addr_mode, [ 'strict', 'moderate', 'loose' ], true ) ) {
			$addr_mode = 'moderate';
		}
		$s['address_validation_mode'] = $addr_mode;

		// Sensitive keys: only writeable by real admins. Non-admin saves preserve
		// existing values so a shop_manager save round-trip doesn't wipe the keys.
		if ( $is_real_admin ) {
			$gmap_key = sanitize_text_field( wp_unslash( $_POST['google_maps_api_key'] ?? '' ) );
			if ( $gmap_key && ! preg_match( '/^AIza[A-Za-z0-9_-]{35}$/', $gmap_key ) ) {
				$gmap_key = '';
			}
			$s['google_maps_api_key'] = $gmap_key;
			$s['easypost_api_key']    = sanitize_text_field( wp_unslash( $_POST['easypost_api_key'] ?? '' ) );
		}

		$s['internal_rate_limit_enabled'] = ! empty( $_POST['internal_rate_limit_enabled'] );

		// Abandoned cart: checkbox is "send built-in emails". Third-party
		// plugins (Omnisend/Klaviyo) have their own recovery flow, so
		// admins using them should uncheck this to avoid duplicate emails.
		$s['abandoned_cart_enabled'] = ! empty( $_POST['abandoned_cart_enabled'] );

		update_option( self::SITE_OPTION, $s );
	}

	private function save_integrations_settings(): void {
		$s = self::get_site_settings();

		$gtm_id = sanitize_text_field( wp_unslash( $_POST['gtm_id'] ?? '' ) );
		if ( $gtm_id && ! preg_match( '/^GTM-[A-Z0-9]+$/', $gtm_id ) ) {
			$gtm_id = '';
		}
		$s['gtm_id'] = $gtm_id;

		// Omnisend brand ID — 24-char hex from their dashboard Store Settings.
		$omnisend_brand = sanitize_text_field( wp_unslash( $_POST['omnisend_brand_id'] ?? '' ) );
		if ( $omnisend_brand && ! preg_match( '/^[a-f0-9]{20,32}$/i', $omnisend_brand ) ) {
			$omnisend_brand = '';
		}
		$s['omnisend_brand_id'] = $omnisend_brand;

		// Ad pixels & analytics trackers. Each is regex-validated against its
		// vendor's documented ID shape; invalid values clear to empty.
		$pixel_fields = [
			'klaviyo_public_key'          => '/^[A-Za-z0-9]{6,8}$/',
			'meta_pixel_id'               => '/^\d{10,20}$/',
			'tiktok_pixel_id'             => '/^[A-Z0-9]{15,30}$/',
			'pinterest_tag_id'            => '/^\d{10,20}$/',
			'clarity_project_id'          => '/^[a-z0-9]{8,12}$/i',
			'hotjar_site_id'              => '/^\d{6,10}$/',
			'google_ads_conversion_id'    => '/^AW-\d{9,12}$/',
			'google_ads_conversion_label' => '/^[A-Za-z0-9_-]{8,30}$/',
		];
		foreach ( $pixel_fields as $field => $pattern ) {
			$val = sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) );
			if ( $val && ! preg_match( $pattern, $val ) ) {
				$val = '';
			}
			$s[ $field ] = $val;
		}

		$review_provider = sanitize_text_field( wp_unslash( $_POST['review_provider'] ?? 'woocommerce' ) );
		$valid_providers = [ 'woocommerce', 'yotpo', 'stamped', 'reviewsio', 'mock' ];
		if ( ! in_array( $review_provider, $valid_providers, true ) ) {
			$review_provider = 'woocommerce';
		}
		$s['review_provider'] = $review_provider;
		$s['review_provider_keys'] = [
			'yotpo_app_key'      => sanitize_text_field( wp_unslash( $_POST['yotpo_app_key'] ?? '' ) ),
			'stamped_api_key'    => sanitize_text_field( wp_unslash( $_POST['stamped_api_key'] ?? '' ) ),
			'stamped_api_secret' => sanitize_text_field( wp_unslash( $_POST['stamped_api_secret'] ?? '' ) ),
			'stamped_store_hash' => sanitize_text_field( wp_unslash( $_POST['stamped_store_hash'] ?? '' ) ),
			'reviewsio_store_id' => sanitize_text_field( wp_unslash( $_POST['reviewsio_store_id'] ?? '' ) ),
			'reviewsio_api_key'  => sanitize_text_field( wp_unslash( $_POST['reviewsio_api_key'] ?? '' ) ),
		];

		// SMTP settings (only save if not overridden by wp-config constants)
		if ( ! defined( 'WCHS_SMTP_HOST' ) ) {
			$s['smtp'] = [
				'enabled'    => ! empty( $_POST['smtp_enabled'] ),
				'host'       => sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? '' ) ),
				'port'       => absint( $_POST['smtp_port'] ?? 465 ),
				'secure'     => sanitize_text_field( wp_unslash( $_POST['smtp_secure'] ?? 'ssl' ) ),
				'username'   => sanitize_text_field( wp_unslash( $_POST['smtp_username'] ?? '' ) ),
				'password'   => wp_unslash( $_POST['smtp_password'] ?? '' ), // Not sanitized - can contain special chars
				'from_email' => sanitize_email( wp_unslash( $_POST['smtp_from_email'] ?? '' ) ),
				'from_name'  => sanitize_text_field( wp_unslash( $_POST['smtp_from_name'] ?? '' ) ),
			];
			if ( ! in_array( $s['smtp']['secure'], [ 'ssl', 'tls', '' ], true ) ) {
				$s['smtp']['secure'] = 'ssl';
			}
		}

		update_option( self::SITE_OPTION, $s );
	}

	private function save_cutover_settings(): ?string {
		$s    = self::get_site_settings();
		$mode = sanitize_text_field( wp_unslash( $_POST['domain_origin_mode'] ?? 'same-origin' ) );
		if ( ! in_array( $mode, [ 'same-origin', 'custom' ], true ) ) {
			$mode = 'same-origin';
		}

		$s['domain_origin_mode'] = $mode;
		$s['custom_spa_origin']  = \function_exists( 'wchs_normalize_origin' )
			? \wchs_normalize_origin( wp_unslash( $_POST['custom_spa_origin'] ?? '' ) )
			: '';
		$s['custom_allowed_origins'] = \function_exists( 'wchs_parse_origin_list' )
			? \wchs_parse_origin_list( wp_unslash( $_POST['custom_allowed_origins'] ?? '' ) )
			: [];
		$s['custom_return_origins'] = \function_exists( 'wchs_parse_origin_list' )
			? \wchs_parse_origin_list( wp_unslash( $_POST['custom_return_origins'] ?? '' ) )
			: [];
		$s['cutover_candidate_domain'] = self::normalize_cutover_domain( (string) wp_unslash( $_POST['cutover_candidate_domain'] ?? '' ) );

		$items      = [];
		$raw_items  = is_array( $_POST['cutover_checklist_items'] ?? null ) ? $_POST['cutover_checklist_items'] : [];
		foreach ( self::cutover_task_definitions() as $key => $_meta ) {
			$items[ $key ] = ! empty( $raw_items[ $key ] );
		}

		$s['cutover_checklist'] = [
			'domain'     => self::current_site_domain(),
			'items'      => $items,
			'updated_at' => current_time( 'mysql' ),
		];

		$cutover_action = sanitize_key( (string) wp_unslash( $_POST['cutover_action'] ?? '' ) );
		$redirect       = null;
		if ( 'preview' === $cutover_action ) {
			$report = self::guided_cutover_report( $s['cutover_candidate_domain'] );
			self::stash_cutover_notice( [
				'status' => $report['ready'] ? 'success' : 'warning',
				'title'  => $report['ready'] ? 'Guided cutover is ready.' : 'Guided cutover needs attention.',
				'report' => $report,
			] );
		} elseif ( 'finalize' === $cutover_action ) {
			$result = self::run_guided_cutover( $s['cutover_candidate_domain'], $s );
			self::stash_cutover_notice( [
				'status' => (string) ( $result['status'] ?? 'info' ),
				'title'  => (string) ( $result['title'] ?? 'Guided cutover result' ),
				'report' => is_array( $result['report'] ?? null ) ? $result['report'] : [],
			] );
			if ( ! empty( $result['redirect_domain'] ) ) {
				$redirect = sprintf(
					'https://%s/wp-admin/admin.php?page=wchs-settings&tab=cutover&updated=1',
					(string) $result['redirect_domain']
				);
			}
		}

		update_option( self::SITE_OPTION, $s );

		return $redirect;
	}

	private function save_access_settings(): void {
		$s = self::get_site_settings();
		$is_real_admin = function_exists( 'wchs_is_real_admin' ) ? wchs_is_real_admin() : current_user_can( 'install_plugins' );

		$mode = (int) ( $_POST['access_mode'] ?? 3 );
		if ( ! in_array( $mode, [ 0, 1, 2, 3 ], true ) ) {
			$mode = 3;
		}
		$s['access_mode'] = $mode;

		$s['anti_bot_enabled'] = ! empty( $_POST['anti_bot_enabled'] );
		if ( $is_real_admin ) {
			$s['turnstile_site_key']   = sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ?? '' ) );
			$s['turnstile_secret_key'] = sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ?? '' ) );
		}

		$s['seo_nosnippet_products']  = ! empty( $_POST['seo_nosnippet_products'] );
		$s['seo_block_cart_checkout'] = ! empty( $_POST['seo_block_cart_checkout'] );

		$s['reg_require_email_verify'] = ! empty( $_POST['reg_require_email_verify'] );
		$s['reg_require_address']      = ! empty( $_POST['reg_require_address'] );
		$s['reg_require_name']         = ! empty( $_POST['reg_require_name'] );
		$s['reg_require_phone']        = ! empty( $_POST['reg_require_phone'] );

		$s['gate_modal'] = [
			'enabled'      => ! empty( $_POST['gate_modal_enabled'] ),
			'strict'       => ! empty( $_POST['gate_modal_strict'] ),
			'title'        => sanitize_text_field( wp_unslash( $_POST['gate_modal_title'] ?? '' ) ),
			'content'      => wp_kses_post( wp_unslash( $_POST['gate_modal_content'] ?? '' ) ),
			'confirm_text' => sanitize_text_field( wp_unslash( $_POST['gate_modal_confirm_text'] ?? 'Enter Site' ) ),
			'decline_text' => sanitize_text_field( wp_unslash( $_POST['gate_modal_decline_text'] ?? '' ) ),
			'decline_url'  => esc_url_raw( wp_unslash( $_POST['gate_modal_decline_url'] ?? '' ) ),
			'version'      => max( 1, (int) ( $_POST['gate_modal_version'] ?? 1 ) ),
		];

		update_option( self::SITE_OPTION, $s );
	}

	private function save_homepage_config(): void {
		$hero = [
			'headline'          => sanitize_text_field( wp_unslash( $_POST['hero_headline'] ?? '' ) ),
			'content_mode'      => sanitize_text_field( wp_unslash( $_POST['hero_content_mode'] ?? 'text' ) ),
			'logo_source'       => sanitize_text_field( wp_unslash( $_POST['hero_logo_source'] ?? 'site_logo' ) ),
			'logo_url'          => esc_url_raw( wp_unslash( $_POST['hero_logo_url'] ?? '' ) ),
			'logo_dark_url'     => esc_url_raw( wp_unslash( $_POST['hero_logo_dark_url'] ?? '' ) ),
			'logo_size'         => sanitize_text_field( wp_unslash( $_POST['hero_logo_size'] ?? 'large' ) ),
			'headline_size'     => sanitize_text_field( wp_unslash( $_POST['hero_headline_size'] ?? 'l' ) ),
			'headline_weight'   => sanitize_text_field( wp_unslash( $_POST['hero_headline_weight'] ?? 'medium' ) ),
			'headline_font'     => sanitize_text_field( wp_unslash( $_POST['hero_headline_font'] ?? 'inter' ) ),
			'text_color_mode'   => sanitize_text_field( wp_unslash( $_POST['hero_text_color_mode'] ?? 'theme' ) ),
			'subheadline'       => sanitize_textarea_field( wp_unslash( $_POST['hero_subheadline'] ?? '' ) ),
			'subheadline_size'  => sanitize_text_field( wp_unslash( $_POST['hero_subheadline_size'] ?? 'm' ) ),
			'cta_text'      => sanitize_text_field( wp_unslash( $_POST['hero_cta_text'] ?? '' ) ),
			'cta_link'      => sanitize_text_field( wp_unslash( $_POST['hero_cta_link'] ?? '' ) ),
			'variant'       => sanitize_text_field( wp_unslash( $_POST['hero_variant'] ?? 'webgl-noise' ) ),
			'layout'        => sanitize_text_field( wp_unslash( $_POST['hero_layout'] ?? 'left' ) ),
			'image_desktop'    => esc_url_raw( wp_unslash( $_POST['hero_image_desktop'] ?? '' ) ),
			'image_mobile'     => esc_url_raw( wp_unslash( $_POST['hero_image_mobile'] ?? '' ) ),
			'image_position_x'        => max( 0, min( 100, (int) ( $_POST['hero_image_position_x'] ?? 50 ) ) ),
			'image_position_y'        => max( 0, min( 100, (int) ( $_POST['hero_image_position_y'] ?? 50 ) ) ),
			'image_position_mobile_x' => max( 0, min( 100, (int) ( $_POST['hero_image_position_mobile_x'] ?? 50 ) ) ),
			'image_position_mobile_y' => max( 0, min( 100, (int) ( $_POST['hero_image_position_mobile_y'] ?? 80 ) ) ),
			'image_zoom'              => max( 50, min( 200, (int) ( $_POST['hero_image_zoom'] ?? 100 ) ) ),
			'image_zoom_mobile'       => max( 50, min( 200, (int) ( $_POST['hero_image_zoom_mobile'] ?? 100 ) ) ),
			'show_eyebrow'     => ! empty( $_POST['hero_show_eyebrow'] ),
			'cta_accent'       => ! empty( $_POST['hero_cta_accent'] ),
			'show_cta'         => ! empty( $_POST['hero_show_cta'] ),
			'show_rating'   => ! empty( $_POST['hero_show_rating'] ),
			'rating_text'   => sanitize_text_field( wp_unslash( $_POST['hero_rating_text'] ?? '' ) ),
			'research_badge'       => sanitize_text_field( wp_unslash( $_POST['hero_research_badge'] ?? '' ) ),
			'cta_secondary_text'   => sanitize_text_field( wp_unslash( $_POST['hero_cta_secondary_text'] ?? '' ) ),
			'cta_secondary_link'   => sanitize_text_field( wp_unslash( $_POST['hero_cta_secondary_link'] ?? '' ) ),
			'research_stats'       => [],
			'trust_items'   => [],
		];

		$valid_variants = [ 'text-only', 'webgl-noise', 'webgl-variant-2', 'webgl-variant-3', 'webgl-variant-4', 'webgl-variant-5', 'webgl-variant-6', 'research-motion' ];
		if ( ! in_array( $hero['variant'], $valid_variants, true ) ) {
			$hero['variant'] = 'webgl-noise';
		}
		if ( ! in_array( $hero['content_mode'], [ 'text', 'logo' ], true ) ) {
			$hero['content_mode'] = 'text';
		}
		if ( ! in_array( $hero['logo_source'], [ 'site_logo', 'custom' ], true ) ) {
			$hero['logo_source'] = 'site_logo';
		}
		if ( ! in_array( $hero['logo_size'], [ 'standard', 'large', 'statement' ], true ) ) {
			$hero['logo_size'] = 'large';
		}

		$valid_layouts = [ 'left', 'center', 'split', 'bottom' ];
		if ( ! in_array( $hero['layout'], $valid_layouts, true ) ) {
			$hero['layout'] = 'left';
		}

		$valid_sizes   = [ 's', 'm', 'l', 'xl' ];
		$valid_weights = [ 'light', 'regular', 'medium', 'semibold', 'bold', 'extrabold', 'black' ];
		$valid_fonts   = [ 'inter', 'barlow', 'bebas', 'playfair', 'space_grotesk', 'archivo', 'oswald' ];
		$valid_text_colors = [ 'theme', 'white', 'black', 'accent' ];
		if ( ! in_array( $hero['headline_size'], $valid_sizes, true ) )           $hero['headline_size']    = 'l';
		if ( ! in_array( $hero['headline_weight'], $valid_weights, true ) )       $hero['headline_weight']  = 'medium';
		if ( ! in_array( $hero['headline_font'], $valid_fonts, true ) )           $hero['headline_font']    = 'inter';
		if ( ! in_array( $hero['text_color_mode'], $valid_text_colors, true ) )   $hero['text_color_mode']  = 'theme';
		if ( ! in_array( $hero['subheadline_size'], [ 's', 'm', 'l' ], true ) )   $hero['subheadline_size'] = 'm';

		// Parse trust items
		$raw_trust = $_POST['hero_trust_items'] ?? [];
		if ( is_array( $raw_trust ) ) {
			foreach ( $raw_trust as $ti ) {
				$text = sanitize_text_field( wp_unslash( $ti['text'] ?? '' ) );
				$icon = sanitize_text_field( $ti['icon'] ?? 'check' );
				if ( $text ) {
					$hero['trust_items'][] = [ 'icon' => $icon, 'text' => $text ];
				}
			}
		}

		$stats_raw = wp_unslash( $_POST['hero_research_stats_json'] ?? '' );
		$decoded_stats = json_decode( $stats_raw, true );
		if ( is_array( $decoded_stats ) ) {
			foreach ( $decoded_stats as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$sv = sanitize_text_field( (string) ( $row['value'] ?? '' ) );
				$sl = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				if ( $sv !== '' && $sl !== '' ) {
					$hero['research_stats'][] = [ 'value' => $sv, 'label' => $sl ];
				}
			}
		}
		if ( empty( $hero['research_stats'] ) ) {
			$hero['research_stats'] = self::homepage_defaults()['hero']['research_stats'];
		}

		$raw_json = json_decode( wp_unslash( $_POST['modules_json'] ?? '[]' ), true );
		$modules  = self::parse_modules_from_post( is_array( $raw_json ) ? $raw_json : [], 'homepage' );

		update_option( self::HOMEPAGE_OPTION, [
			'hero'    => $hero,
			'modules' => $modules,
		] );
	}

	private function save_pdp_config(): void {
		$raw_json = json_decode( wp_unslash( $_POST['modules_json'] ?? '[]' ), true );
		$modules  = self::parse_modules_from_post( is_array( $raw_json ) ? $raw_json : [], 'pdp' );
		$xsell_mode = sanitize_text_field( $_POST['cross_sell_mode'] ?? 'simple' );
		if ( ! in_array( $xsell_mode, [ 'simple', 'complex' ], true ) ) {
			$xsell_mode = 'simple';
		}
		$exclude_raw = sanitize_text_field( wp_unslash( $_POST['slide_cart_cross_sell_exclude_ids'] ?? '' ) );
		$exclude_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', preg_split( '/[\s,]+/', $exclude_raw, -1, PREG_SPLIT_NO_EMPTY ) ?: [] )
				)
			)
		);
		$existing = get_option( self::PDP_OPTION, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		update_option(
			self::PDP_OPTION,
			array_merge(
				$existing,
				[
					'show_reviews'    => ! empty( $_POST['pdp_show_reviews'] ),
					'cross_sell_mode' => $xsell_mode,
					'modules'         => $modules,
					'slide_cart'      => [
						'cross_sell_exclude_product_ids' => $exclude_ids,
						'cross_sell_exclude_slugs'       => array_values(
							array_unique(
								array_merge(
									[ 'bac-water-10ml', 'shipping-protection' ],
									(array) ( $existing['slide_cart']['cross_sell_exclude_slugs'] ?? [] )
								)
							)
						),
					],
				]
			)
		);
	}

	private function save_shop_config(): void {
		$raw_json = json_decode( wp_unslash( $_POST['modules_json'] ?? '[]' ), true );
		$modules  = self::parse_modules_from_post( is_array( $raw_json ) ? $raw_json : [], 'shop' );

		$cols_min = max( 1, min( 8, (int) ( $_POST['shop_cols_min'] ?? 2 ) ) );
		$cols_max = max( 1, min( 8, (int) ( $_POST['shop_cols_max'] ?? 4 ) ) );
		if ( $cols_min > $cols_max ) {
			$cols_min = $cols_max;
		}

		update_option( self::SHOP_OPTION, [
			'modules'      => $modules,
			'cols_min'     => $cols_min,
			'cols_max'     => $cols_max,
			'spacing_h' => in_array( $_POST['shop_spacing_h'] ?? 'normal', [ 'compact', 'normal', 'spacious' ], true )
				? sanitize_text_field( $_POST['shop_spacing_h'] ) : 'normal',
		] );
	}

	/**
	 * Save the admin-curated script registry. Real-administrator only — the
	 * handle_save() dispatch 403s non-admins before we get here, but we
	 * double-check as defense-in-depth.
	 */
	private function save_script_registry_settings(): void {
		$is_real_admin = function_exists( 'wchs_is_real_admin' ) ? wchs_is_real_admin() : current_user_can( 'install_plugins' );
		if ( ! $is_real_admin ) {
			wp_die( 'Administrator capability required.', 'Forbidden', [ 'response' => 403 ] );
		}

		$raw = $_POST['registry'] ?? [];
		$out = [];
		if ( is_array( $raw ) ) {
			foreach ( $raw as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$id = sanitize_key( $entry['id'] ?? '' );
				if ( ! $id ) {
					continue;
				}
				$src = esc_url_raw( wp_unslash( $entry['src_template'] ?? '' ) );
				if ( ! $src ) {
					continue;
				}

				$placement = sanitize_text_field( $entry['placement'] ?? 'head' );
				if ( ! in_array( $placement, self::REGISTRY_ALLOWED_PLACEMENTS, true ) ) {
					$placement = 'head';
				}

				$surfaces = [];
				$raw_surfaces = (array) ( $entry['surfaces'] ?? [ 'spa', 'wp' ] );
				foreach ( $raw_surfaces as $s ) {
					$s = sanitize_key( $s );
					if ( in_array( $s, self::REGISTRY_ALLOWED_SURFACES, true ) ) {
						$surfaces[] = $s;
					}
				}
				if ( empty( $surfaces ) ) {
					$surfaces = [ 'spa', 'wp' ];
				}

				$params = [];
				$raw_params = (array) ( $entry['params'] ?? [] );
				foreach ( $raw_params as $p ) {
					if ( ! is_array( $p ) ) {
						continue;
					}
					$key = sanitize_key( $p['key'] ?? '' );
					if ( ! $key ) {
						continue;
					}
					$type = sanitize_text_field( $p['type'] ?? 'text' );
					if ( ! in_array( $type, self::REGISTRY_ALLOWED_PARAM_TYPES, true ) ) {
						$type = 'text';
					}
					$params[] = [
						'key'      => $key,
						'label'    => sanitize_text_field( wp_unslash( $p['label'] ?? $key ) ),
						'required' => ! empty( $p['required'] ),
						'type'     => $type,
						'example'  => sanitize_text_field( wp_unslash( $p['example'] ?? '' ) ),
					];
				}

				$out[] = [
					'id'           => $id,
					'name'         => sanitize_text_field( wp_unslash( $entry['name'] ?? $id ) ),
					'description'  => sanitize_text_field( wp_unslash( $entry['description'] ?? '' ) ),
					'src_template' => $src,
					'params'       => $params,
					'attributes'   => [
						'async' => ! empty( $entry['attributes']['async'] ),
						'defer' => ! empty( $entry['attributes']['defer'] ),
					],
					'placement'    => $placement,
					'surfaces'     => $surfaces,
					'dedicated_setting_key' => sanitize_key( $entry['dedicated_setting_key'] ?? '' ),
				];
			}
		}

		update_option( self::REGISTRY_OPTION, $out );
	}

	/**
	 * Save which registry entries are active on THIS site + their per-site
	 * param values. Shop_manager-allowed — handler NEVER touches any other
	 * wchs_site_settings key, and drops entries whose id isn't in the registry.
	 *
	 * Sanitization is schema-driven: params are only accepted for keys the
	 * registry defines for that id. Unknown keys silently dropped.
	 */
	private function save_active_scripts_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'manage_woocommerce capability required.', 'Forbidden', [ 'response' => 403 ] );
		}

		$registry = self::get_script_registry();
		$s = self::get_site_settings();

		$out = [];
		$raw = $_POST['active_scripts'] ?? [];
		if ( is_array( $raw ) ) {
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = sanitize_key( $row['id'] ?? '' );
				if ( ! $id || ! isset( $registry[ $id ] ) ) {
					continue; // drop unknown ids
				}
				$enabled = ! empty( $row['enabled'] );

				// Build allowed params from the registry schema for THIS id.
				$allowed_keys = array_map(
					fn( $p ) => $p['key'],
					$registry[ $id ]['params'] ?? []
				);

				$params = [];
				$raw_params = (array) ( $row['params'] ?? [] );
				foreach ( $allowed_keys as $key ) {
					if ( isset( $raw_params[ $key ] ) ) {
						$params[ $key ] = sanitize_text_field( wp_unslash( (string) $raw_params[ $key ] ) );
					}
				}

				$out[] = [
					'id'      => $id,
					'enabled' => $enabled,
					'params'  => $params,
				];
			}
		}

		$s['active_scripts'] = $out;
		update_option( self::SITE_OPTION, $s );
	}

	private function save_pages_config(): void {
		$pages     = [];
		$raw_pages = $_POST['pages'] ?? [];
		if ( is_array( $raw_pages ) ) {
			foreach ( $raw_pages as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$title = sanitize_text_field( wp_unslash( $p['title'] ?? '' ) );
				$slug  = sanitize_title( wp_unslash( $p['slug'] ?? $title ) );
				if ( ! $slug ) {
					continue;
				}
				$raw_json = json_decode( wp_unslash( $p['modules_json'] ?? '[]' ), true );
				$modules  = self::parse_modules_from_post( is_array( $raw_json ) ? $raw_json : [], 'pages' );
				$pages[]  = [
					'slug'    => $slug,
					'title'   => $title,
					'modules' => $modules,
				];
			}
		}
		update_option( self::PAGES_OPTION, [ 'pages' => $pages ] );
	}

	/**
	 * Parse a raw module array into a sanitized module list.
	 * Accepts JSON-decoded module data from the modules_json hidden field.
	 * Delegates to SchemaSanitizer — the single source of truth living in
	 * wp/mu-plugins/wchs-admin/modules/.
	 */
	private static function parse_modules_from_post( ?array $source = null, ?string $context = null ): array {
		return SchemaSanitizer::sanitize_modules( is_array( $source ) ? $source : [], $context );
	}

	// ─── Render ──────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tab      = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'homepage' ) );
		$updated  = isset( $_GET['updated'] );
		$settings = self::get_site_settings();
		$homepage = self::get_homepage_config();
		$hero     = $homepage['hero'];
		$modules  = $homepage['modules'];
		$pdp      = self::get_pdp_config();
		$shop_cfg  = self::get_shop_config();
		$pages_cfg = self::get_pages_config();

		$base_url = admin_url( 'admin.php?page=wchs-settings' );
		?>
		<?php
			// Tabs that get the live preview canvas alongside them
			$preview_tabs = [ 'homepage', 'shop', 'pdp', 'pages', 'design' ];
			$has_canvas   = in_array( $tab, $preview_tabs, true );

			// Resolve SPA origin for data attribute
			$spa_origin_attr = \function_exists( 'wchs_spa_origin' ) ? \wchs_spa_origin() : untrailingslashit( home_url( '/' ) );

			// Resolve PDP path once (used by preview path + chip bar)
			$sample_ids = function_exists( 'wc_get_products' ) ? wc_get_products( [ 'status' => 'publish', 'limit' => 1, 'return' => 'ids' ] ) : [];
			$pdp_path   = ! empty( $sample_ids ) ? '/product/' . get_post_field( 'post_name', $sample_ids[0] ) : '/';

			// Determine preview path based on current tab
			$preview_path = '/';
			if ( 'shop' === $tab ) {
				$preview_path = '/shop';
			} elseif ( 'pages' === $tab ) {
				$first_page = ! empty( $pages_cfg['pages'][0]['slug'] ) ? $pages_cfg['pages'][0]['slug'] : 'shipping-policy';
				$preview_path = '/' . $first_page;
			} elseif ( 'pdp' === $tab ) {
				$preview_path = $pdp_path;
			}
			$all_pages = [
				[ 'slug' => 'home', 'path' => '/',     'label' => 'Home', 'group' => 'storefront' ],
				[ 'slug' => 'shop', 'path' => '/shop',  'label' => 'Shop', 'group' => 'storefront' ],
				[ 'slug' => 'pdp',  'path' => $pdp_path, 'label' => 'PDP',  'group' => 'storefront' ],
			];
			foreach ( $pages_cfg['pages'] as $pg ) {
				if ( ! empty( $pg['slug'] ) ) {
					$all_pages[] = [
						'slug'  => $pg['slug'],
						'path'  => '/' . $pg['slug'],
						'label' => ! empty( $pg['title'] ) ? $pg['title'] : ucfirst( $pg['slug'] ),
						'group' => 'content',
					];
				}
			}

			// Determine which chips are active by default based on current tab
			$initial_slugs = [ 'home' ];
			if ( 'shop' === $tab ) {
				$initial_slugs = [ 'shop' ];
			} elseif ( 'pdp' === $tab ) {
				$initial_slugs = [ 'pdp' ];
			} elseif ( 'pages' === $tab ) {
				$initial_slugs = array_map( function( $pg ) { return $pg['slug']; }, array_filter( $all_pages, function( $pg ) { return 'content' === $pg['group']; } ) );
			} elseif ( 'design' === $tab ) {
				$initial_slugs = [ 'home' ];
			}
		?>
		<div class="wrap wchs-admin <?php echo $has_canvas ? 'wchs-admin--has-canvas' : ''; ?>" data-spa-origin="<?php echo esc_attr( $spa_origin_attr ); ?>" data-preview-path="<?php echo esc_attr( $preview_path ); ?>">

			<?php if ( $has_canvas ) : ?>
			<!-- ═══ Canvas toolbar ═══ -->
			<div class="wchs-canvas-toolbar">
				<h1 class="wchs-canvas-toolbar__title">WCHS Settings</h1>
				<div class="wchs-chip-bar" id="wchs-chip-bar">
					<!-- Presets -->
					<button type="button" class="wchs-chip wchs-chip--preset" data-preset="all">All</button>
					<button type="button" class="wchs-chip wchs-chip--preset" data-preset="storefront">Storefront</button>
					<button type="button" class="wchs-chip wchs-chip--preset" data-preset="content">Content</button>
					<div class="wchs-chip-divider"></div>
					<!-- Individual page chips -->
					<?php foreach ( $all_pages as $pg ) : ?>
						<button type="button" class="wchs-chip <?php echo in_array( $pg['slug'], $initial_slugs, true ) ? 'is-active' : ''; ?>"
								data-slug="<?php echo esc_attr( $pg['slug'] ); ?>"
								data-path="<?php echo esc_attr( $pg['path'] ); ?>"
								data-group="<?php echo esc_attr( $pg['group'] ); ?>">
							<?php echo esc_html( $pg['label'] ); ?>
						</button>
					<?php endforeach; ?>
					<span class="wchs-chip-count" id="wchs-chip-count"></span>
				</div>
				<div class="wchs-canvas-toolbar__devices">
					<button type="button" class="wchs-device-btn is-active" data-device="desktop" title="Desktop (1280×800)">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
					</button>
					<button type="button" class="wchs-device-btn" data-device="tablet" title="Tablet (768×1024)">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M12 18h.01"/></svg>
					</button>
					<button type="button" class="wchs-device-btn" data-device="mobile" title="Mobile (393×852)">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
					</button>
				</div>
				<div class="wchs-canvas-toolbar__theme">
					<button type="button" class="wchs-theme-btn is-active" data-theme="light" title="Preview light theme">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
					</button>
					<button type="button" class="wchs-theme-btn" data-theme="dark" title="Preview dark theme">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
					</button>
				</div>
				<div class="wchs-canvas-toolbar__zoom">
					<button type="button" class="wchs-zoom-btn" data-zoom="out" title="Zoom out">−</button>
					<span class="wchs-zoom-label">100%</span>
					<button type="button" class="wchs-zoom-btn" data-zoom="in" title="Zoom in">+</button>
				</div>
				<a class="wchs-canvas-toolbar__open" href="<?php echo esc_url( $spa_origin_attr . $preview_path ); ?>" target="_blank" rel="noopener" title="Open in new tab">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
				</a>
			</div>
			<?php else : ?>
			<h1>WCHS Settings</h1>
			<?php endif; ?>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
			<?php endif; ?>

			<?php
				$all_tabs = [ 'homepage', 'shop', 'pdp', 'pages', 'design', 'checkout', 'integrations', 'cutover', 'security' ];
				$visible_tabs = apply_filters( 'wchs_admin_visible_tabs', $all_tabs );
				$tab_labels = [
					'homepage'     => 'Homepage',
					'shop'         => 'Shop',
					'pdp'          => 'Product page',
					'pages'        => 'Pages',
					'design'       => 'Design',
					'checkout'     => 'Checkout',
					'integrations' => 'Integrations',
					'cutover'      => 'Cutover',
					'security'     => 'Access & Privacy',
				];
				$tab_icons = [
					'homepage'     => '<path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21V12h6v9"/>',
					'shop'         => '<path d="M6 2L3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7l-3-5z"/><path d="M3 7h18"/><path d="M16 11a4 4 0 0 1-8 0"/>',
					'pdp'          => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>',
					'pages'        => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
					'design'       => '<circle cx="13.5" cy="6.5" r="2.5"/><circle cx="19" cy="13" r="2"/><circle cx="16" cy="20" r="2"/><circle cx="7" cy="18" r="2.5"/><circle cx="5" cy="10.5" r="2"/><circle cx="12" cy="12" r="3" fill="currentColor"/>',
					'checkout'     => '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>',
					'integrations' => '<path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/>',
					'cutover'      => '<path d="M3 12h6"/><path d="M15 12h6"/><path d="M12 3v6"/><path d="M12 15v6"/><circle cx="12" cy="12" r="3.5"/>',
					'security'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
				];
			?>

			<?php if ( $has_canvas ) : ?>
			<!-- ═══ Split-pane: icon rail + settings + canvas ═══ -->
			<div class="wchs-editor">
				<div class="wchs-editor__panel">
					<nav class="wchs-icon-rail" role="tablist">
						<button type="button" id="wchs-global-save" class="wchs-global-save" data-state="idle" title="Save (⌘S) — all pending changes">
							<svg class="wchs-global-save__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
								<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
								<polyline points="17 21 17 13 7 13 7 21"/>
								<polyline points="7 3 7 8 15 8"/>
							</svg>
							<svg class="wchs-global-save__spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
								<circle cx="12" cy="12" r="9" stroke-dasharray="20 40"/>
							</svg>
							<svg class="wchs-global-save__check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="5 13 10 18 20 6"/>
							</svg>
						</button>
						<?php foreach ( $visible_tabs as $t ) : ?>
							<a href="<?php echo esc_url( $base_url . '&tab=' . $t ); ?>"
							   class="wchs-icon-rail__btn <?php echo $t === $tab ? 'is-active' : ''; ?>"
							   title="<?php echo esc_attr( wp_strip_all_tags( $tab_labels[ $t ] ?? ucfirst( $t ) ) ); ?>"
							   data-tab="<?php echo esc_attr( $t ); ?>"
							><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $tab_icons[ $t ] ?? ''; ?></svg></a>
						<?php endforeach; ?>
					</nav>
					<div class="wchs-editor__panel-body">
			<?php else : ?>
				<div class="wchs-editor wchs-editor--no-canvas">
					<nav class="wchs-icon-rail" role="tablist">
						<button type="button" id="wchs-global-save" class="wchs-global-save" data-state="idle" title="Save (⌘S) — all pending changes">
							<svg class="wchs-global-save__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
								<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
								<polyline points="17 21 17 13 7 13 7 21"/>
								<polyline points="7 3 7 8 15 8"/>
							</svg>
							<svg class="wchs-global-save__spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
								<circle cx="12" cy="12" r="9" stroke-dasharray="20 40"/>
							</svg>
							<svg class="wchs-global-save__check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="5 13 10 18 20 6"/>
							</svg>
						</button>
						<?php foreach ( $visible_tabs as $t ) : ?>
							<a href="<?php echo esc_url( $base_url . '&tab=' . $t ); ?>"
							   class="wchs-icon-rail__btn <?php echo $t === $tab ? 'is-active' : ''; ?>"
							   title="<?php echo esc_attr( wp_strip_all_tags( $tab_labels[ $t ] ?? ucfirst( $t ) ) ); ?>"
							   data-tab="<?php echo esc_attr( $t ); ?>"
							><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $tab_icons[ $t ] ?? ''; ?></svg></a>
						<?php endforeach; ?>
					</nav>
					<div class="wchs-editor__panel-body wchs-editor__panel-body--full">
			<?php endif; ?>

			<?php if ( 'design' === $tab ) : ?>
				<?php $this->render_appearance_tab( $settings ); ?>
			<?php elseif ( 'checkout' === $tab ) : ?>
				<?php $this->render_checkout_tab( $settings ); ?>
			<?php elseif ( 'integrations' === $tab ) : ?>
				<?php $this->render_integrations_tab( $settings ); ?>
			<?php elseif ( 'cutover' === $tab ) : ?>
				<?php $this->render_cutover_tab( $settings ); ?>
			<?php elseif ( 'security' === $tab ) : ?>
				<?php $this->render_access_tab( $settings ); ?>
			<?php elseif ( 'pdp' === $tab ) : ?>
				<?php $this->render_pdp_tab( $pdp ); ?>
			<?php elseif ( 'shop' === $tab ) : ?>
				<?php $this->render_shop_tab( $shop_cfg ); ?>
			<?php elseif ( 'pages' === $tab ) : ?>
				<?php $this->render_pages_tab( $pages_cfg['pages'] ); ?>
			<?php else : ?>
				<?php $this->render_homepage_tab( $hero, $modules ); ?>
			<?php endif; ?>

			<?php if ( $has_canvas ) : ?>
					</div><!-- /.wchs-editor__panel-body -->
				</div><!-- /.wchs-editor__panel -->
				<div class="wchs-editor__divider" role="separator"></div>
				<?php
					// Build artboard data from initially active chips
					$artboards = [];
					foreach ( $all_pages as $pg ) {
						if ( in_array( $pg['slug'], $initial_slugs, true ) ) {
							$artboards[] = [
								'slug'  => $pg['slug'],
								'title' => $pg['label'],
								'path'  => $pg['path'],
								'group' => $pg['group'],
							];
						}
					}
					if ( empty( $artboards ) ) {
						$artboards[] = [ 'slug' => 'home', 'title' => 'Home', 'path' => '/', 'group' => 'storefront' ];
					}
				?>
				<?php
					$canvas_active_scripts = function_exists( 'wchs_build_active_scripts' )
						? wchs_build_active_scripts( $settings )
						: [];
				?>
				<div class="wchs-editor__canvas"
					 data-artboards="<?php echo esc_attr( wp_json_encode( $artboards ) ); ?>"
					 data-all-pages="<?php echo esc_attr( wp_json_encode( $all_pages ) ); ?>"
					 data-active-scripts="<?php echo esc_attr( wp_json_encode( $canvas_active_scripts ) ); ?>"
					 data-spa-origin="<?php echo esc_attr( $spa_origin_attr ); ?>"
					 data-tab="<?php echo esc_attr( $tab ); ?>"
				>
					<div class="wchs-editor__canvas-surface" id="wchs-canvas-surface">
						<!-- Artboards are created dynamically by JS -->
					</div>
				</div>
			</div><!-- /.wchs-editor -->
			<?php else : ?>
					</div><!-- /.wchs-editor__panel-body--full -->
				</div><!-- /.wchs-editor--no-canvas -->
			<?php endif; ?>

		</div>
		<?php $this->render_module_template_bank(); ?>
		<?php
	}

	// ─── Appearance Tab ─────────────────────────────────────────
	private function render_appearance_tab( array $settings ): void {
		$accent = $settings['accent_color'] ?? '';
		if ( ! is_string( $accent ) ) $accent = '';
		if ( $accent && ! in_array( $accent, self::ACCENT_PALETTE, true ) ) $accent = '';
		?>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="design" />

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Accent Color <?php echo self::hint_icon('CTA buttons, cart hover, focus rings. Both themes.'); ?></h2>
			<div class="wchs-section__body">
			<div class="wchs-field">
				<input type="hidden" name="accent_color" id="wchs-accent-color" value="<?php echo esc_attr( $accent ); ?>" />
				<div class="wchs-swatches">
					<button type="button" class="wchs-swatch wchs-swatch--none <?php echo empty( $accent ) ? 'active' : ''; ?>" data-color="">
						<span class="wchs-swatch__color"></span>
					</button>
					<?php foreach ( self::ACCENT_PALETTE as $color ) : ?>
						<button type="button" class="wchs-swatch <?php echo $accent === $color ? 'active' : ''; ?>" data-color="<?php echo esc_attr( $color ); ?>">
							<span class="wchs-swatch__color" style="background:<?php echo esc_attr( $color ); ?>"></span>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
			</div></div><!-- /Accent Color -->

			<?php
			$tokens = is_array( $settings['tokens'] ?? null ) ? $settings['tokens'] : [];
			$tok_val = function ( $v ) { return is_int( $v ) ? (string) $v : ''; };
			?>
			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Design tokens <?php echo self::hint_icon('Site-wide CSS variables. Leave blank to use each module\'s built-in defaults. Setting a value cascades everywhere the token is wired.'); ?></h2>
			<div class="wchs-section__body">
				<div class="wchs-field">
					<label>Corner radius (px) <?php echo self::hint_icon('0 = sharp (default), 4–8 = soft, 16+ = rounded. Affects CTA buttons and future-wired components.'); ?></label>
					<input type="number" name="tokens[radius]" min="0" max="32" step="1" value="<?php echo esc_attr( $tok_val( $tokens['radius'] ?? null ) ); ?>" placeholder="(unset)" style="width:120px" />
				</div>
				<div class="wchs-field">
					<label>Vertical spacing — Compact (px) <?php echo self::hint_icon('Module top+bottom padding when spacing_v=compact. Default 12–16px.'); ?></label>
					<input type="number" name="tokens[spacing_v_compact]" min="0" max="48" step="1" value="<?php echo esc_attr( $tok_val( $tokens['spacing_v_compact'] ?? null ) ); ?>" placeholder="(unset)" style="width:120px" />
				</div>
				<div class="wchs-field">
					<label>Vertical spacing — Normal (px) <?php echo self::hint_icon('Module top+bottom padding when spacing_v=normal. Default 32–48px per module.'); ?></label>
					<input type="number" name="tokens[spacing_v_normal]" min="16" max="96" step="1" value="<?php echo esc_attr( $tok_val( $tokens['spacing_v_normal'] ?? null ) ); ?>" placeholder="(unset)" style="width:120px" />
				</div>
				<div class="wchs-field">
					<label>Vertical spacing — Spacious (px) <?php echo self::hint_icon('Module top+bottom padding when spacing_v=spacious. Default 56–80px per module.'); ?></label>
					<input type="number" name="tokens[spacing_v_spacious]" min="48" max="160" step="1" value="<?php echo esc_attr( $tok_val( $tokens['spacing_v_spacious'] ?? null ) ); ?>" placeholder="(unset)" style="width:120px" />
				</div>
			</div>
			</div><!-- /Design tokens -->

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Announcement bar <?php echo self::hint_icon('Scrolling promo strip above the header. One message per line. Uses your accent color.'); ?></h2>
			<div class="wchs-section__body">
			<?php
			$ann_items = $settings['announcement_bar_items'] ?? [];
			$ann_text  = is_array( $ann_items ) ? implode( "\n", $ann_items ) : '';
			?>
			<div class="wchs-field" style="margin-bottom:16px">
				<label class="wchs-toggle">
					<input type="checkbox" name="announcement_bar_enabled" value="1" <?php checked( ! empty( $settings['announcement_bar_enabled'] ) ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Show scrolling announcement bar</span>
				</label>
			</div>
			<div class="wchs-field">
				<label>Messages <?php echo self::hint_icon('One line per message. Example: UP TO 40% OFF TODAY'); ?></label>
				<textarea name="announcement_bar_items" rows="5" style="width:100%;max-width:480px;font-family:monospace;font-size:13px"><?php echo esc_textarea( $ann_text ); ?></textarea>
			</div>
			</div>
			</div><!-- /Announcement bar -->

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Header Navigation <?php echo self::hint_icon('Links shown in the site header. Each can display as text, icon, or both.'); ?></h2>
			<div class="wchs-section__body">
			<?php
			$hlinks = $settings['header_links'] ?? [];
			$icon_names = ['user','users','search','bag','mail','shipping','package','gift','wallet','percent','shield','lock','check','award','lab','leaf','zap','star','heart','thumbsup','phone','globe','clock','refresh','sun','moon','menu'];
			?>
			<div id="wchs-header-links">
				<?php foreach ( $hlinks as $hi => $hl ) : ?>
					<div class="wchs-header-link" style="display:flex;gap:6px;margin-bottom:8px;align-items:center;flex-wrap:wrap">
						<input type="text" name="header_links[<?php echo $hi; ?>][label]" value="<?php echo esc_attr( $hl['label'] ?? '' ); ?>" placeholder="Label" style="width:100px" />
						<input type="text" name="header_links[<?php echo $hi; ?>][url]" value="<?php echo esc_attr( $hl['url'] ?? '' ); ?>" placeholder="/path" style="width:100px" />
						<select name="header_links[<?php echo $hi; ?>][display]" style="width:auto">
							<option value="text" <?php selected( $hl['display'] ?? 'text', 'text' ); ?>>Text</option>
							<option value="icon" <?php selected( $hl['display'] ?? '', 'icon' ); ?>>Icon</option>
							<option value="both" <?php selected( $hl['display'] ?? '', 'both' ); ?>>Both</option>
						</select>
						<?php echo self::render_icon_picker_html( 'header_links[' . $hi . '][icon]', $hl['icon'] ?? '' ); ?>
						<label class="wchs-check">
							<input type="checkbox" name="header_links[<?php echo $hi; ?>][accent]" value="1" <?php checked( ! empty( $hl['accent'] ) ); ?> />
							<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
							<span>Accent</span>
						</label>
						<label class="wchs-check" title="Pin this link inline on mobile (otherwise it goes into the hamburger drawer)">
							<input type="checkbox" name="header_links[<?php echo $hi; ?>][mobile_pin]" value="1" <?php checked( ! empty( $hl['mobile_pin'] ) ); ?> />
							<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
							<span>Pin on mobile</span>
						</label>
						<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="wchs-btn wchs-btn--secondary" id="wchs-add-header-link" style="margin-bottom:12px">+ Add Link</button>
			<div id="wchs-icon-picker-tpl" style="display:none"><?php echo self::render_icon_picker_html(); ?></div>
			<div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
				<label class="wchs-toggle">
					<input type="checkbox" name="header_show_toggle" value="1" <?php checked( $settings['header_show_toggle'] ?? true ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Show theme toggle</span>
				</label>
				<label class="wchs-check">
					<input type="checkbox" name="header_toggle_accent" value="1" <?php checked( $settings['header_toggle_accent'] ?? true ); ?> />
					<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
					<span>Theme toggle accent</span>
				</label>
				<label class="wchs-check">
					<input type="checkbox" name="header_cart_accent" value="1" <?php checked( $settings['header_cart_accent'] ?? true ); ?> />
					<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
					<span>Cart accent</span>
				</label>
				<label class="wchs-check">
					<input type="checkbox" name="header_inverted" value="1" <?php checked( $settings['header_inverted'] ?? false ); ?> />
					<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
					<span>Invert header colors</span>
				</label>
				<label class="wchs-check">
					<input type="checkbox" name="header_borderless" value="1" <?php checked( $settings['header_borderless'] ?? false ); ?> />
					<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
					<span>Borderless icons</span>
				</label>
			</div>

			<?php $theme_default = $settings['theme_default'] ?? 'system'; ?>
			<div class="wchs-field" style="margin-top:20px">
				<label style="display:inline-flex;align-items:center;gap:6px">
					Default theme on first load
					<?php echo self::hint_icon( 'What visitors see on first load. All three modes keep the site-wide light/dark toggle fully functional; this only sets the initial state before anyone clicks.' ); ?>
				</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="theme_default" value="system" <?php checked( $theme_default, 'system' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Respect system preference (recommended)</span></label>
					<label class="wchs-radio"><input type="radio" name="theme_default" value="light" <?php checked( $theme_default, 'light' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Light by default</span></label>
					<label class="wchs-radio"><input type="radio" name="theme_default" value="dark" <?php checked( $theme_default, 'dark' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Dark by default</span></label>
				</div>
			</div>
			<div class="wchs-field" style="margin-bottom:16px">
				<label class="wchs-toggle">
					<input type="checkbox" name="logo_invert_on_dark" value="1" <?php checked( $settings['logo_invert_on_dark'] ?? true ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Auto-invert logo colors in dark mode <?php echo self::hint_icon('Useful when your logo is a single-color mark. Turn off if the logo has color detail or already includes light/dark variants. If a dark-mode logo is uploaded below, it takes precedence and inversion is skipped.'); ?></span>
				</label>
			</div>
			<?php
			$dark_logo_id  = (int) ( $settings['logo_dark_id'] ?? 0 );
			$dark_logo_url = $dark_logo_id ? wp_get_attachment_image_url( $dark_logo_id, 'medium' ) : '';
			?>
			<div class="wchs-field" style="margin-bottom:16px">
				<label>Dark-mode logo (optional) <?php echo self::hint_icon('Upload a separate logo asset to render in dark mode. When set, the auto-invert filter above is skipped. Leave blank to keep using the primary logo (optionally with auto-invert).'); ?></label>
				<div id="wchs-logo-dark-picker" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:6px">
					<img id="wchs-logo-dark-preview"
						src="<?php echo esc_url( $dark_logo_url ); ?>"
						alt=""
						style="max-height:48px;max-width:180px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#0c0c0c;<?php echo $dark_logo_url ? '' : 'display:none;'; ?>" />
					<button type="button" class="wchs-btn wchs-btn--secondary" id="wchs-logo-dark-choose">
						<?php echo $dark_logo_url ? 'Change' : 'Upload / choose'; ?>
					</button>
					<button type="button" class="wchs-btn wchs-btn--secondary" id="wchs-logo-dark-remove" style="color:#a00;<?php echo $dark_logo_url ? '' : 'display:none;'; ?>">Remove</button>
					<input type="hidden" name="logo_dark_id" id="wchs-logo-dark-id" value="<?php echo (int) $dark_logo_id; ?>" />
				</div>
			</div>
			<script>
			(function(){
				var chooseBtn = document.getElementById('wchs-logo-dark-choose');
				var removeBtn = document.getElementById('wchs-logo-dark-remove');
				var preview   = document.getElementById('wchs-logo-dark-preview');
				var idInput   = document.getElementById('wchs-logo-dark-id');
				if (!chooseBtn || !removeBtn || !preview || !idInput) return;
				if (typeof wp === 'undefined' || !wp.media) return;
				var frame = null;
				chooseBtn.addEventListener('click', function(e){
					e.preventDefault();
					if (!frame) {
						frame = wp.media({
							title: 'Select dark-mode logo',
							button: { text: 'Use this image' },
							library: { type: 'image' },
							multiple: false
						});
						frame.on('select', function(){
							var att = frame.state().get('selection').first().toJSON();
							idInput.value = att.id;
							var url = (att.sizes && att.sizes.medium && att.sizes.medium.url) ? att.sizes.medium.url : att.url;
							preview.src = url;
							preview.style.display = '';
							removeBtn.style.display = '';
							chooseBtn.textContent = 'Change';
						});
					}
					frame.open();
				});
				removeBtn.addEventListener('click', function(e){
					e.preventDefault();
					idInput.value = '0';
					preview.src = '';
					preview.style.display = 'none';
					removeBtn.style.display = 'none';
					chooseBtn.textContent = 'Upload / choose';
				});
			})();
			</script>
			<?php $logo_size = $settings['logo_size'] ?? 'standard'; ?>
			<div class="wchs-field" style="margin-bottom:16px">
				<label>Logo size (desktop)</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="logo_size" value="compact"   <?php checked( $logo_size, 'compact' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Compact (24px)</span></label>
					<label class="wchs-radio"><input type="radio" name="logo_size" value="standard"  <?php checked( $logo_size, 'standard' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Standard (32px)</span></label>
					<label class="wchs-radio"><input type="radio" name="logo_size" value="prominent" <?php checked( $logo_size, 'prominent' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Prominent (40px)</span></label>
					<label class="wchs-radio"><input type="radio" name="logo_size" value="xl"        <?php checked( $logo_size, 'xl' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>XL (56px)</span> <?php echo self::hint_icon('Mobile logo size stays constrained (24–28px) for tap-target spacing. Larger logos increase the overall header height.'); ?></label>
				</div>
			</div>
			<?php $brand_position = $settings['brand_position'] ?? 'left'; ?>
			<div class="wchs-field" style="margin-bottom:24px">
				<label>Logo / brand position (desktop)</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="brand_position" value="left"   <?php checked( $brand_position, 'left' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Left — nav on right</span></label>
					<label class="wchs-radio"><input type="radio" name="brand_position" value="nav-center" <?php checked( $brand_position, 'nav-center' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Left logo — nav centered</span> <?php echo self::hint_icon('Desktop only. Logo stays left; Shop/About/links sit in the horizontal center of the header.'); ?></label>
					<label class="wchs-radio"><input type="radio" name="brand_position" value="center" <?php checked( $brand_position, 'center' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Centered logo — nav on right</span> <?php echo self::hint_icon('Mobile brand is always centered regardless of this setting.'); ?></label>
				</div>
			</div>

			<div class="wchs-section__head" style="margin-top:20px">
				<h3 style="margin:0;font-size:13px;font-weight:600;color:var(--wchs-text)">Mobile layout</h3>
				<?php echo self::hint_icon( 'Up to 3 pinned items appear inline on mobile; the rest collapse into the hamburger drawer.' ); ?>
			</div>
			<?php $hamburger_side = $settings['mobile_hamburger_side'] ?? 'right'; ?>
			<div class="wchs-field">
				<label>Hamburger side (mobile only)</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="mobile_hamburger_side" value="right" <?php checked( $hamburger_side, 'right' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Right (pinned items on left)</span></label>
					<label class="wchs-radio"><input type="radio" name="mobile_hamburger_side" value="left" <?php checked( $hamburger_side, 'left' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Left (pinned items on right)</span></label>
					<label class="wchs-radio"><input type="radio" name="mobile_hamburger_side" value="off" <?php checked( $hamburger_side, 'off' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Off (no drawer, all items inline)</span></label>
				</div>
			</div>
			<div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap">
				<label class="wchs-check">
					<input type="checkbox" name="header_cart_mobile_pin" value="1" <?php checked( $settings['header_cart_mobile_pin'] ?? true ); ?> />
					<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
					<span>Pin cart on mobile</span>
				</label>
				<label class="wchs-check">
					<input type="checkbox" name="header_toggle_mobile_pin" value="1" <?php checked( $settings['header_toggle_mobile_pin'] ?? false ); ?> />
					<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
					<span>Pin theme toggle on mobile</span>
				</label>
			</div>
			</div></div><!-- /Header Navigation -->

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Typography <?php echo self::hint_icon('Global font settings applied across the storefront. Heading font applies to section titles, product names, and navigation. Body font applies to descriptions, content blocks, and UI text. Hero headline font remains independent (configured on the Homepage tab).'); ?></h2>
			<div class="wchs-section__body">
			<div class="wchs-field" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
				<div>
					<label>Heading font</label>
					<?php $this->render_font_select( 'typography_heading_font', $settings['typography_heading_font'] ?? 'inter' ); ?>
				</div>
				<div>
					<label>Heading weight</label>
					<select name="typography_heading_weight">
						<?php foreach ( [ 'light' => 'Light (300)', 'regular' => 'Regular (400)', 'medium' => 'Medium (500)', 'semibold' => 'Semibold (600)', 'bold' => 'Bold (700)', 'extrabold' => 'Extra Bold (800)', 'black' => 'Black (900)' ] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $settings['typography_heading_weight'] ?? 'semibold', $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="wchs-field" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
				<div>
					<label>Body font</label>
					<?php $this->render_font_select( 'typography_body_font', $settings['typography_body_font'] ?? 'inter' ); ?>
				</div>
				<div>
					<label>Body text size</label>
					<select name="typography_body_size">
						<option value="s" <?php selected( $settings['typography_body_size'] ?? 'm', 's' ); ?>>Small (14px base)</option>
						<option value="m" <?php selected( $settings['typography_body_size'] ?? 'm', 'm' ); ?>>Medium &mdash; default (15px)</option>
						<option value="l" <?php selected( $settings['typography_body_size'] ?? 'm', 'l' ); ?>>Large (16px base)</option>
					</select>
				</div>
			</div>
			</div></div><!-- /Typography -->

			<?php
			$pc = array_merge(
				[
					'media_aspect_ratio' => '1:1', 'corner_radius' => 'round', 'border' => 'none',
					'hover_effect' => 'shadow', 'button_style' => 'solid',
					'badge_position' => 'top-right', 'badge_style' => 'filled',
					'show_bulk_badge' => true, 'show_tier_hint' => true, 'show_oos_cards' => true,
					'oos_treatment' => 'grayscale', 'title_lines' => 'auto',
					'secondary_image_on_hover' => false, 'sale_badge_text' => 'Sale',
				],
				(array) ( $settings['product_card'] ?? [] )
			);
			?>
			<div class="wchs-section wchs-section--collapsed" data-section="product-card" data-preview-path="/preview/product-card">
			<h2 class="wchs-section__toggle">Product card <?php echo self::hint_icon('Aesthetic + content options for product cards shown in the shop grid, homepage sliders, and cross-sell rail. Changes preview live. Expanding this section switches the canvas to a dedicated card preview.'); ?></h2>
			<div class="wchs-section__body">

			<div class="wchs-field" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
				<div>
					<label>Image aspect ratio</label>
					<select name="product_card[media_aspect_ratio]">
						<?php foreach ( [ '1:1' => 'Square (1:1)', '4:5' => 'Portrait (4:5)', '3:4' => 'Classic portrait (3:4)', '16:9' => 'Landscape (16:9)' ] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pc['media_aspect_ratio'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label>Corner radius</label>
					<select name="product_card[corner_radius]">
						<?php foreach ( [ 'square' => 'Square', 'soft' => 'Soft (4px)', 'round' => 'Round (8px)', 'pill' => 'Pill (16px)' ] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pc['corner_radius'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="wchs-field" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
				<div>
					<label>Border</label>
					<select name="product_card[border]">
						<?php foreach ( [ 'full' => 'Full border', 'bottom-only' => 'Bottom only', 'none' => 'None', 'hover-only' => 'Hover only' ] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pc['border'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label>Hover effect</label>
					<select name="product_card[hover_effect]">
						<?php foreach ( [ 'lift' => 'Lift', 'shadow' => 'Shadow', 'border' => 'Border glow', 'none' => 'None' ] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pc['hover_effect'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="wchs-field" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
				<div>
					<label>Add-to-cart button</label>
					<select name="product_card[button_style]">
						<?php foreach ( [ 'outline' => 'Outline', 'solid' => 'Solid', 'icon-only' => 'Icon only' ] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pc['button_style'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label>Badge position</label>
					<select name="product_card[badge_position]">
						<option value="top-left" <?php selected( $pc['badge_position'], 'top-left' ); ?>>Top left</option>
						<option value="top-right" <?php selected( $pc['badge_position'], 'top-right' ); ?>>Top right</option>
					</select>
				</div>
				<div>
					<label>Badge style</label>
					<select name="product_card[badge_style]">
						<?php foreach ( [ 'filled' => 'Filled', 'outline' => 'Outline', 'minimal' => 'Minimal (text only)' ] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $pc['badge_style'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="wchs-field" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
				<div>
					<label>Title lines</label>
					<select name="product_card[title_lines]">
						<option value="auto" <?php selected( $pc['title_lines'], 'auto' ); ?>>Auto (measured)</option>
						<option value="1" <?php selected( $pc['title_lines'], '1' ); ?>>1 line</option>
						<option value="2" <?php selected( $pc['title_lines'], '2' ); ?>>2 lines</option>
						<option value="3" <?php selected( $pc['title_lines'], '3' ); ?>>3 lines</option>
					</select>
				</div>
				<div>
					<label>Out-of-stock treatment</label>
					<select name="product_card[oos_treatment]">
						<option value="grayscale" <?php selected( $pc['oos_treatment'], 'grayscale' ); ?>>Grayscale (current)</option>
						<option value="dim" <?php selected( $pc['oos_treatment'], 'dim' ); ?>>Dim only</option>
						<option value="hidden-price" <?php selected( $pc['oos_treatment'], 'hidden-price' ); ?>>"Sold out" label (no price)</option>
					</select>
				</div>
			</div>

			<div class="wchs-field" style="margin-top:8px">
				<label style="display:inline-flex;align-items:center;gap:6px">
					Sale badge text
					<?php echo self::hint_icon( 'Shown on sale products. Use {percent} to insert the discount %, e.g. −{percent}%' ); ?>
				</label>
				<input type="text" name="product_card[sale_badge_text]" value="<?php echo esc_attr( $pc['sale_badge_text'] ); ?>" maxlength="40" placeholder="Sale" style="max-width:320px" />
			</div>

			<div class="wchs-field" style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
				<label class="wchs-toggle">
					<input type="checkbox" name="product_card[show_bulk_badge]" value="1" <?php checked( $pc['show_bulk_badge'] ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Show bulk-save badge</span>
				</label>
				<label class="wchs-toggle">
					<input type="checkbox" name="product_card[show_tier_hint]" value="1" <?php checked( $pc['show_tier_hint'] ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Show tier hint ("2+ save 5% · 8+ save 15%") during quantity step</span>
				</label>
				<label class="wchs-toggle">
					<input type="checkbox" name="product_card[show_oos_cards]" value="1" <?php checked( $pc['show_oos_cards'] ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Show out-of-stock products in grids (uncheck to hide entirely)</span>
				</label>
				<label class="wchs-toggle">
					<input type="checkbox" name="product_card[secondary_image_on_hover]" value="1" <?php checked( $pc['secondary_image_on_hover'] ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Show secondary image on hover (desktop only, requires a second product image)</span>
				</label>
			</div>
			</div></div><!-- /Product card -->

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Footer <?php echo self::hint_icon('Footer link columns that appear on every page. Use 1, 3, or 5 columns for best centered alignment. Maximum 5.'); ?></h2>
			<div class="wchs-section__body">
			<div id="wchs-footer-columns" style="margin-top:12px">
				<?php
				$footer = $settings['footer'] ?? [ 'columns' => [] ];
				$cols = $footer['columns'] ?? [];
				foreach ( $cols as $ci => $col ) :
					$col_title = $col['title'] ?? '';
					$links     = $col['links'] ?? [];
				?>
					<div class="wchs-footer-col" style="border:1px solid #ddd;padding:12px 16px;margin-bottom:12px;background:#fff">
						<div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">
							<input type="text" name="footer_columns[<?php echo $ci; ?>][title]" value="<?php echo esc_attr( $col_title ); ?>" placeholder="Column title" style="flex:1" />
							<button type="button" class="wchs-btn wchs-btn--secondary wchs-remove-footer-col" style="color:#a00">Remove</button>
						</div>
						<div class="wchs-footer-links">
							<?php foreach ( $links as $li => $link ) : ?>
								<div class="wchs-footer-link" style="display:flex;gap:6px;margin-bottom:6px">
									<input type="text" name="footer_columns[<?php echo $ci; ?>][links][<?php echo $li; ?>][label]" value="<?php echo esc_attr( $link['label'] ?? '' ); ?>" placeholder="Label" style="flex:1" />
									<input type="text" name="footer_columns[<?php echo $ci; ?>][links][<?php echo $li; ?>][url]" value="<?php echo esc_attr( $link['url'] ?? '' ); ?>" placeholder="/slug or https://..." style="flex:1" />
									<button type="button" class="wchs-accordion-item__remove" title="Remove link">✕</button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-footer-link" data-col-idx="<?php echo $ci; ?>" style="font-size:11px;padding:4px 10px">+ Add Link</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="wchs-btn wchs-btn--secondary" id="wchs-add-footer-col" style="margin-bottom:24px">+ Add Column <?php echo self::hint_icon('Use 1, 3, or 5 columns for best centered alignment. Maximum 5.'); ?></button>

			<?php
			$footer_tagline = $settings['footer']['tagline'] ?? '';
			$social_links   = $settings['social_links'] ?? [];
			$platforms      = [ 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest' ];
			?>
			<div class="wchs-field" style="margin-bottom:20px">
				<label>Footer tagline <?php echo self::hint_icon('Appears below brand name in footer. Keep it short — 10-15 words.'); ?></label>
				<input type="text" name="footer_tagline" value="<?php echo esc_attr( $footer_tagline ); ?>" placeholder="One-line blurb under the brand name" style="max-width:600px;width:100%" />
			</div>

			<div class="wchs-section__head" style="margin-top:24px">
				<h3 style="margin:0;font-size:13px;font-weight:600;color:var(--wchs-text)">Social links</h3>
				<?php echo self::hint_icon( 'Shown as icon row in footer. Supports Instagram, Facebook, X, YouTube, LinkedIn, TikTok, Pinterest.' ); ?>
			</div>
			<div id="wchs-social-links" style="margin-top:12px">
				<?php foreach ( $social_links as $si => $link ) :
					$sp = $link['platform'] ?? '';
					$su = $link['url'] ?? '';
				?>
					<div class="wchs-social-link" style="display:flex;gap:6px;margin-bottom:6px;max-width:600px">
						<select name="social_links[<?php echo $si; ?>][platform]" style="flex:0 0 160px">
							<?php foreach ( $platforms as $pk => $pl ) : ?>
								<option value="<?php echo esc_attr( $pk ); ?>" <?php selected( $sp, $pk ); ?>><?php echo esc_html( $pl ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="url" name="social_links[<?php echo $si; ?>][url]" value="<?php echo esc_attr( $su ); ?>" placeholder="https://instagram.com/yourhandle" style="flex:1" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-remove-social" style="color:#a00">Remove</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="wchs-btn wchs-btn--secondary" id="wchs-add-social" style="margin-bottom:12px">+ Add Social Link</button>
			<template id="wchs-social-template">
				<div class="wchs-social-link" style="display:flex;gap:6px;margin-bottom:6px;max-width:600px">
					<select name="social_links[__IDX__][platform]" style="flex:0 0 160px">
						<?php foreach ( $platforms as $pk => $pl ) : ?>
							<option value="<?php echo esc_attr( $pk ); ?>"><?php echo esc_html( $pl ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="url" name="social_links[__IDX__][url]" placeholder="https://instagram.com/yourhandle" style="flex:1" />
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-remove-social" style="color:#a00">Remove</button>
				</div>
			</template>
			<script>
			(function(){
				var container = document.getElementById('wchs-social-links');
				var addBtn    = document.getElementById('wchs-add-social');
				var tpl       = document.getElementById('wchs-social-template');
				if ( ! container || ! addBtn || ! tpl ) return;
				addBtn.addEventListener('click', function(){
					var idx = container.querySelectorAll('.wchs-social-link').length;
					var html = tpl.innerHTML.replace(/__IDX__/g, idx);
					var wrap = document.createElement('div');
					wrap.innerHTML = html;
					container.appendChild(wrap.firstElementChild);
				});
				container.addEventListener('click', function(e){
					var t = e.target;
					if ( t && t.classList && t.classList.contains('wchs-remove-social') ) {
						var row = t.closest('.wchs-social-link');
						if ( row ) row.remove();
					}
				});
			})();
			</script>

			</div></div><!-- /Footer -->

			<div style="margin-top:16px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>
		<?php
	}

	// ─── Checkout Tab ───────────────────────────────────────────
	/**
	 * Render an API-key input that's masked for non-super-admins.
	 * `install_plugins` is the cap real administrators have and
	 * shop_manager (via headless-preview-role.php) does not — so it's
	 * our "this is a real admin" test. Non-admins see bullets + a
	 * hidden input preserving the existing value on save, so their
	 * save action doesn't clobber the key.
	 */
	private function render_masked_api_key( string $name, string $label, string $value, string $hint = '', bool $is_password = false ): void {
		$is_super_admin = current_user_can( 'install_plugins' );
		?>
		<div class="wchs-field">
			<label><?php echo esc_html( $label ); ?> <?php echo self::hint_icon('<?php echo wp_kses_post( $hint ); ?>'); ?></label>
			<?php if ( $is_super_admin ) : ?>
				<input type="<?php echo $is_password ? 'password' : 'text'; ?>"
				       name="<?php echo esc_attr( $name ); ?>"
				       value="<?php echo esc_attr( $value ); ?>" />
			<?php else : ?>
				<input type="text"
				       value="<?php echo $value ? '•••••••• (hidden — administrator only)' : '(not set — administrator only)'; ?>"
				       readonly disabled
				       style="color:#888;background:#f5f5f5;cursor:not-allowed" />
				<?php /* no hidden input — save handlers preserve existing value for non-admin saves */ ?>
			<?php endif; ?>
			<?php if ( $hint ) : ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_checkout_tab( array $settings ): void {
		$bump_pid = (int) ( $settings['bump_product_id'] ?? 0 );
		$av_mode  = $settings['address_validation_mode'] ?? 'moderate';
		?>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="checkout" />

			<h2>Order Bump <?php echo self::hint_icon('A checkbox offer shown before the Place Order button. When checked, the product is added to the cart and included in fee/tax calculations.'); ?></h2>
			<div class="wchs-field">
				<label>Bump Product</label>
				<?php
				$bump_var_id = (int) ( $settings['bump_variation_id'] ?? 0 );
				if ( $bump_pid > 0 ) :
					$bp = $bump_var_id ? wc_get_product( $bump_var_id ) : wc_get_product( $bump_pid );
					$bp_parent = wc_get_product( $bump_pid );
					if ( $bp && $bp_parent ) :
						$bp_img = $bp->get_image_id() ? wp_get_attachment_image_url( $bp->get_image_id(), 'thumbnail' ) : '';
						if ( ! $bp_img && $bp_parent->get_image_id() ) $bp_img = wp_get_attachment_image_url( $bp_parent->get_image_id(), 'thumbnail' );
						$bp_name = $bp_parent->get_name();
						$bp_attrs = '';
						if ( $bump_var_id && $bp->is_type( 'variation' ) ) {
							$attrs = $bp->get_variation_attributes();
							$parts = [];
							foreach ( $attrs as $k => $v ) $parts[] = $v;
							$bp_attrs = implode( ', ', $parts );
						}
					?>
					<div class="wchs-bump-preview" style="display:flex;gap:12px;align-items:center;padding:10px 14px;border:1px solid #ddd;border-radius:4px;background:#fafafa;margin-bottom:8px">
						<?php if ( $bp_img ) : ?>
							<img src="<?php echo esc_url( $bp_img ); ?>" width="40" height="40" style="object-fit:cover;border-radius:3px;border:1px solid #e0e0e0" />
						<?php endif; ?>
						<div class="wchs-field" style="flex:1;margin-bottom:0">
							<strong style="font-size:13px"><?php echo esc_html( $bp_name ); ?></strong>
							<?php if ( $bp_attrs ) : ?>
								<span style="font-size:12px;color:#555;margin-left:4px">(<?php echo esc_html( $bp_attrs ); ?>)</span>
							<?php endif; ?>
							<span style="font-size:12px;color:#767d88;margin-left:8px"><?php echo html_entity_decode( strip_tags( wc_price( $bp->get_price() ) ), ENT_QUOTES, 'UTF-8' ); ?></span>
						</div>
						<button type="button" style="color:#a00;background:none;border:1px solid #ddd;border-radius:3px;padding:2px 8px;cursor:pointer;font-size:12px" onclick="this.closest('.wchs-bump-preview').style.display='none';var f=this.closest('.wchs-field');f.querySelector('.wchs-product-ids-hidden').value='0';f.querySelector('.wchs-bump-variation-id').value='0';f.querySelector('.wchs-product-picker').style.display='';">Remove</button>
					</div>
					<?php endif; endif; ?>
				<div class="wchs-product-picker wchs-product-picker--single" data-field="bump_product_id" style="<?php echo ( $bump_pid > 0 ) ? 'display:none' : ''; ?>">
					<input type="text" class="wchs-product-search" placeholder="Search products by name…" autocomplete="off" />
					<div class="wchs-product-results"></div>
					<input type="hidden" name="bump_product_id" value="<?php echo esc_attr( $bump_pid ); ?>" class="wchs-product-ids-hidden" />
					<input type="hidden" name="bump_variation_id" value="<?php echo esc_attr( $settings['bump_variation_id'] ?? 0 ); ?>" class="wchs-bump-variation-id" />
					<ul class="wchs-product-tags"></ul>
					<div class="wchs-bump-variations" style="display:none;margin-top:8px"></div>
				</div>
			</div>

			<h2>Post-Checkout Upsell <?php echo self::hint_icon('After checkout, customers see a one-click offer page before the thank-you page. Products are selected from each product\'s Linked Products > Upsells field in WooCommerce, with category best-sellers as fallback.'); ?></h2>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="upsell_enabled" value="1" <?php checked( $settings['upsell_enabled'] ?? false ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Enable post-checkout upsell offers</span>
				</label>
			</div>

			<h2>Address Autocomplete <?php echo self::hint_icon('Google Places API powers the type-ahead address suggestions as the customer types. This is the autocomplete dropdown, not the validation step.'); ?></h2>
			<?php $this->render_masked_api_key(
				'google_maps_api_key',
				'Google Maps API Key',
				$settings['google_maps_api_key'] ?? '',
				'Requires Places API enabled. <a href="https://console.cloud.google.com/apis/library/places-backend.googleapis.com" target="_blank">Enable in Google Cloud Console</a>.'
			); ?>

			<h2>Address Validation <?php echo self::hint_icon('EasyPost verifies the submitted address against USPS/carrier databases after the customer fills the form. Shows a correction modal if the address doesn\'t match. Works for US, Canada, and EU addresses.'); ?></h2>
			<?php $this->render_masked_api_key(
				'easypost_api_key',
				'EasyPost API Key',
				$settings['easypost_api_key'] ?? '',
				'~$0.02/verification. <a href="https://www.easypost.com/account/api-keys" target="_blank">Get your API key</a>.'
			); ?>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="address_validation_enabled" value="1" <?php checked( ! empty( $settings['address_validation_enabled'] ) ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Enable address validation at checkout</span>
				</label>
			</div>
			<div class="wchs-field">
				<label>Enforcement Mode</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="address_validation_mode" value="strict" <?php checked( $av_mode, 'strict' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Strict - customer must use the verified address. Cannot proceed with unverified input.</span></label>
					<label class="wchs-radio"><input type="radio" name="address_validation_mode" value="moderate" <?php checked( $av_mode, 'moderate' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Moderate - suggest the verified address but allow the customer to keep their original input. (recommended)</span></label>
					<label class="wchs-radio"><input type="radio" name="address_validation_mode" value="loose" <?php checked( $av_mode, 'loose' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Loose - show a warning if verification fails but never block checkout.</span> <?php echo self::hint_icon('When enabled, guests who enter their email at checkout but don\'t complete receive two recovery emails (1 hour and 24 hours later). Disable this if you use a third-party plugin (Omnisend, Klaviyo, Mailchimp) that handles abandoned-cart emails — otherwise customers receive duplicate recovery messages.'); ?></label>
				</div>
			</div>

			<h2>Abandoned Cart Recovery</h2>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="abandoned_cart_enabled" value="1" <?php checked( $settings['abandoned_cart_enabled'] ?? true ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Send built-in abandoned cart recovery emails</span>
				</label>
			</div>

			<h2>Offline Payment Methods</h2>
			<?php $this->render_offline_gateways_section(); ?>

			<div style="margin-top:24px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>
		<?php
	}

	// ─── Integrations Tab ───────────────────────────────────────
	private function render_integrations_tab( array $settings ): void {
		$gtm_id       = $settings['gtm_id'] ?? '';
		$omnisend_bid = $settings['omnisend_brand_id'] ?? '';
		$rp           = $settings['review_provider'] ?? 'woocommerce';
		$rp_keys      = $settings['review_provider_keys'] ?? [];
		?>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="integrations" />

			<h2>Analytics</h2>
			<div class="wchs-field">
				<label>Google Tag Manager ID <?php echo self::hint_icon('Loads on SPA and WP pages.'); ?></label>
				<input type="text" name="gtm_id" value="<?php echo esc_attr( $gtm_id ); ?>" placeholder="GTM-XXXXXXX" />
			</div>

			<h2>Omnisend Email / SMS</h2>
			<div class="wchs-field">
				<label>Omnisend Brand ID <?php echo self::hint_icon('Copy from Omnisend dashboard → Store Settings → Brand ID. Enables on-site tracking (viewed product, added to cart, placed order), contact identification at checkout, signup forms, popups, and browser push — on both the SPA and WP checkout surfaces. Leave blank to disable. If set, disable the built-in abandoned-cart emails under Checkout to avoid duplicate recovery sends.'); ?></label>
				<input type="text" name="omnisend_brand_id" value="<?php echo esc_attr( $omnisend_bid ); ?>" placeholder="65a1b2c3d4e5f6..." />
			</div>

			<h2>Pixels &amp; Tracking <?php echo self::hint_icon("Each field below loads the vendor's official snippet on both the SPA and WP checkout pages when filled, and fires standardized events at the same funnel points (viewed product → add to cart → checkout → purchase). Leave any field blank to skip that tracker. All fields are validated against the vendor's documented ID format."); ?></h2>

			<div class="wchs-field">
				<label>Klaviyo Public API Key <?php echo self::hint_icon('Klaviyo → Account → Settings → API Keys → Public API Key (6–8 chars). Powers onsite forms, email identification, abandoned cart, and purchase events.'); ?></label>
				<input type="text" name="klaviyo_public_key" value="<?php echo esc_attr( $settings['klaviyo_public_key'] ?? '' ); ?>" placeholder="ABC123" />
			</div>

			<div class="wchs-field">
				<label>Meta Pixel ID (Facebook / Instagram Ads) <?php echo self::hint_icon('Events Manager → Data Sources → your Pixel → Settings. Fires PageView, ViewContent, AddToCart, InitiateCheckout, Purchase.'); ?></label>
				<input type="text" name="meta_pixel_id" value="<?php echo esc_attr( $settings['meta_pixel_id'] ?? '' ); ?>" placeholder="123456789012345" />
			</div>

			<div class="wchs-field">
				<label>TikTok Pixel ID <?php echo self::hint_icon('TikTok Events Manager → your Pixel → Pixel ID. 20-char alphanumeric. Fires ViewContent, AddToCart, InitiateCheckout, CompletePayment.'); ?></label>
				<input type="text" name="tiktok_pixel_id" value="<?php echo esc_attr( $settings['tiktok_pixel_id'] ?? '' ); ?>" placeholder="CXXXXXXXXXXXXXXXXXXX" />
			</div>

			<div class="wchs-field">
				<label>Pinterest Tag ID <?php echo self::hint_icon('Pinterest Ads → Conversions → Pinterest Tag. 13-digit number. Fires pagevisit, addtocart, checkout.'); ?></label>
				<input type="text" name="pinterest_tag_id" value="<?php echo esc_attr( $settings['pinterest_tag_id'] ?? '' ); ?>" placeholder="2612345678901" />
			</div>

			<div class="wchs-field">
				<label>Microsoft Clarity Project ID <?php echo self::hint_icon('clarity.microsoft.com → your project → Settings → Project ID. Free session recording + heatmaps, no event wiring needed.'); ?></label>
				<input type="text" name="clarity_project_id" value="<?php echo esc_attr( $settings['clarity_project_id'] ?? '' ); ?>" placeholder="abc1234xyz" />
			</div>

			<div class="wchs-field">
				<label>Hotjar Site ID <?php echo self::hint_icon('Hotjar → Settings → Sites &amp; Organizations → Site ID. 7-digit number. Paid competitor to Clarity.'); ?></label>
				<input type="text" name="hotjar_site_id" value="<?php echo esc_attr( $settings['hotjar_site_id'] ?? '' ); ?>" placeholder="1234567" />
			</div>

			<div class="wchs-field">
				<label>Google Ads Conversion ID <?php echo self::hint_icon('Google Ads → Tools → Conversions → your conversion → Tag setup → Install the tag yourself. Copy the <code>AW-XXXXXXXXXX</code> value.'); ?></label>
				<input type="text" name="google_ads_conversion_id" value="<?php echo esc_attr( $settings['google_ads_conversion_id'] ?? '' ); ?>" placeholder="AW-1234567890" />
			</div>

			<div class="wchs-field">
				<label>Google Ads Conversion Label <?php echo self::hint_icon('Same Google Ads conversion screen as above — the label after the slash in the <code>send_to</code> value. Only purchase events are fired.'); ?></label>
				<input type="text" name="google_ads_conversion_label" value="<?php echo esc_attr( $settings['google_ads_conversion_label'] ?? '' ); ?>" placeholder="AbCdEfGhIj-Kl" />
			</div>

			<div class="wchs-section__head">
				<h2 style="margin:0">Outbound Email (SMTP)</h2>
				<?php echo self::hint_icon( 'Authenticates with a real mail provider for reliable delivery (WordPress default PHP mail fails in Docker/VPS/containers and lands in spam). wp-config.php constants WCHS_SMTP_* override these settings if defined.' ); ?>
			</div>
			<?php
			$smtp = $settings['smtp'] ?? [];
			$smtp_enabled   = ! empty( $smtp['enabled'] );
			$smtp_host      = $smtp['host'] ?? '';
			$smtp_port      = $smtp['port'] ?? '465';
			$smtp_secure    = $smtp['secure'] ?? 'ssl';
			$smtp_user      = $smtp['username'] ?? '';
			$smtp_pass      = $smtp['password'] ?? '';
			$smtp_from      = $smtp['from_email'] ?? '';
			$smtp_from_name = $smtp['from_name'] ?? '';
			$has_const      = defined( 'WCHS_SMTP_HOST' );
			?>
			<?php if ( $has_const ) : ?>
				<div class="wchs-info" style="border-left:3px solid #4ade80;background:rgba(74,222,128,0.06)">
					SMTP is configured via wp-config.php constants. The fields below are ignored while constants are defined.
				</div>
			<?php endif; ?>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="smtp_enabled" value="1" <?php checked( $smtp_enabled ); ?> <?php disabled( $has_const ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Use SMTP for outbound email</span>
				</label>
			</div>
			<div style="display:grid;grid-template-columns:2fr 80px 100px;gap:12px;max-width:600px">
				<div class="wchs-field">
					<label>SMTP Host</label>
					<input type="text" name="smtp_host" value="<?php echo esc_attr( $smtp_host ); ?>" placeholder="smtp.fastmail.com" <?php disabled( $has_const ); ?> />
				</div>
				<div class="wchs-field">
					<label>Port</label>
					<input type="number" name="smtp_port" value="<?php echo esc_attr( $smtp_port ); ?>" placeholder="465" <?php disabled( $has_const ); ?> />
				</div>
				<div class="wchs-field">
					<label>Encryption</label>
					<select name="smtp_secure" <?php disabled( $has_const ); ?>>
						<option value="ssl" <?php selected( $smtp_secure, 'ssl' ); ?>>SSL</option>
						<option value="tls" <?php selected( $smtp_secure, 'tls' ); ?>>TLS</option>
						<option value="" <?php selected( $smtp_secure, '' ); ?>>None</option>
					</select>
				</div>
			</div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:600px">
				<div class="wchs-field">
					<label>Username</label>
					<input type="text" name="smtp_username" value="<?php echo esc_attr( $smtp_user ); ?>" placeholder="user@provider.com" autocomplete="off" <?php disabled( $has_const ); ?> />
				</div>
				<div class="wchs-field">
					<label>Password</label>
					<input type="password" name="smtp_password" value="<?php echo esc_attr( $smtp_pass ); ?>" placeholder="App password" autocomplete="new-password" <?php disabled( $has_const ); ?> />
				</div>
			</div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:600px">
				<div class="wchs-field">
					<label>From Email <?php echo self::hint_icon('Must be an address your SMTP provider allows you to send as.'); ?></label>
					<input type="email" name="smtp_from_email" value="<?php echo esc_attr( $smtp_from ); ?>" placeholder="noreply@yourstore.com" <?php disabled( $has_const ); ?> />
				</div>
				<div class="wchs-field">
					<label>From Name</label>
					<input type="text" name="smtp_from_name" value="<?php echo esc_attr( $smtp_from_name ); ?>" placeholder="<?php echo esc_attr( get_option( 'blogname' ) ); ?>" <?php disabled( $has_const ); ?> />
				</div>
			</div>

			<div class="wchs-section__head">
				<h2 style="margin:0">Review Provider</h2>
				<?php echo self::hint_icon( 'Where product reviews come from. Plugins that sync to WC comments (Product Reviews Pro, YITH, ReviewX) work automatically with WooCommerce.' ); ?>
			</div>
			<div class="wchs-grid" style="max-width:600px">
				<div class="wchs-field">
					<label>Provider</label>
					<select name="review_provider" id="wchs-review-provider">
						<option value="woocommerce" <?php selected( $rp, 'woocommerce' ); ?>>WooCommerce (native)</option>
						<option value="yotpo" <?php selected( $rp, 'yotpo' ); ?>>Yotpo</option>
						<option value="stamped" <?php selected( $rp, 'stamped' ); ?>>Stamped.io</option>
						<option value="reviewsio" <?php selected( $rp, 'reviewsio' ); ?>>Reviews.io</option>
						<option value="mock" <?php selected( $rp, 'mock' ); ?>>Mock (testing)</option>
					</select>
				</div>
				<div class="wchs-field wchs-rp-field" data-provider="yotpo" style="<?php echo $rp !== 'yotpo' ? 'display:none' : ''; ?>">
					<label>Yotpo App Key</label>
					<input type="text" name="yotpo_app_key" value="<?php echo esc_attr( $rp_keys['yotpo_app_key'] ?? '' ); ?>" />
				</div>
				<div class="wchs-field wchs-rp-field" data-provider="stamped" style="<?php echo $rp !== 'stamped' ? 'display:none' : ''; ?>">
					<label>Stamped API Key</label>
					<input type="text" name="stamped_api_key" value="<?php echo esc_attr( $rp_keys['stamped_api_key'] ?? '' ); ?>" />
				</div>
				<div class="wchs-field wchs-rp-field" data-provider="stamped" style="<?php echo $rp !== 'stamped' ? 'display:none' : ''; ?>">
					<label>Stamped API Secret</label>
					<input type="password" name="stamped_api_secret" value="<?php echo esc_attr( $rp_keys['stamped_api_secret'] ?? '' ); ?>" />
				</div>
				<div class="wchs-field wchs-rp-field" data-provider="stamped" style="<?php echo $rp !== 'stamped' ? 'display:none' : ''; ?>">
					<label>Stamped Store Hash</label>
					<input type="text" name="stamped_store_hash" value="<?php echo esc_attr( $rp_keys['stamped_store_hash'] ?? '' ); ?>" />
				</div>
				<div class="wchs-field wchs-rp-field" data-provider="reviewsio" style="<?php echo $rp !== 'reviewsio' ? 'display:none' : ''; ?>">
					<label>Reviews.io Store ID</label>
					<input type="text" name="reviewsio_store_id" value="<?php echo esc_attr( $rp_keys['reviewsio_store_id'] ?? '' ); ?>" />
				</div>
				<div class="wchs-field wchs-rp-field" data-provider="reviewsio" style="<?php echo $rp !== 'reviewsio' ? 'display:none' : ''; ?>">
					<label>Reviews.io API Key</label>
					<input type="password" name="reviewsio_api_key" value="<?php echo esc_attr( $rp_keys['reviewsio_api_key'] ?? '' ); ?>" />
				</div>
			</div>

			<!-- ═══ Site Scripts (merged from standalone tab) ═══ -->
			<div class="wchs-section wchs-section--collapsed">
				<h2 class="wchs-section__toggle">Site Scripts <?php echo self::hint_icon('Enable curated third-party scripts on this site. The admin maintains the approved list in Script Registry below; you activate and fill any per-site values (shop handle, brand ID, container ID).'); ?></h2>
				<div class="wchs-section__body">
					<?php
					$registry        = self::get_script_registry();
					$active          = $settings['active_scripts'] ?? [];
					$active_by_id    = [];
					foreach ( $active as $row ) {
						if ( is_array( $row ) && ! empty( $row['id'] ) ) {
							$active_by_id[ $row['id'] ] = $row;
						}
					}
					$site_opts = self::get_site_settings();
					?>

					<?php if ( empty( $registry ) ) : ?>
						<p><em>No scripts in the registry yet. Ask an administrator to add entries under <strong>Script Registry</strong>.</em></p>
					<?php else : ?>
						<?php foreach ( $registry as $id => $entry ) :
							$row      = $active_by_id[ $id ] ?? [ 'enabled' => false, 'params' => [] ];
							$enabled  = ! empty( $row['enabled'] );
							$dkey     = $entry['dedicated_setting_key'] ?? '';
							$dedicated_filled = $dkey && ! empty( $site_opts[ $dkey ] );
						?>
							<div class="wchs-section wchs-section--script-entry" style="margin-bottom:16px;padding:16px;border:1px solid #ddd;background:#fff">
								<label class="wchs-toggle" style="font-weight:600">
									<input type="checkbox"
										name="active_scripts[<?php echo esc_attr( $id ); ?>][enabled]"
										value="1" <?php checked( $enabled ); ?> />
									<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
									<span><?php echo esc_html( $entry['name'] ); ?></span>
								</label>
								<input type="hidden" name="active_scripts[<?php echo esc_attr( $id ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" />

								<?php if ( ! empty( $entry['description'] ) ) : ?>
									<p class="description" style="margin:6px 0 10px;color:#666;font-size:12px">
										<?php echo esc_html( $entry['description'] ); ?>
									</p>
								<?php endif; ?>

								<?php if ( $dedicated_filled ) : ?>
									<p class="description" style="color:#996800;font-size:12px;background:#fff7e1;padding:8px 10px;border-left:3px solid #dba617;margin:6px 0">
										<strong>Note:</strong> this integration already has a dedicated value set under
										<em>Integrations</em> (<code><?php echo esc_html( $dkey ); ?></code>).
										The dedicated pixel mu-plugin will handle it — activating here is redundant and will be skipped to avoid double-firing.
									</p>
								<?php endif; ?>

								<?php if ( ! empty( $entry['params'] ) ) : ?>
									<div class="wchs-script-params" style="display:grid;grid-template-columns:180px 1fr;gap:8px 16px;align-items:center;margin-top:10px">
										<?php foreach ( $entry['params'] as $p ) :
											$pkey   = $p['key'];
											$pval   = $row['params'][ $pkey ] ?? '';
											$plabel = $p['label'] ?? $pkey;
											$preq   = ! empty( $p['required'] );
										?>
											<label style="font-size:12px;text-transform:none;letter-spacing:0;color:#333">
												<?php echo esc_html( $plabel ); ?>
												<?php if ( $preq ) : ?><span style="color:#a00">*</span><?php endif; ?>
											</label>
											<input type="text"
												name="active_scripts[<?php echo esc_attr( $id ); ?>][params][<?php echo esc_attr( $pkey ); ?>]"
												value="<?php echo esc_attr( $pval ); ?>"
												placeholder="<?php echo esc_attr( $p['example'] ?? '' ); ?>"
												style="max-width:420px" />
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<p class="description" style="color:#999;font-size:11px;margin:10px 0 0">
									Source: <code><?php echo esc_html( $entry['src_template'] ); ?></code>
									&middot; Placement: <?php echo esc_html( $entry['placement'] ?? 'head' ); ?>
									&middot; Surfaces: <?php echo esc_html( implode( ', ', $entry['surfaces'] ?? [ 'spa', 'wp' ] ) ); ?>
								</p>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- ═══ Script Registry (merged from standalone tab, admin-only) ═══ -->
			<?php if ( current_user_can( 'install_plugins' ) ) : ?>
			<?php
			// Re-use $registry from Site Scripts section above (already loaded)
			$reg_idx = 0;
			?>
			<div class="wchs-section wchs-section--collapsed">
				<h2 class="wchs-section__toggle">Script Registry <?php echo self::hint_icon('Curate third-party scripts that shop managers can activate. Each entry is a template — src_template plus required params. At render, params are URL-encoded and joined to src, so shop_manager input is always bounded by what you define here.'); ?></h2>
				<div class="wchs-section__body">

					<div id="wchs-script-registry-list">
						<?php foreach ( $registry as $id => $entry ) :
							$i = $reg_idx++;
							$attrs = $entry['attributes'] ?? [ 'async' => true, 'defer' => false ];
							$surfaces = $entry['surfaces'] ?? [ 'spa', 'wp' ];
						?>
							<div class="wchs-registry-entry" style="border:1px solid #ddd;padding:14px 16px;margin-bottom:14px;background:#fff">
								<div style="display:grid;grid-template-columns:160px 1fr;gap:6px 14px;align-items:center">
									<label>ID (slug)</label>
									<input type="text" name="registry[<?php echo $i; ?>][id]" value="<?php echo esc_attr( $id ); ?>" required pattern="[a-z0-9_-]+" style="max-width:240px" />

									<label>Name</label>
									<input type="text" name="registry[<?php echo $i; ?>][name]" value="<?php echo esc_attr( $entry['name'] ?? '' ); ?>" required />

									<label>Description</label>
									<input type="text" name="registry[<?php echo $i; ?>][description]" value="<?php echo esc_attr( $entry['description'] ?? '' ); ?>" />

									<label>Source template URL</label>
									<input type="url" name="registry[<?php echo $i; ?>][src_template]" value="<?php echo esc_attr( $entry['src_template'] ?? '' ); ?>" required placeholder="https://example.com/embed.js" />

									<label>Placement</label>
									<select name="registry[<?php echo $i; ?>][placement]" style="max-width:200px">
										<option value="head" <?php selected( ( $entry['placement'] ?? 'head' ), 'head' ); ?>>head</option>
										<option value="body_end" <?php selected( ( $entry['placement'] ?? 'head' ), 'body_end' ); ?>>body_end</option>
									</select>

									<label>Surfaces</label>
									<div>
										<label class="wchs-check">
											<input type="checkbox" name="registry[<?php echo $i; ?>][surfaces][]" value="spa" <?php checked( in_array( 'spa', $surfaces, true ) ); ?> />
											<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
											<span>SPA routes</span>
										</label>
										<label class="wchs-check" style="margin-left:16px">
											<input type="checkbox" name="registry[<?php echo $i; ?>][surfaces][]" value="wp" <?php checked( in_array( 'wp', $surfaces, true ) ); ?> />
											<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
											<span>WP-rendered pages (checkout, my-account)</span>
										</label>
									</div>

									<label>Attributes</label>
									<div>
										<label class="wchs-check">
											<input type="checkbox" name="registry[<?php echo $i; ?>][attributes][async]" value="1" <?php checked( ! empty( $attrs['async'] ) ); ?> />
											<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
											<span>async</span>
										</label>
										<label class="wchs-check" style="margin-left:16px">
											<input type="checkbox" name="registry[<?php echo $i; ?>][attributes][defer]" value="1" <?php checked( ! empty( $attrs['defer'] ) ); ?> />
											<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
											<span>defer</span>
										</label>
									</div>

									<label>Dedicated setting key</label>
									<input type="text" name="registry[<?php echo $i; ?>][dedicated_setting_key]" value="<?php echo esc_attr( $entry['dedicated_setting_key'] ?? '' ); ?>" placeholder="optional — e.g. gtm_id" style="max-width:240px" />
								</div>

								<div style="margin-top:12px">
									<p style="margin:6px 0;font-size:13px;font-weight:600">Params</p>
									<?php foreach ( ( $entry['params'] ?? [] ) as $pi => $p ) : ?>
										<div style="display:grid;grid-template-columns:120px 140px 80px 120px 1fr;gap:6px;margin-bottom:4px">
											<input type="text" name="registry[<?php echo $i; ?>][params][<?php echo $pi; ?>][key]" value="<?php echo esc_attr( $p['key'] ?? '' ); ?>" placeholder="key" />
											<input type="text" name="registry[<?php echo $i; ?>][params][<?php echo $pi; ?>][label]" value="<?php echo esc_attr( $p['label'] ?? '' ); ?>" placeholder="label" />
											<select name="registry[<?php echo $i; ?>][params][<?php echo $pi; ?>][type]">
												<option value="text" <?php selected( ( $p['type'] ?? 'text' ), 'text' ); ?>>text</option>
												<option value="url"  <?php selected( ( $p['type'] ?? 'text' ), 'url' ); ?>>url</option>
												<option value="hex"  <?php selected( ( $p['type'] ?? 'text' ), 'hex' ); ?>>hex</option>
											</select>
											<label class="wchs-check">
												<input type="checkbox" name="registry[<?php echo $i; ?>][params][<?php echo $pi; ?>][required]" value="1" <?php checked( ! empty( $p['required'] ) ); ?> />
												<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
												<span>required</span>
											</label>
											<input type="text" name="registry[<?php echo $i; ?>][params][<?php echo $pi; ?>][example]" value="<?php echo esc_attr( $p['example'] ?? '' ); ?>" placeholder="example value" />
										</div>
									<?php endforeach; ?>
									<p class="description" style="color:#666;font-size:11px;margin-top:6px">
										To add or remove a param, edit this registry entry via the saved option directly
										(wp_options.wchs_script_registry) — inline add/remove UI is deferred.
									</p>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<p class="description" style="color:#666;font-size:12px">
						To add a new registry entry, edit this file's <code>REGISTRY_SEEDS</code> constant
						or patch <code>wchs_script_registry</code> via wp-cli. Inline add UI is a follow-up.
					</p>
				</div>
			</div>
			<?php endif; ?>

			<div style="margin-top:24px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>
		<?php
	}

	private function render_cutover_tab( array $settings ): void {
		$report            = \function_exists( 'wchs_origin_report' ) ? \wchs_origin_report() : [];
		$mode              = $report['mode'] ?? 'same-origin';
		$mode_source       = $report['mode_source'] ?? 'default';
		$guided_notice     = self::pull_cutover_notice();
		$request_domain    = self::current_request_domain();
		$public_origin     = $report['public_origin'] ?? home_url( '/' );
		$siteurl           = $report['siteurl'] ?? '';
		$home              = $report['home'] ?? '';
		$spa_origin        = $report['spa_origin'] ?? $public_origin;
		$allowed_origins   = is_array( $report['allowed_origins'] ?? null ) ? $report['allowed_origins'] : [ $public_origin ];
		$return_origins    = is_array( $report['return_origins'] ?? null ) ? $report['return_origins'] : [ $public_origin ];
		$custom_spa_origin = (string) ( $settings['custom_spa_origin'] ?? '' );
		$custom_allowed    = implode( "\n", is_array( $settings['custom_allowed_origins'] ?? null ) ? $settings['custom_allowed_origins'] : [] );
		$custom_return     = implode( "\n", is_array( $settings['custom_return_origins'] ?? null ) ? $settings['custom_return_origins'] : [] );
		$legacy            = is_array( $report['legacy'] ?? null ) ? $report['legacy'] : [];
		$errors            = is_array( $report['errors'] ?? null ) ? $report['errors'] : [];
		$warnings          = is_array( $report['warnings'] ?? null ) ? $report['warnings'] : [];
		$checklist         = self::cutover_checklist_state( $settings );
		$tasks             = self::cutover_task_definitions();
		$robots_sitemap    = self::current_robots_sitemap_url();
		$stripe_webhook    = home_url( '/?wc-api=wc_stripe' );
		$wp_sitemap        = home_url( '/wp-sitemap.xml' );
		$spa_sitemap       = home_url( '/sitemap.xml' );
		$site_url          = trailingslashit( $public_origin );
		$account_url       = untrailingslashit( $spa_origin ) . '/account';
		$candidate_domain  = self::candidate_domain_for_cutover( $settings );
		$last_cutover_from = (string) ( $settings['last_cutover_from_domain'] ?? '' );
		$last_cutover_to   = (string) ( $settings['last_cutover_to_domain'] ?? '' );
		$last_cutover_at   = (string) ( $settings['last_cutover_at'] ?? '' );
		$mode_label        = 'same-origin' === $mode ? 'Same-origin (recommended)' : 'Custom override';
		?>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="cutover" />

			<div class="wchs-info">
				WCHS now follows the WordPress public domain by default. Leave this tab on <strong>Same-origin</strong> for normal stores so checkout redirects, login returns, CORS, and config all move with the site when the domain changes.
			</div>

			<?php if ( '' !== $request_domain && $request_domain !== self::current_site_domain() ) : ?>
				<div class="notice notice-info inline">
					<p>Detected wp-admin on <code><?php echo esc_html( $request_domain ); ?></code> while the current public domain is still <code><?php echo esc_html( self::current_site_domain() ); ?></code>. The guided cutover box below is prefilled with the detected host.</p>
				</div>
			<?php endif; ?>

			<?php if ( $checklist['stored_domain'] !== '' && $checklist['stored_domain'] !== $checklist['domain'] ) : ?>
				<div class="notice notice-info inline">
					<p>Checklist state was reset because the public domain changed from <code><?php echo esc_html( $checklist['stored_domain'] ); ?></code> to <code><?php echo esc_html( $checklist['domain'] ); ?></code>.</p>
				</div>
			<?php endif; ?>

			<?php if ( is_array( $guided_notice ) ) : ?>
				<?php
				$notice_class = match ( $guided_notice['status'] ?? 'info' ) {
					'success' => 'notice notice-success inline',
					'warning' => 'notice notice-warning inline',
					'error'   => 'notice notice-error inline',
					default   => 'notice notice-info inline',
				};
				$notice_report = is_array( $guided_notice['report'] ?? null ) ? $guided_notice['report'] : [];
				?>
				<div class="<?php echo esc_attr( $notice_class ); ?>">
					<p><strong><?php echo esc_html( (string) ( $guided_notice['title'] ?? 'Guided cutover result' ) ); ?></strong></p>
					<?php if ( ! empty( $notice_report['checks'] ) ) : ?>
						<ul style="margin:8px 0 0 18px;list-style:disc">
							<?php foreach ( $notice_report['checks'] as $check ) : ?>
								<li>
									<strong><?php echo esc_html( (string) ( $check['label'] ?? 'Check' ) ); ?>:</strong>
									<?php echo esc_html( (string) ( $check['detail'] ?? '' ) ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $notice_report['post_checks'] ) ) : ?>
						<ul style="margin:8px 0 0 18px;list-style:disc">
							<?php foreach ( $notice_report['post_checks'] as $check ) : ?>
								<li>
									<strong><?php echo esc_html( (string) ( $check['label'] ?? 'Post-check' ) ); ?>:</strong>
									<?php echo esc_html( (string) ( $check['detail'] ?? '' ) ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $notice_report['errors'] ) ) : ?>
						<ul style="margin:8px 0 0 18px;list-style:disc">
							<?php foreach ( $notice_report['errors'] as $message ) : ?>
								<li><?php echo esc_html( (string) $message ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $notice_report['warnings'] ) ) : ?>
						<ul style="margin:8px 0 0 18px;list-style:disc">
							<?php foreach ( $notice_report['warnings'] as $message ) : ?>
								<li><?php echo esc_html( (string) $message ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $notice_report['post_errors'] ) ) : ?>
						<ul style="margin:8px 0 0 18px;list-style:disc">
							<?php foreach ( $notice_report['post_errors'] as $message ) : ?>
								<li><?php echo esc_html( (string) $message ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $errors as $message ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endforeach; ?>

			<?php foreach ( $warnings as $message ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endforeach; ?>

			<div class="wchs-section">
				<div class="wchs-section__head">
					<h2 style="margin:0">Guided cutover</h2>
					<?php echo self::hint_icon( 'This is the safer WordPress-like cutover path for same-origin sites. It updates home/siteurl, refreshes a writable robots.txt sitemap line if one exists, flushes caches, and redirects wp-admin to the new domain. It does not run a DB-wide search-replace.' ); ?>
				</div>
				<div class="wchs-field">
					<label>Final production domain</label>
					<input type="text" name="cutover_candidate_domain" value="<?php echo esc_attr( $candidate_domain ); ?>" placeholder="example.com" />
					<p class="description">Enter only the domain. HTTPS is assumed. Keep using the CLI cutover script for older sites that need a full DB search-replace.</p>
				</div>
				<div class="wchs-field">
					<div style="display:flex;gap:12px;flex-wrap:wrap">
						<button type="submit" name="cutover_action" value="preview" class="wchs-btn wchs-btn--secondary">Preview guided cutover</button>
						<button type="submit" name="cutover_action" value="finalize" class="wchs-btn wchs-btn--primary" onclick="return window.confirm('Finalize the domain cutover now? This will update WordPress core URLs and redirect wp-admin to the new domain.');">Finalize cutover</button>
					</div>
				</div>
				<?php if ( '' !== $last_cutover_at && '' !== $last_cutover_to ) : ?>
					<p class="description">Last guided cutover: <code><?php echo esc_html( $last_cutover_from ?: '(unknown)' ); ?></code> → <code><?php echo esc_html( $last_cutover_to ); ?></code> at <?php echo esc_html( $last_cutover_at ); ?>.</p>
				<?php endif; ?>
			</div>

			<div class="wchs-section">
				<div class="wchs-section__head">
					<h2 style="margin:0">Live runtime</h2>
					<?php echo self::hint_icon( 'These are the effective values WCHS is using right now, not just what might still be sitting in wp-config.php.' ); ?>
				</div>
				<div class="wchs-cutover-grid">
					<div class="wchs-cutover-card">
						<span class="wchs-cutover-card__label">Origin mode</span>
						<strong><?php echo esc_html( $mode_label ); ?></strong>
						<p>Source: <?php echo esc_html( $mode_source ); ?></p>
					</div>
					<div class="wchs-cutover-card">
						<span class="wchs-cutover-card__label">Public site origin</span>
						<strong><?php echo esc_html( $public_origin ); ?></strong>
						<p>WordPress should own this for normal cutovers.</p>
					</div>
					<div class="wchs-cutover-card">
						<span class="wchs-cutover-card__label">Effective SPA origin</span>
						<strong><?php echo esc_html( $spa_origin ); ?></strong>
						<p>Checkout and login redirects land here.</p>
					</div>
					<div class="wchs-cutover-card">
						<span class="wchs-cutover-card__label">Allowed origins</span>
						<strong><?php echo esc_html( implode( ', ', $allowed_origins ) ); ?></strong>
						<p>Credentialed Store API requests must originate from one of these.</p>
					</div>
					<div class="wchs-cutover-card">
						<span class="wchs-cutover-card__label">Return origins</span>
						<strong><?php echo esc_html( implode( ', ', $return_origins ) ); ?></strong>
						<p>Login and account return URLs are restricted to these origins.</p>
					</div>
					<div class="wchs-cutover-card">
						<span class="wchs-cutover-card__label">Core URLs</span>
						<strong><?php echo esc_html( $home ); ?></strong>
						<p><code>siteurl</code>: <?php echo esc_html( $siteurl ); ?></p>
					</div>
				</div>
				<?php if ( ! empty( $legacy['spa_origin'] ) || ! empty( $legacy['allowed_origins'] ) || ! empty( $legacy['return_origins'] ) ) : ?>
					<div class="wchs-cutover-legacy">
						<strong>Legacy wp-config overrides</strong>
						<p><code>WCHS_SPA_URL</code>: <?php echo esc_html( $legacy['spa_origin'] ?: '(not set)' ); ?></p>
						<p><code>WCHS_ALLOWED_ORIGINS</code>: <?php echo esc_html( ! empty( $legacy['allowed_origins'] ) ? implode( ', ', $legacy['allowed_origins'] ) : '(not set)' ); ?></p>
						<p><code>WCHS_RETURN_ORIGINS</code>: <?php echo esc_html( ! empty( $legacy['return_origins'] ) ? implode( ', ', $legacy['return_origins'] ) : '(not set)' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<div class="wchs-section">
				<div class="wchs-section__head">
					<h2 style="margin:0">Origin ownership</h2>
					<?php echo self::hint_icon( 'Same-origin is the safe default. Custom mode only exists for split-origin staging or unusual deployments where the SPA lives on a different host.' ); ?>
				</div>
				<div class="wchs-field">
					<div class="wchs-radios">
						<label class="wchs-radio">
							<input type="radio" name="domain_origin_mode" value="same-origin" <?php checked( $mode, 'same-origin' ); ?> />
							<span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span>
							<span>Same-origin. WCHS follows <code>home_url()</code> automatically when the site domain changes.</span>
						</label>
						<label class="wchs-radio">
							<input type="radio" name="domain_origin_mode" value="custom" <?php checked( $mode, 'custom' ); ?> />
							<span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span>
							<span>Custom. Use explicit origins for split-host or staging flows.</span>
						</label>
					</div>
				</div>

				<div class="wchs-field">
					<label>Custom SPA origin</label>
					<input type="url" name="custom_spa_origin" value="<?php echo esc_attr( $custom_spa_origin ); ?>" placeholder="https://shop.example.com" />
					<p class="description">Only used when Custom mode is selected. Leave blank in normal same-domain deployments.</p>
				</div>
				<div class="wchs-field">
					<label>Custom allowed origins <?php echo self::hint_icon( 'One origin per line. These are the origins allowed to make credentialed Store API requests.' ); ?></label>
					<textarea name="custom_allowed_origins" rows="4" placeholder="https://shop.example.com&#10;https://staging.example.com"><?php echo esc_textarea( $custom_allowed ); ?></textarea>
				</div>
				<div class="wchs-field">
					<label>Custom return origins <?php echo self::hint_icon( 'One origin per line. Login/account redirects are limited to these origins.' ); ?></label>
					<textarea name="custom_return_origins" rows="4" placeholder="https://shop.example.com&#10;https://staging.example.com"><?php echo esc_textarea( $custom_return ); ?></textarea>
				</div>
			</div>

			<div class="wchs-section">
				<div class="wchs-section__head">
					<h2 style="margin:0">Cutover copy targets</h2>
					<?php echo self::hint_icon( 'These are the exact values merchants usually need to paste into Stripe, Omnisend, Search Console, and internal runbooks after the domain changes.' ); ?>
				</div>
				<?php self::render_cutover_copy_row( 'Public site URL', $site_url, 'Use this as the canonical store URL in vendor dashboards.' ); ?>
				<?php self::render_cutover_copy_row( 'SPA account URL', $account_url, 'Useful for login-return testing.' ); ?>
				<?php self::render_cutover_copy_row( 'Stripe webhook endpoint', $stripe_webhook, 'Create or update this in Stripe after cutover.' ); ?>
				<?php self::render_cutover_copy_row( 'Current robots sitemap', $robots_sitemap, 'What the live site currently advertises to crawlers.' ); ?>
				<?php self::render_cutover_copy_row( 'WP core sitemap', $wp_sitemap, 'WordPress-generated sitemap.' ); ?>
				<?php self::render_cutover_copy_row( 'Headless sitemap', $spa_sitemap, 'WCHS-generated sitemap for headless routes and WCHS pages.' ); ?>
			</div>

			<div class="wchs-section">
				<div class="wchs-section__head">
					<h2 style="margin:0">Post-cutover checklist</h2>
					<?php echo self::hint_icon( 'Checklist state is tracked per public domain. When the site host changes, this list automatically resets.' ); ?>
				</div>
				<?php foreach ( $tasks as $key => $task ) : ?>
					<div class="wchs-field">
						<label class="wchs-check wchs-cutover-check">
							<input type="checkbox" name="cutover_checklist_items[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $checklist['items'][ $key ] ?? false ); ?> />
							<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
							<span>
								<strong><?php echo esc_html( $task['label'] ); ?></strong>
								<small><?php echo esc_html( $task['help'] ); ?></small>
							</span>
						</label>
					</div>
				<?php endforeach; ?>
				<?php if ( $checklist['updated_at'] !== '' ) : ?>
					<p class="description">Last saved: <?php echo esc_html( $checklist['updated_at'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="wchs-section">
				<div class="wchs-section__head">
					<h2 style="margin:0">External services</h2>
					<?php echo self::hint_icon( 'WCHS can make its own URLs follow the site domain, but Stripe, Omnisend, GA4, GTM, and Search Console still need human verification because they cache or own their own endpoints.' ); ?>
				</div>
				<div class="wchs-cutover-guide">
					<p><strong>Stripe:</strong> update the webhook endpoint to the exact URL above, then confirm WooCommerce has the current <code>whsec_*</code> secret.</p>
					<p><strong>Omnisend:</strong> confirm the store URL in Omnisend matches the live public domain and wait a few minutes for it to refresh.</p>
					<p><strong>GA4:</strong> keep the same property, but review the web data stream, Realtime, and unwanted referrals after the domain swap.</p>
					<p><strong>GTM:</strong> the container usually stays the same. What tends to break is hardcoded hostnames inside tags, triggers, or custom HTML snippets.</p>
					<p><strong>Search Console:</strong> verify the new property and submit the sitemap you want Google to crawl.</p>
				</div>
			</div>

			<div style="margin-top:24px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>
		<?php
	}

	// ─── Access & Privacy Tab ───────────────────────────────────
	private function render_access_tab( array $settings ): void {
		$mode = (int) ( $settings['access_mode'] ?? 3 );
		$gate = wp_parse_args( $settings['gate_modal'] ?? [], [
			'enabled'      => false,
			'strict'       => false,
			'title'        => '',
			'content'      => '',
			'confirm_text' => 'Enter Site',
			'decline_text' => '',
			'decline_url'  => '',
			'version'      => 1,
		] );
		?>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="security" />

			<div class="wchs-info">
				Store name, logo, and currency are managed in
				<a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">Settings → General</a> and
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings' ) ); ?>">WooCommerce → Settings</a>.
			</div>

			<h2>Access Mode</h2>
			<div class="wchs-field">
				<div class="wchs-radios">
					<label class="wchs-radio <?php echo 0 === $mode ? 'wchs-radio--danger' : ''; ?>"><input type="radio" name="access_mode" value="0" <?php checked( $mode, 0 ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Maintenance — site is completely offline except for admins</span></label>
					<label class="wchs-radio"><input type="radio" name="access_mode" value="3" <?php checked( $mode, 3 ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Open — anyone can browse and checkout</span></label>
					<label class="wchs-radio"><input type="radio" name="access_mode" value="2" <?php checked( $mode, 2 ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Browse-only — guests can browse but must sign in to checkout</span></label>
					<label class="wchs-radio"><input type="radio" name="access_mode" value="1" <?php checked( $mode, 1 ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Locked — only registered members can access the store</span></label>
				</div>
			</div>

			<div class="wchs-section__head">
				<h2 style="margin:0">Anti-Bot Protection</h2>
				<?php echo self::hint_icon( 'Cloudflare Turnstile protects checkout, login, registration, and contact forms. Get keys from your Cloudflare dashboard → Turnstile.' ); ?>
			</div>
			<?php
			$ab_enabled = $settings['anti_bot_enabled'] ?? false;
			$ab_site    = $settings['turnstile_site_key'] ?? '';
			$ab_secret  = $settings['turnstile_secret_key'] ?? '';
			?>
			<div class="wchs-grid" style="max-width:600px">
				<div class="wchs-field">
					<label class="wchs-toggle">
						<input type="checkbox" name="anti_bot_enabled" value="1" <?php checked( $ab_enabled ); ?> />
						<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
						<span>Enable Cloudflare Turnstile</span>
					</label>
				</div>
				<?php $this->render_masked_api_key( 'turnstile_site_key', 'Site Key', $ab_site ); ?>
				<?php $this->render_masked_api_key( 'turnstile_secret_key', 'Secret Key', $ab_secret, '', true ); ?>
			</div>

			<h2>SEO Hardening <?php echo self::hint_icon('Prevents Google from pulling product descriptions into search snippets. Recommended for regulated product categories.'); ?></h2>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="seo_nosnippet_products" value="1" <?php checked( $settings['seo_nosnippet_products'] ?? false ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Add nosnippet + noimageindex meta tag to product pages</span>
				</label>
			</div>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="seo_block_cart_checkout" value="1" <?php checked( $settings['seo_block_cart_checkout'] ?? false ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Block /cart/ and /checkout/ in robots.txt <?php echo self::hint_icon('Prevents search engine crawlers from indexing cart and checkout pages. Recommended for all stores.'); ?></span>
				</label>
			</div>

			<h2>Site Gate <?php echo self::hint_icon('A configurable modal shown to first-time visitors. Useful for age verification, RUO disclaimers, or terms acceptance.'); ?></h2>

			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="gate_modal_enabled" value="1" <?php checked( $gate['enabled'] ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Enable site gate modal</span>
				</label>
			</div>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="gate_modal_strict" value="1" <?php checked( $gate['strict'] ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Strict mode — no close button, no click-outside dismiss <?php echo self::hint_icon('Enable for age verification or mandatory disclaimers where the user must explicitly confirm.'); ?></span>
				</label>
			</div>
			<div class="wchs-field">
				<label>Title</label>
				<input type="text" name="gate_modal_title" value="<?php echo esc_attr( $gate['title'] ); ?>" class="regular-text" placeholder="e.g. Age Verification Required" />
			</div>
			<div class="wchs-field">
				<label>Content <?php echo self::hint_icon('Supports basic HTML: &lt;b&gt;, &lt;i&gt;, &lt;a&gt;, &lt;p&gt;, &lt;br&gt;.'); ?></label>
				<?php wp_editor( $gate['content'], 'gate_modal_content', [
				'textarea_name' => 'gate_modal_content',
				'textarea_rows' => 5,
				'media_buttons' => false,
				'teeny'         => true,
				'quicktags'     => true,
				'tinymce'       => false, // quicktags only — avoids hidden-tab TinyMCE init bug
			] ); ?>
			</div>
			<div class="wchs-field">
				<label>Confirm Button Text</label>
				<input type="text" name="gate_modal_confirm_text" value="<?php echo esc_attr( $gate['confirm_text'] ); ?>" class="regular-text" placeholder="Enter Site" />
			</div>
			<div class="wchs-field">
				<label>Decline Button Text <?php echo self::hint_icon('Leave empty to hide the decline button.'); ?></label>
				<input type="text" name="gate_modal_decline_text" value="<?php echo esc_attr( $gate['decline_text'] ); ?>" class="regular-text" placeholder="Leave" />
			</div>
			<div class="wchs-field">
				<label>Decline URL <?php echo self::hint_icon('Where to redirect when the user declines.'); ?></label>
				<input type="url" name="gate_modal_decline_url" value="<?php echo esc_attr( $gate['decline_url'] ); ?>" class="regular-text" placeholder="https://google.com" />
			</div>
			<div class="wchs-field">
				<label>Content Version <?php echo self::hint_icon('Increment to re-show the gate to users who previously accepted.'); ?></label>
				<input type="number" name="gate_modal_version" value="<?php echo (int) $gate['version']; ?>" min="1" style="width:80px" />
			</div>

			<h2>Registration Requirements <?php echo self::hint_icon('Extend the WooCommerce registration form with additional required fields and verification steps. All optional.'); ?></h2>

			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="reg_require_email_verify" value="1" <?php checked( $settings['reg_require_email_verify'] ?? false ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Require email verification before purchasing <?php echo self::hint_icon('Customers can register and browse, but are treated as guests for access mode purposes until they click the verification link in their email. Existing customers are grandfathered.'); ?></span>
				</label>
			</div>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="reg_require_address" value="1" <?php checked( $settings['reg_require_address'] ?? false ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Require shipping address at registration <?php echo self::hint_icon('Address is validated via EasyPost if an API key is configured in the Checkout tab. Saves to customer profile so checkout pre-fills.'); ?></span>
				</label>
			</div>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="reg_require_name" value="1" <?php checked( $settings['reg_require_name'] ?? false ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Require first and last name</span>
				</label>
			</div>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="reg_require_phone" value="1" <?php checked( $settings['reg_require_phone'] ?? false ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Require phone number</span>
				</label>
			</div>

			<div style="margin-top:24px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>
		<?php
	}


	private function render_homepage_tab( array $hero, array $modules ): void {
		$categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		$hero_content_mode = $hero['content_mode'] ?? 'text';
		$hero_logo_source  = $hero['logo_source'] ?? 'site_logo';
		$hero_logo_size    = $hero['logo_size'] ?? 'large';
		$hero_logo_url     = $hero['logo_url'] ?? '';
		$hero_logo_dark    = $hero['logo_dark_url'] ?? '';
		?>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="homepage" />

			<?php
			$hero_layout    = $hero['layout'] ?? 'left';
			$show_eyebrow   = $hero['show_eyebrow'] ?? true;
			$show_rating    = $hero['show_rating'] ?? false;
			$rating_text    = $hero['rating_text'] ?? '';
			$trust_items    = $hero['trust_items'] ?? [];
			$trust_icon_opts = [ 'check', 'shield', 'star', 'shipping', 'lock', 'lab', 'heart', 'leaf', 'zap', 'award' ];
			?>

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Layout</h2>
			<div class="wchs-section__body">
			<div class="wchs-field">
				<label>Text Layout</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="hero_layout" value="left" <?php checked( $hero_layout, 'left' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Left-aligned (default)</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_layout" value="center" <?php checked( $hero_layout, 'center' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Centered</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_layout" value="bottom" <?php checked( $hero_layout, 'bottom' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Bottom-anchored</span></label>
				</div>
			</div>
			</div></div>

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Content</h2>
			<div class="wchs-section__body">
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="hero_show_eyebrow" value="1" <?php checked( $show_eyebrow ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Brand eyebrow</span>
				</label>
			</div>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="hero_show_rating" value="1" <?php checked( $show_rating ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Rating badge</span>
				</label>
			</div>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="hero_cta_accent" value="1" <?php checked( $hero['cta_accent'] ?? true ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Accent CTA</span>
				</label>
			</div>
			<div class="wchs-field">
				<label>Rating Text <?php echo self::hint_icon('Shown above the headline when review aggregate is enabled.'); ?></label>
				<input type="text" name="hero_rating_text" value="<?php echo esc_attr( $rating_text ); ?>" placeholder="4.8 star — Based on 127 reviews" />
			</div>
			<div class="wchs-field">
				<label>Hero body</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="hero_content_mode" value="text" <?php checked( $hero_content_mode, 'text' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Text headline</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_content_mode" value="logo" <?php checked( $hero_content_mode, 'logo' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Logo mark</span> <?php echo self::hint_icon('Logo mode replaces the large visible headline with a brand image. The Headline field below is still kept as the semantic H1 for SEO and accessibility.'); ?></label>
				</div>
			</div>
			<div class="wchs-field">
				<label>Logo source <?php echo self::hint_icon('Use site logo pulls the full-size header logo asset from the Design tab. Custom logo lets the hero use a separate upload, which is usually better for a large body logo.'); ?></label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="hero_logo_source" value="site_logo" <?php checked( $hero_logo_source, 'site_logo' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Use site logo</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_logo_source" value="custom" <?php checked( $hero_logo_source, 'custom' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Use separate hero logo</span></label>
				</div>
			</div>
			<div class="wchs-field">
				<label>Hero logo (custom source)</label>
				<div class="wchs-media-field">
					<input type="text" name="hero_logo_url" value="<?php echo esc_attr( $hero_logo_url ); ?>" class="wchs-media-url" placeholder="No image selected" />
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="<?php echo empty( $hero_logo_url ) ? 'display:none' : ''; ?>">Remove</button>
				</div>
				<?php if ( ! empty( $hero_logo_url ) ) : ?>
					<img class="wchs-media-preview" src="<?php echo esc_url( $hero_logo_url ); ?>" alt="" />
				<?php else : ?>
					<img class="wchs-media-preview" src="" alt="" style="display:none" />
				<?php endif; ?>
			</div>
			<div class="wchs-field">
				<label>Hero logo (dark mode, optional) <?php echo self::hint_icon('Only used when the hero is in Logo mode and Logo source is Custom. Leave blank to keep using the same image in both themes.'); ?></label>
				<div class="wchs-media-field">
					<input type="text" name="hero_logo_dark_url" value="<?php echo esc_attr( $hero_logo_dark ); ?>" class="wchs-media-url" placeholder="No image selected" />
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="<?php echo empty( $hero_logo_dark ) ? 'display:none' : ''; ?>">Remove</button>
				</div>
				<?php if ( ! empty( $hero_logo_dark ) ) : ?>
					<img class="wchs-media-preview" src="<?php echo esc_url( $hero_logo_dark ); ?>" alt="" />
				<?php else : ?>
					<img class="wchs-media-preview" src="" alt="" style="display:none" />
				<?php endif; ?>
			</div>
			<div class="wchs-field">
				<label>Hero logo size</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="hero_logo_size" value="standard" <?php checked( $hero_logo_size, 'standard' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Standard</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_logo_size" value="large" <?php checked( $hero_logo_size, 'large' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Large</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_logo_size" value="statement" <?php checked( $hero_logo_size, 'statement' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Statement</span></label>
				</div>
			</div>
			<div class="wchs-field">
				<label>Headline <?php echo self::hint_icon('Visible in Text mode. In Logo mode this is still kept as the page H1 for SEO and accessibility.'); ?></label>
				<input type="text" name="hero_headline" value="<?php echo esc_attr( $hero['headline'] ); ?>" maxlength="200" />
			</div>
			</div></div><!-- /Hero Content -->

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Typography</h2>
			<div class="wchs-section__body">
			<div class="wchs-field" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
				<div>
					<label>Headline size</label>
					<select name="hero_headline_size">
						<?php $hsize = $hero['headline_size'] ?? 'l'; ?>
						<option value="s"  <?php selected( $hsize, 's' ); ?>>Small (fits ~12 words)</option>
						<option value="m"  <?php selected( $hsize, 'm' ); ?>>Medium (fits ~8 words)</option>
						<option value="l"  <?php selected( $hsize, 'l' ); ?>>Large — default (fits ~5 words)</option>
						<option value="xl" <?php selected( $hsize, 'xl' ); ?>>Extra large (fits ~3 words)</option>
					</select>
				</div>
				<div>
					<label>Headline weight</label>
					<select name="hero_headline_weight">
						<?php $hweight = $hero['headline_weight'] ?? 'medium'; ?>
						<option value="light"     <?php selected( $hweight, 'light' ); ?>>Light (300)</option>
						<option value="regular"   <?php selected( $hweight, 'regular' ); ?>>Regular (400)</option>
						<option value="medium"    <?php selected( $hweight, 'medium' ); ?>>Medium — default (500)</option>
						<option value="semibold"  <?php selected( $hweight, 'semibold' ); ?>>Semibold (600)</option>
						<option value="bold"      <?php selected( $hweight, 'bold' ); ?>>Bold (700)</option>
						<option value="extrabold" <?php selected( $hweight, 'extrabold' ); ?>>Extra Bold (800)</option>
						<option value="black"     <?php selected( $hweight, 'black' ); ?>>Black (900)</option>
					</select>
				</div>
			</div>
			<div class="wchs-field">
				<label>Headline font</label>
				<select name="hero_headline_font" style="max-width:320px;">
					<?php $hfont = $hero['headline_font'] ?? 'inter'; ?>
					<option value="inter"         <?php selected( $hfont, 'inter' ); ?>>Inter — default (clean, neutral sans)</option>
					<option value="barlow"        <?php selected( $hfont, 'barlow' ); ?>>Barlow Semi Condensed (bold, tight)</option>
					<option value="bebas"         <?php selected( $hfont, 'bebas' ); ?>>Bebas Neue (display, uppercase)</option>
					<option value="playfair"      <?php selected( $hfont, 'playfair' ); ?>>Playfair Display (elegant serif)</option>
					<option value="space_grotesk" <?php selected( $hfont, 'space_grotesk' ); ?>>Space Grotesk (modern geometric sans)</option>
					<option value="archivo"       <?php selected( $hfont, 'archivo' ); ?>>Archivo (grotesk, wide)</option>
					<option value="oswald"        <?php selected( $hfont, 'oswald' ); ?>>Oswald (condensed sans)</option>
				</select>
			</div>
			<div class="wchs-field">
				<label>Text color</label>
				<?php $tcm = $hero['text_color_mode'] ?? 'theme'; ?>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="hero_text_color_mode" value="theme"  <?php checked( $tcm, 'theme' );  ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Theme (light/dark toggle) — default</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_text_color_mode" value="white"  <?php checked( $tcm, 'white' );  ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Always white</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_text_color_mode" value="black"  <?php checked( $tcm, 'black' );  ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Always black</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_text_color_mode" value="accent" <?php checked( $tcm, 'accent' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Accent</span> <?php echo self::hint_icon('Theme follows the visitor\'s light/dark toggle (current behavior). Use white or black to lock the hero text against a photo that always has the opposite tone. Accent uses the site\'s accent color.'); ?></label>
				</div>
			</div>
			<div class="wchs-field">
				<label>Subheadline</label>
				<textarea name="hero_subheadline" maxlength="500"><?php echo esc_textarea( $hero['subheadline'] ); ?></textarea>
			</div>
			<div class="wchs-field">
				<label>Subheadline size</label>
				<select name="hero_subheadline_size" style="max-width:260px;">
					<?php $ssize = $hero['subheadline_size'] ?? 'm'; ?>
					<option value="s" <?php selected( $ssize, 's' ); ?>>Small</option>
					<option value="m" <?php selected( $ssize, 'm' ); ?>>Medium — default</option>
					<option value="l" <?php selected( $ssize, 'l' ); ?>>Large</option>
				</select>
			</div>
			<div class="wchs-field">
				<label class="wchs-toggle">
					<input type="checkbox" name="hero_show_cta" value="1" <?php checked( $hero['show_cta'] ?? true ); ?> />
					<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
					<span>Show CTA</span>
				</label>
			</div>
			<div class="wchs-field">
				<label>CTA Text</label>
				<input type="text" name="hero_cta_text" value="<?php echo esc_attr( $hero['cta_text'] ); ?>" maxlength="40" />
			</div>
			<div class="wchs-field">
				<label>CTA Link <?php echo self::hint_icon('URL path, e.g. /shop'); ?></label>
				<input type="text" name="hero_cta_link" value="<?php echo esc_attr( $hero['cta_link'] ); ?>" />
			</div>
			<div class="wchs-field">
				<label>Research badge <?php echo self::hint_icon( 'Pill above the headline when Animation is Research motion (CSS).' ); ?></label>
				<input type="text" name="hero_research_badge" value="<?php echo esc_attr( $hero['research_badge'] ?? '' ); ?>" maxlength="120" />
			</div>
			<div class="wchs-field">
				<label>Secondary CTA text</label>
				<input type="text" name="hero_cta_secondary_text" value="<?php echo esc_attr( $hero['cta_secondary_text'] ?? '' ); ?>" maxlength="80" />
			</div>
			<div class="wchs-field">
				<label>Secondary CTA link <?php echo self::hint_icon( 'Leave blank for Research motion to fall back to the PDP COA library URL when set.' ); ?></label>
				<input type="text" name="hero_cta_secondary_link" value="<?php echo esc_attr( $hero['cta_secondary_link'] ?? '' ); ?>" />
			</div>
			<div class="wchs-field">
				<label>Research stats (JSON) <?php echo self::hint_icon( 'JSON array of objects with value + label (three rows). Empty or invalid restores defaults.' ); ?></label>
				<textarea name="hero_research_stats_json" rows="6" class="large-text code"><?php echo esc_textarea( wp_json_encode( $hero['research_stats'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
			</div>

			</div></div><!-- /Hero Content -->

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Trust indicators <?php echo self::hint_icon('Small text pills below the CTA. Icon + short phrase, separated by dots.'); ?></h2>
			<div class="wchs-section__body">
			<div class="wchs-field">
				<div class="wchs-accordion-items" id="wchs-hero-trust-items">
					<?php foreach ( $trust_items as $j => $ti ) : ?>
						<div class="wchs-accordion-item">
							<select name="hero_trust_items[<?php echo $j; ?>][icon]" style="width:auto;min-width:80px">
								<?php foreach ( $trust_icon_opts as $ico ) : ?>
									<option value="<?php echo esc_attr( $ico ); ?>" <?php selected( $ti['icon'] ?? 'check', $ico ); ?>><?php echo esc_html( ucfirst( $ico ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="hero_trust_items[<?php echo $j; ?>][text]" value="<?php echo esc_attr( $ti['text'] ?? '' ); ?>" placeholder="e.g. Third-party tested" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="wchs-btn wchs-btn--secondary" id="wchs-add-hero-trust" style="margin-top:8px">+ Add Trust Item</button>
			</div>

			</div></div><!-- /Hero Trust -->

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Background &amp; media <?php echo self::hint_icon('Image and animation layer independently. Use one, the other, or both.'); ?></h2>
			<div class="wchs-section__body">
			<div class="wchs-field">
				<label>Image (desktop)</label>
				<div class="wchs-media-field">
					<input type="text" name="hero_image_desktop" value="<?php echo esc_attr( $hero['image_desktop'] ?? '' ); ?>" class="wchs-media-url" placeholder="No image selected" />
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="<?php echo empty( $hero['image_desktop'] ) ? 'display:none' : ''; ?>">Remove</button>
				</div>
				<?php if ( ! empty( $hero['image_desktop'] ) ) : ?>
					<img class="wchs-media-preview" src="<?php echo esc_url( $hero['image_desktop'] ); ?>" alt="" />
				<?php else : ?>
					<img class="wchs-media-preview" src="" alt="" style="display:none" />
				<?php endif; ?>
			</div>
			<div class="wchs-field">
				<label>Image (mobile) <?php echo self::hint_icon('Optional — falls back to desktop.'); ?></label>
				<div class="wchs-media-field">
					<input type="text" name="hero_image_mobile" value="<?php echo esc_attr( $hero['image_mobile'] ?? '' ); ?>" class="wchs-media-url" placeholder="No image selected" />
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="<?php echo empty( $hero['image_mobile'] ) ? 'display:none' : ''; ?>">Remove</button>
				</div>
				<?php if ( ! empty( $hero['image_mobile'] ) ) : ?>
					<img class="wchs-media-preview" src="<?php echo esc_url( $hero['image_mobile'] ); ?>" alt="" />
				<?php else : ?>
					<img class="wchs-media-preview" src="" alt="" style="display:none" />
				<?php endif; ?>
			</div>
			<?php
			$img_pos_x  = $hero['image_position_x'] ?? 50;
			$img_pos_y  = $hero['image_position_y'] ?? 50;
			$img_mpos_x = $hero['image_position_mobile_x'] ?? 50;
			$img_mpos_y = $hero['image_position_mobile_y'] ?? 80;
			$img_zoom   = $hero['image_zoom'] ?? 100;
			$img_mzoom  = $hero['image_zoom_mobile'] ?? 100;
			?>
			<div class="wchs-field">
				<label>Desktop — Horizontal (<span id="wchs-pos-x-val"><?php echo (int) $img_pos_x; ?></span>%)</label>
				<input type="range" name="hero_image_position_x" min="0" max="100" value="<?php echo (int) $img_pos_x; ?>" oninput="document.getElementById('wchs-pos-x-val').textContent=this.value" style="width:100%" />
				<div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:2px"><span>← Left</span><span>Center</span><span>Right →</span></div>
			</div>
			<div class="wchs-field">
				<label>Desktop — Vertical (<span id="wchs-pos-y-val"><?php echo (int) $img_pos_y; ?></span>%)</label>
				<input type="range" name="hero_image_position_y" min="0" max="100" value="<?php echo (int) $img_pos_y; ?>" oninput="document.getElementById('wchs-pos-y-val').textContent=this.value" style="width:100%" />
				<div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:2px"><span>↑ Top</span><span>Center</span><span>Bottom ↓</span></div>
			</div>
			<div class="wchs-field">
				<label>Desktop — Zoom (<span id="wchs-zoom-val"><?php echo (int) $img_zoom; ?></span>%)</label>
				<input type="range" name="hero_image_zoom" min="50" max="200" step="5" value="<?php echo (int) $img_zoom; ?>" oninput="document.getElementById('wchs-zoom-val').textContent=this.value" style="width:100%" />
				<div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:2px"><span>50% (shrink)</span><span>100% (fit)</span><span>200% (crop)</span></div>
			</div>
			<div class="wchs-field">
				<label>Mobile — Horizontal (<span id="wchs-mpos-x-val"><?php echo (int) $img_mpos_x; ?></span>%)</label>
				<input type="range" name="hero_image_position_mobile_x" min="0" max="100" value="<?php echo (int) $img_mpos_x; ?>" oninput="document.getElementById('wchs-mpos-x-val').textContent=this.value" style="width:100%" />
				<div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:2px"><span>← Left</span><span>Center</span><span>Right →</span></div>
			</div>
			<div class="wchs-field">
				<label>Mobile — Vertical (<span id="wchs-mpos-y-val"><?php echo (int) $img_mpos_y; ?></span>%)</label>
				<input type="range" name="hero_image_position_mobile_y" min="0" max="100" value="<?php echo (int) $img_mpos_y; ?>" oninput="document.getElementById('wchs-mpos-y-val').textContent=this.value" style="width:100%" />
				<div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:2px"><span>↑ Top</span><span>Center</span><span>Bottom ↓</span></div>
			</div>
			<div class="wchs-field">
				<label>Mobile — Zoom (<span id="wchs-mzoom-val"><?php echo (int) $img_mzoom; ?></span>%)</label>
				<input type="range" name="hero_image_zoom_mobile" min="50" max="200" step="5" value="<?php echo (int) $img_mzoom; ?>" oninput="document.getElementById('wchs-mzoom-val').textContent=this.value" style="width:100%" />
				<div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:2px"><span>50% (shrink)</span><span>100% (fit)</span><span>200% (crop)</span></div>
			</div>
			<div class="wchs-field">
				<label>Animation</label>
				<div class="wchs-radios">
					<label class="wchs-radio"><input type="radio" name="hero_variant" value="text-only" <?php checked( $hero['variant'], 'text-only' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>None</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_variant" value="webgl-noise" <?php checked( $hero['variant'], 'webgl-noise' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Smoke</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_variant" value="webgl-variant-2" <?php checked( $hero['variant'], 'webgl-variant-2' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Plasma</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_variant" value="webgl-variant-3" <?php checked( $hero['variant'], 'webgl-variant-3' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Voronoi</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_variant" value="webgl-variant-4" <?php checked( $hero['variant'], 'webgl-variant-4' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Hex Grid</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_variant" value="webgl-variant-5" <?php checked( $hero['variant'], 'webgl-variant-5' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Dot Matrix</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_variant" value="webgl-variant-6" <?php checked( $hero['variant'], 'webgl-variant-6' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Bokeh</span></label>
					<label class="wchs-radio"><input type="radio" name="hero_variant" value="research-motion" <?php checked( $hero['variant'], 'research-motion' ); ?> /><span class="wchs-radio__circle"><span class="wchs-radio__dot"></span></span><span>Research motion</span></label>
				</div>
			</div>

			</div></div><!-- /Hero Background -->

			<h2>Modules Below Hero</h2>
			<div class="wchs-modlist" data-context="homepage">
				<input type="hidden" name="modules_json" value="<?php echo esc_attr( wp_json_encode( $modules ) ); ?>" />
				<div class="wchs-modlist__items"></div>
				<div class="wchs-modlist__add">
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-modlist__add-btn">Add</button>
				</div>
			</div>

			<div style="margin-top:24px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>
		<?php
	}

	private function render_shop_tab( array $shop_cfg ): void {
		$modules      = $shop_cfg['modules'] ?? [];
		$cols_min     = (int) ( $shop_cfg['cols_min'] ?? 2 );
		$cols_max     = (int) ( $shop_cfg['cols_max'] ?? 4 );
		$spacing_h = $shop_cfg['spacing_h'] ?? 'normal';
		?>
		<div class="wchs-section__head" style="margin-bottom:12px">
			<h2 style="margin:0">Shop page</h2>
			<?php echo self::hint_icon( 'Content modules appear on the shop page below the product grid. The grid with search, sort, and pagination is always shown first.' ); ?>
		</div>

		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="shop" />

			<div class="wchs-section wchs-section--collapsed">
			<h2 class="wchs-section__toggle">Grid Layout <?php echo self::hint_icon('Cards auto-fit between the min and max as the viewport scales. Orphan rows center automatically.'); ?></h2>
			<div class="wchs-section__body">
			<div style="display:flex;gap:16px;flex-wrap:wrap">
				<div class="wchs-field" style="flex:0 0 auto">
					<label>Min products per row</label>
					<input type="number" name="shop_cols_min" value="<?php echo (int) $cols_min; ?>" min="1" max="8" style="width:80px" />
				</div>
				<div class="wchs-field" style="flex:0 0 auto">
					<label>Max products per row</label>
					<input type="number" name="shop_cols_max" value="<?php echo (int) $cols_max; ?>" min="1" max="8" style="width:80px" />
				</div>
			</div>
			<div class="wchs-field" style="flex:0 0 auto">
				<label>Grid width</label>
				<select name="shop_spacing_h">
					<option value="compact" <?php selected( $spacing_h, 'compact' ); ?>>Full width</option>
					<option value="normal" <?php selected( $spacing_h, 'normal' ); ?>>Contained</option>
					<option value="spacious" <?php selected( $spacing_h, 'spacious' ); ?>>Narrow</option>
				</select>
			</div>
			</div></div><!-- /Grid Layout -->

			<h2>Shop Page Modules</h2>
			<div class="wchs-modlist" data-context="shop">
				<input type="hidden" name="modules_json" value="<?php echo esc_attr( wp_json_encode( $modules ) ); ?>" />
				<div class="wchs-modlist__items"></div>
				<div class="wchs-modlist__add">
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-modlist__add-btn">Add</button>
				</div>
			</div>

			<div style="margin-top:24px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>

		<?php
	}

	private function render_pages_tab( array $pages ): void {
		$categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		if ( is_wp_error( $categories ) ) {
			$categories = [];
		}
		?>
		<?php if ( count( $pages ) > 1 ) : ?>
		<div class="wchs-page-index">
			<select class="wchs-page-selector" id="wchs-page-selector">
				<option value="">Jump to page…</option>
				<?php foreach ( $pages as $pi => $pg ) : ?>
					<option value="<?php echo esc_attr( $pg['slug'] ?? '' ); ?>" data-index="<?php echo (int) $pi; ?>">
						<?php echo esc_html( $pg['title'] ?: $pg['slug'] ?: 'Untitled' ); ?>
						<?php if ( ! empty( $pg['slug'] ) ) : ?> — /<?php echo esc_html( $pg['slug'] ); ?><?php endif; ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="pages" />

			<div id="wchs-pages-list">
				<?php foreach ( $pages as $pi => $pg ) : ?>
					<?php $this->render_page_card( $pi, $pg, $categories ); ?>
				<?php endforeach; ?>
			</div>

			<div style="margin-top:16px">
				<button type="button" class="wchs-btn wchs-btn--secondary" id="wchs-add-page">Add</button>
			</div>

			<div style="margin-top:24px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>

		<template id="wchs-page-template">
			<?php $this->render_page_card( '__PIDX__', [ 'slug' => '', 'title' => '', 'modules' => [] ], $categories ); ?>
		</template>
		<?php
	}

	private function render_page_card( $pidx, array $pg, $categories ): void {
		$title   = $pg['title'] ?? '';
		$slug    = $pg['slug'] ?? '';
		$modules = $pg['modules'] ?? [];
		?>
		<div class="wchs-page-card" data-slug="<?php echo esc_attr( $slug ); ?>">
			<div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:16px">
				<div class="wchs-field" style="flex:1;margin-bottom:0">
					<label>Page Title</label>
					<input type="text" name="pages[<?php echo esc_attr( $pidx ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" placeholder="e.g. Terms of Service" />
				</div>
				<div class="wchs-field" style="flex:1;margin-bottom:0">
					<label>Slug (URL path)</label>
					<input type="text" name="pages[<?php echo esc_attr( $pidx ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" placeholder="e.g. terms-of-service" />
				</div>
				<button type="button" class="wchs-icon-btn wchs-icon-btn--danger wchs-remove-page" title="Remove page"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
			</div>

			<div class="wchs-modlist" data-context="pages">
				<input type="hidden" name="pages[<?php echo esc_attr( $pidx ); ?>][modules_json]" value="<?php echo esc_attr( wp_json_encode( $modules ) ); ?>" />
				<div class="wchs-modlist__items"></div>
				<div class="wchs-modlist__add">
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-modlist__add-btn">Add</button>
				</div>
			</div>
		</div>
		<?php
	}

	// ─── Site Scripts Tab (shop_manager visible) ───────────────
	/**
	 * Per-site activation of curated script registry entries. Shop_managers
	 * see this tab and can toggle entries on/off + fill per-site params.
	 * They cannot add new entries or change the script src — that lives in
	 * the admin-only Script Registry tab.
	 */


	private function render_pdp_tab( array $config ): void {
		$modules      = $config['modules'] ?? [];
		$show_reviews = $config['show_reviews'] ?? true;
		$slide_cart   = is_array( $config['slide_cart'] ?? null ) ? $config['slide_cart'] : [];
		$exclude_ids  = array_values(
			array_unique(
				array_filter( array_map( 'intval', (array) ( $slide_cart['cross_sell_exclude_product_ids'] ?? [] ) ) )
			)
		);
		?>
<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wchs_save_settings', 'wchs_nonce' ); ?>
			<input type="hidden" name="action" value="wchs_save_settings" />
			<input type="hidden" name="wchs_tab" value="pdp" />

			<h2>Features</h2>
			<div class="wchs-grid" style="max-width:600px">
				<div class="wchs-field">
					<label class="wchs-toggle">
						<input type="checkbox" name="pdp_show_reviews" value="1" <?php checked( $show_reviews ); ?> />
						<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
						<span>Reviews</span>
					</label>
				</div>
				<div class="wchs-field">
					<label>Cross-sell display mode</label>
					<?php $xsell_mode = $config['cross_sell_mode'] ?? 'simple'; ?>
					<select name="cross_sell_mode" style="width:auto">
						<option value="simple" <?php selected( $xsell_mode, 'simple' ); ?>>Simple - add button only, modal for variable products</option>
						<option value="complex" <?php selected( $xsell_mode, 'complex' ); ?>>Complex - inline attribute stepper + quantity on card</option>
					</select>
				</div>
			</div>

			<h2>Slide cart <?php echo self::hint_icon( 'Controls the slide-out cart drawer. Cross-sell exclusions apply to the “You might also like” recommendations only.' ); ?></h2>
			<div class="wchs-field wchs-field--full" style="max-width:640px">
				<label>Never recommend in “You might also like” <?php echo self::hint_icon( 'bac-water-10ml and shipping-protection are excluded by default. Add more products here if needed.' ); ?></label>
				<div class="wchs-product-picker" data-field="slide_cart_cross_sell_exclude">
					<input type="text" class="wchs-product-search" placeholder="Search products to exclude…" autocomplete="off" />
					<div class="wchs-product-results"></div>
					<input
						type="hidden"
						name="slide_cart_cross_sell_exclude_ids"
						value="<?php echo esc_attr( implode( ',', $exclude_ids ) ); ?>"
						class="wchs-product-ids-hidden"
					/>
					<ul class="wchs-product-tags">
						<?php
						foreach ( $exclude_ids as $pid ) :
							$p = wc_get_product( $pid );
							if ( ! $p ) {
								continue;
							}
							?>
						<li class="wchs-product-tag" data-id="<?php echo esc_attr( (string) $pid ); ?>">
							<?php echo esc_html( $p->get_name() ); ?>
							<button type="button" class="wchs-product-tag__remove" aria-label="Remove">×</button>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<h2>PDP Modules</h2>
			<div class="wchs-modlist" data-context="pdp">
				<input type="hidden" name="modules_json" value="<?php echo esc_attr( wp_json_encode( $modules ) ); ?>" />
				<div class="wchs-modlist__items"></div>
				<div class="wchs-modlist__add">
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-modlist__add-btn">Add</button>
				</div>
			</div>

			<div style="margin-top:24px">
				<button type="submit" class="wchs-btn wchs-btn--primary">Save</button>
			</div>
		</form>
		<?php
	}

	// NOTE: The old `render_module_card()` method (379 lines of per-module
	// form-field HTML) was deleted in the 2026-04-14 refactor cleanup. It
	// was replaced by:
	//   - assets/admin.js ModuleManager (renders cards client-side from JSON)
	//   - render_module_template_bank() below (HTML templates ModuleManager clones)
	//   - modules_json hidden input on each tab's form (single round-trip)
	// If you need to reintroduce server-side rendering for a specific case,
	// extract from git history; don't re-add this function wholesale.

	// ─── Legacy offline payment methods renderer ────────────────

	private function save_offline_gateways(): void {
		$gateways    = [];
		$raw         = $_POST['gateways'] ?? [];
		if ( is_array( $raw ) ) {
			foreach ( $raw as $g ) {
				if ( ! is_array( $g ) || empty( $g['id'] ) ) {
					continue;
				}
				$link_tpl = wp_unslash( $g['link_template'] ?? '' );
				if ( str_starts_with( $link_tpl, 'bitcoin:' ) ) {
					$link_tpl = sanitize_text_field( $link_tpl );
				} else {
					$link_tpl = esc_url_raw( $link_tpl );
				}
				$fee_type  = sanitize_text_field( $g['fee_type'] ?? 'none' );
				if ( ! in_array( $fee_type, [ 'none', 'flat_add', 'flat_sub', 'pct_add', 'pct_sub' ], true ) ) {
					$fee_type = 'none';
				}
				$fee_value = '';
				if ( 'none' !== $fee_type ) {
					$raw_val = sanitize_text_field( $g['fee_value'] ?? '' );
					$fee_value = is_numeric( $raw_val ) && (float) $raw_val > 0 ? $raw_val : '';
					if ( ! $fee_value ) $fee_type = 'none'; // invalid value = no modifier
				}
				$gateways[] = [
					'id'            => sanitize_key( $g['id'] ),
					'title'         => sanitize_text_field( wp_unslash( $g['title'] ?? '' ) ),
					'description'   => sanitize_textarea_field( wp_unslash( $g['description'] ?? '' ) ),
					'instructions'  => sanitize_textarea_field( wp_unslash( $g['instructions'] ?? '' ) ),
					'handle'        => sanitize_text_field( wp_unslash( $g['handle'] ?? '' ) ),
					'link_template' => $link_tpl,
					'show_qr'       => ! empty( $g['show_qr'] ),
					'enabled'       => ! empty( $g['enabled'] ),
					'fee_type'      => $fee_type,
					'fee_value'     => $fee_value,
				];
			}
		}
		update_option( self::GATEWAYS_OPTION, $gateways );
	}

	private function render_offline_gateways_section(): void {
		$gateways = get_option( self::GATEWAYS_OPTION, [] );
		if ( ! is_array( $gateways ) ) {
			$gateways = [];
		}
		$presets = function_exists( 'wchs_offline_gateway_presets' ) ? \wchs_offline_gateway_presets() : [];
		?>
		<div class="wchs-section__head" style="margin-bottom:12px">
			<h3 style="margin:0;font-size:14px">Offline Payment Methods</h3>
			<?php echo self::hint_icon( 'Payment methods shown at checkout. Orders placed on hold until you verify payment manually.' ); ?>
		</div>
		<div class="wchs-gateway-presets" style="margin-bottom:12px">
			<?php foreach ( $presets as $key => $preset ) : ?>
				<button type="button" class="wchs-btn wchs-btn--secondary wchs-preset-btn" data-preset="<?php echo esc_attr( $key ); ?>">+ <?php echo esc_html( $preset['title'] ); ?></button>
			<?php endforeach; ?>
			<button type="button" class="wchs-btn wchs-btn--secondary wchs-preset-btn" data-preset="custom">+ Custom</button>
		</div>
		<div id="wchs-gateways">
			<?php foreach ( $gateways as $i => $gw ) : ?>
				<?php $this->render_gateway_card( $i, $gw ); ?>
			<?php endforeach; ?>
		</div>
		<template id="wchs-gateway-template">
			<?php $this->render_gateway_card( '__IDX__', [
				'id'            => '__ID__',
				'title'         => '',
				'description'   => '',
				'instructions'  => '',
				'handle'        => '',
				'link_template' => '',
				'show_qr'       => false,
				'enabled'       => true,
				'fee_type'      => 'none',
				'fee_value'     => '',
			] ); ?>
		</template>
		<?php
	}


	private function render_gateway_card( $idx, array $gw ): void {
		$id       = $gw['id'] ?? '';
		$enabled  = $gw['enabled'] ?? false;
		$show_qr  = $gw['show_qr'] ?? false;
		?>
		<div class="wchs-module wchs-gateway-card">
			<div class="wchs-module__header">
				<strong style="font-size:12px;text-transform:uppercase;letter-spacing:0.06em;color:#767d88">
					<?php echo esc_html( $gw['title'] ?: 'Payment Method' ); ?>
					<?php if ( $enabled ) : ?>
						<span style="color:#059669;margin-left:8px">● Active</span>
					<?php else : ?>
						<span style="color:#9ca3af;margin-left:8px">● Disabled</span>
					<?php endif; ?>
				</strong>
				<div style="display:flex;gap:4px">
					<button type="button" data-action="remove" class="wchs-module__remove" title="Remove">✕</button>
				</div>
			</div>
			<input type="hidden" name="gateways[<?php echo esc_attr( $idx ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" />
			<div class="wchs-module__fields" style="grid-template-columns:1fr 1fr">
				<div class="wchs-field">
					<label>Title (shown at checkout)</label>
					<input type="text" name="gateways[<?php echo esc_attr( $idx ); ?>][title]" value="<?php echo esc_attr( $gw['title'] ?? '' ); ?>" />
				</div>
				<div class="wchs-field">
					<label>Your Handle / Address</label>
					<input type="text" name="gateways[<?php echo esc_attr( $idx ); ?>][handle]" value="<?php echo esc_attr( $gw['handle'] ?? '' ); ?>" placeholder="e.g. $mycashtag, @myvenmo, 1BvBMS..." />
				</div>
				<div class="wchs-field" style="grid-column:1/-1">
					<label>Description (shown below the payment option at checkout)</label>
					<input type="text" name="gateways[<?php echo esc_attr( $idx ); ?>][description]" value="<?php echo esc_attr( $gw['description'] ?? '' ); ?>" />
				</div>
				<div class="wchs-field" style="grid-column:1/-1">
					<label>Instructions (shown on thank-you page + email)</label>
					<textarea name="gateways[<?php echo esc_attr( $idx ); ?>][instructions]" rows="2"><?php echo esc_textarea( $gw['instructions'] ?? '' ); ?></textarea>
				</div>
				<div class="wchs-field" style="grid-column:1/-1">
					<label>Payment Link Template <?php echo self::hint_icon('Placeholders: {handle}, {amount}, {order_id}. Leave empty for instructions-only.'); ?></label>
					<input type="text" name="gateways[<?php echo esc_attr( $idx ); ?>][link_template]" value="<?php echo esc_attr( $gw['link_template'] ?? '' ); ?>" placeholder="https://cash.app/{handle}/{amount}" />
				</div>
				<?php
				$mod_type  = $gw['fee_type'] ?? 'none';
				$mod_value = $gw['fee_value'] ?? '';
				?>
				<div class="wchs-field">
					<label>Price Modifier <?php echo self::hint_icon('Percentage calculates from order total after all discounts. Shows as a line item at checkout.'); ?></label>
					<div style="display:flex;gap:6px;align-items:center">
						<select name="gateways[<?php echo esc_attr( $idx ); ?>][fee_type]" style="width:auto">
							<option value="none" <?php selected( $mod_type, 'none' ); ?>>None</option>
							<option value="flat_add" <?php selected( $mod_type, 'flat_add' ); ?>>+ Flat surcharge</option>
							<option value="flat_sub" <?php selected( $mod_type, 'flat_sub' ); ?>>− Flat discount</option>
							<option value="pct_add" <?php selected( $mod_type, 'pct_add' ); ?>>+ % surcharge</option>
							<option value="pct_sub" <?php selected( $mod_type, 'pct_sub' ); ?>>− % discount</option>
						</select>
						<input type="text" name="gateways[<?php echo esc_attr( $idx ); ?>][fee_value]" value="<?php echo esc_attr( $mod_value ); ?>" placeholder="e.g. 2.50 or 3" style="width:80px" />
					</div>
				</div>
				<div class="wchs-field">
					<label class="wchs-toggle">
						<input type="checkbox" name="gateways[<?php echo esc_attr( $idx ); ?>][show_qr]" value="1" <?php checked( $show_qr ); ?> />
						<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
						<span>Show QR code on thank-you page</span>
					</label>
				</div>
				<div class="wchs-field">
					<label class="wchs-toggle">
						<input type="checkbox" name="gateways[<?php echo esc_attr( $idx ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> />
						<span class="wchs-toggle__track"><span class="wchs-toggle__thumb"></span></span>
						<span>Enabled</span>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	// ─── Module Template Bank (for modal editor) ────────────────
	// Rendered once, hidden. JS clones the appropriate template into
	// the modal body when editing a module. Fields use data-field
	// attributes instead of name attributes.

	/**
	 * Render a hover-revealed hint icon. Replaces the .wchs-info boxes
	 * and long .wchs-hint paragraphs that used to take up vertical space
	 * at the top of every admin section. Tooltip copy is set via data-tip
	 * and shown purely via CSS on hover/focus.
	 */
	public static function hint_icon( string $tip, array $opts = [] ): string {
		$class = 'wchs-hint-icon';
		if ( ! empty( $opts['flip_left'] ) ) {
			$class .= ' wchs-hint-icon--flip-left';
		}
		return sprintf(
			'<span class="%s" tabindex="0" role="note" aria-label="%s" data-tip="%s">i</span>',
			esc_attr( $class ),
			esc_attr( $tip ),
			esc_attr( $tip )
		);
	}

	/**
	 * Render a row of accent-color override swatches for a module modal.
	 * First button = "Default" (inherit from Design tab), remaining = palette.
	 * Hidden input is keyed by $data_field; admin.js listens for change events
	 * to stream previews + persists to module.overrides.accent_color on save.
	 */
	public static function accent_override_swatches( string $data_field = 'overrides_accent_color' ): string {
		$out  = '<input type="hidden" data-field="' . esc_attr( $data_field ) . '" value="" />';
		$out .= '<div class="wchs-override-swatches" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">';
		// Default (inherit) — diagonal stripe pattern conveys "no override set"
		$out .= '<button type="button" class="wchs-override-swatch wchs-override-swatch--default active" data-override-value="" title="Use site default accent" style="position:relative;width:28px;height:28px;border:1px solid var(--wchs-text-muted,#94a3b8);background:transparent;cursor:pointer;padding:0">'
			. '<span style="display:block;width:100%;height:100%;background:repeating-linear-gradient(45deg,transparent 0 4px,var(--wchs-text-muted,#94a3b8) 4px 5px);opacity:0.6"></span>'
			. '</button>';
		foreach ( self::ACCENT_PALETTE as $color ) {
			$out .= sprintf(
				'<button type="button" class="wchs-override-swatch" data-override-value="%1$s" title="%1$s" style="width:28px;height:28px;border:1px solid var(--wchs-text-muted,#94a3b8);background:%1$s;cursor:pointer;padding:0"></button>',
				esc_attr( $color )
			);
		}
		$out .= '</div>';
		return $out;
	}

	private static function icon_svg_paths(): array {
		return [
			'shipping'  => '<path d="M3.8 8.8h10.2v7.7H3.8z"/><path d="M14 11h3.1l3.1 3.2v2.3H14"/><circle cx="8" cy="17.6" r="1.7"/><circle cx="17.6" cy="17.6" r="1.7"/>',
			'lab'       => '<path d="M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8"/><path d="M9.1 14.6h5.8"/>',
			'shield'    => '<path d="M12 3.6 18.4 6v5.5c0 4-2.5 7.5-6.4 8.9-3.9-1.4-6.4-4.9-6.4-8.9V6Z"/><path d="m9.3 12.2 1.9 1.9 3.6-3.9"/>',
			'star'      => '<path d="M12 3.6l2.3 4.9 5.4.8-3.9 3.8.9 5.3-4.7-2.5-4.8 2.5.9-5.3L4.2 9.3l5.4-.8L12 3.6z" fill="currentColor" stroke="none"/>',
			'heart'     => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
			'lock'      => '<rect x="4.5" y="11" width="15" height="9.5" rx="1.5"/><path d="M7.5 11V8a4.5 4.5 0 0 1 9 0v3"/>',
			'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
			'refresh'   => '<path d="M21.5 5v5h-5"/><path d="M2.5 19v-5h5"/><path d="M4.2 9.5a8.5 8.5 0 0 1 14-3l3.3 3.5M2.5 14l3.3 3.5a8.5 8.5 0 0 0 14-3"/>',
			'check'     => '<path d="M5 12.5l4.2 4.2L19 7"/>',
			'leaf'      => '<path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.95-2.3c.48.17.98.3 1.34.3C19 20 22 3 22 3c-1 2-8 2.25-13 3.25S2 11.5 2 13.5s1.75 3.75 1.75 3.75"/>',
			'gift'      => '<path d="M4 12v9a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-9"/><rect x="2.5" y="7.5" width="19" height="5" rx="1"/><path d="M12 22V7.5"/><path d="M12 7.5H8a2.5 2.5 0 0 1 0-5C11 2.5 12 7.5 12 7.5z"/><path d="M12 7.5h4a2.5 2.5 0 0 0 0-5C13 2.5 12 7.5 12 7.5z"/>',
			'award'     => '<circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5L7 22l5-3 5 3-1.5-8.5"/>',
			'globe'     => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5a13 13 0 0 1 3.5 8.5 13 13 0 0 1-3.5 8.5 13 13 0 0 1-3.5-8.5A13 13 0 0 1 12 3.5z"/>',
			'wallet'    => '<rect x="2.5" y="5.5" width="19" height="14.5" rx="1.5"/><path d="M2.5 10h19"/><circle cx="17" cy="14.5" r="1.2" fill="currentColor" stroke="none"/>',
			'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7.5" r="3.5"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
			'zap'       => '<path d="M13 2L3 14h9l-1 8 10-12h-9z"/>',
			'percent'   => '<path d="M19 5L5 19"/><circle cx="6.5" cy="6.5" r="2.2"/><circle cx="17.5" cy="17.5" r="2.2"/>',
			'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.88.37 1.76.7 2.61a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.33 1.73.57 2.61.7A2 2 0 0 1 22 16.92z"/>',
			'package'   => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>',
			'thumbsup'  => '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>',
			'database'  => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
			'cart'      => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
			'bag'       => '<circle cx="10" cy="20.5" r="1.5"/><circle cx="18" cy="20.5" r="1.5"/><path d="M2.5 2.5h3l2.7 12.4a1.5 1.5 0 0 0 1.5 1.1h7.7a1.5 1.5 0 0 0 1.4-1l2.7-7.2H7.1"/>',
			'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
			'moon'      => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>',
		];
	}

	private static function render_icon_picker_html( string $input_name = '', string $selected = '' ): string {
		$icons = self::icon_svg_paths();

		// Trigger button showing current selection
		$preview_svg = '';
		if ( $selected && isset( $icons[ $selected ] ) ) {
			$preview_svg = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $icons[ $selected ] . '</svg>';
		}
		$preview_label = $selected ? '' : 'No icon';

		$html = '<div class="wchs-icon-picker">'
			. '<button type="button" class="wchs-icon-picker__trigger">'
			. '<span class="wchs-icon-picker__preview">' . $preview_svg . $preview_label . '</span>'
			. '<span class="wchs-icon-picker__arrow">&#9662;</span>'
			. '</button>'
			. '<div class="wchs-icon-picker__popover">';

		// None option
		$none_sel = empty( $selected ) ? ' is-selected' : '';
		$html .= '<button type="button" class="wchs-icon-picker__btn wchs-icon-picker__btn--none' . $none_sel . '" data-icon="" title="No icon">'
			. '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.4"><path d="M18 6L6 18M6 6l12 12"/></svg>'
			. '</button>';

		foreach ( $icons as $name => $path ) {
			$is_sel = $selected === $name ? ' is-selected' : '';
			$html .= '<button type="button" class="wchs-icon-picker__btn' . $is_sel . '" data-icon="' . esc_attr( $name ) . '" title="' . esc_attr( ucfirst( $name ) ) . '">'
				. '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>'
				. '</button>';
		}

		$html .= '</div>' // popover
			. '<input type="hidden" class="wchs-icon-picker__value"'
			. ( $input_name ? ' name="' . esc_attr( $input_name ) . '"' : '' )
			. ' value="' . esc_attr( $selected ) . '" />'
			. '</div>';
		return $html;
	}

	private function render_module_template_bank(): void {
		$categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		if ( is_wp_error( $categories ) ) $categories = [];
		$icon_picker_html = self::render_icon_picker_html();
		$cat_opts_html = '<option value="">All categories</option>';
		foreach ( $categories as $cat ) {
			$cat_opts_html .= '<option value="' . esc_attr( $cat->slug ) . '">' . esc_html( $cat->name ) . '</option>';
		}
		$cat_id_opts_html = '<option value="">— Select —</option>';
		foreach ( $categories as $cat ) {
			$cat_id_opts_html .= '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</option>';
		}
		?>
		<!-- Product Slider -->
		<div id="wchs-mod-tpl-product_slider" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field"><label>Title</label><input type="text" data-field="title" /></div>
				<div class="wchs-field"><label>Source</label>
					<select data-field="source" data-role="source">
						<option value="all">All products</option>
						<option value="featured">Featured</option>
						<option value="category">By category</option>
						<option value="best_sellers">Best sellers</option>
						<option value="manual">Manual (pick IDs)</option>
					</select>
				</div>
				<div class="wchs-field" data-role="category-field" style="display:none"><label>Category</label><select data-field="category"><?php echo $cat_opts_html; ?></select></div>
				<div class="wchs-field" data-role="ids-field" style="display:none"><label>Product IDs</label><input type="text" data-field="product_ids" placeholder="12,34,56" /></div>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Review Slider -->
		<div id="wchs-mod-tpl-review_slider" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field"><label>Title</label><input type="text" data-field="title" /></div>
				<div class="wchs-field"><label class="wchs-check"><input type="checkbox" data-field="photos_only" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Show only reviews with photos</span></label></div>
				<div class="wchs-field wchs-field--full">
					<label>Product IDs (comma-separated) <?php echo self::hint_icon('Pull reviews from these products. Leave empty to fall back to a built-in list.'); ?></label>
					<input type="text" data-field="product_ids" placeholder="664,535,635" />
				</div>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Trust Bar -->
		<div id="wchs-mod-tpl-trust_bar" style="display:none">
			<div style="display:flex;flex-direction:column;gap:16px">
				<div class="wchs-field"><label>Section Title</label><input type="text" data-field="title" placeholder="e.g. Why shop with us" style="width:100%" /></div>
				<div class="wchs-field">
					<label>Trust Items</label>
					<div class="wchs-accordion-items" style="display:flex;flex-direction:column;gap:10px">
						<div class="wchs-accordion-item wchs-trust-item">
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:0">Icon</label>
							<?php echo $icon_picker_html; ?>
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Headline</label>
							<input type="text" placeholder="e.g. Free Shipping Over $50" />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Description</label>
							<input type="text" placeholder="e.g. Fast, tracked delivery on all orders." />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-trust-item-modal" style="margin-top:10px">+ Add Trust Item</button>
				</div>
				<div class="wchs-field" style="margin-top:12px"><label class="wchs-check"><input type="checkbox" data-field="icon_accent" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Use accent color for icons</span></label></div>
				<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
					<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
						Accent color override
						<?php echo self::hint_icon( 'Pick a different accent for this section only. Default inherits from Design tab accent.' ); ?>
					</label>
					<?php echo self::accent_override_swatches(); ?>
				</div>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Accordion -->
		<div id="wchs-mod-tpl-accordion" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field wchs-field--full"><label>Title</label><input type="text" data-field="title" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Items</label>
					<div class="wchs-accordion-items">
						<div class="wchs-accordion-item">
							<input type="text" placeholder="Question" />
							<textarea placeholder="Answer" rows="3" data-wysiwyg="1"></textarea>
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-accordion-item-modal" style="margin-top:8px">+ Add Item</button>
				</div>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Listicle -->
		<div id="wchs-mod-tpl-listicle" style="display:none">
			<div class="wchs-module__fields" style="display:flex;flex-direction:column;gap:14px">
				<p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#666">First section (image left, copy right)</p>
				<div class="wchs-field wchs-field--full"><label>Hero image (left column)</label>
					<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
						<input type="text" data-field="lc_hero_image" class="wchs-media-url" placeholder="No image selected" style="flex:1;min-width:0" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
					</div>
					<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:180px;margin-top:8px;border:1px solid #e0e0e0" />
				</div>
				<div class="wchs-field wchs-field--full"><label>Hero image alt text</label><input type="text" data-field="lc_hero_image_alt" /></div>
				<div class="wchs-field wchs-field--full"><label>Eyebrow (optional)</label><input type="text" data-field="lc_section_eyebrow" placeholder="THE FIVE REASONS" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline</label><input type="text" data-field="lc_headline" placeholder="5 Reasons Why…" /></div>
				<div class="wchs-field wchs-field--full"><label>Description</label><textarea rows="5" data-field="lc_intro" data-wysiwyg="1" placeholder="Paragraphs under the headline, before the CTA…"></textarea></div>
				<div class="wchs-field wchs-field--full"><label>CTA label</label><input type="text" data-field="lc_cta_label" placeholder="Shop research-grade peptides" /></div>
				<div class="wchs-field wchs-field--full"><label>CTA link</label><input type="text" data-field="lc_cta_href" placeholder="/shop" /></div>
				<p style="margin:8px 0 0;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#666">Points section</p>
				<div class="wchs-field wchs-field--full"><label>Points section heading</label><input type="text" data-field="lc_items_headline" placeholder="Here is why more research teams…" /></div>
				<div class="wchs-field wchs-field--full"><label>Closing paragraph (optional)</label><textarea rows="3" data-field="lc_closing" data-wysiwyg="1" placeholder="So why have researchers switched…"></textarea></div>
				<div class="wchs-field wchs-field--full">
					<label>List points <?php echo self::hint_icon( 'Odd rows: text left, image right. Even rows: image left, text right.' ); ?></label>
					<div class="wchs-listicle-items wchs-accordion-items" style="display:flex;flex-direction:column;gap:10px">
						<div class="wchs-accordion-item wchs-listicle-item" style="display:flex;flex-direction:column;gap:8px;padding:10px;border:1px solid #ddd;background:#fafafa">
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:0">Point number (optional)</label>
							<input type="text" data-field="lc_item_number" placeholder="01" style="max-width:80px" />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Category label</label>
							<input type="text" data-field="lc_item_label" placeholder="U.S. MANUFACTURING" />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Point headline</label>
							<input type="text" data-field="lc_item_headline" placeholder="Headline for this point" />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Body</label>
							<textarea rows="4" data-field="lc_item_body" placeholder="Supporting copy" data-wysiwyg="1"></textarea>
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Callout box (optional)</label>
							<textarea rows="3" data-field="lc_item_callout" placeholder="Highlighted summary line…" data-wysiwyg="1"></textarea>
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Image</label>
							<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
								<input type="text" data-field="lc_item_image" class="wchs-media-url" placeholder="No image selected" style="flex:1;min-width:0" />
								<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
								<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
							</div>
							<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:140px;margin-top:8px;border:1px solid #e0e0e0" />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Image alt text</label>
							<input type="text" data-field="lc_item_image_alt" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-listicle-item-modal" style="margin-top:10px">+ Add point</button>
				</div>
			</div>
						<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Number badges and CTA use this accent.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Promo offer (split) -->
		<div id="wchs-mod-tpl-promo_offer" style="display:none">
			<div class="wchs-module__fields" style="display:flex;flex-direction:column;gap:14px">
				<div class="wchs-field wchs-field--full"><label>Intro heading (above box)</label><input type="text" data-field="po_intro_subheadline" placeholder="✨ We don't hand these out every day…" /></div>
				<div class="wchs-field wchs-field--full"><label>Top badge</label><input type="text" data-field="po_badge_text" placeholder="LIMITED TIME OFFER ✨" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Product image (left column)</label>
					<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
						<input type="text" data-field="po_image" class="wchs-media-url" placeholder="No image selected" style="flex:1;min-width:0" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
					</div>
					<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:140px;margin-top:8px;border:1px solid #e0e0e0" />
				</div>
				<div class="wchs-field wchs-field--full"><label>Image alt text</label><input type="text" data-field="po_image_alt" /></div>
				<div class="wchs-field wchs-field--full"><label>Ribbon line</label><input type="text" data-field="po_ribbon_text" placeholder="PUBLISHED COAs + BATCH DOCS WITH EVERY ORDER" /></div>
				<div class="wchs-field wchs-field--full"><label>Offer line (accent)</label><input type="text" data-field="po_offer_primary" placeholder="UP TO 40% OFF" /></div>
				<div class="wchs-field wchs-field--full"><label>Offer line (secondary)</label><input type="text" data-field="po_offer_secondary" placeholder="FOR A LIMITED TIME ONLY!" /></div>
				<div class="wchs-field wchs-field--full"><label>Scarcity text</label><input type="text" data-field="po_scarcity_text" /></div>
				<div class="wchs-field wchs-field--full"><label>CTA label</label><input type="text" data-field="po_cta_label" placeholder="GET 40% OFF" /></div>
				<div class="wchs-field wchs-field--full"><label>CTA link</label><input type="text" data-field="po_cta_href" placeholder="/shop" /></div>
				<div class="wchs-field">
					<label style="display:inline-flex;align-items:center;gap:8px;font-weight:500">
						<input type="checkbox" data-field="po_show_countdown" checked /> Show countdown timer
					</label>
				</div>
				<div class="wchs-field wchs-field--full">
					<label>Countdown ends at <?php echo self::hint_icon( 'Local time. Leave blank to hide the timer.' ); ?></label>
					<input type="datetime-local" data-field="po_countdown_end_at" />
				</div>
				<div class="wchs-field"><label>Status label</label><input type="text" data-field="po_status_label" placeholder="Sell-out risk:" /></div>
				<div class="wchs-field"><label>Status value</label><input type="text" data-field="po_status_value" placeholder="High" /></div>
				<div class="wchs-field wchs-field--full"><label>Status note</label><input type="text" data-field="po_status_note" placeholder="Faster shipping" /></div>
				<div class="wchs-field wchs-field--full"><label>Footer line</label><input type="text" data-field="po_footer_text" placeholder="Try it today with a 60-Day Money-Back Guarantee!" /></div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Badge, CTA, offer highlight, countdown digits, and status emphasis.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Reviews listicle -->
		<div id="wchs-mod-tpl-reviews_listicle" style="display:none">
			<div class="wchs-module__fields" style="display:flex;flex-direction:column;gap:14px">
				<div class="wchs-field wchs-field--full">
					<label>Headline <?php echo self::hint_icon( 'No review count — e.g. “Amazing Reviews with a 4.9 Rating”.' ); ?></label>
					<input type="text" data-field="rl_headline" placeholder="Amazing Reviews with a 4.9 Rating" />
				</div>
				<div class="wchs-field wchs-field--full">
					<label>Reviews (shows up to 3)</label>
					<div class="wchs-reviews-listicle-items wchs-accordion-items" style="display:flex;flex-direction:column;gap:10px">
						<div class="wchs-accordion-item" style="display:flex;flex-direction:column;gap:8px;padding:10px;border:1px solid #ddd;background:#fafafa">
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:0">Review quote</label>
							<textarea rows="4" placeholder="Customer quote"></textarea>
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Name</label>
							<input type="text" placeholder="e.g. Vincent R." />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Star rating (1–5)</label>
							<input type="number" min="1" max="5" value="5" style="max-width:80px" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-reviews-listicle-item-modal" style="margin-top:10px">+ Add review</button>
				</div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Filled star color.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Listicle FAQs -->
		<div id="wchs-mod-tpl-listicle_faqs" style="display:none">
			<div class="wchs-module__fields" style="display:flex;flex-direction:column;gap:14px">
				<div class="wchs-field wchs-field--full"><label>Eyebrow</label><input type="text" data-field="lf_eyebrow" placeholder="COMMON QUESTIONS" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline</label><input type="text" data-field="lf_headline" placeholder="What researchers ask before ordering" /></div>
				<div class="wchs-field wchs-field--full">
					<label>FAQ items</label>
					<div class="wchs-listicle-faqs-items wchs-accordion-items" style="display:flex;flex-direction:column;gap:10px">
						<div class="wchs-accordion-item" style="display:flex;flex-direction:column;gap:8px;padding:10px;border:1px solid #ddd;background:#fafafa">
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:0">Question</label>
							<input type="text" placeholder="Question" />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Answer</label>
							<textarea rows="4" placeholder="Answer" data-wysiwyg="1"></textarea>
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-listicle-faqs-item-modal" style="margin-top:10px">+ Add FAQ</button>
				</div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Eyebrow, headline accent, and chevron.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Text Block -->
		<div id="wchs-mod-tpl-text_block" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field">
					<label>Layout <?php echo self::hint_icon( 'Auto: if the title contains “Why Alyve” or “Why Choose”, a brand comparison table appears under the intro (with default rows until you add your own).' ); ?></label>
					<select data-field="tb_layout">
						<option value="auto">Auto</option>
						<option value="standard">Text only</option>
						<option value="comparison">Brand comparison table</option>
					</select>
				</div>
				<div class="wchs-field wchs-field--full"><label>Title / eyebrow</label><input type="text" data-field="title" placeholder="e.g. WHY ALYVE" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline (optional)</label><input type="text" data-field="tb_headline" placeholder="Large heading below eyebrow" /></div>
				<div class="wchs-field wchs-field--full"><label>Content</label><textarea data-field="content" rows="8" data-wysiwyg="1" style="width:100%"></textarea></div>
				<div class="wchs-field"><label>Brand column name</label><input type="text" data-field="tb_brand_name" placeholder="Leave blank for site name" /></div>
				<div class="wchs-field"><label>Competitor column name</label><input type="text" data-field="tb_competitor_name" placeholder="Unverified Sellers" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Brand logo (optional)</label>
					<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
						<input type="text" data-field="tb_brand_logo" class="wchs-media-url" placeholder="" style="flex:1;min-width:0" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
					</div>
					<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:80px;margin-top:8px;border:1px solid #e0e0e0" />
				</div>
				<div class="wchs-field wchs-field--full">
					<label>Competitor image (optional)</label>
					<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
						<input type="text" data-field="tb_competitor_logo" class="wchs-media-url" placeholder="" style="flex:1;min-width:0" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
					</div>
					<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:80px;margin-top:8px;border:1px solid #e0e0e0" />
				</div>
				<div class="wchs-field wchs-field--full">
					<label>Comparison row labels</label>
					<p style="margin:0 0 8px;font-size:12px;color:#666">Leave empty to use default rows when Auto or Comparison layout applies.</p>
					<div class="wchs-tb-compare-rows">
						<div class="wchs-accordion-item" style="display:flex;gap:8px;align-items:center;padding:6px 8px;border:1px solid #ddd;background:#fafafa">
							<input type="text" style="flex:1" placeholder="Row label (left column)" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-tb-compare-row-modal" style="margin-top:8px">+ Add row</button>
				</div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Comparison table: highlighted column background.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Gallery -->
		<div id="wchs-mod-tpl-gallery" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field"><label>Title</label><input type="text" data-field="title" /></div>
				<div class="wchs-field"><label>Columns</label><select data-field="columns"><option value="2">2</option><option value="3" selected>3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option></select></div>
				<div class="wchs-field"><label>Gap (px)</label><select data-field="gap"><option value="0">0</option><option value="4">4</option><option value="8" selected>8</option><option value="16">16</option><option value="24">24</option></select></div>
				<div class="wchs-field"><label>Aspect Ratio</label><select data-field="aspect_ratio"><option value="1/1">1:1</option><option value="4/3">4:3</option><option value="3/4">3:4</option></select></div>
			</div>
			<div class="wchs-field" style="margin-top:12px">
				<label>Images</label>
				<div class="wchs-gallery-items">
					<div class="wchs-gallery-item" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;padding:8px;border:1px solid #ddd;background:#fafafa">
						<div style="flex-shrink:0;width:80px">
							<img src="" style="width:80px;height:80px;object-fit:cover;display:none;border:1px solid #ddd" class="wchs-gallery-thumb" />
							<input type="hidden" value="" class="wchs-gallery-src" />
							<button type="button" class="wchs-btn wchs-btn--secondary wchs-gallery-pick" style="font-size:10px;padding:2px 6px;margin-top:4px;width:100%">Choose</button>
						</div>
						<div style="flex:1;display:flex;flex-direction:column;gap:4px">
							<input type="text" placeholder="Title (optional)" />
							<input type="text" placeholder="Description (optional)" />
						</div>
						<button type="button" class="wchs-accordion-item__remove" title="Remove" style="flex-shrink:0">✕</button>
					</div>
				</div>
				<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-gallery-item-modal" style="margin-top:8px">+ Add Image</button>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Shop Grid -->
		<div id="wchs-mod-tpl-shop_grid" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field"><label>Title</label><input type="text" data-field="title" /></div>
				<div class="wchs-field"><label>Category filter</label><select data-field="category"><?php echo $cat_opts_html; ?></select></div>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Contact Form -->
		<div id="wchs-mod-tpl-contact_form" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field"><label>Title</label><input type="text" data-field="title" /></div>
				<div class="wchs-field"><label>Recipient Email</label><input type="email" data-field="recipient_email" /></div>
				<div class="wchs-field"><label>Subject Prefix</label><input type="text" data-field="subject_prefix" /></div>
				<div class="wchs-field"><label>Success Message</label><input type="text" data-field="success_message" placeholder="Thank you for your message!" /></div>
			</div>
			<div class="wchs-field" style="margin-top:12px">
				<label>Form Fields</label>
				<div class="wchs-accordion-items">
					<div class="wchs-accordion-item" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
						<input type="text" placeholder="field_name" style="width:100px" />
						<input type="text" placeholder="Label" style="flex:1" />
						<select style="width:auto"><option value="text">Text</option><option value="email">Email</option><option value="textarea">Textarea</option></select>
						<label class="wchs-check"><input type="checkbox" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Req</span></label>
						<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
					</div>
				</div>
				<div style="display:flex;gap:8px;margin-top:8px">
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-cf-field-modal" style="font-size:11px;padding:4px 10px">+ Add Field</button>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-cf-preset-modal" style="font-size:11px;padding:4px 10px">Load Standard Preset</button>
				</div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Use a different accent for this form only (affects the submit button). Default inherits from Design tab accent.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Category Grid -->
		<div id="wchs-mod-tpl-category_grid" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field"><label>Title</label><input type="text" data-field="title" /></div>
				<div class="wchs-field"><label>Columns</label><select data-field="columns"><option value="2">2</option><option value="3">3</option><option value="4" selected>4</option><option value="5">5</option><option value="6">6</option></select></div>
				<div class="wchs-field"><label>Gap (px)</label><select data-field="gap"><option value="0">0</option><option value="4">4</option><option value="8">8</option><option value="12" selected>12</option><option value="16">16</option><option value="24">24</option><option value="32">32</option></select></div>
			</div>
			<div class="wchs-field" style="margin-top:12px">
				<label>Categories</label>
				<div class="wchs-accordion-items">
					<div class="wchs-accordion-item" style="display:flex;gap:8px;align-items:center">
						<select style="flex:1"><?php echo $cat_id_opts_html; ?></select>
						<input type="hidden" value="" class="wchs-gallery-src" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-gallery-pick" style="font-size:10px;padding:2px 8px;flex-shrink:0">Add image</button>
						<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
					</div>
				</div>
				<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-catgrid-item-modal" style="margin-top:8px">+ Add Category</button>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Split Features -->
		<div id="wchs-mod-tpl-split_features" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field">
					<label>Layout <?php echo self::hint_icon('Comparison table uses each row’s Heading only (leave images blank). Title containing “Why Choose” auto-opens comparison on the storefront.'); ?></label>
					<select data-field="sf_layout">
						<option value="alternating">Alternating image / text</option>
						<option value="comparison">Brand comparison table</option>
					</select>
				</div>
				<div class="wchs-field wchs-field--full"><label>Eyebrow (optional)</label><input type="text" data-field="title" placeholder="Small label above headline when headline is set" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline</label><input type="text" data-field="sf_headline" placeholder="Why Choose Alyve" /></div>
				<div class="wchs-field wchs-field--full"><label>Intro text</label><textarea data-field="sf_subtitle" rows="3" placeholder="Paragraph below headline"></textarea></div>
				<div class="wchs-field"><label>Brand column name</label><input type="text" data-field="sf_brand_name" placeholder="Leave blank for site name" /></div>
				<div class="wchs-field"><label>Competitor column name</label><input type="text" data-field="sf_competitor_name" placeholder="Unverified Sellers" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Brand logo (optional)</label>
					<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
						<input type="text" data-field="sf_brand_logo" class="wchs-media-url" placeholder="" style="flex:1;min-width:0" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
					</div>
					<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:80px;margin-top:8px;border:1px solid #e0e0e0" />
				</div>
				<div class="wchs-field wchs-field--full">
					<label>Competitor image (optional)</label>
					<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
						<input type="text" data-field="sf_competitor_logo" class="wchs-media-url" placeholder="" style="flex:1;min-width:0" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
					</div>
					<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:80px;margin-top:8px;border:1px solid #e0e0e0" />
				</div>
				<div class="wchs-field wchs-field--full">
					<label>Rows / feature blocks</label>
					<p style="margin:0 0 8px;font-size:12px;color:#666">Comparison mode: set <strong>Heading</strong> for each row label (left column). Alternating mode: fill image + copy as before.</p>
					<div class="wchs-accordion-items">
						<div class="wchs-accordion-item" style="display:flex;gap:8px;align-items:flex-start;padding:8px;border:1px solid #ddd;background:#fafafa">
							<div style="flex-shrink:0;width:80px">
								<img src="" style="width:80px;height:80px;object-fit:cover;display:none;border:1px solid #ddd" class="wchs-gallery-thumb" />
								<input type="hidden" value="" class="wchs-gallery-src" />
								<button type="button" class="wchs-btn wchs-btn--secondary wchs-gallery-pick" style="font-size:10px;padding:2px 6px;margin-top:4px;width:100%">Choose</button>
							</div>
							<div style="flex:1;display:flex;flex-direction:column;gap:4px">
								<input type="text" placeholder="Eyebrow (e.g. VERIFICATION)" />
								<input type="text" placeholder="Heading / row label" />
								<textarea placeholder="Description" rows="3" data-wysiwyg="1"></textarea>
							</div>
							<button type="button" class="wchs-accordion-item__remove" title="Remove" style="flex-shrink:0">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-splitfeature-item-modal" style="margin-top:8px">+ Add row</button>
				</div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon('Comparison table: highlighted column background. Alternating layout ignores this.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Split value (BOGO promo) -->
		<div id="wchs-mod-tpl-split_value" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field wchs-field--full"><label>Rating line</label><input type="text" data-field="sv_rating_line" placeholder="Rated 4.98/5 · 24,987+ reviews" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline (before accent)</label><input type="text" data-field="sv_headline_prefix" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline accent word</label><input type="text" data-field="sv_headline_accent" placeholder="Peptides." /></div>
				<div class="wchs-field">
					<label style="display:inline-flex;align-items:center;gap:8px;font-weight:500">
						<input type="checkbox" data-field="sv_accent_underline" /> Accent underline on highlight word
					</label>
				</div>
				<div class="wchs-field wchs-field--full">
					<label>Bullet list</label>
					<div class="wchs-sv-bullets">
						<div class="wchs-accordion-item" style="display:flex;gap:8px;align-items:center;padding:6px 8px;border:1px solid #ddd;background:#fafafa">
							<input type="text" style="flex:1" placeholder="e.g. Fast U.S. Shipping" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-sv-bullet-modal" style="margin-top:8px">+ Add bullet</button>
				</div>
				<div class="wchs-field wchs-field--full"><label>CTA label</label><input type="text" data-field="sv_cta_label" placeholder="Buy 1 Get 1 Free" /></div>
				<div class="wchs-field wchs-field--full"><label>CTA link</label><input type="text" data-field="sv_cta_href" placeholder="/shop" /></div>
				<div class="wchs-field wchs-field--full"><label>Trust line (under button)</label><input type="text" data-field="sv_trust_note" /></div>
				<div class="wchs-field"><label>Promo badge — small line</label><input type="text" data-field="sv_promo_eyebrow" placeholder="LIMITED TIME" /></div>
				<div class="wchs-field"><label>Promo badge — title</label><input type="text" data-field="sv_promo_title" placeholder="Buy 1 Get 1 Free" /></div>
				<div class="wchs-field wchs-field--full" style="margin-top:12px">
					<label>Product image</label>
					<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
						<input type="text" data-field="sv_image" class="wchs-media-url" placeholder="No image selected" style="flex:1;min-width:0" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
					</div>
					<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:140px;margin-top:8px;border:1px solid #e0e0e0" />
				</div>
				<div class="wchs-field wchs-field--full"><label>Image alt text</label><input type="text" data-field="sv_image_alt" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Stats row (under image)</label>
					<div class="wchs-sv-stats">
						<div class="wchs-accordion-item" style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center;padding:6px 8px;border:1px solid #ddd;background:#fafafa">
							<input type="text" placeholder="Value (e.g. 99%)" />
							<input type="text" placeholder="Label (e.g. Purity)" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-sv-stat-modal" style="margin-top:8px">+ Add stat</button>
				</div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Optional accent for this block (button, stars, highlights).' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Order handling -->
		<div id="wchs-mod-tpl-order_handling" style="display:none">
			<div class="wchs-module__fields" style="display:flex;flex-direction:column;gap:14px">
				<div class="wchs-field wchs-field--full"><label>Badge text</label><input type="text" data-field="oh_badge_text" placeholder="Our Process" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline</label><input type="text" data-field="oh_headline" placeholder="How Every Order Is Handled" /></div>
				<div class="wchs-field wchs-field--full"><label>Subheadline</label><input type="text" data-field="oh_subheadline" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Process steps</label>
					<div class="wchs-oh-steps wchs-accordion-items" style="display:flex;flex-direction:column;gap:10px">
						<div class="wchs-accordion-item" style="display:flex;flex-direction:column;gap:8px;padding:10px;border:1px solid #ddd;background:#fafafa">
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:0">Icon</label>
							<select data-field="oh_step_variant">
								<option value="verified">Verified batches</option>
								<option value="lab">Lab testing</option>
								<option value="shipping">Shipping</option>
								<option value="support">Support</option>
							</select>
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Title</label>
							<input type="text" placeholder="Step title" />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Description</label>
							<input type="text" placeholder="Step description" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-oh-step-modal" style="margin-top:10px">+ Add step</button>
				</div>
				<div class="wchs-field wchs-field--full"><label>Metrics panel title</label><input type="text" data-field="oh_metrics_title" placeholder="Quality Metrics" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Quality metrics</label>
					<div class="wchs-oh-metrics wchs-accordion-items" style="display:flex;flex-direction:column;gap:10px">
						<div class="wchs-accordion-item" style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center;padding:6px 8px;border:1px solid #ddd;background:#fafafa">
							<input type="text" placeholder="Value (e.g. 99.8%)" />
							<input type="text" placeholder="Label" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-oh-metric-modal" style="margin-top:10px">+ Add metric</button>
				</div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Optional accent for badge, metrics, and arrows.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Feature highlights -->
		<div id="wchs-mod-tpl-feature_highlights" style="display:none">
			<div class="wchs-module__fields" style="display:flex;flex-direction:column;gap:14px">
				<div class="wchs-field wchs-field--full"><label>Badge text</label><input type="text" data-field="fh_badge_text" placeholder="Verified & Trusted" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline (before accent)</label><input type="text" data-field="fh_headline_prefix" /></div>
				<div class="wchs-field wchs-field--full"><label>Headline accent</label><input type="text" data-field="fh_headline_accent" /></div>
				<div class="wchs-field wchs-field--full"><label>Subheadline</label><input type="text" data-field="fh_subheadline" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Highlight cards</label>
					<div class="wchs-fh-items wchs-accordion-items" style="display:flex;flex-direction:column;gap:10px">
						<div class="wchs-accordion-item wchs-fh-item" style="display:flex;flex-direction:column;gap:8px;padding:10px;border:1px solid #ddd;background:#fafafa">
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:0">Card style</label>
							<select data-field="fh_variant">
								<option value="pin">USA / location</option>
								<option value="star">Reviews / star</option>
								<option value="lab">Lab testing</option>
								<option value="award">Quality badge</option>
							</select>
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Title</label>
							<input type="text" placeholder="Title" />
							<label style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#999;margin:8px 0 0">Description</label>
							<input type="text" placeholder="Description" />
							<button type="button" class="wchs-accordion-item__remove" title="Remove">✕</button>
						</div>
					</div>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-fh-item-modal" style="margin-top:10px">+ Add card</button>
				</div>
				<div class="wchs-field wchs-field--full"><label>CTA label</label><input type="text" data-field="fh_cta_label" placeholder="Buy 1 Get 1 Free" /></div>
				<div class="wchs-field wchs-field--full"><label>CTA link</label><input type="text" data-field="fh_cta_href" placeholder="/shop" /></div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Optional accent for headline highlight and CTA.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Hero -->
		<div id="wchs-mod-tpl-hero" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field wchs-field--full"><label>Headline</label><input type="text" data-field="hero_headline" placeholder="A short, punchy line" /></div>
				<div class="wchs-field wchs-field--full"><label>Subheadline</label><input type="text" data-field="hero_subheadline" placeholder="Optional supporting sentence" /></div>
				<div class="wchs-field">
					<label>Layout</label>
					<select data-field="hero_layout">
						<option value="left">Left-aligned (default)</option>
						<option value="center">Centered</option>
						<option value="bottom">Bottom-anchored</option>
					</select>
				</div>
				<div class="wchs-field">
					<label>Text color</label>
					<select data-field="hero_text_color_mode">
						<option value="theme">Theme (follows light/dark)</option>
						<option value="white">Always white</option>
						<option value="black">Always black</option>
						<option value="accent">Accent</option>
					</select>
				</div>
			</div>

			<!-- Image -->
			<div class="wchs-field wchs-field--full" style="margin-top:12px">
				<label>Image (desktop)</label>
				<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
					<input type="text" data-field="hero_image_desktop" class="wchs-media-url" placeholder="No image selected" style="flex:1;min-width:0" />
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
				</div>
				<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:140px;margin-top:8px;border:1px solid #e0e0e0" />
			</div>
			<div class="wchs-field wchs-field--full">
				<label>Image (mobile) <span style="font-weight:400;color:#999;font-size:11px">— optional, falls back to desktop</span></label>
				<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
					<input type="text" data-field="hero_image_mobile" class="wchs-media-url" placeholder="No image selected" style="flex:1;min-width:0" />
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
					<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
				</div>
				<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:100px;margin-top:8px;border:1px solid #e0e0e0" />
			</div>

			<!-- Image positioning -->
			<div class="wchs-module__fields" style="margin-top:12px">
				<div class="wchs-field">
					<label>Horizontal (<span class="wchs-hero-mod-pos-x-val">50</span>%)</label>
					<input type="range" data-field="hero_image_position_x" min="0" max="100" value="50"
						oninput="this.parentElement.querySelector('.wchs-hero-mod-pos-x-val').textContent=this.value" />
				</div>
				<div class="wchs-field">
					<label>Vertical (<span class="wchs-hero-mod-pos-y-val">50</span>%)</label>
					<input type="range" data-field="hero_image_position_y" min="0" max="100" value="50"
						oninput="this.parentElement.querySelector('.wchs-hero-mod-pos-y-val').textContent=this.value" />
				</div>
				<div class="wchs-field">
					<label>Zoom (<span class="wchs-hero-mod-zoom-val">100</span>%)</label>
					<input type="range" data-field="hero_image_zoom" min="50" max="200" step="5" value="100"
						oninput="this.parentElement.querySelector('.wchs-hero-mod-zoom-val').textContent=this.value" />
				</div>
			</div>

			<!-- Animation -->
			<div class="wchs-field wchs-field--full" style="margin-top:12px">
				<label style="display:inline-flex;align-items:center;gap:6px">
					Animation
					<?php echo self::hint_icon( 'WebGL animated backgrounds layer above the image. Use one per page — stacking multiple WebGL heroes can exhaust the browser\'s GPU context limit.' ); ?>
				</label>
				<select data-field="hero_variant">
					<option value="text-only">None</option>
					<option value="webgl-noise">Smoke</option>
					<option value="webgl-variant-2">Plasma</option>
					<option value="webgl-variant-3">Voronoi</option>
					<option value="webgl-variant-4">Hex Grid</option>
					<option value="webgl-variant-5">Dot Matrix</option>
					<option value="webgl-variant-6">Bokeh</option>
					<option value="research-motion">Research motion (CSS)</option>
				</select>
			</div>

			<!-- CTA -->
			<div class="wchs-module__fields" style="margin-top:12px">
				<div class="wchs-field wchs-field--full">
					<label class="wchs-check"><input type="checkbox" data-field="hero_show_cta" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Show CTA button</span></label>
				</div>
				<div class="wchs-field"><label>CTA text</label><input type="text" data-field="hero_cta_text" placeholder="Learn more" /></div>
				<div class="wchs-field"><label>CTA link</label><input type="text" data-field="hero_cta_link" placeholder="/shop" /></div>
				<div class="wchs-field wchs-field--full"><label>Research badge</label><input type="text" data-field="hero_research_badge" placeholder="• RESEARCH USE ONLY" maxlength="120" /></div>
				<div class="wchs-field"><label>Secondary CTA text</label><input type="text" data-field="hero_cta_secondary_text" maxlength="80" /></div>
				<div class="wchs-field"><label>Secondary CTA link</label><input type="text" data-field="hero_cta_secondary_link" placeholder="/shop" /></div>
				<div class="wchs-field wchs-field--full">
					<label style="display:inline-flex;align-items:center;gap:6px">Research stats (JSON) <?php echo self::hint_icon( 'Array of {"value","label"} objects. Empty restores defaults.' ); ?></label>
					<textarea data-field="hero_research_stats_json" rows="5" class="large-text code" style="width:100%;font-family:monospace;font-size:11px" placeholder='[{"value":"≥99%","label":"VERIFIED PURITY"}]'></textarea>
				</div>
			</div>

			<!-- Typography override -->
			<div class="wchs-module__fields" style="margin-top:12px">
				<div class="wchs-field">
					<label>Headline size</label>
					<select data-field="hero_headline_size">
						<option value="s">Small</option>
						<option value="m">Medium</option>
						<option value="l">Large (default)</option>
						<option value="xl">Extra large</option>
					</select>
				</div>
				<div class="wchs-field">
					<label>Headline weight</label>
					<select data-field="hero_headline_weight">
						<option value="light">Light (300)</option>
						<option value="regular">Regular (400)</option>
						<option value="medium">Medium (500)</option>
						<option value="semibold">Semibold (600)</option>
						<option value="bold">Bold (700)</option>
						<option value="extrabold">Extra Bold (800)</option>
						<option value="black">Black (900)</option>
					</select>
				</div>
				<div class="wchs-field">
					<label>Headline font</label>
					<select data-field="hero_headline_font">
						<option value="inter">Inter (default)</option>
						<option value="barlow">Barlow</option>
						<option value="bebas">Bebas Neue</option>
						<option value="playfair">Playfair Display</option>
						<option value="space_grotesk">Space Grotesk</option>
						<option value="archivo">Archivo</option>
						<option value="oswald">Oswald</option>
					</select>
				</div>
				<div class="wchs-field">
					<label>Subheadline size</label>
					<select data-field="hero_subheadline_size">
						<option value="s">Small</option>
						<option value="m">Medium (default)</option>
						<option value="l">Large</option>
					</select>
				</div>
			</div>

			<!-- Accent override -->
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Pick a different accent for this hero only. Applies to the CTA button + any accent-colored text. Default inherits from Design tab accent.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>

			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- CTA button -->
		<div id="wchs-mod-tpl-cta" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field wchs-field--full"><label>Label</label><input type="text" data-field="cta_label" placeholder="Shop now" /></div>
				<div class="wchs-field wchs-field--full"><label>Link</label><input type="text" data-field="cta_href" placeholder="/shop" /></div>
				<div class="wchs-field">
					<label>Style</label>
					<select data-field="cta_style">
						<option value="primary">Primary (filled)</option>
						<option value="ghost">Ghost (outlined)</option>
						<option value="text">Text (inline)</option>
					</select>
				</div>
				<div class="wchs-field">
					<label>Size</label>
					<select data-field="cta_size">
						<option value="sm">Small</option>
						<option value="md" selected>Medium</option>
						<option value="lg">Large</option>
					</select>
				</div>
				<div class="wchs-field">
					<label>Alignment</label>
					<select data-field="cta_align">
						<option value="left">Left</option>
						<option value="center" selected>Center</option>
						<option value="right">Right</option>
					</select>
				</div>
				<div class="wchs-field wchs-field--full">
					<label class="wchs-check"><input type="checkbox" data-field="cta_open_new_tab" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Open in new tab</span></label>
				</div>
			</div>
			<div class="wchs-field wchs-overrides-row" style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5">
				<label style="display:inline-flex;align-items:center;gap:6px;font-weight:500">
					Accent color override
					<?php echo self::hint_icon( 'Pick a different accent for this button only. Default inherits from Design tab accent.' ); ?>
				</label>
				<?php echo self::accent_override_swatches(); ?>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Spacer -->
		<div id="wchs-mod-tpl-spacer" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field wchs-field--full">
					<label>Height (<span class="wchs-spacer-mod-h-val">40</span>px)</label>
					<input type="range" data-field="spacer_height" min="8" max="160" step="8" value="40"
						oninput="this.parentElement.querySelector('.wchs-spacer-mod-h-val').textContent=this.value" />
				</div>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Video / embed -->
		<div id="wchs-mod-tpl-video" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field wchs-field--full"><label>Title (optional eyebrow)</label><input type="text" data-field="title" placeholder="Watch the story" /></div>
				<div class="wchs-field wchs-field--full">
					<label>Video URL <?php echo self::hint_icon('YouTube, Vimeo, or direct .mp4 URL. YouTube uses youtube-nocookie.com, Vimeo uses player.vimeo.com. MP4 plays via native <video>.'); ?></label>
					<input type="text" data-field="source_url" placeholder="https://www.youtube.com/watch?v=... | https://vimeo.com/... | https://cdn.example.com/clip.mp4" />
				</div>
				<div class="wchs-field wchs-field--full">
					<label>Poster URL <?php echo self::hint_icon('Optional thumbnail shown before the video loads. Only used for direct MP4 — YouTube/Vimeo supply their own poster.'); ?></label>
					<div class="wchs-media-field" style="display:flex;gap:8px;align-items:center">
						<input type="text" data-field="poster_url" class="wchs-media-url" placeholder="No poster selected" style="flex:1;min-width:0" />
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-select">Select</button>
						<button type="button" class="wchs-btn wchs-btn--secondary wchs-media-remove" style="display:none">Remove</button>
					</div>
					<img class="wchs-media-preview" src="" alt="" style="display:none;max-width:140px;margin-top:8px;border:1px solid #e0e0e0" />
				</div>
				<div class="wchs-field">
					<label>Aspect ratio</label>
					<select data-field="aspect_ratio">
						<option value="16/9">16 : 9 (landscape)</option>
						<option value="4/3">4 : 3</option>
						<option value="1/1">1 : 1 (square)</option>
						<option value="9/16">9 : 16 (vertical)</option>
					</select>
				</div>
				<div class="wchs-field wchs-field--full">
					<label class="wchs-check"><input type="checkbox" data-field="controls" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Show playback controls</span></label>
				</div>
				<div class="wchs-field wchs-field--full">
					<label class="wchs-check"><input type="checkbox" data-field="autoplay" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Autoplay <?php echo self::hint_icon('Browsers only autoplay muted videos. Autoplay implies muted — both checkboxes will behave that way at render.'); ?></span></label>
				</div>
				<div class="wchs-field wchs-field--full">
					<label class="wchs-check"><input type="checkbox" data-field="muted" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Start muted</span></label>
				</div>
				<div class="wchs-field wchs-field--full">
					<label class="wchs-check"><input type="checkbox" data-field="loop" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Loop</span></label>
				</div>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>

		<!-- Logo Strip -->
		<div id="wchs-mod-tpl-logo_strip" style="display:none">
			<div class="wchs-module__fields">
				<div class="wchs-field wchs-field--full"><label>Title (optional eyebrow)</label><input type="text" data-field="title" placeholder="As featured in" /></div>
				<div class="wchs-field wchs-field--full">
					<label class="wchs-check"><input type="checkbox" data-field="logo_grayscale" value="1" /><span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span><span>Grayscale (colorize on hover)</span></label>
				</div>
			</div>
			<div class="wchs-field" style="margin-top:12px">
				<label>Logos</label>
				<div class="wchs-logo-strip-items">
					<div class="wchs-logo-strip-item" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;padding:8px;border:1px solid #ddd;background:#fafafa">
						<div style="flex-shrink:0;width:80px">
							<img src="" style="width:80px;height:48px;object-fit:contain;display:none;border:1px solid #ddd;background:#fff" class="wchs-logo-thumb" />
							<input type="hidden" value="" class="wchs-logo-src" />
							<button type="button" class="wchs-btn wchs-btn--secondary wchs-logo-pick" style="font-size:10px;padding:2px 6px;margin-top:4px;width:100%">Choose</button>
						</div>
						<div style="flex:1;display:flex;flex-direction:column;gap:4px">
							<input type="text" placeholder="Alt text" />
							<input type="text" placeholder="Link (optional)" />
						</div>
						<button type="button" class="wchs-accordion-item__remove" title="Remove" style="flex-shrink:0">&#10005;</button>
					</div>
				</div>
				<button type="button" class="wchs-btn wchs-btn--secondary wchs-add-logo-item-modal" style="margin-top:8px">+ Add logo</button>
			</div>
			<?php $this->render_module_common_fields(); ?>
		</div>
		<?php
	}

	private function render_font_select( string $name, string $selected ): void {
		$fonts = [
			'inter'        => 'Inter (default)',
			'barlow'       => 'Barlow',
			'bebas'        => 'Bebas Neue',
			'playfair'     => 'Playfair Display',
			'space_grotesk' => 'Space Grotesk',
			'archivo'      => 'Archivo',
			'oswald'       => 'Oswald',
		];
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $fonts as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( $selected, $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	}

	private function render_module_common_fields(): void {
		?>
		<div class="wchs-modal__common-fields">
			<div class="wchs-field">
				<label>Visibility</label>
				<select data-field="visibility">
					<option value="all">Everyone</option>
					<option value="members">Members only</option>
					<option value="guests">Guests only</option>
				</select>
			</div>
			<div class="wchs-field">
				<label>Vertical spacing</label>
				<select data-field="spacing_v">
					<option value="compact">Compact</option>
					<option value="normal">Normal</option>
					<option value="spacious">Spacious</option>
				</select>
			</div>
			<div class="wchs-field">
				<label>Width</label>
				<select data-field="spacing_h">
					<option value="compact">Full width</option>
					<option value="normal">Contained</option>
					<option value="spacious">Narrow</option>
				</select>
			</div>
			<div class="wchs-field">
				<label class="wchs-check">
					<input type="checkbox" data-field="center_header" value="1" />
					<span class="wchs-check__box"><svg class="wchs-check__svg" viewBox="0 0 12 12"><polyline points="2.5 6 5 8.5 9.5 3.5"/></svg></span>
					<span>Center header</span>
				</label>
			</div>
		</div>
		<div class="wchs-modal__common-fields wchs-modal__schedule">
			<div class="wchs-field">
				<label>Show from <?php echo self::hint_icon('Module hidden until this date/time. Leave blank for always-on. Use for upcoming launches, holiday banners, or scheduled promos.'); ?></label>
				<input type="datetime-local" data-field="start_at" />
			</div>
			<div class="wchs-field">
				<label>Show until <?php echo self::hint_icon('Module hidden after this date/time. Leave blank for no end. Use for flash sales, countdowns, or time-limited offers.'); ?></label>
				<input type="datetime-local" data-field="end_at" />
			</div>
		</div>
		<?php
	}
}
