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
			'default' => '5 Reasons Researchers Choose Verified Peptide Suppliers Over Gray-Market Listings',
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
					'headline' => 'Unverified purity claims can invalidate your data.',
					'body'     => '<p>Your outcomes depend on what is actually in the vial. Without independent testing on every batch, you are trusting a label—not a lab result. Third-party COAs and published batch records let you align compound identity and purity with your protocol before you spend time in the bench.</p>',
				],
				[
					'headline' => 'No COA before purchase means no audit trail.',
					'body'     => '<p>Reputable suppliers publish Certificates of Analysis tied to batch numbers before you buy. Gray-market listings rarely offer the same transparency, which makes reproducibility and compliance documentation much harder when results need to be defended.</p>',
				],
				[
					'headline' => 'Inconsistent sourcing slows every experiment cycle.',
					'body'     => '<p>Switching vendors mid-study introduces variables you cannot control. A single catalog with documented batches, clear SKUs, and predictable domestic fulfillment keeps your team focused on research—not re-qualifying material.</p>',
				],
				[
					'headline' => 'Research-use standards matter for your reputation.',
					'body'     => '<p>Materials labeled and handled for research use, with clear disclaimers and batch traceability, reduce ambiguity for PI review, institutional policy, and downstream publication integrity.</p>',
				],
				[
					'headline' => 'Verified supply is faster to trust than faster to ship.',
					'body'     => '<p>Tracked domestic shipping matters—but only after purity and documentation are settled. The best workflow pairs batch-tested inventory with fulfillment you can plan around.</p>',
				],
			],
			'item'             => [
				[ 'id' => 'number', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'label', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'headline', 'type' => 'text' ],
				[ 'id' => 'body', 'type' => 'wysiwyg' ],
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
