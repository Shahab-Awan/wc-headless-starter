<?php
/**
 * Create / update the Vault landing page with hero module.
 * Run: wp eval-file scripts/patch-vault-page.php
 */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WCHS\\Admin\\AdminPage' ) ) {
	WP_CLI::error( 'WCHS Admin not loaded' );
}

$vault_hero_module = [
	'type'       => 'vault_hero',
	'visibility' => 'all',
	'spacing_v'  => 'normal',
	'spacing_h'  => 'normal',
	'config'     => [
		'headline'           => 'Quality You Can Verify, Not Just Trust',
		'stats'              => [
			[ 'icon' => 'shield', 'label' => '99%+ Purity Guaranteed' ],
			[ 'icon' => 'lab', 'label' => '5 Quality Checks' ],
			[ 'icon' => 'globe', 'label' => '100% US Verified' ],
		],
		'cta_text'           => 'Browse the Vault →',
		'cta_href'           => '/shop',
		'bg_image'           => '',
		'vial_primary'       => '',
		'vial_primary_alt'   => '',
		'vial_secondary'     => '',
		'vial_secondary_alt' => '',
		'vial_tertiary'      => '',
		'vial_tertiary_alt'  => '',
	],
];

$vault_quality_tabs_module = [
	'type'       => 'vault_quality_tabs',
	'visibility' => 'all',
	'spacing_v'  => 'normal',
	'spacing_h'  => 'normal',
	'config'     => [
		'section_title'     => 'The Alyve Vault Guarantee',
		'section_subtitle'  => 'Every batch is independently verified — purity, identity, endotoxin, stability, and consistency.',
		'product_image'     => '',
		'product_image_alt' => '',
		'image_badge'       => '99.4% Purity — Verified by HPLC',
		'panel_bg'          => '#ebe6f5',
		'tabs'              => [
			[
				'title'       => 'Purity',
				'summary'     => 'HPLC ≥99%',
				'body'        => '<p>Every batch is verified by High-Performance Liquid Chromatography (HPLC) to confirm peptide purity meets or exceeds 99%.</p>',
				'why_matters' => 'Impurities can skew receptor binding and invalidate your study data.',
				'chart_image' => '',
			],
			[
				'title'       => 'Identity',
				'summary'     => 'Mass Spec confirmed',
				'body'        => '<p>Mass spectrometry confirms the molecular weight and sequence identity of each peptide lot before release.</p>',
				'why_matters' => 'Ensures you receive the exact compound specified — not a mislabeled analog.',
				'chart_image' => '',
			],
			[
				'title'       => 'Endotoxin',
				'summary'     => 'LAL tested, pharma-grade low',
				'body'        => '<p>Limulus Amebocyte Lysate (LAL) testing verifies endotoxin levels meet pharmaceutical-grade thresholds.</p>',
				'why_matters' => 'Elevated endotoxins can trigger immune responses that confound in vitro and in vivo results.',
				'chart_image' => '',
			],
			[
				'title'       => 'Stability',
				'summary'     => 'Lyophilized for shelf life',
				'body'        => '<p>Peptides are lyophilized under controlled conditions to maximize stability during storage and transit.</p>',
				'why_matters' => 'Proper lyophilization preserves bioactivity from synthesis to your bench.',
				'chart_image' => '',
			],
			[
				'title'       => 'Consistency',
				'summary'     => 'Batch-to-batch variance data',
				'body'        => '<p>We publish lot-to-lot analytical data so you can compare batches across your study timeline.</p>',
				'why_matters' => 'Reproducible research requires predictable material from order to order.',
				'chart_image' => '',
			],
		],
	],
];

$pages_cfg = \WCHS\Admin\AdminPage::get_pages_config();
$pages     = is_array( $pages_cfg['pages'] ?? null ) ? $pages_cfg['pages'] : [];

$vault_idx = null;
foreach ( $pages as $i => $page ) {
	if ( ! is_array( $page ) ) {
		continue;
	}
	if ( ( $page['slug'] ?? '' ) === 'vault' ) {
		$vault_idx = $i;
		break;
	}
}

if ( null === $vault_idx ) {
	$pages[] = [
		'title'   => 'Vault',
		'slug'    => 'vault',
		'modules' => [ $vault_hero_module, $vault_quality_tabs_module ],
	];
} else {
	$modules = is_array( $pages[ $vault_idx ]['modules'] ?? null ) ? $pages[ $vault_idx ]['modules'] : [];
	$hero_idx = null;
	$tabs_idx = null;
	foreach ( $modules as $j => $mod ) {
		if ( ! is_array( $mod ) ) {
			continue;
		}
		if ( ( $mod['type'] ?? '' ) === 'vault_hero' ) {
			$hero_idx = $j;
		}
		if ( ( $mod['type'] ?? '' ) === 'vault_quality_tabs' ) {
			$tabs_idx = $j;
		}
	}
	if ( null === $hero_idx ) {
		array_unshift( $modules, $vault_hero_module );
		$hero_idx = 0;
	} else {
		$modules[ $hero_idx ] = array_merge( $modules[ $hero_idx ], $vault_hero_module );
		$modules[ $hero_idx ]['config'] = array_merge(
			is_array( $modules[ $hero_idx ]['config'] ?? null ) ? $modules[ $hero_idx ]['config'] : [],
			$vault_hero_module['config']
		);
	}
	if ( null === $tabs_idx ) {
		array_splice( $modules, $hero_idx + 1, 0, [ $vault_quality_tabs_module ] );
	} else {
		$modules[ $tabs_idx ] = array_merge( $modules[ $tabs_idx ], $vault_quality_tabs_module );
		$modules[ $tabs_idx ]['config'] = array_merge(
			is_array( $modules[ $tabs_idx ]['config'] ?? null ) ? $modules[ $tabs_idx ]['config'] : [],
			$vault_quality_tabs_module['config']
		);
	}
	$pages[ $vault_idx ]['title']   = 'Vault';
	$pages[ $vault_idx ]['slug']    = 'vault';
	$pages[ $vault_idx ]['modules'] = \WCHS\Admin\SchemaSanitizer::sanitize_modules( $modules, 'pages' );
}

if ( null === $vault_idx ) {
	$last = count( $pages ) - 1;
	$pages[ $last ]['modules'] = \WCHS\Admin\SchemaSanitizer::sanitize_modules(
		$pages[ $last ]['modules'],
		'pages'
	);
}

$pages_cfg['pages'] = $pages;
update_option( 'wchs_pages_config', $pages_cfg );
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

WP_CLI::success( 'Vault page ready at /vault — upload hero + quality tab images in WCHS → Pages → Vault.' );
