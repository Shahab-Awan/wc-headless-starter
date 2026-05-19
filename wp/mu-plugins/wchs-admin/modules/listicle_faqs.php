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
			'default' => 'COMMON QUESTIONS',
		],
		[
			'id'      => 'headline',
			'type'    => 'text',
			'default' => 'What researchers ask before ordering',
		],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [
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
			'item'          => [
				[ 'id' => 'q', 'type' => 'text' ],
				[ 'id' => 'a', 'type' => 'wysiwyg' ],
			],
			'item_any_required' => [ 'q', 'a' ],
		],
	],
];
