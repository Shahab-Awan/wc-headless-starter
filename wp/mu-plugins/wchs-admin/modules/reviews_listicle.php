<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'reviews_listicle',
	'name'     => 'Reviews listicle',
	'icon'     => 'star-filled',
	'category' => 'content',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
		'contexts'   => [ 'homepage', 'shop', 'pdp', 'pages' ],
	],
	'fields'   => [
		[
			'id'      => 'headline',
			'type'    => 'text',
			'default' => 'Amazing Reviews with a 4.9 Rating',
		],
		[
			'id'      => 'subheadline',
			'type'    => 'text',
			'default' => '',
		],
		[
			'id'      => 'proof_headline',
			'type'    => 'text',
			'default' => 'Trusted by 10K+ Researchers Worldwide',
		],
		[
			'id'      => 'proof_subheadline',
			'type'    => 'text',
			'default' => 'Real labs. Real protocols. Trusted for consistency.',
		],
		[
			'id'      => 'marquee_headline',
			'type'    => 'text',
			'default' => 'Amazing Reviews with a 4.9 Rating',
		],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [
				[
					'quote'   => 'COAs matched the batch numbers on our BPC-157 vials. Documentation was clear and easy to file for our lab records.',
					'name'    => 'Vincent R.',
					'product' => 'BPC-157 5mg',
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
			'item'          => [
				[ 'id' => 'quote', 'type' => 'textarea' ],
				[ 'id' => 'name', 'type' => 'text' ],
				[ 'id' => 'product', 'type' => 'text' ],
				[
					'id'      => 'rating',
					'type'    => 'number',
					'default' => 5,
					'min'     => 1,
					'max'     => 5,
				],
			],
			'item_required' => [ 'quote', 'name' ],
		],
		[
			'id'      => 'proof_items',
			'type'    => 'repeater',
			'default' => [
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
					'quote'    => 'Consistency across every vial was exactly as expected. Labeling, batch traceability, and purity data aligned with the COA without any discrepancies.',
					'name'     => 'J.R.',
					'location' => 'San Diego, CA',
					'rating'   => 5,
				],
				[
					'title'    => 'Accurate Labeling and Reliable Sample Integrity',
					'quote'    => 'The documentation clarity and sample integrity were excellent. Every detail from concentration to labeling was consistent with what was promised.',
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
			'item'          => [
				[ 'id' => 'title', 'type' => 'text' ],
				[ 'id' => 'quote', 'type' => 'textarea' ],
				[ 'id' => 'name', 'type' => 'text' ],
				[ 'id' => 'location', 'type' => 'text' ],
				[
					'id'      => 'rating',
					'type'    => 'number',
					'default' => 5,
					'min'     => 1,
					'max'     => 5,
				],
			],
			'item_required' => [ 'title', 'quote', 'name' ],
		],
		[
			'id'      => 'marquee_items',
			'type'    => 'repeater',
			'default' => [
				[
					'quote'  => 'COAs matched the batch numbers on our vials. Documentation was clear and easy to file for our lab records.',
					'name'   => 'Vincent R.',
					'rating' => 5,
				],
				[
					'quote'  => 'Ordering was straightforward and fulfillment was faster than our previous supplier.',
					'name'   => 'Justin F.',
					'rating' => 5,
				],
				[
					'quote'  => 'Consistent quality across reorders — no surprises between batches.',
					'name'   => 'Carlos B.',
					'rating' => 5,
				],
			],
			'item'          => [
				[ 'id' => 'quote', 'type' => 'textarea' ],
				[ 'id' => 'name', 'type' => 'text' ],
				[
					'id'      => 'rating',
					'type'    => 'number',
					'default' => 5,
					'min'     => 1,
					'max'     => 5,
				],
			],
			'item_required' => [ 'quote', 'name' ],
		],
	],
];
