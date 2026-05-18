<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'shop_grid',
	'name'     => 'Shop grid',
	'icon'     => 'shop',
	'category' => 'commerce',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
		'contexts'   => [ 'homepage', 'shop' ],
	],
	'fields'   => [
		[ 'id' => 'title', 'type' => 'text', 'default' => '' ],
		[
			'id'       => 'category',
			'type'     => 'text',
			'default'  => null,
			'validate' => function ( $value ) {
				$v = sanitize_text_field( (string) $value );
				return $v !== '' ? $v : null;
			},
		],
	],
];
