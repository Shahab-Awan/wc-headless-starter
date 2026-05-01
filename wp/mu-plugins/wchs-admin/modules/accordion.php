<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'accordion',
	'name'     => 'Accordion',
	'icon'     => 'chevron-down',
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
				[ 'id' => 'q', 'type' => 'text' ],
				[ 'id' => 'a', 'type' => 'wysiwyg' ],
			],
			// Drop items where both q and a are empty (legacy behavior)
			'item_any_required' => [ 'q', 'a' ],
		],
	],
];
