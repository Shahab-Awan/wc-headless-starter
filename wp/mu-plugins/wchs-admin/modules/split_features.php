<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'split_features',
	'name'     => 'Split features',
	'icon'     => 'columns',
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
			'default' => 'alternating',
			'options' => [
				'alternating' => 'Alternating image / text',
				'comparison'  => 'Brand comparison table',
			],
		],
		[ 'id' => 'headline',         'type' => 'text',     'default' => '' ],
		[ 'id' => 'subtitle',        'type' => 'textarea', 'default' => '' ],
		[ 'id' => 'brand_name',      'type' => 'text',     'default' => '' ],
		[ 'id' => 'competitor_name', 'type' => 'text',     'default' => 'Unverified Sellers' ],
		[ 'id' => 'brand_logo',      'type' => 'image',    'default' => '' ],
		[ 'id' => 'competitor_logo', 'type' => 'image',    'default' => '' ],
		[ 'id' => 'title',           'type' => 'text',     'default' => '' ],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [],
			'item'    => [
				[ 'id' => 'eyebrow',     'type' => 'text' ],
				[ 'id' => 'heading',     'type' => 'text' ],
				[ 'id' => 'description', 'type' => 'wysiwyg' ],
				[ 'id' => 'image',       'type' => 'image' ],
			],
			'item_any_required' => [ 'heading', 'image' ],
		],
	],
];
