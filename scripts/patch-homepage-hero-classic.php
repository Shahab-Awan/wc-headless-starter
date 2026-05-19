<?php
/**
 * Restore homepage hero to classic Bokeh (pre–research-motion default).
 * Run: wp eval-file scripts/patch-homepage-hero-classic.php
 */
defined( 'ABSPATH' ) || exit;

$cfg = get_option( 'wchs_homepage_config', [] );
if ( ! is_array( $cfg ) || ! is_array( $cfg['hero'] ?? null ) ) {
	WP_CLI::error( 'wchs_homepage_config missing or invalid' );
}

$cfg['hero']['variant']       = 'webgl-variant-6';
$cfg['hero']['layout']        = 'left';
$cfg['hero']['show_eyebrow']  = $cfg['hero']['show_eyebrow'] ?? true;
$cfg['hero']['content_mode']  = $cfg['hero']['content_mode'] ?? 'text';

update_option( 'wchs_homepage_config', $cfg );
WP_CLI::success( 'Homepage hero variant set to webgl-variant-6 (Bokeh), layout left' );
