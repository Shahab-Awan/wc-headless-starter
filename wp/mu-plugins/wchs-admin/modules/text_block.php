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
	],
	'fields'   => [
		[ 'id' => 'title',   'type' => 'text',    'default' => '' ],
		[ 'id' => 'content', 'type' => 'wysiwyg', 'default' => '' ],
	],
];
