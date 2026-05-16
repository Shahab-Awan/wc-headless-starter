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

if ( ! function_exists( 'wchs_hero_default_research_stats' ) ) {
	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	function wchs_hero_default_research_stats(): array {
		return [
			[ 'value' => '≥99%', 'label' => 'VERIFIED PURITY' ],
			[ 'value' => '6-panel', 'label' => 'COA EVERY BATCH' ],
			[ 'value' => '60+', 'label' => 'RESEARCH COMPOUNDS' ],
		];
	}
}

if ( ! function_exists( 'wchs_hero_sanitize_research_stats' ) ) {
	/**
	 * @param mixed $value Raw POST JSON string or decoded array.
	 * @return array<int, array{value: string, label: string}>
	 */
	function wchs_hero_sanitize_research_stats( $value, array $values ): array {
		unset( $values );
		$defaults = wchs_hero_default_research_stats();
		if ( is_array( $value ) ) {
			$rows = [];
			foreach ( $value as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$v = sanitize_text_field( (string) ( $row['value'] ?? '' ) );
				$l = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				if ( $v !== '' && $l !== '' ) {
					$rows[] = [ 'value' => $v, 'label' => $l ];
				}
			}
			return ! empty( $rows ) ? $rows : $defaults;
		}
		$raw = is_string( $value ) ? wp_unslash( $value ) : '';
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? wchs_hero_sanitize_research_stats( $decoded, [] ) : $defaults;
	}
}

return [
	'type'     => 'hero',
	'name'     => 'Hero',
	'icon'     => 'image',
	'category' => 'branding',
	'supports' => [
		'spacing'    => false,
		'visibility' => true,
		'header'     => false,
		'color'      => [ 'accent' => true ],
		'typography' => true,
	],
	'fields'   => [
		[ 'id' => 'image_desktop',    'type' => 'media_url', 'default' => '' ],
		[ 'id' => 'image_mobile',     'type' => 'media_url', 'default' => '' ],
		[ 'id' => 'image_position_x', 'type' => 'int',       'default' => 50, 'min' => 0,  'max' => 100 ],
		[ 'id' => 'image_position_y', 'type' => 'int',       'default' => 50, 'min' => 0,  'max' => 100 ],
		[ 'id' => 'image_zoom',       'type' => 'int',       'default' => 100, 'min' => 50, 'max' => 200 ],
		[
			'id'      => 'variant',
			'type'    => 'enum',
			'default' => 'text-only',
			'options' => [
				'text-only'         => 'None',
				'webgl-noise'       => 'Smoke',
				'webgl-variant-2'   => 'Plasma',
				'webgl-variant-3'   => 'Voronoi',
				'webgl-variant-4'   => 'Hex Grid',
				'webgl-variant-5'   => 'Dot Matrix',
				'webgl-variant-6'   => 'Bokeh',
				'research-motion'   => 'Research motion (CSS)',
			],
		],

		[ 'id' => 'headline',    'type' => 'text', 'default' => '' ],
		[ 'id' => 'subheadline', 'type' => 'text', 'default' => '' ],
		[ 'id' => 'show_cta',    'type' => 'boolean', 'default' => true ],
		[ 'id' => 'cta_text',    'type' => 'text', 'default' => '' ],
		[ 'id' => 'cta_link',    'type' => 'text', 'default' => '#' ],
		[ 'id' => 'research_badge',       'type' => 'text', 'default' => '' ],
		[ 'id' => 'cta_secondary_text',   'type' => 'text', 'default' => '' ],
		[ 'id' => 'cta_secondary_link',   'type' => 'text', 'default' => '' ],
		[
			'id'       => 'research_stats',
			'type'     => 'textarea',
			'default'  => '',
			'validate' => 'wchs_hero_sanitize_research_stats',
		],

		[
			'id'      => 'layout',
			'type'    => 'enum',
			'default' => 'left',
			'options' => [
				'left'   => 'Left',
				'center' => 'Center',
				'bottom' => 'Bottom',
			],
		],

		[
			'id'      => 'headline_size',
			'type'    => 'enum',
			'default' => 'l',
			'options' => [
				's'  => 'Small',
				'm'  => 'Medium',
				'l'  => 'Large',
				'xl' => 'Extra large',
			],
		],
		[
			'id'      => 'headline_weight',
			'type'    => 'enum',
			'default' => 'medium',
			'options' => [
				'light'     => 'Light',
				'regular'   => 'Regular',
				'medium'    => 'Medium',
				'semibold'  => 'Semibold',
				'bold'      => 'Bold',
				'extrabold' => 'Extra bold',
				'black'     => 'Black',
			],
		],
		[
			'id'      => 'headline_font',
			'type'    => 'enum',
			'default' => 'inter',
			'options' => [
				'inter'         => 'Inter',
				'barlow'        => 'Barlow Semi Condensed',
				'bebas'         => 'Bebas Neue',
				'playfair'      => 'Playfair Display',
				'space_grotesk' => 'Space Grotesk',
				'archivo'       => 'Archivo',
				'oswald'        => 'Oswald',
			],
		],
		[
			'id'      => 'subheadline_size',
			'type'    => 'enum',
			'default' => 'm',
			'options' => [
				's' => 'Small',
				'm' => 'Medium',
				'l' => 'Large',
			],
		],
		[
			'id'      => 'text_color_mode',
			'type'    => 'enum',
			'default' => 'theme',
			'options' => [
				'theme'  => 'Theme',
				'white'  => 'Always white',
				'black'  => 'Always black',
				'accent' => 'Accent',
			],
		],
	],
];
