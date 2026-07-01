<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'featured_products',
	'name'     => 'Featured products',
	'icon'     => 'package',
	'category' => 'commerce',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[
			'id'      => 'eyebrow',
			'type'    => 'text',
			'default' => 'Bestsellers',
		],
		[
			'id'      => 'headline_prefix',
			'type'    => 'text',
			'default' => 'Featured',
		],
		[
			'id'      => 'headline_accent',
			'type'    => 'text',
			'default' => 'Products',
		],
		[
			'id'      => 'subheadline',
			'type'    => 'text',
			'default' => 'Explore our most popular research compounds, chosen for their quality, purity, and consistency.',
		],
		[
			'id'      => 'product_badge',
			'type'    => 'text',
			'default' => 'Most Popular',
		],
		[
			'id'      => 'source',
			'type'    => 'enum',
			'default' => 'popular',
			'options' => [
				'popular'      => 'Curated bestsellers (BPC-157, Retatrutide, GHK-Cu)',
				'best_sellers' => 'WooCommerce popularity ranking',
			],
		],
		[
			'id'      => 'product_limit',
			'type'    => 'number',
			'default' => 3,
		],
		[
			'id'      => 'cta_text',
			'type'    => 'text',
			'default' => 'Explore All Products',
		],
		[ 'id' => 'cta_href', 'type' => 'text', 'default' => '/shop' ],
	],
];
