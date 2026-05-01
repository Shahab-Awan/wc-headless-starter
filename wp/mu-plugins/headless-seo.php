<?php
/**
 * Plugin Name: Headless SEO
 * Description: Serves /sitemap.xml + /robots.txt for the SPA. Pulls URLs
 *   from WC products + WCHS pages. WP answers these routes ahead of the
 *   .htaccess SPA fallback so crawlers see proper SEO payloads.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'redirect_canonical',
	function ( $redirect_url ) {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
		if ( ! is_string( $path ) ) {
			return $redirect_url;
		}

		if ( preg_match( '#^/wp-sitemap(?:-[a-z0-9_-]+)?\.xml$#i', $path ) || $path === '/wp-sitemap.xsl' ) {
			return false;
		}

		return $redirect_url;
	},
	1
);

// wp_loaded fires after WP + all plugins initialized, so wc_get_products
// is available. init fires too early — WC's own `init` hook runs later.
add_action(
	'wp_loaded',
	function () {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
		$is_wp_sitemap = is_string( $uri ) && preg_match( '#^/wp-sitemap(?:-[a-z0-9_-]+)?\.xml$#i', $uri );
		if ( $uri === '/sitemap.xml' || $is_wp_sitemap ) {
			wchs_seo_send_sitemap();
		} elseif ( $uri === '/robots.txt' ) {
			wchs_seo_send_robots();
		}
	},
	1
);

function wchs_seo_send_sitemap(): void {
	$origin   = home_url( '', 'https' );
	$now_iso  = gmdate( 'c' );
	$entries  = [];

	// Home + shop landing
	$entries[] = [ 'loc' => $origin . '/',     'lastmod' => $now_iso, 'changefreq' => 'daily',  'priority' => '1.0' ];
	$entries[] = [ 'loc' => $origin . '/shop', 'lastmod' => $now_iso, 'changefreq' => 'daily',  'priority' => '0.9' ];

	// Category landing pages. These are the canonical, indexable category
	// URLs; transient UI filters/sorts remain query params and canonicalize
	// back to these clean paths.
	$terms = get_terms( [
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
	] );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( empty( $term->slug ) || 'uncategorized' === $term->slug ) {
				continue;
			}
			$entries[] = [
				'loc'        => $origin . '/shop/' . $term->slug,
				'lastmod'    => $now_iso,
				'changefreq' => 'weekly',
				'priority'   => '0.7',
			];
		}
	}

	// Products
	if ( function_exists( 'wc_get_products' ) ) {
		$products = wc_get_products( [
			'status'  => 'publish',
			'limit'   => -1,
			'return'  => 'ids',
		] );
		foreach ( $products as $pid ) {
			$slug = get_post_field( 'post_name', $pid );
			if ( ! $slug ) continue;
			$mod  = get_post_field( 'post_modified_gmt', $pid );
			$entries[] = [
				'loc'        => $origin . '/product/' . $slug,
				'lastmod'    => $mod ? gmdate( 'c', strtotime( $mod . ' UTC' ) ) : $now_iso,
				'changefreq' => 'weekly',
				'priority'   => '0.8',
			];
		}
	}

	// WCHS-authored pages (privacy, terms, FAQ, etc.)
	if ( class_exists( '\WCHS\Admin\AdminPage' ) && is_callable( [ '\WCHS\Admin\AdminPage', 'get_pages_config' ] ) ) {
		$pages = \WCHS\Admin\AdminPage::get_pages_config();
	} elseif ( class_exists( '\WCHS\Admin\WCHS_Settings_Page' ) ) {
		$pages = \WCHS\Admin\WCHS_Settings_Page::get_pages_config();
	} elseif ( class_exists( 'WCHS_Settings_Page' ) ) {
		$pages = WCHS_Settings_Page::get_pages_config();
	} else {
		$opt = get_option( 'wchs_pages_config', [] );
		$pages = is_array( $opt ) ? $opt : [];
	}
	if ( is_array( $pages ) && isset( $pages['pages'] ) && is_array( $pages['pages'] ) ) {
		$pages = $pages['pages'];
	}
	if ( is_array( $pages ) ) {
		foreach ( $pages as $p ) {
			if ( ! is_array( $p ) ) continue;
			$slug = isset( $p['slug'] ) ? sanitize_title( $p['slug'] ) : '';
			if ( ! $slug ) continue;
			$entries[] = [
				'loc'        => $origin . '/' . $slug,
				'lastmod'    => $now_iso,
				'changefreq' => 'monthly',
				'priority'   => '0.5',
			];
		}
	}

	// Emit XML
	nocache_headers();
	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $entries as $e ) {
		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url( $e['loc'] ) . "</loc>\n";
		echo "\t\t<lastmod>" . esc_html( $e['lastmod'] ) . "</lastmod>\n";
		echo "\t\t<changefreq>" . esc_html( $e['changefreq'] ) . "</changefreq>\n";
		echo "\t\t<priority>" . esc_html( $e['priority'] ) . "</priority>\n";
		echo "\t</url>\n";
	}
	echo '</urlset>';
	exit;
}

function wchs_seo_send_robots(): void {
	$origin = home_url( '', 'https' );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo "User-agent: *\n";
	echo "Allow: /\n";
	echo "Allow: /wp-json/wchs/v1/config\n";
	echo "Allow: /wp-json/wchs/v1/reviews\n";
	echo "Allow: /wp-json/wc/store/v1/products\n";
	echo "Disallow: /cart\n";
	echo "Disallow: /checkout\n";
	echo "Disallow: /account\n";
	echo "Disallow: /wp-admin/\n";
	echo "Disallow: /wp-json/\n";
	echo "\n";
	echo "Sitemap: " . esc_url( $origin . '/sitemap.xml' ) . "\n";
	exit;
}
