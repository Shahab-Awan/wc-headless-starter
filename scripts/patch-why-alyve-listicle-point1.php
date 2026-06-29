<?php
/**
 * Update Why Alyve listicle hero + point 01 + default icons on all 8 points.
 * Run: wp eval-file scripts/patch-why-alyve-listicle-point1.php
 */
defined( 'ABSPATH' ) || exit;

$slug = 'why-alyve';
$cfg  = get_option( 'wchs_pages_config', [ 'pages' => [] ] );
if ( ! is_array( $cfg['pages'] ?? null ) ) {
	WP_CLI::error( 'wchs_pages_config.pages is missing or invalid.' );
}

$point_one = [
	'icon'     => 'shipping',
	'headline' => 'Domestic Fulfillment, Direct to Your Lab',
	'body'     => '<p>Every Alyve order is fulfilled through our U.S. operations with an emphasis on transparency and dependable service. From sourcing to shipment, products are carefully handled and prepared under established quality practices to help maintain consistency. No unknown middlemen and no complicated fulfillment chains.</p><div class="listicle__highlight-callout"><p>Orders placed before 2PM EST ship same day. Delivered in 2–3 business days via tracked carrier.</p></div>',
	'badges'   => [ 'Quality Standards', 'Supply Chain Transparency', 'Direct Fulfillment' ],
];

$default_icons = [ 'shipping', 'lab', 'shield', 'check', 'refresh', 'award', 'clock', 'lock' ];

$found = false;
$updated = false;

foreach ( $cfg['pages'] as $pi => $page ) {
	if ( ( $page['slug'] ?? '' ) !== $slug ) {
		continue;
	}
	$found = true;
	$modules = is_array( $page['modules'] ?? null ) ? $page['modules'] : [];

	foreach ( $modules as $mi => $mod ) {
		if ( ( $mod['type'] ?? '' ) !== 'listicle' ) {
			continue;
		}
		$config = is_array( $mod['config'] ?? null ) ? $mod['config'] : [];
		$items  = is_array( $config['items'] ?? null ) ? $config['items'] : [];

		while ( count( $items ) < 8 ) {
			$items[] = [ 'headline' => 'Placeholder point ' . ( count( $items ) + 1 ) ];
		}

		$items[0] = array_merge( $items[0] ?? [], $point_one );

		for ( $i = 0; $i < 8; $i++ ) {
			if ( empty( $items[ $i ]['icon'] ) && isset( $default_icons[ $i ] ) ) {
				$items[ $i ]['icon'] = $default_icons[ $i ];
			}
		}

		if ( empty( $config['headline'] ) || str_contains( (string) $config['headline'], '5 Reasons' ) || str_contains( (string) $config['headline'], 'Verified Peptide Suppliers' ) ) {
			$config['headline'] = '8 Reasons Researchers Choose Alyve For their Research Compounds';
		}

		$config['hero_layout']   = 'editorial';
		$config['trust_brand']   = 'Alyve Peptides';
		$config['trust_items']   = [
			'99%+ HPLC Verified',
			'3rd-Party Tested Every Batch',
			'COA Pre-Purchase',
		];
		unset( $config['trust_pillars'], $config['persona_name'], $config['persona_image'], $config['persona_image_alt'], $config['persona_badge'], $config['persona_updated'] );
		$config['hero_callout']         = 'READ THIS BEFORE YOU BUY RESEARCH COMPOUNDS FROM ANY OTHER COMPANY';
		$config['hero_cta_image']       = '/wp-content/uploads/2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp';
		$config['hero_cta_image_alt']   = 'Alyve research-grade peptide vials';
		$config['hero_cta_headline']    = 'Up to 40% Off — Verified Batches In Stock';
		$config['hero_cta_label']       = 'Shop Now — Check Availability';
		$config['hero_cta_href']        = '/shop';
		$config['intro']                = '';
		$config['coa_embed_href']       = '/coa-library';
		$config['coa_embed_link_label'] = 'View COA Library →';
		$config['coa_embed_image_alt']  = 'Sample Certificate of Analysis preview';

		$config['items']                   = $items;
		$modules[ $mi ]['config']          = $config;
		$cfg['pages'][ $pi ]['modules']    = \WCHS\Admin\SchemaSanitizer::sanitize_modules( $modules, 'pages' );
		$updated                           = true;
		break 2;
	}
}

if ( ! $found ) {
	WP_CLI::error( "Page slug “{$slug}” not found in wchs_pages_config." );
}
if ( ! $updated ) {
	WP_CLI::warning( "No listicle module on {$slug}; nothing changed." );
	return;
}

update_option( 'wchs_pages_config', $cfg );
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

WP_CLI::success( "Updated {$slug} listicle hero, point 01, and icons." );
