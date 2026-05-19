<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'order_handling',
	'name'     => 'Order handling',
	'icon'     => 'list',
	'category' => 'content',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[ 'id' => 'badge_text',  'type' => 'text', 'default' => 'Our Process' ],
		[ 'id' => 'headline',    'type' => 'text', 'default' => 'How Every Order Is Handled' ],
		[ 'id' => 'subheadline', 'type' => 'text', 'default' => '' ],
		[
			'id'      => 'bg_color',
			'type'    => 'color',
			'default' => '',
		],
		[
			'id'               => 'steps',
			'type'             => 'repeater',
			'default'          => [],
			'item'             => [
				[
					'id'      => 'variant',
					'type'    => 'enum',
					'default' => 'verified',
					'options' => [
						'verified' => 'Verified batches',
						'lab'      => 'Lab testing',
						'shipping' => 'Shipping',
						'support'  => 'Support',
					],
				],
				[ 'id' => 'icon_url',    'type' => 'image', 'default' => '' ],
				[ 'id' => 'headline',    'type' => 'text', 'default' => '' ],
				[ 'id' => 'description', 'type' => 'text', 'default' => '' ],
			],
			'item_required' => [ 'headline' ],
		],
		[ 'id' => 'metrics_title', 'type' => 'text', 'default' => 'Quality Metrics' ],
		[
			'id'                => 'metrics',
			'type'              => 'repeater',
			'default'           => [],
			'item'              => [
				[ 'id' => 'value', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'label', 'type' => 'text', 'default' => '' ],
			],
			'item_any_required' => [ 'value', 'label' ],
		],
	],
];
