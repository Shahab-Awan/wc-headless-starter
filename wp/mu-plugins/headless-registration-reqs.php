<?php
/**
 * Plugin Name: Headless Registration Requirements
 * Description: Configurable registration requirements for WooCommerce:
 *              email verification, verified shipping address, required
 *              name and phone fields. All toggleable via WCHS admin settings.
 *
 * Hooks into WC's existing /my-account/ registration form. Does not
 * replace it - adds fields and validation steps. Data persists to
 * WC customer billing/shipping meta so checkout pre-fills.
 *
 *
 * Author:      WCHS Contributors
 *
 * Email verification: account is created immediately but unverified
 * users are treated as guests for access mode purposes (can't
 * purchase in modes 1/2, etc.). Existing customers are grandfathered.
 */

defined( 'ABSPATH' ) || exit;

// ─── Config helpers ─────────────────────────────────────────────

function wchs_reg_config(): array {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return [
			'email_verify' => false,
			'address'      => false,
			'name'         => false,
			'phone'        => false,
		];
	}
	$s = \WCHS\Admin\AdminPage::get_site_settings();
	return [
		'email_verify' => ! empty( $s['reg_require_email_verify'] ),
		'address'      => ! empty( $s['reg_require_address'] ),
		'name'         => ! empty( $s['reg_require_name'] ),
		'phone'        => ! empty( $s['reg_require_phone'] ),
	];
}

/**
 * Is the current user's email verified?
 * Returns true if:
 *   - Email verification is disabled globally
 *   - User is not logged in (let other gates handle that)
 *   - User has no wchs_email_verified meta (grandfathered)
 *   - User has wchs_email_verified = true
 * Returns false only if wchs_email_verified is explicitly '0' or 'false'.
 */
function wchs_is_email_verified( ?int $user_id = null ): bool {
	$cfg = wchs_reg_config();
	if ( ! $cfg['email_verify'] ) {
		return true;
	}

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return true; // Not logged in - let access control handle it
	}

	// Admins are always verified
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	$meta = get_user_meta( $user_id, 'wchs_email_verified', true );

	// No meta = grandfathered (registered before feature was enabled)
	if ( $meta === '' ) {
		return true;
	}

	return (bool) $meta;
}

// ─── Render extra fields on registration form ───────────────────

add_action( 'woocommerce_register_form', function () {
	$cfg = wchs_reg_config();

	if ( $cfg['name'] ) :
		$fn = sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ?? '' ) );
		$ln = sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ?? '' ) );
		?>
		<p class="form-row form-row-first">
			<label for="reg_first_name">First name <span class="required">*</span></label>
			<input type="text" class="input-text" name="billing_first_name" id="reg_first_name" value="<?php echo esc_attr( $fn ); ?>" required />
		</p>
		<p class="form-row form-row-last">
			<label for="reg_last_name">Last name <span class="required">*</span></label>
			<input type="text" class="input-text" name="billing_last_name" id="reg_last_name" value="<?php echo esc_attr( $ln ); ?>" required />
		</p>
		<div class="clear"></div>
		<?php
	endif;

	if ( $cfg['phone'] ) :
		$phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ?? '' ) );
		?>
		<p class="form-row form-row-wide">
			<label for="reg_phone">Phone <span class="required">*</span></label>
			<input type="tel" class="input-text" name="billing_phone" id="reg_phone" value="<?php echo esc_attr( $phone ); ?>" required />
		</p>
		<?php
	endif;

	if ( $cfg['address'] ) :
		$a1 = sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ?? '' ) );
		$ct = sanitize_text_field( wp_unslash( $_POST['billing_city'] ?? '' ) );
		$st = sanitize_text_field( wp_unslash( $_POST['billing_state'] ?? '' ) );
		$zp = sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ?? '' ) );
		$co = sanitize_text_field( wp_unslash( $_POST['billing_country'] ?? 'US' ) );
		?>
		<h3 style="margin:16px 0 8px;font-size:14px;font-weight:500;">Shipping Address</h3>
		<p class="form-row form-row-wide">
			<label for="reg_address_1">Street address <span class="required">*</span></label>
			<input type="text" class="input-text" name="billing_address_1" id="reg_address_1" value="<?php echo esc_attr( $a1 ); ?>" required autocomplete="address-line1" />
		</p>
		<p class="form-row form-row-first">
			<label for="reg_city">City <span class="required">*</span></label>
			<input type="text" class="input-text" name="billing_city" id="reg_city" value="<?php echo esc_attr( $ct ); ?>" required autocomplete="address-level2" />
		</p>
		<p class="form-row form-row-last">
			<label for="reg_state">State / Province <span class="required">*</span></label>
			<input type="text" class="input-text" name="billing_state" id="reg_state" value="<?php echo esc_attr( $st ); ?>" required autocomplete="address-level1" />
		</p>
		<div class="clear"></div>
		<p class="form-row form-row-first">
			<label for="reg_postcode">ZIP / Postal code <span class="required">*</span></label>
			<input type="text" class="input-text" name="billing_postcode" id="reg_postcode" value="<?php echo esc_attr( $zp ); ?>" required autocomplete="postal-code" />
		</p>
		<p class="form-row form-row-last">
			<label for="reg_country">Country <span class="required">*</span></label>
			<select name="billing_country" id="reg_country" class="input-text" autocomplete="country">
				<?php
				foreach ( WC()->countries->get_allowed_countries() as $code => $name ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $code ), selected( $co, $code, false ), esc_html( $name ) );
				}
				?>
			</select>
		</p>
		<div class="clear"></div>
		<?php
	endif;

	if ( $cfg['email_verify'] ) :
		?>
		<p class="form-row form-row-wide" style="margin-top:8px">
			<small style="color:var(--fg-muted, #767d88)">A verification email will be sent to confirm your email address.</small>
		</p>
		<?php
	endif;
} );

