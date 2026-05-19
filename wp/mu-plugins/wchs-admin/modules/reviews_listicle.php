<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'reviews_listicle',
	'name'     => 'Reviews listicle',
	'icon'     => 'star-filled',
	'category' => 'content',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
		'contexts'   => [ 'homepage', 'shop', 'pdp', 'pages' ],
	],
	'fields'   => [
		[
			'id'      => 'headline',
			'type'    => 'text',
			'default' => 'Amazing Reviews with a 4.9 Rating',
		],
		[
			'id'      => 'items',
			'type'    => 'repeater',
			'default' => [
				[
					'quote'  => 'COAs matched the batch numbers on our vials. Documentation was clear and easy to file for our lab records.',
					'name'   => 'Vincent R.',
					'rating' => 5,
				],
				[
					'quote'  => 'Ordering was straightforward and fulfillment was faster than our previous supplier. Purity reports were posted before we checked out.',
					'name'   => 'Justin F.',
					'rating' => 5,
				],
				[
					'quote'  => 'Consistent quality across reorders — no surprises between batches. Support answered technical questions the same day.',
					'name'   => 'Carlos B.',
					'rating' => 5,
				],
			],
			'item'          => [
				[ 'id' => 'quote', 'type' => 'textarea' ],
				[ 'id' => 'name', 'type' => 'text' ],
				[
					'id'      => 'rating',
					'type'    => 'number',
					'default' => 5,
					'min'     => 1,
					'max'     => 5,
				],
			],
			'item_required' => [ 'quote', 'name' ],
		],
	],
];
