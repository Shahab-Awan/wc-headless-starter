<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'vault_why_choose',
	'name'     => 'Vault why choose',
	'icon'     => 'grid',
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
			'default' => 'Why Choose Alyve',
		],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [
				[
					'title'       => 'Always In Stock',
					'description' => 'Core research compounds restocked on a reliable cadence — your protocol stays on schedule.',
					'icon'        => 'stock',
					'accent'      => 'violet',
				],
				[
					'title'       => 'Volume Pricing',
					'description' => 'Transparent tiered discounts from 3 vials up — scale your order and save on every batch.',
					'icon'        => 'volume',
					'accent'      => 'green',
				],
				[
					'title'       => 'Safe & Protected Shipping',
					'description' => 'Tracked domestic fulfillment with shipment protection on every order.',
					'icon'        => 'shipping',
					'accent'      => 'amber',
				],
				[
					'title'       => 'Third-Party Verified',
					'description' => 'Independent U.S. laboratory testing confirms identity, purity, and safety before release.',
					'icon'        => 'verified',
					'accent'      => 'rose',
				],
				[
					'title'       => 'COA Every Batch',
					'description' => 'Full Certificates of Analysis published for every lot — review documentation before you buy.',
					'icon'        => 'coa',
					'accent'      => 'blue',
				],
				[
					'title'       => 'Same-Day Fulfillment',
					'description' => 'Orders placed before 2PM EST ship same day via tracked carrier.',
					'icon'        => 'fulfillment',
					'accent'      => 'teal',
				],
			],
			'item'    => [
				[ 'id' => 'title', 'type' => 'text' ],
				[ 'id' => 'description', 'type' => 'text' ],
				[
					'id'      => 'icon',
					'type'    => 'enum',
					'default' => 'stock',
					'options' => [
						'stock'        => 'Stacked boxes (inventory)',
						'volume'       => 'Discount badge (%)',
						'shipping'     => 'Package check (protected shipping)',
						'verified'     => 'Verified badge',
						'coa'          => 'Document check (COA)',
						'fulfillment'  => 'Lightning (same-day)',
					],
				],
				[
					'id'      => 'accent',
					'type'    => 'enum',
					'default' => 'violet',
					'options' => [
						'violet' => 'Violet',
						'green'  => 'Green',
						'amber'  => 'Amber',
						'rose'   => 'Rose',
						'blue'   => 'Blue',
						'teal'   => 'Teal',
					],
				],
			],
			'item_required' => [ 'title' ],
		],
	],
];
