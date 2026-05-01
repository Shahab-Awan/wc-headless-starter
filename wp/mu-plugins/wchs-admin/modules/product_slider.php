<?php
/**
 * Module schema: product_slider
 *
 * Schema keys consumed by WCHS\Admin\ModuleRegistry + SchemaSanitizer:
 *   type      (required) module slug
 *   name      admin-facing label
 *   icon      chip icon key for the canvas tracker row (Phase 2+)
 *   category  chip category for tracker colour mapping
 *   supports  { spacing, visibility, header, typography, color } opt-ins
 *   fields[]  { id, type, default, options, validate, hidden }
 *             - type primitives: text, enum, boolean, wysiwyg, number,
 *               email, url, product_list, category_ref, repeater, image
 *             - validate: fn($value, $values) => sanitized value
 *             - hidden: bool | fn($values) => bool (evaluated server+client)
 */
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'product_slider',
	'name'     => 'Product slider',
	'icon'     => 'package',
	'category' => 'commerce',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
	],
	'fields'   => [
		[
			'id'       => 'title',
			'type'     => 'text',
			'default'  => '',
		],
		[
			'id'       => 'source',
			'type'     => 'enum',
			'default'  => 'all',
			'options'  => [
				'all'          => 'All products',
				'featured'     => 'Featured',
				'category'     => 'By category',
				'best_sellers' => 'Best sellers',
				'manual'       => 'Manual (pick products)',
			],
		],
		[
			'id'       => 'category',
			'type'     => 'text',
			'default'  => null,
			'hidden'   => function ( $values ) { return ( $values['source'] ?? 'all' ) !== 'category'; },
			'validate' => function ( $value ) {
				$v = sanitize_text_field( (string) $value );
				return $v !== '' ? $v : null;
			},
		],
		[
			'id'       => 'product_ids',
			'type'     => 'product_list',
			'default'  => [],
			'hidden'   => function ( $values ) { return ( $values['source'] ?? 'all' ) !== 'manual'; },
		],
	],
];
