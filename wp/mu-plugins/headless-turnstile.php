<?php
/**
 * Plugin Name: Headless Turnstile Anti-Bot
 * Description: Cloudflare Turnstile integration for checkout, login, registration.
 *              Toggleable via WCHS admin settings. Uses wp_remote_post() for server-
 *              side token verification. Fail-open on network errors to avoid locking
 *              out legitimate users.

 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verify a Cloudflare Turnstile token server-side.
 * Returns true if valid, disabled, or on network error (fail-open).
 * Returns false only on definitive rejection.
 */
function wchs_verify_turnstile( string $token ): bool {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return true;
	}
	$settings = \WCHS\Admin\AdminPage::get_site_settings();
	if ( empty( $settings['anti_bot_enabled'] ) ) {
		return true; // disabled — pass through
	}
	$secret = $settings['turnstile_secret_key'] ?? '';
	if ( ! $secret ) {
		return true; // no secret configured — can't verify, fail-open
	}
	if ( ! $token ) {
		return false; // anti-bot enabled + no token = reject
	}

	$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
		'body'    => [
			'secret'   => $secret,
			'response' => $token,
			'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
		],
		'timeout' => 5,
	] );

	if ( is_wp_error( $response ) ) {
		return true; // network error — fail-open
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	return ! empty( $body['success'] );
}

/**
 * Check if anti-bot is enabled and configured.
 */
function wchs_turnstile_is_active(): bool {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return false;
	}
	$settings = \WCHS\Admin\AdminPage::get_site_settings();
	return ! empty( $settings['anti_bot_enabled'] )
		&& ! empty( $settings['turnstile_site_key'] )
		&& ! empty( $settings['turnstile_secret_key'] );
}

/**
 * Get the Turnstile site key (public, safe to expose).
 */
function wchs_turnstile_site_key(): string {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return '';
	}
	$settings = \WCHS\Admin\AdminPage::get_site_settings();
	if ( empty( $settings['anti_bot_enabled'] ) ) {
		return '';
	}
	return $settings['turnstile_site_key'] ?? '';
}

// ─── Render the Turnstile widget HTML + script ───────────────────

function wchs_render_turnstile_widget(): void {
	if ( ! wchs_turnstile_is_active() ) {
		return;
	}
	$site_key = wchs_turnstile_site_key();
	?>
	<div class="wchs-turnstile-wrap" style="margin: 12px 0;">
		<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-theme="auto" data-appearance="always"></div>
	</div>
	<?php
	static $rendered_once = false;
	if ( $rendered_once ) {
		// WC's update_order_review AJAX re-fires this action and replaces
		// the order-review HTML. The widget div comes along, but Turnstile's
		// JS state is gone — no one calls turnstile.render() on the new
		// container, and the hidden cf-turnstile-response input never gets
		// created. The re-render script below listens for the 'updated_checkout'
		// jQuery event (fires after WC's AJAX completes) and forces a
		// re-render of any .cf-turnstile div that Turnstile hasn't touched.
		return;
	}
	$rendered_once = true;
	?>
	<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
	<script>
	(function() {
		// Only re-render widgets that WC's update_order_review replaced.
		// Turnstile auto-renders on first load — we only intervene after
		// the AJAX replaces the widget div, at which point we mark the
		// fresh div with data-wchs-rerendered to avoid double-render.
		function rerenderOrphaned() {
			if (typeof window.turnstile === 'undefined') return;
			document.querySelectorAll('.cf-turnstile').forEach(function(el) {
				if (el.querySelector('iframe')) return; // Turnstile already owns this one
				if (el.getAttribute('data-wchs-rerendered')) return;
				try {
					window.turnstile.render(el);
					el.setAttribute('data-wchs-rerendered', '1');
				} catch (e) { /* Turnstile might be racing our re-render — ignore */ }
			});
		}
		if (typeof jQuery !== 'undefined') {
			jQuery(document.body).on('updated_checkout', function() {
				// DOM was replaced. Previous widgets are gone; fresh ones
				// are here. Small delay so Turnstile's own auto-render gets
				// a chance to pick them up first.
				setTimeout(rerenderOrphaned, 600);
			});
		}
	})();
	</script>
	<?php
}

/**
 * Extract Turnstile token from POST data (works for both WP forms and REST).
 */
function wchs_get_turnstile_token(): string {
	return sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );
}

// ─── WooCommerce Checkout ────────────────────────────────────────

add_action( 'woocommerce_review_order_before_submit', function () {
	wchs_render_turnstile_widget();
} );

add_action( 'woocommerce_checkout_process', function () {
	if ( ! wchs_turnstile_is_active() ) {
		return;
	}
	$token = wchs_get_turnstile_token();
	if ( ! wchs_verify_turnstile( $token ) ) {
		wc_add_notice( 'Bot verification failed. Please try again.', 'error' );
	}
} );

// ─── WordPress Login ─────────────────────────────────────────────

add_action( 'login_form', function () {
	wchs_render_turnstile_widget();
} );

add_filter( 'wp_authenticate_user', function ( $user, $password ) {
	if ( is_wp_error( $user ) ) {
		return $user;
	}
	if ( ! wchs_turnstile_is_active() ) {
		return $user;
	}
	// Skip for AJAX/REST requests (WC Store API login uses cookies, not form POST)
	if ( defined( 'DOING_AJAX' ) || defined( 'REST_REQUEST' ) ) {
		return $user;
	}
	$token = wchs_get_turnstile_token();
	if ( ! wchs_verify_turnstile( $token ) ) {
		return new \WP_Error( 'turnstile_failed', 'Bot verification failed. Please try again.' );
	}
	return $user;
}, 30, 2 );

// ─── WooCommerce Registration ────────────────────────────────────

add_action( 'woocommerce_register_form', function () {
	wchs_render_turnstile_widget();
} );

add_filter( 'woocommerce_register_post', function ( $username, $email, $errors ) {
	if ( ! wchs_turnstile_is_active() ) {
		return $errors;
	}
	$token = wchs_get_turnstile_token();
	if ( ! wchs_verify_turnstile( $token ) ) {
		$errors->add( 'turnstile_failed', 'Bot verification failed. Please try again.' );
	}
	return $errors;
}, 10, 3 );
