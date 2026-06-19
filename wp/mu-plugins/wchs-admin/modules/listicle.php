<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'listicle',
	'name'     => 'Listicle',
	'icon'     => 'list',
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
			'id'      => 'section_eyebrow',
			'type'    => 'text',
			'default' => '',
		],
		[
			'id'      => 'headline',
			'type'    => 'text',
			'default' => '8 Reasons Researchers Choose Verified Peptide Suppliers Over Gray-Market Listings',
		],
		[
			'id'      => 'hero_image',
			'type'    => 'image',
			'default' => '',
		],
		[
			'id'      => 'hero_image_alt',
			'type'    => 'text',
			'default' => '',
		],
		[
			'id'      => 'intro',
			'type'    => 'wysiwyg',
			'default' => '<p>Many labs still source peptides from unverified sellers because the price looks right and the listing looks legitimate.</p><p>That shortcut often means missing batch documentation, inconsistent purity claims, and no traceable COA before you commit budget to a run.</p>',
		],
		[
			'id'      => 'items_headline',
			'type'    => 'text',
			'default' => 'Here is why more research teams standardize on documented, batch-tested supply:',
		],
		[
			'id'      => 'closing',
			'type'    => 'wysiwyg',
			'default' => '',
		],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [
				[
					'icon'     => 'shipping',
					'headline' => 'Domestic Fulfillment, Direct to Your Lab',
					'body'     => '<p>Every Alyve order is fulfilled through our U.S. operations with an emphasis on transparency and dependable service. From sourcing to shipment, products are carefully handled and prepared under established quality practices to help maintain consistency. No unknown middlemen and no complicated fulfillment chains.</p>',
					'badges'   => [ 'Quality Standards', 'Supply Chain Transparency', 'Direct Fulfillment' ],
				],
				[
					'icon'     => 'lab',
					'headline' => 'Endotoxin Testing Standard',
					'body'     => '<p>Placeholder copy — content coming soon.</p>',
				],
				[
					'icon'     => 'shield',
					'headline' => 'Unverified purity claims can invalidate your data.',
					'body'     => '<p>Your outcomes depend on what is actually in the vial. Without independent testing on every batch, you are trusting a label—not a lab result.</p>',
				],
				[
					'icon'     => 'check',
					'headline' => 'No COA before purchase means no audit trail.',
					'body'     => '<p>Reputable suppliers publish Certificates of Analysis tied to batch numbers before you buy.</p>',
				],
				[
					'icon'     => 'refresh',
					'headline' => 'Inconsistent sourcing slows every experiment cycle.',
					'body'     => '<p>Switching vendors mid-study introduces variables you cannot control.</p>',
				],
				[
					'icon'     => 'award',
					'headline' => 'Research-use standards matter for your reputation.',
					'body'     => '<p>Materials labeled and handled for research use reduce ambiguity for PI review and institutional policy.</p>',
				],
				[
					'icon'     => 'clock',
					'headline' => 'Verified supply is faster to trust than faster to ship.',
					'body'     => '<p>Tracked domestic shipping matters—but only after purity and documentation are settled.</p>',
				],
				[
					'icon'     => 'lock',
					'headline' => 'Batch documentation you can defend in review.',
					'body'     => '<p>Placeholder copy — content coming soon.</p>',
				],
			],
			'item'             => [
				[ 'id' => 'number', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'icon', 'type' => 'icon', 'default' => '' ],
				[ 'id' => 'icon_text', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'label', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'headline', 'type' => 'text' ],
				[ 'id' => 'body', 'type' => 'wysiwyg' ],
				[
					'id'       => 'badges',
					'type'     => 'text',
					'default'  => '',
					'validate' => function ( $value ) {
						if ( is_array( $value ) ) {
							return array_values(
								array_filter(
									array_map(
										static fn( $badge ) => sanitize_text_field( wp_unslash( (string) $badge ) ),
										$value
									)
								)
							);
						}
						$raw = sanitize_text_field( wp_unslash( (string) $value ) );
						if ( $raw === '' ) {
							return [];
						}
						return array_values(
							array_filter(
								array_map( 'trim', explode( ',', $raw ) )
							)
						);
					},
				],
				[ 'id' => 'callout', 'type' => 'wysiwyg', 'default' => '' ],
				[ 'id' => 'image', 'type' => 'image', 'default' => '' ],
				[ 'id' => 'image_alt', 'type' => 'text', 'default' => '' ],
			],
			'item_required'    => [ 'headline' ],
		],
		[ 'id' => 'cta_label', 'type' => 'text', 'default' => 'Shop research-grade peptides' ],
		[ 'id' => 'cta_href',  'type' => 'text', 'default' => '/shop' ],
	],
];
