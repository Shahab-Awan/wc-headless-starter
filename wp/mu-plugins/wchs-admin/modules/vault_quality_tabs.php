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
			'default' => 'Documented quality for research and laboratory use. Every batch meets our internal purity standards.',
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
			'id'      => 'guarantee_cards',
			'type'    => 'repeater',
			'default' => [
				[
					'title'       => '99% Purity Guaranteed',
					'description' => 'Every batch verified',
					'tooltip'     => '',
					'accent'      => 'green',
					'icon'        => 'purity',
				],
				[
					'title'       => 'Shipment Protection',
					'description' => 'Every order fully covered',
					'tooltip'     => 'Full replacement or refund if your shipment is lost or damaged in transit.',
					'accent'      => 'blue',
					'icon'        => 'shipping',
				],
				[
					'title'       => 'COA with Every Batch',
					'description' => 'Third Party tested in America',
					'tooltip'     => 'Independent U.S. lab Certificates of Analysis ship with every order and are published before purchase.',
					'accent'      => 'yellow',
					'icon'        => 'coa',
				],
			],
			'item'    => [
				[ 'id' => 'title', 'type' => 'text' ],
				[ 'id' => 'description', 'type' => 'text' ],
				[ 'id' => 'tooltip', 'type' => 'text' ],
				[
					'id'      => 'accent',
					'type'    => 'enum',
					'default' => 'green',
					'options' => [
						'green'  => 'Green',
						'blue'   => 'Blue',
						'yellow' => 'Yellow',
					],
				],
				[
					'id'      => 'icon',
					'type'    => 'enum',
					'default' => 'purity',
					'options' => [
						'purity'   => 'Purity badge',
						'shipping' => 'Shipping truck',
						'coa'      => 'Certificate / document',
					],
				],
			],
			'item_required' => [ 'title' ],
		],
	],
];
