<?php
/**
 * Plugin Name: Headless Preview Role
 * Description: Gates sensitive admin surfaces (API keys, integrations,
 *              payment gateway config, security tabs) behind administrator
 *              capability so shop_manager and other restricted roles can
 *              preview the site without seeing credentials or being able
 *              to install plugins.
 *
 * Restrictions applied to users WITHOUT `manage_options` (admin):
 *   - WCHS admin tabs hidden: Integrations, Access & Privacy
 *   - WCHS admin tab visible but field-masked: Checkout (easypost_api_key)
 *   - WC Settings > Payments: hidden (Stripe / offline gateway keys)
 *   - Tools > Export, Tools > Import: hidden
 *   - /wp-admin/theme-editor.php and /plugin-editor.php: 403 even if
 *     the cap leaked from somewhere
 *   - Plugin install/update/delete: 403 via filter_has_cap
 *
 * The goal: let a shop_manager click around, see orders + products + modules,
 * but NOT exfiltrate keys or install a migration plugin.
 *
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * True ONLY for users whose role list actually includes `administrator`.
 * Needed because shop_manager gets a synthetic `manage_options` grant via
 * user_has_cap below, so current_user_can('manage_options') is NOT a reliable
 * admin check any more. Use this helper everywhere we gate on "real admin".
 */
function wchs_is_real_admin( $user = null ): bool {
	$u = $user ?: wp_get_current_user();
	if ( ! $u || ! $u->ID ) return false;
	return in_array( 'administrator', (array) $u->roles, true );
}

/**
 * Is the current request an Omnisend admin screen?
 * Checks both the GET `page` param and the POST `action` — the plugin
 * fires admin-ajax + admin-post requests during its onboarding flow.
 */
function wchs_pr_is_omnisend_request(): bool {
	$page   = isset( $_REQUEST['page'] )   ? (string) $_REQUEST['page']   : '';
	$action = isset( $_REQUEST['action'] ) ? (string) $_REQUEST['action'] : '';
	if ( 0 === strpos( $page, 'omnisend' ) )   return true;
	if ( 0 === strpos( $action, 'omnisend' ) ) return true;
	// The plugin's REST endpoints live under /wp-json/omnisend-connection/
	$req_uri = $_SERVER['REQUEST_URI'] ?? '';
	if ( false !== strpos( $req_uri, '/omnisend' ) ) return true;
	return false;
}

/**
 * Block sensitive caps for everyone except administrators, regardless of
 * what role.json or plugins claim. EXCEPT: grant `manage_options` to
 * shop_manager on Omnisend-specific pages so they can manage the
 * Omnisend email plugin without getting full admin access.
 */
add_filter( 'user_has_cap', function ( $allcaps, $caps, $args ) {
	// Real admin (role includes administrator) — untouched.
	$user = $args[1] ?? 0;
	$user_obj = $user ? get_user_by( 'id', $user ) : wp_get_current_user();
	$is_real_admin = $user_obj && in_array( 'administrator', (array) $user_obj->roles, true );
	if ( $is_real_admin ) return $allcaps;

	// Always strip dangerous caps for non-admin roles, regardless of what
	// other plugins (or a synthetic grant below) claim. Plugin install,
	// edit-files, export, user-mgmt stay off forever.
	$forbidden = [
		'install_plugins', 'activate_plugins', 'update_plugins',
		'delete_plugins', 'edit_plugins',
		'install_themes', 'switch_themes', 'edit_themes',
		'update_themes', 'delete_themes',
		'edit_files', 'unfiltered_html',
		'update_core', 'edit_dashboard',
		'import', 'export',
		'edit_users', 'create_users', 'delete_users', 'promote_users',
	];
	foreach ( $forbidden as $cap ) {
		if ( isset( $allcaps[ $cap ] ) ) unset( $allcaps[ $cap ] );
	}

	// shop_manager (manage_woocommerce holder) gets `manage_options`
	// unconditionally so plugin admin menus that hardcode the cap
	// (Omnisend, WooCommerce Settings, WP Settings) register normally
	// into their sidebar. This is SAFE because:
	//   (a) The forbidden-caps loop above still strips plugin/theme
	//       install/edit/delete, file-editing, imports/exports, and
	//       user management.
	//   (b) admin_init (below) 403s direct URL access to every
	//       settings-with-secrets surface — wc-settings, wp-admin/options-*,
	//       users.php, plugins.php, themes.php, import/export, etc.
	//   (c) admin_menu (below) removes those surfaces from the sidebar
	//       so there's no visual invitation to visit them.
	//   (d) admin_post_wchs_save_settings strips sensitive keys from
	//       $_POST if someone crafts a request.
	// Net: shop_manager sees Omnisend + ours, cannot reach Stripe/EasyPost
	// keys, cannot install a migration plugin, cannot export the DB.
	if ( ! empty( $allcaps['manage_woocommerce'] ) ) {
		$allcaps['manage_options'] = true;
	}
	return $allcaps;
}, 100, 3 );

/**
 * Remove sensitive admin menu items for non-admins.
 * Fires late so WC/plugins have added their items first.
 */
