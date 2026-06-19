<?php
/**
 * Update Why Alyve comparison table rows + column headers.
 * Run: wp eval-file scripts/patch-why-alyve-comparison.php
 */
defined( 'ABSPATH' ) || exit;

$slug = 'why-alyve';
$cfg  = get_option( 'wchs_pages_config', [ 'pages' => [] ] );
if ( ! is_array( $cfg['pages'] ?? null ) ) {
	WP_CLI::error( 'wchs_pages_config.pages is missing or invalid.' );
}

$compare_config = [
	'layout'            => 'comparison',
	'brand_name'        => 'Alyve',
	'competitor_name'   => 'Generic Peptide Sites',
	'competitor_name_2' => 'Overseas / Grey-Market',
	'comparison_rows'   => [
		[
			'heading'      => '🧬 Endotoxin Testing',
			'brand'        => 'LAB tested every batch, pharma-grade low',
			'competitor'   => 'Skipped entirely',
			'competitor_2' => 'Unknown, never tested',
		],
		[
			'heading'      => '🧪 Purity',
			'brand'        => '99%+ HPLC-verified at manufacture',
			'competitor'   => 'Estimated, not proven',
			'competitor_2' => 'Label claim only',
		],
		[
			'heading'      => '📄 Third-Party Verification',
			'brand'        => "Accredited labs, COA per batch, test it yourself and we'll reimburse",
			'competitor'   => 'In-house claims only',
			'competitor_2' => 'Redacted or none',
		],
		[
			'heading'      => '🚚 Shipping',
			'brand'        => 'Same-day, tracked, discreet, 2–3 days',
			'competitor'   => 'Slow, sometimes tracked',
			'competitor_2' => '2–6 weeks, customs risk',
		],
	],
];

$found = false;
$updated = false;

foreach ( $cfg['pages'] as $pi => $page ) {
	if ( ( $page['slug'] ?? '' ) !== $slug ) {
		continue;
	}
	$found = true;
	$modules = is_array( $page['modules'] ?? null ) ? $page['modules'] : [];

	foreach ( $modules as $mi => $mod ) {
		if ( ( $mod['type'] ?? '' ) !== 'text_block' ) {
			continue;
		}
		$config = is_array( $mod['config'] ?? null ) ? $mod['config'] : [];
		$title  = (string) ( $config['title'] ?? '' );
		if ( stripos( $title, 'why alyve' ) === false && ( $config['layout'] ?? '' ) !== 'comparison' ) {
			continue;
		}
		$modules[ $mi ]['config'] = array_merge( $config, $compare_config );
		$updated                  = true;
		break;
	}

	if ( $updated ) {
		$cfg['pages'][ $pi ]['modules'] = \WCHS\Admin\SchemaSanitizer::sanitize_modules( $modules, 'pages' );
	}
	break;
}

if ( ! $found ) {
	WP_CLI::error( "Page slug “{$slug}” not found in wchs_pages_config." );
}
if ( ! $updated ) {
	WP_CLI::warning( "No Why Alyve text_block comparison module found; nothing changed." );
	return;
}

update_option( 'wchs_pages_config', $cfg );
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

WP_CLI::success( "Updated {$slug} comparison table." );
