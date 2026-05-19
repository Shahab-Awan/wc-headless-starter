<?php
/**
 * Update reviews_listicle headline on saved pages (local or prod via wp eval-file).
 */
defined( 'ABSPATH' ) || exit;

$new_headline = 'Amazing Reviews with a 4.9 Rating';
$old_patterns = [ 'Rated 4.9 Stars', 'Rated 4.9 stars', 'rated 4.9 stars' ];

$cfg = get_option( 'wchs_pages_config', [ 'pages' => [] ] );
if ( ! is_array( $cfg['pages'] ?? null ) ) {
	WP_CLI::error( 'wchs_pages_config has no pages.' );
}

$updated = 0;
foreach ( $cfg['pages'] as $pi => $page ) {
	$modules = $page['modules'] ?? [];
	if ( ! is_array( $modules ) ) {
		continue;
	}
	foreach ( $modules as $mi => $mod ) {
		if ( ( $mod['type'] ?? '' ) !== 'reviews_listicle' ) {
			continue;
		}
		$headline = (string) ( $mod['config']['headline'] ?? '' );
		$should   = $headline === ''
			|| in_array( $headline, $old_patterns, true )
			|| stripos( $headline, 'rated 4.9' ) !== false;
		if ( $should ) {
			$cfg['pages'][ $pi ]['modules'][ $mi ]['config']['headline'] = $new_headline;
			++$updated;
		}
	}
}

update_option( 'wchs_pages_config', $cfg );
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

WP_CLI::success( "Updated {$updated} reviews_listicle headline(s) to: {$new_headline}" );
