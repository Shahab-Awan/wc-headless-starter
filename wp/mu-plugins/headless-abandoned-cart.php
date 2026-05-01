<?php
/**
 * Plugin Name: Headless Abandoned Cart
 * Description: Captures email at checkout, tracks cart abandonment, sends
 *              timed recovery emails with cart restore links. Runs via
 *              WP-Cron (+ system cron for reliability).
 *
 *
 * Author:      WCHS Contributors
 *
 * Flow:
 *   1. Customer enters email at checkout → captured via AJAX
 *   2. WP-Cron checks every 5 min for carts older than thresholds
 *   3. Email #1 at 1 hour: "You left something behind"
 *   4. Email #2 at 24 hours: "Your cart is still waiting"
 *   5. Cart restore link repopulates WC cart session
 *   6. Completed orders mark the cart as recovered
 */

defined( 'ABSPATH' ) || exit;

// ─── DB table ───────────────────────────────────────────────────

register_activation_hook( __FILE__, 'wchs_abandoned_cart_create_table' );
add_action( 'admin_init', 'wchs_abandoned_cart_create_table' );

function wchs_abandoned_cart_create_table(): void {
	if ( get_option( 'wchs_abandoned_cart_db_version' ) === '1.0' ) return;

	global $wpdb;
	$table   = $wpdb->prefix . 'wchs_abandoned_carts';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		email VARCHAR(191) NOT NULL,
		cart_contents LONGTEXT NOT NULL,
		cart_total DECIMAL(10,2) NOT NULL DEFAULT 0,
		token VARCHAR(64) NOT NULL,
		captured_at DATETIME NOT NULL,
		emails_sent TINYINT NOT NULL DEFAULT 0,
		last_email_at DATETIME DEFAULT NULL,
		recovered TINYINT NOT NULL DEFAULT 0,
		recovered_at DATETIME DEFAULT NULL,
		KEY idx_email (email),
		KEY idx_token (token),
		KEY idx_captured (captured_at)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'wchs_abandoned_cart_db_version', '1.0' );
}

// ─── Capture email at checkout ──────────────────────────────────

add_action( 'wp_ajax_wchs_capture_cart_email', 'wchs_capture_cart_email' );
add_action( 'wp_ajax_nopriv_wchs_capture_cart_email', 'wchs_capture_cart_email' );

function wchs_capture_cart_email(): void {
	check_ajax_referer( 'wchs_capture_cart_email', 'nonce' );

	// Rate limit: 5 per minute per IP
	$ip     = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
	$bucket = 'abandon_email_' . md5( $ip );
	$count  = (int) get_transient( $bucket );
	if ( $count >= 5 ) {
		wp_send_json_error( 'Too many requests', 429 );
	}
	set_transient( $bucket, $count + 1, 60 );

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		wp_send_json_error( 'Invalid email' );
	}

	$cart = WC()->cart;
	if ( ! $cart || $cart->is_empty() ) {
		wp_send_json_error( 'Cart is empty' );
	}

	$cart_contents = wp_json_encode( $cart->get_cart_for_session() );
	$cart_total    = $cart->get_total( 'edit' );
	$token         = wp_generate_password( 32, false );

	global $wpdb;
	$table = $wpdb->prefix . 'wchs_abandoned_carts';

	// Upsert: update if email already has an active (non-recovered) row
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$table} WHERE email = %s AND recovered = 0 ORDER BY captured_at DESC LIMIT 1",
		$email
	) );

	if ( $existing ) {
		$wpdb->update( $table, [
			'cart_contents' => $cart_contents,
			'cart_total'    => $cart_total,
			'token'         => $token,
			'captured_at'   => current_time( 'mysql', true ),
			'emails_sent'   => 0,
			'last_email_at' => null,
		], [ 'id' => $existing ] );
	} else {
		$wpdb->insert( $table, [
			'email'         => $email,
			'cart_contents' => $cart_contents,
			'cart_total'    => $cart_total,
			'token'         => $token,
			'captured_at'   => current_time( 'mysql', true ),
		] );
	}

	wp_send_json_success();
}

// ─── Inject email capture JS on checkout ────────────────────────

add_action( 'woocommerce_after_checkout_form', function () {
	?>
	<script>
	(function(){
		var field = document.getElementById('billing_email');
		if (!field) return;
		var last = '';
		field.addEventListener('blur', function() {
			var v = field.value.trim();
			if (v && v !== last && v.includes('@')) {
				last = v;
				var fd = new FormData();
				fd.append('action', 'wchs_capture_cart_email');
				fd.append('email', v);
				fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST', body: fd, credentials: 'same-origin'
				});
			}
		});
	})();
	</script>
	<?php
} );

// ─── Cron: check for abandoned carts + send emails ──────────────

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'wchs_abandoned_cart_check' ) ) {
		wp_schedule_event( time(), 'five_minutes', 'wchs_abandoned_cart_check' );
	}
	if ( ! wp_next_scheduled( 'wchs_abandoned_cart_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'wchs_abandoned_cart_cleanup' );
	}
} );

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['five_minutes'] = [
		'interval' => 300,
		'display'  => 'Every 5 minutes',
	];
	return $schedules;
} );

add_action( 'wchs_abandoned_cart_check', 'wchs_abandoned_cart_process' );

// ─── Cron: cleanup old abandoned cart rows ────────────────────────

