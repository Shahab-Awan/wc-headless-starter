<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'listicle_faqs',
	'name'     => 'Listicle FAQs',
	'icon'     => 'editor-help',
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
			'id'      => 'eyebrow',
			'type'    => 'text',
			'default' => 'PRODUCT QUESTIONS',
		],
		[
			'id'      => 'headline',
			'type'    => 'text',
			'default' => 'FAQs by compound',
		],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [
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
			'item'          => [
				[ 'id' => 'q', 'type' => 'text' ],
				[ 'id' => 'a', 'type' => 'wysiwyg' ],
			],
			'item_any_required' => [ 'q', 'a' ],
		],
	],
];
