<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'review_slider',
	'name'     => 'Review slider',
	'icon'     => 'star',
	'category' => 'social_proof',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
	],
	'fields'   => [
		[ 'id' => 'title',       'type' => 'text',         'default' => '' ],
		[ 'id' => 'photos_only', 'type' => 'boolean',      'default' => false ],
		[ 'id' => 'product_ids', 'type' => 'product_list', 'default' => [] ],
	],
];
