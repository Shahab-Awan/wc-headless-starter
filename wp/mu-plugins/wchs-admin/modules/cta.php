<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'cta',
	'name'     => 'CTA button',
	'icon'     => 'link',
	'category' => 'content',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[ 'id' => 'label',        'type' => 'text',    'default' => 'Shop now' ],
		[ 'id' => 'href',         'type' => 'text',    'default' => '/shop' ],
		[ 'id' => 'style',        'type' => 'enum',    'default' => 'primary',
		  'values' => [ 'primary', 'ghost', 'text' ] ],
		[ 'id' => 'size',         'type' => 'enum',    'default' => 'md',
		  'values' => [ 'sm', 'md', 'lg' ] ],
		[ 'id' => 'align',        'type' => 'enum',    'default' => 'center',
		  'values' => [ 'left', 'center', 'right' ] ],
		[ 'id' => 'open_new_tab', 'type' => 'boolean', 'default' => false ],
	],
];
