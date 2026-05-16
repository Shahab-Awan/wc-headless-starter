<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'category_grid',
	'name'     => 'Category grid',
	'icon'     => 'grid',
	'category' => 'commerce',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
		'contexts'   => [ 'homepage', 'pages' ],
	],
	'fields'   => [
		[ 'id' => 'title',   'type' => 'text',   'default' => '' ],
		[ 'id' => 'columns', 'type' => 'number', 'default' => 4, 'min' => 1, 'max' => 6 ],
		[ 'id' => 'gap',     'type' => 'number', 'default' => 12, 'min' => 0, 'max' => 32 ],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [],
			'item'    => [
				[ 'id' => 'category_id', 'type' => 'number', 'min' => 1 ],
				[ 'id' => 'image',       'type' => 'image' ],
			],
			// Drop items without a category
			'item_required' => [ 'category_id' ],
		],
	],
];
