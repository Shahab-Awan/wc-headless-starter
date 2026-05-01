<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'spacer',
	'name'     => 'Spacer',
	'icon'     => 'rows',
	'category' => 'content',
	'supports' => [
		'spacing'    => false,
		'visibility' => true,
		'header'     => false,
	],
	'fields'   => [
		[ 'id' => 'height', 'type' => 'int', 'default' => 40, 'min' => 8, 'max' => 160 ],
	],
];
