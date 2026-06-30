<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'vault_quality_tabs',
	'name'     => 'Vault quality tabs',
	'icon'     => 'lab',
	'category' => 'branding',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'contexts'   => [ 'pages' ],
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[
			'id'      => 'section_title',
			'type'    => 'text',
			'default' => 'The Alyve Vault Guarantee',
		],
		[
			'id'      => 'section_subtitle',
			'type'    => 'text',
			'default' => 'Every batch is independently verified — purity, identity, endotoxin, stability, and consistency.',
		],
		[ 'id' => 'product_image', 'type' => 'image', 'default' => '' ],
		[ 'id' => 'product_image_alt', 'type' => 'text', 'default' => '' ],
		[
			'id'      => 'image_badge',
			'type'    => 'text',
			'default' => '99.4% Purity — Verified by HPLC',
		],
		[
			'id'      => 'panel_bg',
			'type'    => 'text',
			'default' => '#ebe6f5',
		],
		[
			'id'      => 'detail_cta_text',
			'type'    => 'text',
			'default' => 'See the Proof → View COA Library',
		],
		[ 'id' => 'detail_cta_href', 'type' => 'text', 'default' => '/coa-library' ],
		[
			'id'      => 'tabs',
			'type'    => 'repeater',
			'default' => [
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
			'item'    => [
				[ 'id' => 'title', 'type' => 'text' ],
				[ 'id' => 'summary', 'type' => 'text' ],
				[ 'id' => 'body', 'type' => 'richtext' ],
				[ 'id' => 'why_matters', 'type' => 'text' ],
				[ 'id' => 'chart_image', 'type' => 'image' ],
			],
			'item_required' => [ 'title' ],
		],
	],
];
