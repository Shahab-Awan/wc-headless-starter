<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'contact_form',
	'name'     => 'Contact form',
	'icon'     => 'mail',
	'category' => 'engagement',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => true,
		'color'      => [ 'accent' => true ],
		'contexts'   => [ 'homepage', 'pages' ],
	],
	'fields'   => [
		[ 'id' => 'title',           'type' => 'text',  'default' => '' ],
		[ 'id' => 'recipient_email', 'type' => 'email', 'default' => '' ],
		[ 'id' => 'subject_prefix',  'type' => 'text',  'default' => '' ],
		[ 'id' => 'success_message', 'type' => 'text',  'default' => '' ],
		[
			'id'      => 'fields',
			'type'    => 'repeater',
			'default' => [],
			'item'    => [
				[ 'id' => 'name',  'type' => 'slug' ],
				[ 'id' => 'label', 'type' => 'text' ],
				[
					'id'      => 'type',
					'type'    => 'enum',
					'default' => 'text',
					'options' => [
						'text'     => 'Text',
						'email'    => 'Email',
						'textarea' => 'Textarea',
					],
				],
				[ 'id' => 'required', 'type' => 'boolean', 'default' => false ],
			],
			// Drop fields missing name or label
			'item_required' => [ 'name', 'label' ],
		],
	],
];
