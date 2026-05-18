<?php
/**
 * Local showcase seed for wc-headless-starter.
 *
 * This file is executed inside the wpcli container by scripts/seed-showcase.sh.
 * It intentionally replaces the local product catalog so screenshots do not
 * inherit old project data from a developer's reused Docker volume.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

if ( ! function_exists( 'wc_get_product' ) ) {
	WP_CLI::error( 'WooCommerce must be active before running showcase seed. Run ./scripts/seed.sh first.' );
}

const WCHS_SHOWCASE_ASSET_DIR = '/tmp/wchs-showcase-assets';

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

function wchs_showcase_asset_path( string $filename ): string {
	return WCHS_SHOWCASE_ASSET_DIR . '/' . $filename;
}

function wchs_showcase_delete_existing_catalog(): void {
	$attachment_ids = get_posts( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'meta_key'       => '_wchs_showcase_asset',
		'meta_compare'   => 'EXISTS',
		'suppress_filters' => false,
	] );
	foreach ( $attachment_ids as $attachment_id ) {
		wp_delete_attachment( (int) $attachment_id, true );
	}

	$product_ids = get_posts( [
		'post_type'      => [ 'product_variation', 'product' ],
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'orderby'        => 'post_type',
		'order'          => 'ASC',
		'suppress_filters' => false,
	] );
	foreach ( $product_ids as $product_id ) {
		wp_delete_post( (int) $product_id, true );
	}

	$terms = get_terms( [
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	] );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( 'uncategorized' === $term->slug ) {
				continue;
			}
			wp_delete_term( (int) $term->term_id, 'product_cat' );
		}
	}
}

function wchs_showcase_import_asset( string $filename, string $title, int $post_id = 0 ): array {
	$source = wchs_showcase_asset_path( $filename );
	if ( ! is_readable( $source ) ) {
		WP_CLI::error( "Missing showcase asset: {$filename}" );
	}

	$upload = wp_upload_bits( $filename, null, file_get_contents( $source ) );
	if ( ! empty( $upload['error'] ) ) {
		WP_CLI::error( "Could not import {$filename}: {$upload['error']}" );
	}

	$filetype      = wp_check_filetype( $upload['file'], null );
	$attachment_id = wp_insert_attachment(
		[
			'post_mime_type' => $filetype['type'] ?: 'image/webp',
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		],
		$upload['file'],
		$post_id
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		WP_CLI::error( "Could not create attachment for {$filename}" );
	}

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	update_post_meta( $attachment_id, '_wchs_showcase_asset', $filename );

	return [
		'id'  => (int) $attachment_id,
		'url' => (string) wp_get_attachment_image_url( $attachment_id, 'full' ),
	];
}

function wchs_showcase_term( string $name, string $slug, int $image_id = 0 ): int {
	$existing = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $existing ) {
		$term_id = (int) $existing->term_id;
	} else {
		$created = wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] );
		if ( is_wp_error( $created ) ) {
			WP_CLI::error( "Could not create category {$name}: " . $created->get_error_message() );
		}
		$term_id = (int) $created['term_id'];
	}

	if ( $image_id > 0 ) {
		update_term_meta( $term_id, 'thumbnail_id', $image_id );
	}

	return $term_id;
}

function wchs_showcase_create_product( array $args ): int {
	$product = new WC_Product_Simple();
	$product->set_name( $args['name'] );
	$product->set_slug( $args['slug'] );
	$product->set_sku( $args['sku'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_regular_price( (string) $args['price'] );
	if ( ! empty( $args['sale_price'] ) ) {
		$product->set_sale_price( (string) $args['sale_price'] );
	}
	$product->set_manage_stock( true );
	$product->set_stock_quantity( (int) ( $args['stock'] ?? 80 ) );
	$product->set_stock_status( 'instock' );
	$product->set_featured( ! empty( $args['featured'] ) );
	$product->set_short_description( $args['short'] );
	$product->set_description( $args['description'] );
	$product->set_category_ids( array_map( 'intval', $args['category_ids'] ) );
	$product->set_image_id( (int) $args['image_id'] );
	$product_id = $product->save();

	update_post_meta( $product_id, '_wchs_showcase_product', '1' );
	return (int) $product_id;
}

function wchs_showcase_review( int $product_id, string $author, string $content, int $rating ): void {
	$comment_id = wp_insert_comment( [
		'comment_post_ID'      => $product_id,
		'comment_author'       => $author,
		'comment_author_email' => sanitize_title( $author ) . '@example.com',
		'comment_content'      => $content,
		'comment_type'         => 'review',
		'comment_approved'     => 1,
		'comment_date'         => current_time( 'mysql' ),
	] );

	if ( $comment_id ) {
		update_comment_meta( $comment_id, 'rating', max( 1, min( 5, $rating ) ) );
	}
}

function wchs_showcase_sync_review_meta( array $product_ids ): void {
	foreach ( $product_ids as $product_id ) {
		$comments = get_comments( [
			'post_id' => (int) $product_id,
			'status'  => 'approve',
			'type'    => 'review',
		] );

		$rating_count = [];
		$total        = 0;
		$count        = 0;
		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
			if ( $rating < 1 || $rating > 5 ) {
				continue;
			}
			$rating_count[ $rating ] = ( $rating_count[ $rating ] ?? 0 ) + 1;
			$total += $rating;
			$count++;
		}

		$average = $count > 0 ? number_format( $total / $count, 2, '.', '' ) : '0';
		update_post_meta( $product_id, '_wc_average_rating', $average );
		update_post_meta( $product_id, '_wc_review_count', $count );
		update_post_meta( $product_id, '_wc_rating_count', $rating_count );
		clean_post_cache( $product_id );
	}
}

wchs_showcase_delete_existing_catalog();

update_option( 'blogname', 'Northstar Supply' );
update_option( 'blogdescription', 'Modern everyday goods for a polished headless WooCommerce demo.' );
update_option( 'woocommerce_store_address', '100 Market Street' );
update_option( 'woocommerce_store_city', 'Denver' );
update_option( 'woocommerce_default_country', 'US:CO' );
update_option( 'woocommerce_store_postcode', '80202' );
update_option( 'woocommerce_currency', 'USD' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_onboarding_profile', [ 'completed' => true ] );
remove_theme_mod( 'custom_logo' );
delete_option( 'wchs_script_registry' );

$assets = [
	'hero_desktop' => wchs_showcase_import_asset( 'hero-desktop.webp', 'Northstar hero desktop' ),
	'hero_mobile'  => wchs_showcase_import_asset( 'hero-mobile.webp', 'Northstar hero mobile' ),
	'daypack'      => wchs_showcase_import_asset( 'product-daypack.webp', 'Northstar field pack' ),
	'tote'         => wchs_showcase_import_asset( 'product-tote.webp', 'Canvas market tote' ),
	'tumbler'      => wchs_showcase_import_asset( 'product-tumbler.webp', 'Sage travel tumbler' ),
	'lamp'         => wchs_showcase_import_asset( 'product-lamp.webp', 'Halo desk lamp' ),
	'notebook'     => wchs_showcase_import_asset( 'product-notebook.webp', 'Field notebook set' ),
	'pouch'        => wchs_showcase_import_asset( 'product-pouch.webp', 'Tech organizer pouch' ),
];

$cats = [
	'packs'     => wchs_showcase_term( 'Packs', 'packs', $assets['daypack']['id'] ),
	'workspace' => wchs_showcase_term( 'Workspace', 'workspace', $assets['lamp']['id'] ),
	'carry'     => wchs_showcase_term( 'Everyday Carry', 'everyday-carry', $assets['tote']['id'] ),
	'drinkware' => wchs_showcase_term( 'Drinkware', 'drinkware', $assets['tumbler']['id'] ),
];

$products = [];
$products['daypack'] = wchs_showcase_create_product( [
	'name'         => 'Northstar Field Pack',
	'slug'         => 'northstar-field-pack',
	'sku'          => 'WCHS-DEMO-DAYPACK',
	'price'        => '148.00',
	'stock'        => 64,
	'featured'     => true,
	'image_id'     => $assets['daypack']['id'],
	'category_ids' => [ $cats['packs'], $cats['carry'] ],
	'short'        => 'A structured daily pack with clean lines, padded storage, and a compact travel profile.',
	'description'  => '<p>The Northstar Field Pack is sized for everyday movement: laptop sleeve, quick-access front pocket, and a durable woven shell that keeps the silhouette sharp.</p>',
] );
$products['tote'] = wchs_showcase_create_product( [
	'name'         => 'Canvas Market Tote',
	'slug'         => 'canvas-market-tote',
	'sku'          => 'WCHS-DEMO-TOTE',
	'price'        => '68.00',
	'stock'        => 92,
	'featured'     => true,
	'image_id'     => $assets['tote']['id'],
	'category_ids' => [ $cats['carry'] ],
	'short'        => 'A sturdy canvas carryall with soft structure and enough room for the daily run.',
	'description'  => '<p>Heavyweight cotton canvas, reinforced handles, and a clean interior pocket make this a reliable tote for work, errands, and weekend packing.</p>',
] );
$products['tumbler'] = wchs_showcase_create_product( [
	'name'         => 'Sage Travel Tumbler',
	'slug'         => 'sage-travel-tumbler',
	'sku'          => 'WCHS-DEMO-TUMBLER',
	'price'        => '34.00',
	'stock'        => 120,
	'featured'     => true,
	'image_id'     => $assets['tumbler']['id'],
	'category_ids' => [ $cats['drinkware'] ],
	'short'        => 'A matte ceramic tumbler built for calm desk mornings and commutes.',
	'description'  => '<p>Double-wall insulation, a comfortable lid, and a muted sage finish keep the tumbler easy to use and easy to pair with the rest of the collection.</p>',
] );
$products['lamp'] = wchs_showcase_create_product( [
	'name'         => 'Halo Desk Lamp',
	'slug'         => 'halo-desk-lamp',
	'sku'          => 'WCHS-DEMO-LAMP',
	'price'        => '126.00',
	'stock'        => 38,
	'featured'     => true,
	'image_id'     => $assets['lamp']['id'],
	'category_ids' => [ $cats['workspace'] ],
	'short'        => 'A compact brushed-steel lamp with soft, focused light for late work sessions.',
	'description'  => '<p>The Halo Desk Lamp brings a warm pool of light to small work surfaces without visual clutter. Its weighted base keeps it stable and minimal.</p>',
] );
$products['notebook'] = wchs_showcase_create_product( [
	'name'         => 'Field Notebook Set',
	'slug'         => 'field-notebook-set',
	'sku'          => 'WCHS-DEMO-NOTEBOOK',
	'price'        => '28.00',
	'stock'        => 150,
	'image_id'     => $assets['notebook']['id'],
	'category_ids' => [ $cats['workspace'] ],
	'short'        => 'Two linen-cover notebooks with smooth paper for planning, sketches, and lists.',
	'description'  => '<p>Each set includes one ruled notebook and one dot-grid notebook, both bound in understated linen covers with lay-flat stitching.</p>',
] );
$products['pouch'] = wchs_showcase_create_product( [
	'name'         => 'Tech Organizer Pouch',
	'slug'         => 'tech-organizer-pouch',
	'sku'          => 'WCHS-DEMO-POUCH',
	'price'        => '44.00',
	'sale_price'   => '38.00',
	'stock'        => 87,
	'image_id'     => $assets['pouch']['id'],
	'category_ids' => [ $cats['carry'], $cats['workspace'] ],
	'short'        => 'A padded organizer for cables, adapters, pens, and small daily tools.',
	'description'  => '<p>Elastic loops, a soft divider, and a compact profile make the pouch easy to drop into a tote or pack without hunting for loose cables.</p>',
] );

$ordered_ids = array_values( $products );
foreach ( $ordered_ids as $index => $product_id ) {
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		continue;
	}
	$cross_sells = array_values( array_filter( $ordered_ids, fn( $id ) => $id !== $product_id ) );
	$product->set_cross_sell_ids( array_slice( array_merge( array_slice( $cross_sells, $index ), $cross_sells ), 0, 3 ) );
	$product->save();
}

wchs_showcase_review( $products['daypack'], 'Maya R.', 'The pack looks sharp in person and the checkout handoff was smooth during testing.', 5 );
wchs_showcase_review( $products['lamp'], 'Jon P.', 'Clean product page, fast cart, and the lamp image really sells the workspace look.', 5 );
wchs_showcase_review( $products['tote'], 'Nina K.', 'The demo catalog feels complete enough to judge the storefront flow quickly.', 4 );
wchs_showcase_sync_review_meta( array_values( $products ) );

$settings = get_option( 'wchs_site_settings', [] );
if ( ! is_array( $settings ) ) {
	$settings = [];
}
$settings['access_mode']            = 3;
$settings['accent_color']           = '#059669';
$settings['static_seo_title']       = 'Northstar Supply - Headless WooCommerce Demo';
$settings['static_seo_description'] = 'A polished demo catalog for the wc-headless-starter local development environment.';
$settings['domain_origin_mode']     = 'custom';
$settings['custom_spa_origin']      = getenv( 'WCHS_SHOWCASE_SPA_ORIGIN' ) ?: 'http://localhost:5175';
$settings['custom_allowed_origins'] = [ $settings['custom_spa_origin'] ];
$settings['custom_return_origins']  = [ $settings['custom_spa_origin'] ];
$settings['gtm_id']                 = '';
$settings['omnisend_brand_id']      = '';
$settings['klaviyo_public_key']     = '';
$settings['meta_pixel_id']          = '';
$settings['tiktok_pixel_id']        = '';
$settings['pinterest_tag_id']       = '';
$settings['clarity_project_id']     = '';
$settings['hotjar_site_id']         = '';
$settings['google_ads_conversion_id'] = '';
$settings['google_ads_conversion_label'] = '';
$settings['active_scripts']          = [];
$settings['review_provider']        = 'woocommerce';
$settings['review_provider_keys']   = [];
$settings['smtp']                   = [
	'enabled'    => false,
	'host'       => '',
	'port'       => 465,
	'secure'     => 'ssl',
	'username'   => '',
	'password'   => '',
	'from_email' => '',
	'from_name'  => '',
];
$settings['turnstile_site_key']     = '';
$settings['turnstile_secret_key']   = '';
$settings['theme_default']          = 'light';
$settings['header_links']           = [
	[ 'label' => 'Shop', 'url' => '/shop', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'COA Library', 'url' => '/coa-library', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'About', 'url' => '/about', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'FAQ', 'url' => '/faq', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'Account', 'url' => '/account', 'display' => 'icon', 'icon' => 'user', 'accent' => true, 'mobile_pin' => false ],
];
$settings['header_show_toggle']       = false;
$settings['header_toggle_accent']     = true;
$settings['header_cart_accent']       = true;
$settings['header_borderless']        = false;
$settings['mobile_hamburger_side']    = 'right';
$settings['logo_dark_id']             = 0;
$settings['logo_size']                = 'standard';
$settings['brand_position']           = 'left';
$settings['typography_heading_font']  = 'space_grotesk';
$settings['typography_body_font']     = 'inter';
$settings['typography_heading_weight'] = 'semibold';
$settings['typography_body_size']     = 'm';
$settings['product_card']             = [
	'media_aspect_ratio'       => '1:1',
	'corner_radius'            => 'soft',
	'border'                   => 'full',
	'hover_effect'             => 'lift',
	'button_style'             => 'outline',
	'badge_position'           => 'top-right',
	'badge_style'              => 'filled',
	'show_bulk_badge'          => true,
	'show_tier_hint'           => true,
	'show_oos_cards'           => true,
	'oos_treatment'            => 'grayscale',
	'title_lines'              => 'auto',
	'secondary_image_on_hover' => false,
	'sale_badge_text'          => 'Sale',
];
$settings['tokens']                   = [
	'radius'             => 8,
	'spacing_v_compact'  => 20,
	'spacing_v_normal'   => 52,
	'spacing_v_spacious' => 88,
];
$settings['footer']                   = [
	'tagline' => 'A compact demo store for static headless WooCommerce deployments.',
	'columns' => [
		[ 'title' => 'Shop', 'links' => [
			[ 'label' => 'All products', 'url' => '/shop' ],
			[ 'label' => 'Packs', 'url' => '/shop/packs' ],
			[ 'label' => 'Workspace', 'url' => '/shop/workspace' ],
		] ],
		[ 'title' => 'Store', 'links' => [
			[ 'label' => 'About', 'url' => '/about' ],
			[ 'label' => 'FAQ', 'url' => '/faq' ],
			[ 'label' => 'Account', 'url' => '/account' ],
		] ],
	],
];
$settings['social_links']             = [
	[ 'platform' => 'instagram', 'url' => 'https://example.com' ],
	[ 'platform' => 'youtube', 'url' => 'https://example.com' ],
];
update_option( 'wchs_site_settings', $settings );

$featured_ids = [ $products['daypack'], $products['tote'], $products['lamp'], $products['pouch'] ];
$homepage     = [
	'hero'    => [
		'headline'                => 'Everyday Goods. Better Built.',
		'content_mode'            => 'text',
		'logo_source'             => 'site_logo',
		'logo_url'                => '',
		'logo_dark_url'           => '',
		'logo_size'               => 'large',
		'headline_size'           => 'xl',
		'headline_weight'         => 'semibold',
		'headline_font'           => 'space_grotesk',
		'text_color_mode'         => 'white',
		'subheadline'             => 'A polished static storefront backed by native WooCommerce checkout, products, accounts, and admin tools.',
		'subheadline_size'        => 'm',
		'cta_text'                => 'Shop the demo',
		'cta_link'                => '/shop',
		'variant'                 => 'webgl-variant-4',
		'layout'                  => 'left',
		'image_desktop'           => $assets['hero_desktop']['url'],
		'image_mobile'            => $assets['hero_mobile']['url'],
		'image_position_x'        => 62,
		'image_position_y'        => 52,
		'image_position_mobile_x' => 50,
		'image_position_mobile_y' => 45,
		'image_zoom'              => 100,
		'image_zoom_mobile'       => 100,
		'show_eyebrow'            => true,
		'show_rating'             => true,
		'rating_text'             => '4.8 average demo rating',
		'cta_accent'              => true,
		'show_cta'                => true,
		'trust_items'             => [
			[ 'icon' => 'shipping', 'text' => 'Free shipping threshold' ],
			[ 'icon' => 'lock', 'text' => 'Native Woo checkout' ],
			[ 'icon' => 'zap', 'text' => 'Static SPA speed' ],
		],
	],
	'modules' => [
		[
			'id'            => 'demo-trust',
			'type'          => 'trust_bar',
			'visibility'    => 'all',
			'spacing_v'     => 'compact',
			'spacing_h'     => 'normal',
			'center_header' => true,
			'overrides'     => [ 'accent_color' => '#059669' ],
			'config'        => [
				'title'       => 'Built for the messy middle of real WooCommerce',
				'icon_accent' => true,
				'items'       => [
					[ 'icon' => 'shield', 'headline' => 'WP stays in charge', 'description' => 'Checkout, accounts, orders, and gateway hooks remain native.' ],
					[ 'icon' => 'zap', 'headline' => 'Static frontend', 'description' => 'SvelteKit builds to files that Apache can serve on shared hosting.' ],
					[ 'icon' => 'grid', 'headline' => 'Admin controlled', 'description' => 'Hero, modules, cards, scripts, and access modes are edited in wp-admin.' ],
				],
			],
		],
		[
			'id'            => 'featured-products',
			'type'          => 'product_slider',
			'visibility'    => 'all',
			'spacing_v'     => 'normal',
			'spacing_h'     => 'normal',
			'center_header' => false,
			'config'        => [
				'title'       => 'Featured products',
				'source'      => 'manual',
				'category'    => null,
				'product_ids' => $featured_ids,
			],
		],
		[
			'id'            => 'workspace-feature',
			'type'          => 'split_features',
			'visibility'    => 'all',
			'spacing_v'     => 'spacious',
			'spacing_h'     => 'normal',
			'center_header' => false,
			'config'        => [
				'layout' => 'alternating',
				'title' => 'Launch with content that already looks like a store',
				'items' => [
					[
						'eyebrow'     => 'Storefront modules',
						'heading'     => 'Hero sections, sliders, galleries, FAQs, and CTA blocks are all admin-driven.',
						'description' => '<p>The demo uses the same module pipeline a real store uses, so screenshots reflect actual runtime configuration.</p>',
						'image'       => $assets['hero_mobile']['url'],
					],
					[
						'eyebrow'     => 'Commerce paths',
						'heading'     => 'Products, cart, checkout, my-account, and order received stay connected to WooCommerce.',
						'description' => '<p>The SPA handles browsing and cart UX while native WooCommerce handles the parts plugins expect.</p>',
						'image'       => $assets['daypack']['url'],
					],
				],
			],
		],
		[
			'id'            => 'shop-categories',
			'type'          => 'category_grid',
			'visibility'    => 'all',
			'spacing_v'     => 'normal',
			'spacing_h'     => 'normal',
			'center_header' => false,
			'config'        => [
				'title'   => 'Shop by category',
				'columns' => 4,
				'gap'     => 12,
				'items'   => [
					[ 'category_id' => $cats['packs'], 'image' => $assets['daypack']['url'] ],
					[ 'category_id' => $cats['workspace'], 'image' => $assets['lamp']['url'] ],
					[ 'category_id' => $cats['carry'], 'image' => $assets['tote']['url'] ],
					[ 'category_id' => $cats['drinkware'], 'image' => $assets['tumbler']['url'] ],
				],
			],
		],
		[
			'id'            => 'demo-faq',
			'type'          => 'accordion',
			'visibility'    => 'all',
			'spacing_v'     => 'normal',
			'spacing_h'     => 'normal',
			'center_header' => true,
			'config'        => [
				'title' => 'Starter questions',
				'items' => [
					[ 'q' => 'Does this require Node.js in production?', 'a' => '<p>No. The SPA builds to static files and WordPress remains the runtime backend.</p>' ],
					[ 'q' => 'Can I still use WooCommerce plugins?', 'a' => '<p>Yes for checkout, accounts, order hooks, gateways, taxes, shipping, and admin-side jobs. SPA-visible widgets may need custom integration.</p>' ],
					[ 'q' => 'Where do storefront edits happen?', 'a' => '<p>Most brand, module, script, checkout, and access settings live under the WCHS menu in wp-admin.</p>' ],
				],
			],
		],
	],
];
update_option( 'wchs_homepage_config', $homepage );

update_option( 'wchs_shop_config', [
	'cols_min'  => 2,
	'cols_max'  => 4,
	'spacing_h' => 'normal',
	'modules'   => [
		[
			'id'            => 'shop-help',
			'type'          => 'text_block',
			'visibility'    => 'all',
			'spacing_v'     => 'compact',
			'spacing_h'     => 'spacious',
			'center_header' => true,
			'config'        => [
				'title'   => 'Demo catalog',
				'content' => '<p>Use this local seed to test product cards, category routing, cart behavior, checkout handoff, and responsive layouts without real client content.</p>',
			],
		],
	],
] );

update_option( 'wchs_pdp_config', [
	'show_reviews'    => true,
	'cross_sell_mode' => 'simple',
	'modules'         => [
		[
			'id'            => 'pdp-related',
			'type'          => 'product_slider',
			'visibility'    => 'all',
			'spacing_v'     => 'normal',
			'spacing_h'     => 'normal',
			'center_header' => false,
			'config'        => [
				'title'       => 'Complete the setup',
				'source'      => 'manual',
				'category'    => null,
				'product_ids' => [ $products['tote'], $products['tumbler'], $products['notebook'], $products['pouch'] ],
			],
		],
	],
] );

update_option( 'wchs_pages_config', [
	'pages' => [
		[
			'slug'    => 'about',
			'title'   => 'About Northstar Supply',
			'modules' => [
				[
					'id'            => 'about-hero',
					'type'          => 'hero',
					'visibility'    => 'all',
					'overrides'     => [ 'accent_color' => '#059669' ],
					'config'        => [
						'headline'         => 'A demo brand for real storefront flows.',
						'subheadline'      => 'Northstar Supply exists to make local WCHS development look and feel like a finished commerce site.',
						'show_cta'         => true,
						'cta_text'         => 'Browse products',
						'cta_link'         => '/shop',
						'layout'           => 'bottom',
						'variant'          => 'webgl-variant-2',
						'image_desktop'    => $assets['hero_desktop']['url'],
						'image_mobile'     => $assets['hero_mobile']['url'],
						'image_position_x' => 55,
						'image_position_y' => 50,
						'image_zoom'       => 105,
						'headline_size'    => 'l',
						'headline_weight'  => 'semibold',
						'headline_font'    => 'space_grotesk',
						'subheadline_size' => 'm',
						'text_color_mode'  => 'white',
					],
				],
				[
					'id'            => 'about-copy',
					'type'          => 'text_block',
					'visibility'    => 'all',
					'spacing_v'     => 'normal',
					'spacing_h'     => 'spacious',
					'center_header' => false,
					'config'        => [
						'title'   => 'Why this seed exists',
						'content' => '<p>A blank WooCommerce install can make the frontend feel flatter than it is. This seed adds enough products, categories, media, modules, and settings to verify the real WCHS experience quickly.</p>',
					],
				],
			],
		],
		[
			'slug'    => 'faq',
			'title'   => 'FAQ',
			'modules' => [
				[
					'id'            => 'faq-main',
					'type'          => 'accordion',
					'visibility'    => 'all',
					'spacing_v'     => 'normal',
					'spacing_h'     => 'spacious',
					'center_header' => true,
					'config'        => [
						'title' => 'Common setup questions',
						'items' => [
							[ 'q' => 'What does WCHS deploy?', 'a' => '<p>It deploys WordPress mu-plugins, a minimal theme shim, guarded Apache routing, and a static SvelteKit build.</p>' ],
							[ 'q' => 'Where should secrets live?', 'a' => '<p>Use .env locally, wp-config.php constants or WordPress admin settings on the site, and GitHub Actions secrets for CI/CD.</p>' ],
							[ 'q' => 'Can I change the demo content?', 'a' => '<p>Yes. The seed is only a starting point for local screenshots and QA.</p>' ],
						],
					],
				],
			],
		],
	],
] );

if ( class_exists( 'WC_Cache_Helper' ) ) {
	WC_Cache_Helper::get_transient_version( 'product', true );
}
delete_transient( 'wc_term_counts' );
wp_cache_flush();
flush_rewrite_rules();

WP_CLI::success( 'Seeded Northstar Supply showcase catalog, WCHS settings, pages, and media.' );
