<?php
/**
 * Plugin Name: WCHS Design System
 * Description: Shared design tokens, WooCommerce widget overrides, theme sync, and mobile-responsive styles for the native WP pages we keep (/checkout, /my-account, /cart, wp-login).
 * Version:     0.1.0
 * Author:      WCHS Contributors
*
 * Architecture
 *   Loader file (this file) lives at the top of wp/mu-plugins/ so WP
 *   auto-loads it. The real code lives in the sibling subdirectory
 *   wchs-design-system/ which WP ignores by convention. We require_once
 *   each src file and instantiate its class.
 *
 * Why mu-plugin instead of a theme or regular plugin
 *   - Auto-loaded, zero activation friction (matches the rest of our
 *     mu-plugin stack — headless-cors, headless-cart-bridge, etc.)
 *   - Survives theme swaps (unlike child-theme enqueue logic)
 *   - Runs before WC's enqueue at priority 10, so we can dequeue WC's
 *     own stylesheets cleanly at priority 999 and win the cascade fight
 *
 * What this owns
 *   - assets/tokens.css           — shared color/type/motion tokens (symlinked into SPA too)
 *   - assets/wc-overrides.css     — hand-authored WC widget overrides + mobile responsive
 *   - assets/theme-sync.js        — reads localStorage.wchs_theme, sets data-theme, wires toggle
 *   - src/Assets.php              — enqueue chain + DEQUEUE WC's own styles
 *   - src/ThemeSync.php           — enqueue theme-sync.js
 *   - src/HeaderRenderer.php      — render the native WP header shell + in-header theme toggle
 *   - src/ToggleRenderer.php      — legacy floating toggle renderer (currently unused)
 *   - src/WcOverrides.php         — PHP-side WC behavior hooks (classic cart/checkout force, breadcrumb)
 */

defined( 'ABSPATH' ) || exit;

define( 'WCHS_DS_DIR', __DIR__ . '/wchs-design-system' );
define( 'WCHS_DS_URL', WPMU_PLUGIN_URL . '/wchs-design-system' );
define( 'WCHS_DS_VERSION', '1.0.0' );

require_once WCHS_DS_DIR . '/src/Assets.php';
require_once WCHS_DS_DIR . '/src/ThemeSync.php';
require_once WCHS_DS_DIR . '/src/ToggleRenderer.php';
require_once WCHS_DS_DIR . '/src/HeaderRenderer.php';
require_once WCHS_DS_DIR . '/src/WcOverrides.php';

( new \WCHS\DesignSystem\Assets() )->register();
( new \WCHS\DesignSystem\ThemeSync() )->register();
// Floating toggle replaced by in-header toggle (HeaderRenderer).
// ( new \WCHS\DesignSystem\ToggleRenderer() )->register();
( new \WCHS\DesignSystem\HeaderRenderer() )->register();
( new \WCHS\DesignSystem\WcOverrides() )->register();
