<?php
/**
 * Plugin Name: WCHS Admin
 * Description: Homepage configuration and site settings for the headless SPA. Provides a top-level "WCHS" admin menu with hero text, module ordering, accent color, and access mode controls.
 * Version:     0.1.0
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

define( 'WCHS_ADMIN_DIR', __DIR__ . '/wchs-admin' );
define( 'WCHS_ADMIN_URL', WPMU_PLUGIN_URL . '/wchs-admin' );

require_once WCHS_ADMIN_DIR . '/class-module-registry.php';
require_once WCHS_ADMIN_DIR . '/class-schema-sanitizer.php';
require_once WCHS_ADMIN_DIR . '/class-resolver-service.php';
require_once WCHS_ADMIN_DIR . '/admin-page.php';

( new \WCHS\Admin\AdminPage() )->register();
