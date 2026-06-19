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
	'body'     => '<p>Every Alyve order is fulfilled through our U.S. operations with an emphasis on transparency and dependable service. From sourcing to shipment, products are carefully handled and prepared under established quality practices to help maintain consistency. No unknown middlemen and no complicated fulfillment chains.</p>',
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

		$config['hero_layout']      = 'editorial';
		$config['persona_name']   = 'Jessica H, Biotech CEO';
		$config['persona_badge']    = 'Verified';
		$config['persona_updated']  = 'UPDATED 2 DAYS AGO';
		$config['hero_callout']     = 'READ THIS BEFORE YOU BUY RESEARCH COMPOUNDS FROM ANY OTHER COMPANY';
		$config['intro']            = '';

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
