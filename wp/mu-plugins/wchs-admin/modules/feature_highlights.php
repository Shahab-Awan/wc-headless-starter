<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'feature_highlights',
	'name'     => 'Feature highlights',
	'icon'     => 'grid',
	'category' => 'content',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[ 'id' => 'badge_text',       'type' => 'text', 'default' => 'Verified & Trusted' ],
		[ 'id' => 'headline_prefix',  'type' => 'text', 'default' => 'The Standard for ' ],
		[ 'id' => 'headline_accent',  'type' => 'text', 'default' => 'Verified Peptides' ],
		[ 'id' => 'subheadline',      'type' => 'text', 'default' => '' ],
		[
			'id'               => 'items',
			'type'             => 'repeater',
			'default'          => [],
			'item'             => [
				[
					'id'      => 'variant',
					'type'    => 'enum',
					'default' => 'pin',
					'options' => [
						'pin'   => 'Pin / USA',
						'star'  => 'Star / reviews',
						'lab'   => 'Lab flask',
						'award' => 'Award / quality',
					],
				],
				[ 'id' => 'headline',    'type' => 'text', 'default' => '' ],
				[ 'id' => 'description', 'type' => 'text', 'default' => '' ],
			],
			'item_required' => [ 'headline' ],
		],
		[ 'id' => 'cta_label', 'type' => 'text', 'default' => 'Buy 1 Get 1 Free' ],
		[ 'id' => 'cta_href',  'type' => 'text', 'default' => '/shop' ],
	],
];
