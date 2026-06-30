<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'vault_hero',
	'name'     => 'Vault hero',
	'icon'     => 'award',
	'category' => 'branding',
	'supports' => [
		'spacing'    => true,
		'visibility' => true,
		'header'     => false,
		'contexts'   => [ 'pages' ],
		'color'      => [ 'accent' => true ],
	],
	'fields'   => [
		[
			'id'      => 'headline',
			'type'    => 'text',
			'default' => 'Quality You Can Verify, Not Just Trust',
		],
		[
			'id'      => 'stats',
			'type'    => 'repeater',
			'default' => [
				[ 'label' => '99%+ Purity Guaranteed' ],
				[ 'label' => '5 Quality Checks' ],
				[ 'label' => '100% US Verified' ],
			],
			'item'    => [
				[ 'id' => 'label', 'type' => 'text' ],
			],
			'item_required' => [ 'label' ],
		],
		[ 'id' => 'cta_text', 'type' => 'text', 'default' => 'Browse the Vault →' ],
		[ 'id' => 'cta_href', 'type' => 'text', 'default' => '/shop' ],
		[ 'id' => 'bg_image', 'type' => 'image', 'default' => '' ],
		[ 'id' => 'vial_primary', 'type' => 'image', 'default' => '' ],
		[ 'id' => 'vial_primary_alt', 'type' => 'text', 'default' => '' ],
		[ 'id' => 'vial_secondary', 'type' => 'image', 'default' => '' ],
		[ 'id' => 'vial_secondary_alt', 'type' => 'text', 'default' => '' ],
		[ 'id' => 'vial_tertiary', 'type' => 'image', 'default' => '' ],
		[ 'id' => 'vial_tertiary_alt', 'type' => 'text', 'default' => '' ],
	],
];
