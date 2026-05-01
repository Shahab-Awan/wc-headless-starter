<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'logo_strip',
	'name'     => 'Logo strip',
	'icon'     => 'image',
	'category' => 'branding',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
	],
	'fields'   => [
		[ 'id' => 'title',     'type' => 'text',    'default' => '' ],
		[ 'id' => 'grayscale', 'type' => 'boolean', 'default' => true ],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [],
			'item'    => [
				[ 'id' => 'src',      'type' => 'media_url', 'default' => '' ],
				[ 'id' => 'alt',      'type' => 'text',      'default' => '' ],
				[ 'id' => 'link_url', 'type' => 'text',      'default' => '' ],
			],
			'item_required' => [ 'src' ],
		],
	],
];