// ─── Validate on registration ───────────────────────────────────

add_filter( 'woocommerce_register_post', function ( $username, $email, $errors ) {
	$cfg = wchs_reg_config();

	if ( $cfg['name'] ) {
		$fn = sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ?? '' ) );
		$ln = sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ?? '' ) );
		if ( empty( $fn ) ) $errors->add( 'first_name_required', 'First name is required.' );
		if ( empty( $ln ) ) $errors->add( 'last_name_required', 'Last name is required.' );
	}

	if ( $cfg['phone'] ) {
		$phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ?? '' ) );
		if ( empty( $phone ) ) {
			$errors->add( 'phone_required', 'Phone number is required.' );
		} elseif ( ! preg_match( '/^[\+\d\s\-\(\)]{7,20}$/', $phone ) ) {
			$errors->add( 'phone_invalid', 'Please enter a valid phone number.' );
		}
	}

	if ( $cfg['address'] ) {
		$a1 = sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ?? '' ) );
		$ct = sanitize_text_field( wp_unslash( $_POST['billing_city'] ?? '' ) );
		$st = sanitize_text_field( wp_unslash( $_POST['billing_state'] ?? '' ) );
		$zp = sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ?? '' ) );
		$co = sanitize_text_field( wp_unslash( $_POST['billing_country'] ?? '' ) );

		if ( empty( $a1 ) ) $errors->add( 'address_required', 'Street address is required.' );
		if ( empty( $ct ) ) $errors->add( 'city_required', 'City is required.' );
		if ( empty( $st ) ) $errors->add( 'state_required', 'State is required.' );
		if ( empty( $zp ) ) $errors->add( 'postcode_required', 'ZIP / Postal code is required.' );
		if ( empty( $co ) ) $errors->add( 'country_required', 'Country is required.' );

		// EasyPost validation (if configured and no field errors)
		if ( ! $errors->has_errors() && function_exists( 'wchs_verify_address_via_easypost' ) ) {
			$result = wchs_verify_address_via_easypost( [
				'street1' => $a1,
				'city'    => $ct,
				'state'   => $st,
				'zip'     => $zp,
				'country' => $co,
			] );

			$av_config = wchs_address_validation_config();
			$mode = $av_config['mode'] ?? 'moderate';

			if ( $result['status'] === 'failed' ) {
				if ( $mode === 'strict' ) {
					$msg = ! empty( $result['errors'] ) ? implode( ' ', $result['errors'] ) : 'Address could not be verified.';
					$errors->add( 'address_invalid', $msg );
				}
				// Moderate + loose: allow registration, address saves as-is
			}

			if ( $result['status'] === 'corrected' && $result['corrected'] ) {
				// Overwrite POST data with corrected address so it saves correctly
				$_POST['billing_address_1'] = $result['corrected']['street1'];
				$_POST['billing_city']      = $result['corrected']['city'];
				$_POST['billing_state']     = $result['corrected']['state'];
				$_POST['billing_postcode']  = $result['corrected']['zip'];
				$_POST['billing_country']   = $result['corrected']['country'];
			}
		}
	}

	return $errors;
}, 10, 3 );

