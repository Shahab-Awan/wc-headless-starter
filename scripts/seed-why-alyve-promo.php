<?php
/**
 * Append promo_offer (and ensure listicle) on the Why Alyve page.
 * Run: wp eval-file scripts/seed-why-alyve-promo.php
 */
defined( 'ABSPATH' ) || exit;

$slug = 'why-alyve';
$cfg  = get_option( 'wchs_pages_config', [ 'pages' => [] ] );
if ( ! is_array( $cfg['pages'] ?? null ) ) {
	$cfg = [ 'pages' => [] ];
}

$promo_defaults = [
	'type'       => 'promo_offer',
	'visibility' => 'all',
	'spacing_v'  => 'normal',
	'spacing_h'  => 'normal',
	'config'     => [
		'intro_headline'    => '',
		'intro_subheadline' => '✨ We don\'t hand these out every day… consider this an exclusive Alyve hookup, just for you.',
		'badge_text'        => 'LIMITED TIME OFFER ✨',
		'image'             => '/wp-content/uploads/2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp',
		'image_alt'         => 'Alyve research-grade peptides — Buy one get one free',
		'ribbon_text'       => 'PUBLISHED COAs + BATCH DOCS WITH EVERY ORDER',
		'offer_primary'     => 'UP TO 40% OFF',
		'offer_secondary'   => 'FOR A LIMITED TIME ONLY!',
		'scarcity_text'     => 'High demand — popular batches sell out quickly.',
		'cta_label'         => 'Check Availability',
		'cta_href'          => '/shop',
		'show_countdown'    => true,
		'countdown_end_at'  => gmdate( 'c', time() + 3 * DAY_IN_SECONDS ),
		'status_label'      => 'Sell-out risk:',
		'status_value'      => 'High',
		'status_note'       => 'Faster shipping',
		'footer_text'       => 'Try it today with a 60-Day Money-Back Guarantee!',
	],
];

$found = false;
foreach ( $cfg['pages'] as $i => $page ) {
	if ( ( $page['slug'] ?? '' ) !== $slug ) {
		continue;
	}
	$found   = true;
	$modules = is_array( $page['modules'] ?? null ) ? $page['modules'] : [];
	$has     = false;
	foreach ( $modules as $mi => $m ) {
		if ( ( $m['type'] ?? '' ) === 'promo_offer' ) {
			$has = true;
			$modules[ $mi ]['config']['cta_label'] = 'Check Availability';
			break;
		}
	}
	if ( ! $has ) {
		$modules[] = $promo_defaults;
	}

	$reviews_defaults = [
		'type'       => 'reviews_listicle',
		'visibility' => 'all',
		'spacing_v'  => 'normal',
		'spacing_h'  => 'normal',
		'config'     => [
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
			],
		],
	];
	$has_reviews = false;
	foreach ( $modules as $m ) {
		if ( ( $m['type'] ?? '' ) === 'reviews_listicle' ) {
			$has_reviews = true;
			break;
		}
	}
	if ( ! $has_reviews ) {
		$modules[] = $reviews_defaults;
	}

	$why_alyve_block = [
		'type'          => 'text_block',
		'visibility'    => 'all',
		'spacing_v'     => 'normal',
		'spacing_h'     => 'normal',
		'center_header' => true,
		'config'        => [
			'layout'            => 'comparison',
			'title'             => 'Why Alyve',
			'headline'          => '',
			'content'           => '<p>Alyve started from a simple frustration: finding reliable, documented research peptides shouldn\'t be this hard. Every batch we sell is third-party tested. Every Certificate of Analysis is published before purchase.</p>',
			'brand_name'        => 'Alyve',
			'competitor_name'   => 'Generic Peptide Sites',
			'competitor_name_2' => 'Overseas / Grey-Market',
			'brand_logo'        => '',
			'competitor_logo'   => '',
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
		],
	];
	$has_why_block = false;
	$why_block_index = null;
	foreach ( $modules as $wi => $m ) {
		if ( ( $m['type'] ?? '' ) === 'text_block' ) {
			$cfg_check = $m['config'] ?? [];
			if ( stripos( (string) ( $cfg_check['title'] ?? '' ), 'why alyve' ) !== false
				|| ( $cfg_check['layout'] ?? '' ) === 'comparison' ) {
				$has_why_block = true;
				$why_block_index = $wi;
				break;
			}
		}
	}
	if ( ! $has_why_block ) {
		$modules[] = $why_alyve_block;
	} else {
		$modules[ $why_block_index ]['config'] = array_merge(
			$modules[ $why_block_index ]['config'] ?? [],
			$why_alyve_block['config']
		);
	}

	$listicle_faqs_defaults = [
		'type'       => 'listicle_faqs',
		'visibility' => 'all',
		'spacing_v'  => 'normal',
		'spacing_h'  => 'normal',
		'config'     => [
			'eyebrow'   => 'COMMON QUESTIONS',
			'headline'  => 'What researchers ask before ordering',
			'items'            => [
				[
					'q' => 'How much bacteriostatic water do I use to reconstitute?',
					'a' => '<p>Follow the reconstitution guidance on your product label and COA. A common starting point for research vials is adding the volume of bacteriostatic water (BAC) that yields your target concentration—many protocols use 1–2 mL per vial, but always defer to the compound-specific instructions supplied with the batch.</p>',
				],
				[
					'q' => 'Are COAs available before I purchase?',
					'a' => '<p>Yes. Every batch is tested by independent third-party laboratories. Certificates of Analysis confirming purity are published on product pages and available before you add items to cart.</p>',
				],
				[
					'q' => 'What is your return policy?',
					'a' => '<p>Unopened products in original packaging may be returned within 30 days of delivery. Opened or reconstituted materials cannot be accepted due to research-use handling requirements. Contact support with your order number to start a return.</p>',
				],
				[
					'q' => 'How quickly does the order ship?',
					'a' => '<p>Orders ship within 1–2 business days from our U.S. facility. You will receive tracking as soon as the carrier scans the package. Free shipping applies on qualifying order totals shown at checkout.</p>',
				],
				[
					'q' => 'What testing methodology do you use to verify purity?',
					'a' => '<p>Each batch undergoes third-party laboratory testing—including HPLC for purity and identity—with results documented on the COA. We publish methodology summaries and batch numbers so your team can align material qualification with your protocol.</p>',
				],
			],
		],
	];
	$has_faqs = false;
	foreach ( $modules as $m ) {
		if ( ( $m['type'] ?? '' ) === 'listicle_faqs' ) {
			$has_faqs = true;
			break;
		}
	}
	if ( ! $has_faqs ) {
		$modules[] = $listicle_faqs_defaults;
	}

	$cfg['pages'][ $i ]['modules'] = \WCHS\Admin\SchemaSanitizer::sanitize_modules( $modules, 'pages' );
	break;
}

if ( ! $found ) {
	WP_CLI::error( "Page slug “{$slug}” not found in wchs_pages_config." );
}

update_option( 'wchs_pages_config', $cfg );
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

WP_CLI::success( "Updated {$slug} modules: " . implode( ', ', array_column( $cfg['pages'][ $i ]['modules'] ?? [], 'type' ) ) );
