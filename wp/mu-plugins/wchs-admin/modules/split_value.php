<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'split_value',
	'name'     => 'Value split (BOGO)',
	'icon'     => 'columns',
	'category' => 'commerce',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[ 'id' => 'rating_line', 'type' => 'text', 'default' => 'Rated 4.98/5 · 24,987+ reviews' ],
		[ 'id' => 'headline_prefix', 'type' => 'text', 'default' => 'A Leading Provider of Research Grade' ],
		[ 'id' => 'headline_accent', 'type' => 'text', 'default' => 'Peptides.' ],
		[ 'id' => 'accent_underline', 'type' => 'boolean', 'default' => true ],
		[
			'id'               => 'bullets',
			'type'             => 'repeater',
			'default'          => [],
			'item'             => [
				[ 'id' => 'text', 'type' => 'text', 'default' => '' ],
			],
			'item_required'    => [ 'text' ],
		],
		[ 'id' => 'cta_label', 'type' => 'text', 'default' => 'Buy 1 Get 1 Free' ],
		[ 'id' => 'cta_href', 'type' => 'text', 'default' => '/shop' ],
		[ 'id' => 'trust_note', 'type' => 'text', 'default' => 'Research use only. All major credit/debit cards, PayPal, ACH, BTC, Zelle.' ],
		[ 'id' => 'promo_badge_eyebrow', 'type' => 'text', 'default' => 'LIMITED TIME' ],
		[ 'id' => 'promo_badge_title', 'type' => 'text', 'default' => 'Buy 1 Get 1 Free' ],
		[ 'id' => 'image', 'type' => 'image', 'default' => '' ],
		[ 'id' => 'image_alt', 'type' => 'text', 'default' => '' ],
		[
			'id'               => 'stats',
			'type'             => 'repeater',
			'default'          => [],
			'item'             => [
				[ 'id' => 'value', 'type' => 'text', 'default' => '' ],
				[ 'id' => 'label', 'type' => 'text', 'default' => '' ],
			],
			'item_any_required' => [ 'value', 'label' ],
		],
	],
];