// ─── Save fields + send verification email ──────────────────────

add_action( 'woocommerce_created_customer', function ( $customer_id, $new_customer_data, $password_generated ) {
	$cfg = wchs_reg_config();

	// Save name fields
	if ( $cfg['name'] ) {
		$fn = sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ?? '' ) );
		$ln = sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ?? '' ) );
		if ( $fn ) {
			update_user_meta( $customer_id, 'billing_first_name', $fn );
			update_user_meta( $customer_id, 'shipping_first_name', $fn );
			update_user_meta( $customer_id, 'first_name', $fn );
		}
		if ( $ln ) {
			update_user_meta( $customer_id, 'billing_last_name', $ln );
			update_user_meta( $customer_id, 'shipping_last_name', $ln );
			update_user_meta( $customer_id, 'last_name', $ln );
		}
	}

	// Save phone
	if ( $cfg['phone'] ) {
		$phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ?? '' ) );
		if ( $phone ) {
			update_user_meta( $customer_id, 'billing_phone', $phone );
		}
	}

	// Save address
	if ( $cfg['address'] ) {
		$fields = [ 'address_1', 'city', 'state', 'postcode', 'country' ];
		foreach ( $fields as $f ) {
			$val = sanitize_text_field( wp_unslash( $_POST[ 'billing_' . $f ] ?? '' ) );
			if ( $val ) {
				update_user_meta( $customer_id, 'billing_' . $f, $val );
				update_user_meta( $customer_id, 'shipping_' . $f, $val );
			}
		}
	}

	// Email verification
	if ( $cfg['email_verify'] ) {
		update_user_meta( $customer_id, 'wchs_email_verified', '0' );
		wchs_send_verification_email( $customer_id );
	}
}, 10, 3 );

// ─── Email verification: send ───────────────────────────────────

function wchs_send_verification_email( int $user_id ): bool {
	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->user_email ) {
		return false;
	}

	// Generate 6-digit code
	$code    = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	$expires = time() + ( 15 * 60 ); // 15 minutes

	update_user_meta( $user_id, 'wchs_email_verify_code', wp_hash( $code ) );
	update_user_meta( $user_id, 'wchs_email_verify_expires', $expires );
	update_user_meta( $user_id, 'wchs_email_verify_attempts', 0 );

	$brand   = get_option( 'blogname', 'Our Store' );
	$subject = $code . ' is your verification code - ' . $brand;
	$greeting = 'Hi' . ( $user->first_name ? ' ' . $user->first_name : '' ) . ',';

	// Use WC's email system for consistent branding
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$mailer = WC()->mailer();
		$content = '<p>' . esc_html( $greeting ) . '</p>'
			. '<p>Your verification code is:</p>'
			. '<p style="margin:16px 0;font-size:32px;font-weight:700;letter-spacing:8px;font-family:monospace;text-align:center;color:#1a1a1a">' . esc_html( $code ) . '</p>'
			. '<p style="font-size:13px;color:#666;text-align:center">Enter this code on the website to verify your email address.</p>'
			. '<p style="font-size:13px;color:#666;text-align:center">This code expires in 15 minutes.</p>'
			. '<p style="font-size:12px;color:#999;margin-top:24px">If you didn\'t create an account with ' . esc_html( $brand ) . ', you can ignore this email.</p>';
		$wrapped = $mailer->wrap_message( $subject, $content );
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		return $mailer->send( $user->user_email, $subject, $wrapped, $headers );
	}

	// Fallback: plain text
	$body = $greeting . "\n\n"
		. "Your verification code is: " . $code . "\n\n"
		. "Enter this code on the website to verify your email address.\n"
		. "This code expires in 15 minutes.\n\n"
		. "If you didn't create this account, you can ignore this email.\n\n"
		. $brand;

	return wp_mail( $user->user_email, $subject, $body );
}

