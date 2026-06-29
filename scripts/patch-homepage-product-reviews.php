<?php
/**
 * Homepage layout: trust strip, product reviews, compound FAQs.
 * Run: wp eval-file scripts/patch-homepage-product-reviews.php
 */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WCHS\\Admin\\AdminPage' ) ) {
	WP_CLI::error( 'WCHS Admin not loaded' );
}

$trust_bar = [
	'type'       => 'trust_bar',
	'visibility' => 'all',
	'spacing_v'  => 'compact',
	'spacing_h'  => 'normal',
	'config'     => [
		'title'       => '',
		'icon_accent' => true,
		'items'       => [
			[
				'icon'        => 'percent',
				'headline'    => 'Price Below Market',
				'description' => 'Research-grade pricing without inflated reseller markups.',
			],
			[
				'icon'        => 'lab',
				'headline'    => '1 Vial 3 Tests',
				'description' => 'Purity, identity, and endotoxin verification on every batch.',
			],
			[
				'icon'        => 'check',
				'headline'    => 'COA Before Purchase',
				'description' => 'Batch documentation published before you add to cart.',
			],
			[
				'icon'        => 'shipping',
				'headline'    => 'Same-Day US Fulfillment',
				'description' => 'Orders placed before 2PM EST ship the same business day.',
			],
		],
	],
];

$reviews_config = [
	'headline'          => 'What researchers say after ordering',
	'proof_subheadline' => '4.9 stars · 200+ verified orders.',
	'proof_headline'    => '',
	'proof_items'       => [],
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
			'quote'   => 'Ipamorelin vials arrived cold-packed with batch COA attached. Purity matched the published report on the first HPLC rerun.',
			'name'    => 'Sarah M.',
			'product' => 'Ipamorelin 2mg',
			'rating'  => 5,
		],
		[
			'quote'   => 'Consistent Retatrutide quality across reorders — no surprises between batches. Support answered technical questions the same day.',
			'name'    => 'Carlos B.',
			'product' => 'Retatrutide 5mg',
			'rating'  => 5,
		],
	],
];

$faqs_config = [
	'eyebrow'  => 'PRODUCT QUESTIONS',
	'headline' => 'FAQs by compound',
	'items'    => [
		[
			'q' => 'What purity should I expect from BPC-157 batches?',
			'a' => '<p>Every BPC-157 batch is third-party tested for identity and purity via HPLC. Published COAs list the exact percentage for the batch tied to your vial — typically ≥99% on recent lots.</p>',
		],
		[
			'q' => 'Is the BPC-157 COA available before I order?',
			'a' => '<p>Yes. Batch-specific Certificates of Analysis are posted on the product page and in our COA library before checkout, so your team can qualify material against protocol requirements in advance.</p>',
		],
		[
			'q' => 'How is TB-500 tested before release?',
			'a' => '<p>TB-500 undergoes independent laboratory testing for purity, identity, and endotoxin. Results are tied to a batch number printed on each vial and documented on the COA shipped with your order.</p>',
		],
		[
			'q' => 'Can I match TB-500 batch numbers to the published COA?',
			'a' => '<p>Every TB-500 vial label matches the batch identifier on its COA. Search by product name or batch number in the COA library to pull the exact report for the lot you received.</p>',
		],
		[
			'q' => 'What documentation comes with Ipamorelin orders?',
			'a' => '<p>Ipamorelin shipments include batch-linked COA PDFs with HPLC purity, identity confirmation, and storage guidance. Research-use labeling and batch traceability are included for lab filing.</p>',
		],
	],
];

$homepage = get_option( 'wchs_homepage_config', [] );
if ( ! is_array( $homepage ) ) {
	$homepage = [];
}
$modules = is_array( $homepage['modules'] ?? null ) ? $homepage['modules'] : [];

$has_trust   = false;
$has_reviews = false;
$has_faqs    = false;

foreach ( $modules as $i => $mod ) {
	if ( ! is_array( $mod ) ) {
		continue;
	}
	$type = $mod['type'] ?? '';
	if ( 'trust_bar' === $type ) {
		$modules[ $i ]['config'] = array_merge(
			is_array( $mod['config'] ?? null ) ? $mod['config'] : [],
			$trust_bar['config']
		);
		$modules[ $i ]['spacing_v'] = 'compact';
		$has_trust                  = true;
	}
	if ( 'reviews_listicle' === $type ) {
		$modules[ $i ]['config']        = array_merge(
			is_array( $mod['config'] ?? null ) ? $mod['config'] : [],
			$reviews_config
		);
		$modules[ $i ]['center_header'] = true;
		$has_reviews                    = true;
	}
	if ( 'listicle_faqs' === $type ) {
		$modules[ $i ]['config'] = array_merge(
			is_array( $mod['config'] ?? null ) ? $mod['config'] : [],
			$faqs_config
		);
		$has_faqs = true;
	}
	if ( 'feature_highlights' === $type ) {
		$modules[ $i ]['visibility'] = 'hidden';
	}
}

if ( ! $has_trust ) {
	array_unshift( $modules, $trust_bar );
}

if ( ! $has_reviews ) {
	$catalog_idx = null;
	foreach ( $modules as $i => $mod ) {
		if ( is_array( $mod ) && in_array( $mod['type'] ?? '', [ 'product_slider', 'shop_grid' ], true ) ) {
			$catalog_idx = $i;
			break;
		}
	}
	$insert = [
		'type'          => 'reviews_listicle',
		'visibility'    => 'all',
		'spacing_v'     => 'normal',
		'spacing_h'     => 'normal',
		'center_header' => true,
		'config'        => $reviews_config,
	];
	if ( null !== $catalog_idx ) {
		array_splice( $modules, $catalog_idx + 1, 0, [ $insert ] );
	} else {
		$modules[] = $insert;
	}
}

if ( ! $has_faqs ) {
	$reviews_idx = null;
	foreach ( $modules as $i => $mod ) {
		if ( is_array( $mod ) && ( $mod['type'] ?? '' ) === 'reviews_listicle' ) {
			$reviews_idx = $i;
			break;
		}
	}
	$insert = [
		'type'       => 'listicle_faqs',
		'visibility' => 'all',
		'spacing_v'  => 'normal',
		'spacing_h'  => 'normal',
		'config'     => $faqs_config,
	];
	if ( null !== $reviews_idx ) {
		array_splice( $modules, $reviews_idx + 1, 0, [ $insert ] );
	} else {
		$modules[] = $insert;
	}
}

$homepage['modules'] = \WCHS\Admin\SchemaSanitizer::sanitize_modules( $modules, 'homepage' );
update_option( 'wchs_homepage_config', $homepage );
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

WP_CLI::success( 'Homepage updated: trust strip, product reviews, compound FAQs.' );
