<?php
/**
 * Merge homepage feature_highlights + order_handling defaults into saved config.
 * Run: wp eval-file scripts/patch-homepage-content-modules.php
 */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WCHS\\Admin\\AdminPage' ) ) {
	WP_CLI::error( 'WCHS Admin not loaded' );
}

$homepage = \WCHS\Admin\AdminPage::get_homepage_config();
update_option( 'wchs_homepage_config', $homepage );
WP_CLI::success( 'Homepage modules merged with Alyve defaults (feature highlights, order handling).' );