add_action( 'wchs_abandoned_cart_cleanup', function () {
	global $wpdb;
	$table = $wpdb->prefix . 'wchs_abandoned_carts';

	// Delete non-recovered rows older than 30 days
	$wpdb->query( "DELETE FROM {$table} WHERE recovered = 0 AND captured_at < DATE_SUB(NOW(), INTERVAL 30 DAY)" );

	// Delete recovered rows older than 90 days
	$wpdb->query( "DELETE FROM {$table} WHERE recovered = 1 AND recovered_at < DATE_SUB(NOW(), INTERVAL 90 DAY)" );
} );

function wchs_abandoned_cart_process(): void {
	// Admins using third-party email marketing (Omnisend, Klaviyo,
	// Mailchimp) can disable our built-in recovery emails via the
	// Checkout tab. Email capture still happens — Omnisend may want
	// to read the captured email via its own sync. We just skip the
	// wp_mail dispatch here to avoid duplicate "you forgot something"
	// messages. Read the setting on every run so toggling takes
	// effect at the next 5-min cron tick.
	if ( class_exists( '\WCHS\Admin\AdminPage' ) ) {
		$settings = \WCHS\Admin\AdminPage::get_site_settings();
		if ( empty( $settings['abandoned_cart_enabled'] ) ) {
			return;
		}
	}

	global $wpdb;
	$table = $wpdb->prefix . 'wchs_abandoned_carts';
	$now   = current_time( 'mysql', true );

	// Thresholds
	$thresholds = [
		[ 'min_age' => 3600,  'max_emails' => 0 ], // 1 hour, email #1
		[ 'min_age' => 86400, 'max_emails' => 1 ], // 24 hours, email #2
	];

	foreach ( $thresholds as $t ) {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $t['min_age'] );
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE recovered = 0 AND emails_sent = %d AND captured_at <= %s LIMIT 10",
			$t['max_emails'],
			$cutoff
		) );

		foreach ( $rows as $row ) {
			// Check if a completed order exists for this email since capture
			$order_exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE customer_id IN (
					SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE email = %s
				) AND date_created >= %s AND status IN ('wc-processing', 'wc-completed')",
				$row->email,
				$row->captured_at
			) );

			if ( $order_exists > 0 ) {
				$wpdb->update( $table, [
					'recovered'    => 1,
					'recovered_at' => $now,
				], [ 'id' => $row->id ] );
				continue;
			}

			// Send recovery email
			$restore_url = add_query_arg( [
				'wchs_restore_cart' => $row->token,
			], wc_get_checkout_url() );

			$subject = $t['max_emails'] === 0
				? 'You left something behind'
				: 'Your cart is still waiting for you';

			$cart_total = wc_price( $row->cart_total );
			$body = $t['max_emails'] === 0
				? "<p>Hi there,</p><p>You were shopping with us and left before completing your order of {$cart_total}.</p><p><a href=\"{$restore_url}\" style=\"display:inline-block;padding:12px 24px;background:#000;color:#fff;text-decoration:none;font-weight:600;\">Complete your order</a></p><p>Your cart is saved and ready for you.</p>"
				: "<p>Hi there,</p><p>Just a reminder — your cart of {$cart_total} is still waiting for you.</p><p><a href=\"{$restore_url}\" style=\"display:inline-block;padding:12px 24px;background:#000;color:#fff;text-decoration:none;font-weight:600;\">Return to your cart</a></p>";

			$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
			$site    = get_bloginfo( 'name' );

			// Wrap in WC email template for consistent styling
			if ( function_exists( 'WC' ) ) {
				ob_start();
				wc_get_template( 'emails/email-header.php', [ 'email_heading' => $subject ] );
				echo $body;
				wc_get_template( 'emails/email-footer.php' );
				$body = ob_get_clean();
			}

			$sent    = wp_mail( $row->email, "[{$site}] {$subject}", $body, $headers );

			if ( $sent ) {
				$wpdb->update( $table, [
					'emails_sent'   => $row->emails_sent + 1,
					'last_email_at' => $now,
				], [ 'id' => $row->id ] );
			}
		}
	}
}

// ─── Cart restore via link ──────────────────────────────────────

add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['wchs_restore_cart'] ) ) return;

	$token = sanitize_text_field( wp_unslash( $_GET['wchs_restore_cart'] ) );
	if ( ! $token ) return;

	global $wpdb;
	$table = $wpdb->prefix . 'wchs_abandoned_carts';
	$row   = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE token = %s AND recovered = 0 LIMIT 1",
		$token
	) );

	if ( ! $row ) return;

	$cart_data = json_decode( $row->cart_contents, true );
	if ( ! is_array( $cart_data ) || empty( $cart_data ) ) return;

	// Clear current cart and repopulate
	WC()->cart->empty_cart();
	foreach ( $cart_data as $item ) {
		$product_id   = $item['product_id'] ?? 0;
		$quantity     = $item['quantity'] ?? 1;
		$variation_id = $item['variation_id'] ?? 0;
		$variation    = $item['variation'] ?? [];

		if ( $product_id ) {
			WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
		}
	}

	// Redirect to clean checkout URL (without the token)
	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}, 1 );

// ─── Mark recovered on order completion ─────────────────────────

add_action( 'woocommerce_order_status_processing', 'wchs_abandoned_cart_check_recovery' );
add_action( 'woocommerce_order_status_completed', 'wchs_abandoned_cart_check_recovery' );

function wchs_abandoned_cart_check_recovery( int $order_id ): void {
	$order = wc_get_order( $order_id );
	if ( ! $order ) return;

	$email = $order->get_billing_email();
	if ( ! $email ) return;

	global $wpdb;
	$table = $wpdb->prefix . 'wchs_abandoned_carts';

	$wpdb->update( $table, [
		'recovered'    => 1,
		'recovered_at' => current_time( 'mysql', true ),
	], [
		'email'     => $email,
		'recovered' => 0,
	] );
}
