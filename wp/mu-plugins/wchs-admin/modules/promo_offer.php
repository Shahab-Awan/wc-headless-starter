<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'promo_offer',
	'name'     => 'Promo offer (split)',
	'icon'     => 'tag',
	'category' => 'commerce',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
		'contexts'   => [ 'homepage', 'shop', 'pdp', 'pages' ],
	],
	'fields'   => [
		[ 'id' => 'intro_headline',    'type' => 'text',    'default' => '' ],
		[ 'id' => 'intro_subheadline', 'type' => 'text',    'default' => '✨ We don\'t hand these out every day… consider this an exclusive Alyve hookup, just for you.' ],
		[ 'id' => 'badge_text',        'type' => 'text',    'default' => 'LIMITED TIME OFFER ✨' ],
		[ 'id' => 'image',             'type' => 'image',   'default' => '/wp-content/uploads/2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp' ],
		[ 'id' => 'image_alt',         'type' => 'text',    'default' => 'Alyve research-grade peptides — Buy one get one free' ],
		[ 'id' => 'ribbon_text',       'type' => 'text',    'default' => 'PUBLISHED COAs + BATCH DOCS WITH EVERY ORDER' ],
		[ 'id' => 'offer_primary',     'type' => 'text',    'default' => 'UP TO 40% OFF' ],
		[ 'id' => 'offer_secondary',   'type' => 'text',    'default' => 'FOR A LIMITED TIME ONLY!' ],
		[ 'id' => 'scarcity_text',     'type' => 'text',    'default' => 'High demand — popular batches sell out quickly.' ],
		[ 'id' => 'cta_label',         'type' => 'text',    'default' => 'GET 40% OFF' ],
		[ 'id' => 'cta_href',          'type' => 'text',    'default' => '/shop' ],
		[ 'id' => 'show_countdown',    'type' => 'boolean', 'default' => true ],
		[ 'id' => 'countdown_end_at',  'type' => 'text',    'default' => '' ],
		[ 'id' => 'status_label',      'type' => 'text',    'default' => 'Sell-out risk:' ],
		[ 'id' => 'status_value',      'type' => 'text',    'default' => 'High' ],
		[ 'id' => 'status_note',       'type' => 'text',    'default' => 'Faster shipping' ],
		[ 'id' => 'footer_text',       'type' => 'text',    'default' => 'Try it today with a 60-Day Money-Back Guarantee!' ],
	],
];
