<?php
/**
 * One-off: set WCHS header nav links (Home, Shop, About, COA Library, Contact + Account icon).
 *
 * Usage (local Docker):
 *   docker cp scripts/patch-header-links.php wchs-wpcli:/tmp/patch-header-links.php
 *   docker exec wchs-wpcli wp eval-file /tmp/patch-header-links.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run via: wp eval-file\n" );
}

if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
	echo "WCHS AdminPage not loaded.\n";
	exit( 1 );
}

$links = [
	[ 'label' => 'Home', 'url' => '/', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'Shop', 'url' => '/shop', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'About', 'url' => '/about', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'COA Library', 'url' => '/coa-library', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'Contact', 'url' => '/contact', 'display' => 'text', 'icon' => '', 'accent' => false, 'mobile_pin' => false ],
	[ 'label' => 'Account', 'url' => '/account', 'display' => 'icon', 'icon' => 'user', 'accent' => true, 'mobile_pin' => false ],
];

$settings = \WCHS\Admin\AdminPage::get_site_settings();
$settings['header_links'] = $links;
update_option( 'wchs_site_settings', $settings, false );

echo "Updated header_links (" . count( $links ) . " items).\n";
