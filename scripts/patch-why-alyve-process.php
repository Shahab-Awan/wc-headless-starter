<?php
/**
 * Three-step order process block on Why Alyve (Browse → Order → Fulfillment).
 * Run: wp eval-file scripts/patch-why-alyve-process.php
 */
defined( 'ABSPATH' ) || exit;

$slug = 'why-alyve';
$cfg  = get_option( 'wchs_pages_config', [ 'pages' => [] ] );
if ( ! is_array( $cfg['pages'] ?? null ) ) {
	WP_CLI::error( 'wchs_pages_config.pages is missing or invalid.' );
}

$process_config = [
	'badge_text'    => '',
	'headline'      => 'Order Process',
	'subheadline'   => '',
	'bg_color'      => '',
	'steps'         => [
		[
			'variant'     => 'verified',
			'headline'    => 'Browse & Verify',
			'description' => 'Browse the catalog. Every product has a downloadable COA. Verify purity before you buy.',
		],
		[
			'variant'     => 'lab',
			'headline'    => 'Order — Discount Auto-Applied',
			'description' => 'Add to cart. Your discount applies automatically at checkout. No code needed.',
		],
		[
			'variant'     => 'shipping',
			'headline'    => 'Fast-Track Fulfillment',
			'description' => 'Orders before 2PM EST ship same day. Tracked, discreet, 2-3 business days.',
		],
	],
	'metrics_title' => '',
	'metrics'       => [],
];

$process_module_defaults = [
	'type'          => 'order_handling',
	'visibility'    => 'all',
	'spacing_v'     => 'normal',
	'spacing_h'     => 'normal',
	'center_header' => true,
	'config'        => $process_config,
];

$found   = false;
$updated = false;

foreach ( $cfg['pages'] as $pi => $page ) {
	if ( ( $page['slug'] ?? '' ) !== $slug ) {
		continue;
	}
	$found   = true;
	$modules = is_array( $page['modules'] ?? null ) ? $page['modules'] : [];

	foreach ( $modules as $mi => $mod ) {
		if ( ( $mod['type'] ?? '' ) !== 'order_handling' ) {
			continue;
		}
		$modules[ $mi ]['config'] = array_merge(
			is_array( $mod['config'] ?? null ) ? $mod['config'] : [],
			$process_config
		);
		$updated = true;
		break;
	}

	if ( ! $updated ) {
		$modules[] = $process_module_defaults;
		$updated   = true;
	}

	$cfg['pages'][ $pi ]['modules'] = \WCHS\Admin\SchemaSanitizer::sanitize_modules( $modules, 'pages' );
	break;
}

if ( ! $found ) {
	WP_CLI::error( "Page slug “{$slug}” not found in wchs_pages_config." );
}

update_option( 'wchs_pages_config', $cfg );
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

WP_CLI::success( "Updated {$slug} order process steps." );
