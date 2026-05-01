<?php
/**
 * Plugin Name: Headless Media
 * Description: Auto-WebP output for new uploads + helpers for the WCHS
 *   admin's pre-save alt-text check. Existing uploads are untouched.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prefer WebP as the output format when WP's image editor resizes new
 * JPEGs / PNGs. Preserves the original upload (the editor only rewrites
 * the generated sub-sizes). WP auto-falls-back to the source format
 * when the server lacks WebP support in GD/Imagick, so this is safe.
 */
add_filter(
	'wp_image_editor_output_format',
	function ( $formats ) {
		if ( ! is_array( $formats ) ) {
			$formats = [];
		}
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	},
	10
);