// ─── Email verification: AJAX code check ────────────────────────

add_action( 'wp_ajax_wchs_verify_email_code', function () {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( 'Not logged in.' );
	}

	$verified = get_user_meta( $user_id, 'wchs_email_verified', true );
	if ( $verified === '1' ) {
		wp_send_json_success( 'Already verified.' );
	}

	$code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
	if ( ! $code || ! preg_match( '/^\d{6}$/', $code ) ) {
		wp_send_json_error( 'Please enter a 6-digit code.' );
	}

	// Check attempt count (max 5 before code is burned)
	$attempts = (int) get_user_meta( $user_id, 'wchs_email_verify_attempts', true );
	if ( $attempts >= 5 ) {
		delete_user_meta( $user_id, 'wchs_email_verify_code' );
		delete_user_meta( $user_id, 'wchs_email_verify_expires' );
		delete_user_meta( $user_id, 'wchs_email_verify_attempts' );
		wp_send_json_error( 'Too many attempts. Please request a new code.' );
	}

	// Check expiry
	$expires = (int) get_user_meta( $user_id, 'wchs_email_verify_expires', true );
	if ( time() > $expires ) {
		delete_user_meta( $user_id, 'wchs_email_verify_code' );
		delete_user_meta( $user_id, 'wchs_email_verify_expires' );
		delete_user_meta( $user_id, 'wchs_email_verify_attempts' );
		wp_send_json_error( 'Code expired. Please request a new one.' );
	}

	// Verify code (stored as wp_hash)
	$saved_hash = get_user_meta( $user_id, 'wchs_email_verify_code', true );
	if ( ! $saved_hash || ! hash_equals( $saved_hash, wp_hash( $code ) ) ) {
		update_user_meta( $user_id, 'wchs_email_verify_attempts', $attempts + 1 );
		$remaining = 4 - $attempts;
		wp_send_json_error( 'Incorrect code. ' . $remaining . ' attempt' . ( $remaining !== 1 ? 's' : '' ) . ' remaining.' );
	}

	// Success - verify the account
	update_user_meta( $user_id, 'wchs_email_verified', '1' );
	delete_user_meta( $user_id, 'wchs_email_verify_code' );
	delete_user_meta( $user_id, 'wchs_email_verify_expires' );
	delete_user_meta( $user_id, 'wchs_email_verify_attempts' );

	wp_send_json_success( 'Email verified!' );
} );

// ─── Resend verification email (AJAX) ───────────────────────────

add_action( 'wp_ajax_wchs_resend_verification', function () {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( 'Not logged in' );
	}

	$verified = get_user_meta( $user_id, 'wchs_email_verified', true );
	if ( $verified === '1' ) {
		wp_send_json_error( 'Already verified' );
	}

	// Rate limit: 1 per 60 seconds
	$last_sent = (int) get_user_meta( $user_id, 'wchs_email_verify_last_sent', true );
	if ( time() - $last_sent < 60 ) {
		wp_send_json_error( 'Please wait before requesting another verification email.' );
	}

	$sent = wchs_send_verification_email( $user_id );
	if ( $sent ) {
		update_user_meta( $user_id, 'wchs_email_verify_last_sent', time() );
		wp_send_json_success( 'Verification email sent.' );
	} else {
		wp_send_json_error( 'Failed to send email. Please try again.' );
	}
} );

// ─── My Account: verification banner ────────────────────────────

