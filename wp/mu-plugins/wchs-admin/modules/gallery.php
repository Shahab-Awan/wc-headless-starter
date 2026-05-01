<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'gallery',
	'name'     => 'Gallery',
	'icon'     => 'image',
	'category' => 'media',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
	],
	'fields'   => [
		[ 'id' => 'title',        'type' => 'text',   'default' => '' ],
		[ 'id' => 'columns',      'type' => 'number', 'default' => 3, 'min' => 1, 'max' => 6 ],
		[ 'id' => 'gap',          'type' => 'number', 'default' => 8, 'min' => 0, 'max' => 32 ],
		[
			'id'      => 'aspect_ratio',
			'type'    => 'enum',
			'default' => '1/1',
			'options' => [ '1/1' => '1:1', '4/3' => '4:3', '3/4' => '3:4' ],
		],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [],
			'item'    => [
				[ 'id' => 'src',         'type' => 'image' ],
				[ 'id' => 'title',       'type' => 'text' ],
				[ 'id' => 'description', 'type' => 'text' ],
			],
			// Drop items without a src
			'item_required' => [ 'src' ],
		],
	],
];
