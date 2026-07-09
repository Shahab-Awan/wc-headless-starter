<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'price_comparison',
	'name'     => 'Price comparison',
	'icon'     => 'chart-bar',
	'category' => 'commerce',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[ 'id' => 'headline', 'type' => 'text', 'default' => 'Priced Below The Market, Guaranteed.' ],
		[
			'id'      => 'body',
			'type'    => 'textarea',
			'default' => 'We watch pricing across the research peptide market and adjust ours to stay below verified competitors — for the same purity, batch documentation, and fulfillment standards.',
		],
		[
			'id'               => 'bullets',
			'type'             => 'repeater',
			'default'          => [],
			'item'             => [
				[ 'id' => 'variant', 'type' => 'select', 'default' => 'globe', 'options' => [ 'globe', 'price_search', 'award' ] ],
				[ 'id' => 'headline', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'description', 'type' => 'text', 'default' => '' ],
			],
			'item_required'    => [ 'headline' ],
		],
		[ 'id' => 'cta_label', 'type' => 'text', 'default' => 'Browse Catalog' ],
		[ 'id' => 'cta_href', 'type' => 'text', 'default' => '/shop' ],
		[ 'id' => 'status_label', 'type' => 'text', 'default' => 'LIVE PRICE COMPARISON' ],
		[ 'id' => 'product_label', 'type' => 'text', 'default' => 'BPC-157 5MG' ],
		[ 'id' => 'lowest_badge', 'type' => 'text', 'default' => 'LOWEST' ],
		[ 'id' => 'brand_name', 'type' => 'text', 'default' => '' ],
		[ 'id' => 'brand_price', 'type' => 'text', 'default' => '28.00' ],
		[ 'id' => 'brand_tags', 'type' => 'text', 'default' => 'IN STOCK · SHIPS FAST · COA ON FILE' ],
		[
			'id'               => 'competitors',
			'type'             => 'repeater',
			'default'          => [],
			'item'             => [
				[ 'id' => 'letter', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'name', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'price', 'type' => 'text', 'default' => '' ],
			],
			'item_any_required' => [ 'name', 'price' ],
		],
		[
			'id'      => 'footnote',
			'type'    => 'textarea',
			'default' => 'Prices tracked from publicly listed research peptide vendors for comparable SKU, dose, and purity tier. Updated regularly; for research use only.',
		],
	],
];
