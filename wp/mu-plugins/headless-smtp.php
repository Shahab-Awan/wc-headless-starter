<?php
/**
 * Plugin Name: Headless SMTP
 * Description: Configures wp_mail to use SMTP instead of PHP's mail().
 *              Reads from wp-config.php constants first, falls back to
 *              admin panel settings (Integrations tab). All WC emails
 *              (orders, password resets, verification codes) route through
 *              this automatically via the phpmailer_init hook.

 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve SMTP config from constants (priority) or admin settings (fallback).
 */
function wchs_smtp_config(): array {
	// wp-config constants take priority
	if ( defined( 'WCHS_SMTP_HOST' ) ) {
		return [
			'enabled'    => true,
			'host'       => WCHS_SMTP_HOST,
			'port'       => defined( 'WCHS_SMTP_PORT' ) ? (int) WCHS_SMTP_PORT : 465,
			'secure'     => defined( 'WCHS_SMTP_SECURE' ) ? WCHS_SMTP_SECURE : 'ssl',
			'username'   => defined( 'WCHS_SMTP_USER' ) ? WCHS_SMTP_USER : '',
			'password'   => defined( 'WCHS_SMTP_PASS' ) ? WCHS_SMTP_PASS : '',
			'from_email' => defined( 'WCHS_SMTP_FROM' ) ? WCHS_SMTP_FROM : '',
			'from_name'  => defined( 'WCHS_SMTP_FROM_NAME' ) ? WCHS_SMTP_FROM_NAME : '',
		];
	}

	// Fall back to admin settings. FROM fields live flat at the top of the
	// option (smtp_from_email / smtp_from_name) because historically we only
	// exposed the FROM override — the rest of the SMTP auth is config-only.
	// Keep compatibility with any future nested `smtp` object too.
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return [ 'enabled' => false ];
	}
	$s = \WCHS\Admin\AdminPage::get_site_settings();
	$smtp = $s['smtp'] ?? [];
	$from_email = $smtp['from_email'] ?? ( $s['smtp_from_email'] ?? '' );
	$from_name  = $smtp['from_name']  ?? ( $s['smtp_from_name']  ?? '' );
	// If a host isn't configured but a FROM override is set, the plugin
	// still needs to "enable" itself so the wp_mail_from filters fire
	// (we're relaying via Siteground's default PHP mail(), just rewriting
	// the envelope From — no SMTP auth required).
	$from_only = ! empty( $from_email ) || ! empty( $from_name );
	$host_configured = ! empty( $smtp['host'] );
	return [
		'enabled'    => ! empty( $smtp['enabled'] ) || $from_only,
		'host'       => $smtp['host'] ?? '',
		'port'       => (int) ( $smtp['port'] ?? 465 ),
		'secure'     => $smtp['secure'] ?? 'ssl',
		'username'   => $smtp['username'] ?? '',
		'password'   => $smtp['password'] ?? '',
		'from_email' => $from_email,
		'from_name'  => $from_name,
	];
}

add_action( 'phpmailer_init', function ( $phpmailer ) {
	$cfg = wchs_smtp_config();
	if ( ! $cfg['enabled'] || ! $cfg['host'] || ! $cfg['username'] ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = $cfg['host'];
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Port       = $cfg['port'];
	$phpmailer->SMTPSecure = $cfg['secure'] ?: '';
	$phpmailer->Username   = $cfg['username'];
	$phpmailer->Password   = $cfg['password'];

	if ( $cfg['from_email'] ) {
		$phpmailer->From = $cfg['from_email'];
	}
	if ( $cfg['from_name'] ) {
		$phpmailer->FromName = $cfg['from_name'];
	}
}, 999 );

// From address filter (runs before phpmailer_init for WP core emails)
add_filter( 'wp_mail_from', function ( $from ) {
	$cfg = wchs_smtp_config();
	return ( $cfg['enabled'] && $cfg['from_email'] ) ? $cfg['from_email'] : $from;
} );

add_filter( 'wp_mail_from_name', function ( $name ) {
	$cfg = wchs_smtp_config();
	return ( $cfg['enabled'] && $cfg['from_name'] ) ? $cfg['from_name'] : $name;
} );