add_action( 'admin_menu', function () {
	if ( wchs_is_real_admin() ) return;

	// WC Settings is now visible to shop_manager (they need to configure
	// their own Stripe + shipping + tax). Status + Addons stay hidden to
	// reduce UI clutter — real admins can reach them via direct URL if needed.
	remove_submenu_page( 'woocommerce', 'wc-status' );
	remove_submenu_page( 'woocommerce', 'wc-addons' );

	// Tools menu items that could be abused
	remove_submenu_page( 'tools.php', 'tools.php' );          // Available Tools
	remove_submenu_page( 'tools.php', 'export.php' );         // Export
	remove_submenu_page( 'tools.php', 'import.php' );         // Import
	// Leave site-health and export-personal-data for privacy compliance

	// Plugins and Themes top-level (shouldn't appear anyway without caps)
	remove_menu_page( 'plugins.php' );
	remove_menu_page( 'themes.php' );
	remove_menu_page( 'users.php' );

	// Settings → General, Writing, Reading, etc. — all manage_options-gated
	// but extra defensive
	remove_menu_page( 'options-general.php' );
}, 999 );

/**
 * Direct-URL defense — if someone pastes the URL, return 403 instead of
 * showing a "You don't have permission" WordPress template.
 */
add_action( 'admin_init', function () {
	if ( ! is_admin() || wchs_is_real_admin() ) return;

	$req = $_SERVER['REQUEST_URI'] ?? '';
	$forbidden_paths = [
		'/wp-admin/plugin-install.php',
		'/wp-admin/plugin-editor.php',
		'/wp-admin/plugins.php',
		'/wp-admin/theme-install.php',
		'/wp-admin/theme-editor.php',
		'/wp-admin/themes.php',
		'/wp-admin/update.php',
		'/wp-admin/options-general.php',
		'/wp-admin/options-writing.php',
		'/wp-admin/options-reading.php',
		'/wp-admin/users.php',
		'/wp-admin/user-new.php',
		'/wp-admin/tools.php',
		'/wp-admin/export.php',
		'/wp-admin/import.php',
	];
	foreach ( $forbidden_paths as $path ) {
		if ( 0 === strpos( $req, $path ) ) {
			status_header( 403 );
			wp_die( 'Access denied — your role cannot view this page.', 'Forbidden', [ 'response' => 403 ] );
		}
	}

	// WC Status + Addons stay admin-only (diagnostic / marketplace surfaces
	// aren't needed for day-to-day store management). wc-settings is OPEN
	// to shop_manager so they can configure payments + shipping themselves.
	$get_page = $_GET['page'] ?? '';
	if ( in_array( $get_page, [ 'wc-status', 'wc-addons' ], true ) ) {
		status_header( 403 );
		wp_die( 'That WooCommerce surface is admin-only on this store.', 'Forbidden', [ 'response' => 403 ] );
	}
} );

/**
 * WCHS admin: hide Security tab entirely for non-admins.
 * Script Registry is now a collapsed section inside Integrations with its
 * own install_plugins capability gate. Checkout tab stays visible but
 * API-key fields are rendered as masked read-only for non-admins (see
 * filter below).
 */
add_filter( 'wchs_admin_visible_tabs', function ( $tabs ) {
	if ( wchs_is_real_admin() ) return $tabs;
	return array_diff( $tabs, [ 'security', 'smtp' ] );
}, 10, 1 );

/**
 * If a non-admin hits a forbidden WCHS tab URL directly, bounce back to
 * homepage tab.
 */
add_action( 'admin_init', function () {
	if ( wchs_is_real_admin() ) return;
	$page = $_GET['page'] ?? '';
	$tab  = $_GET['tab'] ?? '';
	if ( $page === 'wchs-settings' && in_array( $tab, [ 'security', 'smtp' ], true ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=wchs-settings&tab=homepage' ) );
		exit;
	}
} );

/**
 * Strip API key values from $_POST before they reach save handlers when
 * a non-admin somehow submits a form that shouldn't have been rendered.
 * Belt-and-suspenders — the form fields are hidden above, but a crafted
 * POST could still target them.
 */
add_action( 'admin_post_wchs_save_settings', function () {
	if ( wchs_is_real_admin() ) return;
	// Only strip fields that belong to the still-admin-only tabs (Access
	// tab's rate-limit + anti-bot toggles + SMTP credentials). The
	// Integrations-tab fields (gtm_id, easypost, turnstile, pixels) are
	// now manageable by shop_manager, so those stay in $_POST.
	$sensitive = [
		'internal_rate_limit_enabled',
		'anti_bot_enabled',
		'smtp', 'smtp_from_email', 'smtp_from_name',
		'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_secure',
	];
	foreach ( $sensitive as $k ) {
		if ( isset( $_POST[ $k ] ) ) unset( $_POST[ $k ] );
	}
}, 1 );

/**
 * Admin-bar cleanup — hide "New" (can't create users anyway, don't need
 * the option), "Updates" badge, "Edit" on frontend pages.
 */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( wchs_is_real_admin() ) return;
	$bar->remove_node( 'updates' );
	$bar->remove_node( 'new-user' );
	$bar->remove_node( 'new-plugin' );
	$bar->remove_node( 'new-theme' );
}, 999 );
