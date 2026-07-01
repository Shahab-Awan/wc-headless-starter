<?php
defined( 'ABSPATH' ) || exit;

return [
	'type'     => 'vault_cta',
	'name'     => 'Vault bottom CTA',
	'icon'     => 'megaphone',
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
			'id'      => 'headline_prefix',
			'type'    => 'text',
			'default' => 'Ready to Verify? Browse the',
		],
		[
			'id'      => 'headline_accent',
			'type'    => 'text',
			'default' => 'Research Vault.',
		],
		[
			'id'      => 'primary_cta_text',
			'type'    => 'text',
			'default' => 'Browse Catalog →',
		],
		[ 'id' => 'primary_cta_href', 'type' => 'text', 'default' => '/shop' ],
		[
			'id'      => 'secondary_cta_text',
			'type'    => 'text',
			'default' => 'View COA Library',
		],
		[ 'id' => 'secondary_cta_href', 'type' => 'text', 'default' => '/coa-library' ],
	],
];
