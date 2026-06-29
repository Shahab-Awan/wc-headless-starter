<?php
/**
 * Product-tied mid-CTA reviews + proof block on Why Alyve (BPC-157, TB-500, etc.).
 * Run: wp eval-file scripts/patch-why-alyve-product-reviews.php
 */
defined( 'ABSPATH' ) || exit;

$slug = 'why-alyve';
$cfg  = get_option( 'wchs_pages_config', [ 'pages' => [] ] );
if ( ! is_array( $cfg['pages'] ?? null ) ) {
	WP_CLI::error( 'wchs_pages_config.pages is missing or invalid.' );
}

$reviews_config = [
	'headline'          => 'What researchers say after ordering',
	'proof_headline'    => 'Trusted by 10K+ Researchers Worldwide',
	'proof_subheadline' => 'Real labs. Real protocols. Trusted for consistency.',
	'items'             => [
		[
			'quote'   => 'COAs matched the batch numbers on our BPC-157 vials. Documentation was clear and easy to file for our lab records.',
			'name'    => 'Vincent R.',
			'product' => 'BPC-157 5mg',
			'rating'  => 5,
		],
		[
			'quote'   => 'TB-500 batch purity matched the published COA exactly. Reconstitution notes were clear and shipment arrived tracked within two days.',
			'name'    => 'James T.',
			'product' => 'TB-500 5mg',
			'rating'  => 5,
		],
		[
			'quote'   => 'Tirzepatide purity report was posted before checkout — exactly what our QC process requires.',
			'name'    => 'Justin F.',
			'product' => 'Tirzepatide 10mg',
			'rating'  => 5,
		],
		[
			'quote'   => 'Consistent Retatrutide quality across reorders — no surprises between batches. Support answered technical questions the same day.',
			'name'    => 'Carlos B.',
			'product' => 'Retatrutide 5mg',
			'rating'  => 5,
		],
	],
	'proof_items'       => [
		[
			'title'    => 'Purity and documentation matched perfectly',
			'quote'    => 'Batch number, vial presentation, and stated specifications were all consistent. This level of QC is what keeps my research on track.',
			'name'     => 'K.S.',
			'location' => 'Sydney',
			'rating'   => 5,
		],
		[
			'title'    => 'Complete Transparency From Packaging to Documentation',
			'quote'    => 'Material arrived well-documented with clear batch identifiers and intact packaging. Everything matched the specification sheet precisely, which gave me full confidence in proceeding with my protocol.',
			'name'     => 'A.S.',
			'location' => 'Chicago, IL',
			'rating'   => 5,
		],
		[
			'title'    => 'Consistency and COA Alignment Were Flawless',
			'quote'    => 'Consistency across every vial was exactly as expected. Labeling, batch traceability, and purity data aligned with the COA without any discrepancies. That reliability is essential for reproducible results.',
			'name'     => 'J.R.',
			'location' => 'San Diego, CA',
			'rating'   => 5,
		],
		[
			'title'    => 'Accurate Labeling and Reliable Sample Integrity',
			'quote'    => 'The documentation clarity and sample integrity were excellent. Every detail from concentration to labeling was consistent with what was promised, making the entire process seamless.',
			'name'     => 'L.M.',
			'location' => 'Miami, FL',
			'rating'   => 5,
		],
		[
			'title'    => 'Strict Quality Control Reflected in Every Detail',
			'quote'    => 'What stood out most was the traceability and clean presentation of each batch. COA alignment was exact, and the overall quality control standards are clearly very strict.',
			'name'     => 'N.K.',
			'location' => 'New York, NY',
			'rating'   => 5,
		],
	],
];

$reviews_module_defaults = [
	'type'       => 'reviews_listicle',
	'visibility' => 'all',
	'spacing_v'  => 'normal',
	'spacing_h'  => 'normal',
	'config'     => $reviews_config,
];

$found   = false;
$updated = false;
$page_index = null;

foreach ( $cfg['pages'] as $pi => $page ) {
	if ( ( $page['slug'] ?? '' ) !== $slug ) {
		continue;
	}
	$found      = true;
	$page_index = $pi;
	$modules    = is_array( $page['modules'] ?? null ) ? $page['modules'] : [];

	foreach ( $modules as $mi => $mod ) {
		if ( ( $mod['type'] ?? '' ) !== 'reviews_listicle' ) {
			continue;
		}
		$modules[ $mi ]['config'] = array_merge(
			is_array( $mod['config'] ?? null ) ? $mod['config'] : [],
			$reviews_config
		);
		$updated = true;
		break;
	}

	if ( ! $updated ) {
		$modules[] = $reviews_module_defaults;
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

WP_CLI::success( "Updated {$slug} product-tied review items." );