add_action( 'woocommerce_before_my_account', function () {
	if ( ! is_user_logged_in() ) return;

	$cfg = wchs_reg_config();
	if ( ! $cfg['email_verify'] ) return;

	$verified = get_user_meta( get_current_user_id(), 'wchs_email_verified', true );
	if ( $verified !== '0' ) return; // Verified or grandfathered

	$resend_nonce = wp_create_nonce( 'wchs_resend_verification' );
	?>
	<div style="padding:20px 24px;border:2px solid var(--accent, #0c0c0c);border-radius:4px;margin-bottom:24px;background:var(--bg-muted, #f4f5f7)">
		<p style="margin:0 0 4px;font-weight:600;font-size:15px;color:var(--fg, #0c0c0c)">Verify your email address</p>
		<p style="margin:0 0 16px;font-size:13px;color:var(--fg-muted, #767d88)">
			We sent a 6-digit code to <strong><?php echo esc_html( wp_get_current_user()->user_email ); ?></strong>. Enter it below to verify your account.
		</p>
		<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap" id="wchs-verify-form">
			<input type="text" id="wchs-verify-code" maxlength="6" pattern="\d{6}" placeholder="000000"
				style="width:120px;font-size:20px;font-family:monospace;letter-spacing:6px;text-align:center;padding:8px 12px;border:2px solid var(--border, #ccc);border-radius:4px" />
			<button type="button" id="wchs-verify-submit"
				style="padding:10px 20px;background:var(--accent, #0c0c0c);color:var(--accent-fg, #fff);border:none;cursor:pointer;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
				Verify
			</button>
			<button type="button" id="wchs-verify-resend"
				style="padding:10px 16px;background:transparent;color:var(--fg-muted, #767d88);border:1px solid var(--border, #ccc);cursor:pointer;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">
				Resend code
			</button>
		</div>
		<p id="wchs-verify-msg" style="margin:8px 0 0;font-size:13px;display:none"></p>
	</div>
	<script>
	(function() {
		var ajaxUrl = '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>';
		var nonce = '<?php echo wp_create_nonce( "wchs_resend_verification" ); ?>';
		var codeInput = document.getElementById('wchs-verify-code');
		var submitBtn = document.getElementById('wchs-verify-submit');
		var resendBtn = document.getElementById('wchs-verify-resend');
		var msgEl = document.getElementById('wchs-verify-msg');

		function showMsg(text, isError) {
			msgEl.textContent = text;
			msgEl.style.display = '';
			msgEl.style.color = isError ? 'var(--danger, #dc3545)' : 'var(--success, #4ade80)';
		}

		submitBtn.addEventListener('click', function() {
			var code = codeInput.value.trim();
			if (!/^\d{6}$/.test(code)) { showMsg('Enter a 6-digit code.', true); return; }
			submitBtn.disabled = true;
			submitBtn.textContent = 'Verifying...';
			var fd = new FormData();
			fd.append('action', 'wchs_verify_email_code');
			fd.append('code', code);
			fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'include' })
				.then(function(r) { return r.json(); })
				.then(function(d) {
					if (d.success) {
						showMsg('Verified! Refreshing...', false);
						setTimeout(function() { location.reload(); }, 1000);
					} else {
						showMsg(d.data || 'Verification failed.', true);
						submitBtn.disabled = false;
						submitBtn.textContent = 'Verify';
						codeInput.value = '';
						codeInput.focus();
					}
				})
				.catch(function() { showMsg('Network error. Try again.', true); submitBtn.disabled = false; submitBtn.textContent = 'Verify'; });
		});

		codeInput.addEventListener('keydown', function(e) {
			if (e.key === 'Enter') submitBtn.click();
		});

		// Auto-submit when 6 digits are entered
		codeInput.addEventListener('input', function() {
			codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
			if (codeInput.value.length === 6) submitBtn.click();
		});

		resendBtn.addEventListener('click', function() {
			resendBtn.disabled = true;
			resendBtn.textContent = 'Sending...';
			fetch(ajaxUrl + '?action=wchs_resend_verification&_wpnonce=' + nonce, { credentials: 'include' })
				.then(function(r) { return r.json(); })
				.then(function(d) {
					if (d.success) {
						showMsg('New code sent! Check your inbox.', false);
					} else {
						showMsg(d.data || 'Could not resend.', true);
					}
					setTimeout(function() { resendBtn.disabled = false; resendBtn.textContent = 'Resend code'; }, 60000);
				})
				.catch(function() { showMsg('Network error.', true); resendBtn.disabled = false; resendBtn.textContent = 'Resend code'; });
		});
	})();
	</script>
	<?php
} );
