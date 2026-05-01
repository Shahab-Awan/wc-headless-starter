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
	],
	'fields'   => [
		[ 'id' => 'title', 'type' => 'text', 'default' => '' ],
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
			// Keep items with at least heading or image (legacy behavior)
			'item_any_required' => [ 'heading', 'image' ],
		],
	],
];
