<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'trust_bar',
	'name'     => 'Trust bar',
	'icon'     => 'shield',
	'category' => 'branding',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[ 'id' => 'title',       'type' => 'text',    'default' => '' ],
		[ 'id' => 'icon_accent', 'type' => 'boolean', 'default' => true ],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [],
			'item'    => [
				[ 'id' => 'icon',        'type' => 'icon', 'default' => 'check' ],
				[ 'id' => 'headline',    'type' => 'text' ],
				[ 'id' => 'description', 'type' => 'text' ],
			],
			// Drop items with no headline (matches legacy sanitize behavior)
			'item_required' => [ 'headline' ],
		],
	],
];
