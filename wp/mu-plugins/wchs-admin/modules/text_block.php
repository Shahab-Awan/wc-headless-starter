<?php
defined( 'ABSPATH' ) || exit;

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
		[ 'id' => 'brand_name',      'type' => 'text',    'default' => '' ],
		[ 'id' => 'competitor_name', 'type' => 'text',    'default' => 'Unverified Sellers' ],
		[ 'id' => 'brand_logo',      'type' => 'image',   'default' => '' ],
		[ 'id' => 'competitor_logo', 'type' => 'image',   'default' => '' ],
		[
			'id'      => 'comparison_rows',
			'type'    => 'repeater',
			'default' => [],
			'item'    => [
				[ 'id' => 'heading', 'type' => 'text' ],
			],
			'item_any_required' => [ 'heading' ],
		],
	],
];
