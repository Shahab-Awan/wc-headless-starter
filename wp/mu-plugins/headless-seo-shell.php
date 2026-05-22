<?php
/**
 * Plugin Name: WCHS SEO Shell
 * Description: Serves the static SPA shell with route-specific raw SEO tags for crawlers and unfurlers that do not execute JavaScript.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_loaded', 'wchs_seo_shell_maybe_send', 5 );

function wchs_seo_shell_maybe_send(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$path    = wchs_seo_shell_request_path();
	$payload = wchs_seo_shell_payload_for_path( $path );
	if ( ! $payload ) {
		return;
	}

	wchs_seo_shell_send( $payload );
}

function wchs_seo_shell_request_path(): string {
	$raw = $_SERVER['REQUEST_URI'] ?? '';
	if ( ! is_string( $raw ) || '' === $raw ) {
		return '/';
	}

	$path = wp_parse_url( $raw, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		return '/';
	}

	$path = '/' . trim( $path, '/' );
	return '//' === $path ? '/' : $path;
}

function wchs_seo_shell_payload_for_path( string $path ): ?array {
	$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), static fn( $part ) => '' !== $part ) );
	if ( [] === $segments ) {
		return null;
	}

	if ( wchs_seo_shell_is_wp_owned_root( $segments[0] ) ) {
		return null;
	}

	if ( 'shop' === $segments[0] ) {
		if ( 1 === count( $segments ) ) {
			return wchs_seo_shell_shop_payload();
		}
		return 2 === count( $segments )
			? wchs_seo_shell_category_payload( sanitize_title( rawurldecode( $segments[1] ) ) )
			: wchs_seo_shell_not_found_payload( $path );
	}

	if ( 'product' === $segments[0] ) {
		return 2 === count( $segments )
			? wchs_seo_shell_product_payload( sanitize_title( rawurldecode( $segments[1] ) ) )
			: wchs_seo_shell_not_found_payload( $path );
	}

	if ( 'account' === $segments[0] ) {
		return wchs_seo_shell_private_payload( 'Account', 'Sign in to view your account.', $path );
	}

	if ( 'order-received' === $segments[0] ) {
		return wchs_seo_shell_private_payload( 'Order Received', 'Review your order confirmation.', $path );
	}

	if ( 1 === count( $segments ) ) {
		$slug = sanitize_title( rawurldecode( $segments[0] ) );
		$page = wchs_seo_shell_find_wchs_page( $slug );
		return $page ? wchs_seo_shell_page_payload( $page ) : wchs_seo_shell_not_found_payload( $path );
	}

	return null;
}

function wchs_seo_shell_is_wp_owned_root( string $root ): bool {
	return in_array(
		$root,
		[
			'cart',
			'checkout',
			'checkouts',
			'thankyou',
			'thank-you',
			'offer',
			'upsell',
			'my-account',
			'wp-admin',
			'wp-json',
			'wp-content',
			'wp-includes',
			'wc-auth',
			'wc-api',
			'feed',
			'comments',
		],
		true
	);
}

function wchs_seo_shell_site_context(): array {
	$settings = [];
	if ( class_exists( '\WCHS\Admin\AdminPage' ) && is_callable( [ '\WCHS\Admin\AdminPage', 'get_site_settings' ] ) ) {
		$settings = \WCHS\Admin\AdminPage::get_site_settings();
	} else {
		$saved = get_option( 'wchs_site_settings', [] );
		$settings = is_array( $saved ) ? $saved : [];
	}

	$origin = function_exists( 'wchs_spa_origin' ) ? wchs_spa_origin() : home_url( '', 'https' );
	$origin = untrailingslashit( is_string( $origin ) && '' !== $origin ? $origin : home_url( '', 'https' ) );

	$brand = defined( 'WCHS_BRAND_NAME' ) && is_string( WCHS_BRAND_NAME )
		? WCHS_BRAND_NAME
		: get_bloginfo( 'name' );

	$logo_id = (int) get_theme_mod( 'custom_logo', 0 );
	$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	return [
		'origin'   => $origin,
		'brand'    => wchs_seo_shell_plain_text( $brand ) ?: 'Online Store',
		'logo'     => is_string( $logo ) ? $logo : '',
		'settings' => $settings,
	];
}

function wchs_seo_shell_shop_payload(): array {
	$ctx   = wchs_seo_shell_site_context();
	$title = 'Shop';

	return [
		'title'       => wchs_seo_shell_full_title( $title, $ctx['brand'] ),
		'description' => $ctx['brand'] . ' - browse all products.',
		'canonical'   => $ctx['origin'] . '/shop',
		'type'        => 'website',
		'image'       => $ctx['logo'],
		'schemas'     => [
			wchs_seo_shell_breadcrumb_schema(
				$ctx,
				[
					[ 'name' => 'Shop', 'url' => $ctx['origin'] . '/shop' ],
				]
			),
		],
	];
}

function wchs_seo_shell_category_payload( string $slug ): array {
	$ctx = wchs_seo_shell_site_context();
	if ( '' === $slug || ! taxonomy_exists( 'product_cat' ) ) {
		return wchs_seo_shell_not_found_payload( '/shop/' . $slug );
	}

	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term instanceof WP_Term || (int) $term->count <= 0 ) {
		return wchs_seo_shell_not_found_payload( '/shop/' . $slug );
	}

	$title       = wchs_seo_shell_plain_text( $term->name );
	$description = wchs_seo_shell_plain_text( term_description( $term, 'product_cat' ) );
	if ( '' === $description ) {
		$description = $ctx['brand'] . ' - browse ' . $title . '.';
	}

	return [
		'title'       => wchs_seo_shell_full_title( $title, $ctx['brand'] ),
		'description' => $description,
		'canonical'   => $ctx['origin'] . '/shop/' . $term->slug,
		'type'        => 'website',
		'image'       => $ctx['logo'],
		'schemas'     => [
			wchs_seo_shell_breadcrumb_schema(
				$ctx,
				[
					[ 'name' => 'Shop', 'url' => $ctx['origin'] . '/shop' ],
					[ 'name' => $title, 'url' => $ctx['origin'] . '/shop/' . $term->slug ],
				]
			),
		],
	];
}

function wchs_seo_shell_product_payload( string $slug ): array {
	$ctx = wchs_seo_shell_site_context();
	if ( '' === $slug || ! function_exists( 'wc_get_product' ) ) {
		return wchs_seo_shell_not_found_payload( '/product/' . $slug );
	}

	$post = get_page_by_path( $slug, OBJECT, 'product' );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return wchs_seo_shell_not_found_payload( '/product/' . $slug );
	}

	$product = wc_get_product( $post->ID );
	if ( ! $product ) {
		return wchs_seo_shell_not_found_payload( '/product/' . $slug );
	}

	$title       = wchs_seo_shell_plain_text( $product->get_name() );
	$description = wchs_seo_shell_plain_text( $product->get_short_description() ?: $product->get_description() );
	if ( '' === $description ) {
		$description = $title . ' from ' . $ctx['brand'] . '.';
	}

	$image_id = $product->get_image_id();
	$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
	if ( ! $image ) {
		$gallery = $product->get_gallery_image_ids();
		$image   = ! empty( $gallery[0] ) ? wp_get_attachment_image_url( (int) $gallery[0], 'full' ) : '';
	}

	$canonical = $ctx['origin'] . '/product/' . $product->get_slug();
	$price     = $product->is_type( 'variable' ) ? $product->get_variation_price( 'min', true ) : $product->get_price();
	$currency  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

	$product_schema = [
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => $title,
		'description' => $description,
		'sku'         => $product->get_sku(),
		'url'         => $canonical,
		'offers'      => [
			'@type'         => 'Offer',
			'url'           => $canonical,
			'price'         => '' !== (string) $price ? wc_format_decimal( $price, wc_get_price_decimals() ) : '0',
			'priceCurrency' => $currency,
			'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			'itemCondition' => 'https://schema.org/NewCondition',
		],
	];
	if ( $image ) {
		$product_schema['image'] = $image;
	}

	$settings = is_array( $ctx['settings'] ) ? $ctx['settings'] : [];

	return [
		'title'       => wchs_seo_shell_full_title( $title, $ctx['brand'] ),
		'description' => $description,
		'canonical'   => $canonical,
		'type'        => 'product',
		'image'       => is_string( $image ) ? $image : '',
		'nosnippet'   => ! empty( $settings['seo_nosnippet_products'] ),
		'schemas'     => [
			$product_schema,
			wchs_seo_shell_breadcrumb_schema(
				$ctx,
				[
					[ 'name' => 'Shop', 'url' => $ctx['origin'] . '/shop' ],
					[ 'name' => $title, 'url' => $canonical ],
				]
			),
		],
	];
}

function wchs_seo_shell_page_payload( array $page ): array {
	$ctx   = wchs_seo_shell_site_context();
	$slug  = sanitize_title( (string) ( $page['slug'] ?? '' ) );
	$title = wchs_seo_shell_plain_text( $page['title'] ?? $slug );
	if ( '' === $title ) {
		$title = ucwords( str_replace( '-', ' ', $slug ) );
	}

	$description = wchs_seo_shell_page_description( $page );
	if ( '' === $description ) {
		$description = $title . ' - ' . $ctx['brand'] . '.';
	}

	return [
		'title'       => wchs_seo_shell_full_title( $title, $ctx['brand'] ),
		'description' => $description,
		'canonical'   => $ctx['origin'] . '/' . $slug,
		'type'        => 'website',
		'image'       => wchs_seo_shell_page_image( $page, $ctx['origin'] ) ?: $ctx['logo'],
		'schemas'     => [
			wchs_seo_shell_breadcrumb_schema(
				$ctx,
				[
					[ 'name' => $title, 'url' => $ctx['origin'] . '/' . $slug ],
				]
			),
		],
	];
}

function wchs_seo_shell_private_payload( string $title, string $description, string $path ): array {
	$ctx = wchs_seo_shell_site_context();
	return [
		'title'       => wchs_seo_shell_full_title( $title, $ctx['brand'] ),
		'description' => $description,
		'canonical'   => $ctx['origin'] . '/' . ltrim( $path, '/' ),
		'type'        => 'website',
		'noindex'     => true,
		'image'       => $ctx['logo'],
	];
}

function wchs_seo_shell_not_found_payload( string $path ): array {
	$ctx = wchs_seo_shell_site_context();
	return [
		'title'       => wchs_seo_shell_full_title( 'Not Found', $ctx['brand'] ),
		'description' => 'Page not found.',
		'canonical'   => $ctx['origin'] . '/' . ltrim( $path, '/' ),
		'type'        => 'website',
		'noindex'     => true,
		'image'       => $ctx['logo'],
	];
}

function wchs_seo_shell_find_wchs_page( string $slug ): ?array {
	foreach ( wchs_seo_shell_pages() as $page ) {
		$page_slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );
		if ( $slug === $page_slug ) {
			return $page;
		}
	}
	return null;
}

function wchs_seo_shell_pages(): array {
	if ( class_exists( '\WCHS\Admin\AdminPage' ) && is_callable( [ '\WCHS\Admin\AdminPage', 'get_pages_config' ] ) ) {
		$config = \WCHS\Admin\AdminPage::get_pages_config();
	} else {
		$config = get_option( 'wchs_pages_config', [] );
	}

	if ( isset( $config['pages'] ) && is_array( $config['pages'] ) ) {
		return array_values( array_filter( $config['pages'], 'is_array' ) );
	}

	return is_array( $config ) ? array_values( array_filter( $config, 'is_array' ) ) : [];
}

function wchs_seo_shell_page_description( array $page ): string {
	$modules = isset( $page['modules'] ) && is_array( $page['modules'] ) ? $page['modules'] : [];
	foreach ( $modules as $module ) {
		if ( ! is_array( $module ) || ( $module['type'] ?? '' ) !== 'text_block' ) {
			continue;
		}
		$config = isset( $module['config'] ) && is_array( $module['config'] ) ? $module['config'] : [];
		if ( ! empty( $config['content'] ) ) {
			return wchs_seo_shell_plain_text( $config['content'], 300 );
		}
	}
	return '';
}

function wchs_seo_shell_page_image( array $page, string $origin ): string {
	$modules = isset( $page['modules'] ) && is_array( $page['modules'] ) ? $page['modules'] : [];
	foreach ( $modules as $module ) {
		if ( ! is_array( $module ) ) {
			continue;
		}
		$config = isset( $module['config'] ) && is_array( $module['config'] ) ? $module['config'] : [];
		foreach ( [ 'image_desktop', 'image', 'src' ] as $key ) {
			if ( ! empty( $config[ $key ] ) ) {
				return wchs_seo_shell_absolute_url( (string) $config[ $key ], $origin );
			}
		}
		if ( ! empty( $config['items'][0] ) && is_array( $config['items'][0] ) ) {
			foreach ( [ 'src', 'image', 'image_desktop' ] as $key ) {
				if ( ! empty( $config['items'][0][ $key ] ) ) {
					return wchs_seo_shell_absolute_url( (string) $config['items'][0][ $key ], $origin );
				}
			}
		}
	}
	return '';
}

function wchs_seo_shell_send( array $payload ): void {
	$index_path = ABSPATH . 'index.html';
	if ( ! is_readable( $index_path ) ) {
		return;
	}

	$html = file_get_contents( $index_path );
	if ( ! is_string( $html ) || '' === $html ) {
		return;
	}

	$block = wchs_seo_shell_build_block( $payload );
	$start = '<!-- STATIC_SEO_START -->';
	$end   = '<!-- STATIC_SEO_END -->';
	$pattern = '/' . preg_quote( $start, '/' ) . '.*?' . preg_quote( $end, '/' ) . '/s';
	if ( preg_match( $pattern, $html ) ) {
		$html = preg_replace( $pattern, $block, $html, 1 );
	} else {
		$html = str_replace( '</head>', "\t\t" . $block . "\n\t</head>", $html );
	}

	status_header( 200 );
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	header( 'X-WCHS-SEO-Shell: 1' );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Full HTML shell with escaped replacement block.
	exit;
}

function wchs_seo_shell_build_block( array $payload ): string {
	$title       = wchs_seo_shell_plain_text( $payload['title'] ?? 'Online Store', 300 );
	$description = wchs_seo_shell_plain_text( $payload['description'] ?? '', 300 );
	$canonical   = esc_url( (string) ( $payload['canonical'] ?? '' ) );
	$type        = wchs_seo_shell_plain_text( $payload['type'] ?? 'website' ) ?: 'website';
	$image       = esc_url( (string) ( $payload['image'] ?? '' ) );

	$lines = [
		'<!-- STATIC_SEO_START -->',
		'<title data-static-seo="title">' . esc_html( $title ) . '</title>',
	];

	if ( '' !== $description ) {
		$lines[] = '<meta data-static-seo="description" name="description" content="' . esc_attr( $description ) . '" />';
	}
	if ( ! empty( $payload['noindex'] ) ) {
		$lines[] = '<meta data-static-seo="robots" name="robots" content="noindex" />';
	}
	if ( ! empty( $payload['nosnippet'] ) ) {
		$lines[] = '<meta data-static-seo="googlebot" name="googlebot" content="nosnippet, noimageindex" />';
	}
	if ( '' !== $canonical ) {
		$lines[] = '<link data-static-seo="canonical" rel="canonical" href="' . $canonical . '" />';
	}

	$lines[] = '<meta data-static-seo="og:type" property="og:type" content="' . esc_attr( $type ) . '" />';
	$lines[] = '<meta data-static-seo="og:title" property="og:title" content="' . esc_attr( $title ) . '" />';
	if ( '' !== $description ) {
		$lines[] = '<meta data-static-seo="og:description" property="og:description" content="' . esc_attr( $description ) . '" />';
	}
	if ( '' !== $canonical ) {
		$lines[] = '<meta data-static-seo="og:url" property="og:url" content="' . $canonical . '" />';
	}
	if ( '' !== $image ) {
		$lines[] = '<meta data-static-seo="og:image" property="og:image" content="' . $image . '" />';
	}

	$ctx = wchs_seo_shell_site_context();
	if ( ! empty( $ctx['brand'] ) ) {
		$lines[] = '<meta data-static-seo="og:site_name" property="og:site_name" content="' . esc_attr( $ctx['brand'] ) . '" />';
	}

	$lines[] = '<meta data-static-seo="twitter:card" name="twitter:card" content="' . ( '' !== $image ? 'summary_large_image' : 'summary' ) . '" />';
	$lines[] = '<meta data-static-seo="twitter:title" name="twitter:title" content="' . esc_attr( $title ) . '" />';
	if ( '' !== $description ) {
		$lines[] = '<meta data-static-seo="twitter:description" name="twitter:description" content="' . esc_attr( $description ) . '" />';
	}
	if ( '' !== $image ) {
		$lines[] = '<meta data-static-seo="twitter:image" name="twitter:image" content="' . $image . '" />';
	}

	$schemas = isset( $payload['schemas'] ) && is_array( $payload['schemas'] ) ? $payload['schemas'] : [];
	foreach ( $schemas as $schema ) {
		if ( ! is_array( $schema ) ) {
			continue;
		}
		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( is_string( $json ) && '' !== $json ) {
			$lines[] = '<script data-static-seo="schema" type="application/ld+json">' . str_replace( '<', '\u003c', $json ) . '</script>';
		}
	}

	$lines[] = '<!-- STATIC_SEO_END -->';
	return implode( "\n\t\t", $lines );
}

function wchs_seo_shell_breadcrumb_schema( array $ctx, array $items ): array {
	$list = [
		[
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => $ctx['brand'],
			'item'     => $ctx['origin'] . '/',
		],
	];
	foreach ( $items as $item ) {
		$list[] = [
			'@type'    => 'ListItem',
			'position' => count( $list ) + 1,
			'name'     => wchs_seo_shell_plain_text( $item['name'] ?? '' ),
			'item'     => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
		];
	}

	return [
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $list,
	];
}

function wchs_seo_shell_full_title( string $title, string $brand ): string {
	$title = wchs_seo_shell_plain_text( $title );
	$brand = wchs_seo_shell_plain_text( $brand );
	if ( '' === $title ) {
		return $brand;
	}
	if ( '' === $brand || false !== stripos( $title, $brand ) ) {
		return $title;
	}
	return $title . ' | ' . $brand;
}

function wchs_seo_shell_plain_text( $value, int $limit = 0 ): string {
	$text = is_scalar( $value ) ? (string) $value : '';
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
	$text = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
	if ( $limit > 0 && function_exists( 'mb_substr' ) ) {
		return mb_substr( $text, 0, $limit );
	}
	return $limit > 0 ? substr( $text, 0, $limit ) : $text;
}

function wchs_seo_shell_absolute_url( string $url, string $origin ): string {
	$url = trim( $url );
	if ( '' === $url ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $url ) ) {
		return $url;
	}
	return $origin . '/' . ltrim( $url, '/' );
}
