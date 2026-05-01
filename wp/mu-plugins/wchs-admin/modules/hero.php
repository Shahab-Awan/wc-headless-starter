<?php
defined( 'ABSPATH' ) || exit;

/**
 * Hero module — places a full-featured hero section anywhere a page renders
 * modules. Pared-down from the homepage-hardcoded hero: no trust items
 * (use trust_bar module below), no rating badge or brand eyebrow
 * (homepage-specific), no mobile-specific position/zoom (falls back to
 * desktop values; merchants who need mobile-specific framing can revisit).
 *
 * Supports per-module accent + font overrides so a hero module can have
 * its own brand color scoped without affecting the site default.
 */

return [
	'type'     => 'hero',
	'name'     => 'Hero',
	'icon'     => 'image',
	'category' => 'branding',
	'supports' => [
		'spacing'    => false, // hero has its own internal spacing/mobile rules
		'visibility' => true,
		'header'     => false, // hero renders its own headline; no outer header
		'color'      => [ 'accent' => true ],
		'typography' => true,
	],
	'fields'   => [
		// ── Background & media ──
		[ 'id' => 'image_desktop',     'type' => 'media_url', 'default' => '' ],
		[ 'id' => 'image_mobile',      'type' => 'media_url', 'default' => '' ],
		[ 'id' => 'image_position_x',  'type' => 'int',       'default' => 50, 'min' => 0,  'max' => 100 ],
		[ 'id' => 'image_position_y',  'type' => 'int',       'default' => 50, 'min' => 0,  'max' => 100 ],
		[ 'id' => 'image_zoom',        'type' => 'int',       'default' => 100, 'min' => 50, 'max' => 200 ],
		[ 'id' => 'variant',           'type' => 'enum',      'default' => 'text-only',
		  'values' => [ 'text-only', 'webgl-noise', 'webgl-variant-2', 'webgl-variant-3',
		                'webgl-variant-4', 'webgl-variant-5', 'webgl-variant-6' ] ],

		// ── Content ──
		[ 'id' => 'headline',         'type' => 'text', 'default' => '' ],
		[ 'id' => 'subheadline',      'type' => 'text', 'default' => '' ],
		[ 'id' => 'show_cta',         'type' => 'boolean', 'default' => true ],
		[ 'id' => 'cta_text',         'type' => 'text', 'default' => '' ],
		[ 'id' => 'cta_link',         'type' => 'text', 'default' => '#' ],
		[ 'id' => 'layout',           'type' => 'enum',  'default' => 'left',
		  'values' => [ 'left', 'center', 'bottom' ] ],

		// ── Typography override ──
		[ 'id' => 'headline_size',    'type' => 'enum', 'default' => 'l',
		  'values' => [ 's', 'm', 'l', 'xl' ] ],
		[ 'id' => 'headline_weight',  'type' => 'enum', 'default' => 'medium',
		  'values' => [ 'light', 'regular', 'medium', 'semibold', 'bold', 'extrabold', 'black' ] ],
		[ 'id' => 'headline_font',    'type' => 'enum', 'default' => 'inter',
		  'values' => [ 'inter', 'barlow', 'bebas', 'playfair', 'space_grotesk', 'archivo', 'oswald' ] ],
		[ 'id' => 'subheadline_size', 'type' => 'enum', 'default' => 'm',
		  'values' => [ 's', 'm', 'l' ] ],
		[ 'id' => 'text_color_mode',  'type' => 'enum', 'default' => 'theme',
		  'values' => [ 'theme', 'white', 'black', 'accent' ] ],
	],
];
