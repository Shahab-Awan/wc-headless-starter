<?php
defined( 'ABSPATH' ) || exit;

$default_compare_rows = [
	[
		'heading'      => '🧬 Endotoxin Testing',
		'brand'        => 'LAL tested every batch, pharma-grade low',
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
];

return [
	'type'     => 'text_block',
	'name'     => 'Text block',
	'icon'     => 'text',
	'category' => 'content',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[
			'id'      => 'layout',
			'type'    => 'enum',
			'default' => 'auto',
			'options' => [
				'auto'       => 'Auto — comparison table when title matches Why Alyve / Why Choose',
				'standard'   => 'Text only (never comparison)',
				'comparison' => 'Brand comparison table',
			],
		],
		[ 'id' => 'title',           'type' => 'text',    'default' => '' ],
		[ 'id' => 'headline',        'type' => 'text',    'default' => '' ],
		[ 'id' => 'content',         'type' => 'wysiwyg', 'default' => '' ],
		[ 'id' => 'brand_name',      'type' => 'text',    'default' => 'Alyve' ],
		[ 'id' => 'competitor_name', 'type' => 'text',    'default' => 'Generic Peptide Sites' ],
		[ 'id' => 'competitor_name_2', 'type' => 'text',  'default' => 'Overseas / Grey-Market' ],
		[ 'id' => 'brand_logo',      'type' => 'image',   'default' => '' ],
		[ 'id' => 'competitor_logo', 'type' => 'image',   'default' => '' ],
		[
			'id'      => 'comparison_rows',
			'type'    => 'repeater',
			'default' => $default_compare_rows,
			'item'    => [
				[ 'id' => 'heading', 'type' => 'text' ],
				[ 'id' => 'brand', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'competitor', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'competitor_2', 'type' => 'text', 'default' => '' ],
			],
			'item_any_required' => [ 'heading' ],
		],
	],
];
