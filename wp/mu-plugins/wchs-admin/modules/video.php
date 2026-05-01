<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'video',
	'name'     => 'Video / embed',
	'icon'     => 'image',
	'category' => 'content',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
	],
	'fields'   => [
		[ 'id' => 'title',        'type' => 'text',    'default' => '' ],
		[ 'id' => 'source_url',   'type' => 'text',    'default' => '' ],
		[ 'id' => 'poster_url',   'type' => 'text',    'default' => '' ],
		[ 'id' => 'aspect_ratio', 'type' => 'enum',    'default' => '16/9',
		  'values' => [ '16/9', '4/3', '1/1', '9/16' ] ],
		[ 'id' => 'autoplay',     'type' => 'boolean', 'default' => false ],
		[ 'id' => 'muted',        'type' => 'boolean', 'default' => true ],
		[ 'id' => 'loop',         'type' => 'boolean', 'default' => false ],
		[ 'id' => 'controls',     'type' => 'boolean', 'default' => true ],
	],
];
