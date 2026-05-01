<?php
/**
 * Plugin Name: Headless Address Validation
 * Description: Address validation via EasyPost API at checkout. Intercepts the
 *              Place Order button, validates via AJAX, shows a modal if the
 *              address needs correction or is invalid. Hard-rejects addresses
 *              that EasyPost cannot find.
 *
 *
 * Author:      WCHS Contributors
 *
 * Config: WCHS admin → Site Configuration → EasyPost API Key + toggle.
 * Client-side autocomplete handled by separate Google plugin.
 */

defined( 'ABSPATH' ) || exit;

function wchs_address_validation_config(): array {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return [ 'api_key' => '', 'enabled' => false, 'mode' => 'moderate' ];
	}
	$settings = \WCHS\Admin\AdminPage::get_site_settings();
	return [
		'api_key' => $settings['easypost_api_key'] ?? '',
		'enabled' => ! empty( $settings['address_validation_enabled'] ),
		'mode'    => $settings['address_validation_mode'] ?? 'moderate',
	];
}

// ─── Reusable EasyPost address verification ─────────────────────
// Returns: [ 'status' => 'success'|'corrected'|'failed'|'skip',
//            'corrected' => [...address fields...] or null,
//            'errors' => [...string messages...] ]

function wchs_verify_address_via_easypost( array $address ): array {
	$config = wchs_address_validation_config();
	if ( ! $config['api_key'] ) {
		return [ 'status' => 'skip', 'corrected' => null, 'errors' => [] ];
	}

	$street1 = $address['street1'] ?? '';
	$street2 = $address['street2'] ?? '';
	$city    = $address['city'] ?? '';
	$state   = $address['state'] ?? '';
	$zip     = $address['zip'] ?? '';
	$country = $address['country'] ?? 'US';

	if ( empty( $street1 ) ) {
		return [ 'status' => 'skip', 'corrected' => null, 'errors' => [] ];
	}

	$ep_url = 'https://api.easypost.com/v2/addresses?' . http_build_query( [
		'verify[]'          => 'delivery',
		'address[street1]'  => $street1,
		'address[street2]'  => $street2,
		'address[city]'     => $city,
		'address[state]'    => $state,
		'address[zip]'      => $zip,
		'address[country]'  => $country,
	] );

	$response = wp_remote_post( $ep_url, [
		'timeout' => 10,
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode( $config['api_key'] . ':' ),
			'Content-Type'  => 'application/x-www-form-urlencoded',
		],
	] );

	if ( is_wp_error( $response ) ) {
		error_log( 'WCHS AV: EasyPost error - ' . $response->get_error_message() );
		return [ 'status' => 'skip', 'corrected' => null, 'errors' => [] ];
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) || ! isset( $body['verifications']['delivery'] ) ) {
		return [ 'status' => 'skip', 'corrected' => null, 'errors' => [] ];
	}

	$delivery = $body['verifications']['delivery'];

	if ( empty( $delivery['success'] ) ) {
		$errors = [];
		if ( ! empty( $delivery['errors'] ) ) {
			foreach ( $delivery['errors'] as $e ) {
				$errors[] = $e['message'] ?? 'Address verification failed';
			}
		}
		return [ 'status' => 'failed', 'corrected' => null, 'errors' => $errors ?: [ 'Address could not be verified.' ] ];
	}

	// Success - check if EasyPost corrected anything
	$corrected = [
		'street1' => $body['street1'] ?? $street1,
		'street2' => $body['street2'] ?? $street2,
		'city'    => $body['city'] ?? $city,
		'state'   => $body['state'] ?? $state,
		'zip'     => $body['zip'] ?? $zip,
		'country' => $body['country'] ?? $country,
	];

	// Determine if anything was meaningfully corrected
	$norm = function ( $s ) {
		$s = strtolower( trim( $s ) );
		$abbr = [
			'lane' => 'ln', 'drive' => 'dr', 'street' => 'st', 'avenue' => 'ave',
			'road' => 'rd', 'boulevard' => 'blvd', 'court' => 'ct', 'circle' => 'cir',
			'place' => 'pl', 'terrace' => 'ter', 'highway' => 'hwy', 'parkway' => 'pkwy',
		];
		foreach ( $abbr as $full => $short ) {
			$s = preg_replace( '/\b' . $full . '\b/', $short, $s );
		}
		return preg_replace( '/\s+/', ' ', $s );
	};
	$norm_zip = function ( $z ) { return explode( '-', trim( $z ) )[0]; };

	$was_corrected = false;
	if ( $norm( $street1 ) !== $norm( $corrected['street1'] ) ) $was_corrected = true;
	if ( $norm( $city ) !== $norm( $corrected['city'] ) ) $was_corrected = true;
	if ( $norm_zip( $zip ) !== $norm_zip( $corrected['zip'] ) ) $was_corrected = true;

	return [
		'status'    => $was_corrected ? 'corrected' : 'success',
		'corrected' => $was_corrected ? $corrected : null,
		'errors'    => [],
	];
}

// ─── AJAX endpoint: validate address via EasyPost ───────────────

add_action( 'wp_ajax_wchs_validate_address', 'wchs_validate_address_ajax' );
add_action( 'wp_ajax_nopriv_wchs_validate_address', 'wchs_validate_address_ajax' );

function wchs_validate_address_ajax(): void {
	check_ajax_referer( 'wchs_address_validation', 'nonce' );

	// Rate limit: 10 per minute per IP (each call costs $0.02 via EasyPost)
	$ip     = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
	$bucket = 'addr_val_' . md5( $ip );
	$count  = (int) get_transient( $bucket );
	if ( $count >= 10 ) {
		wp_send_json_error( 'Too many validation requests. Please wait a moment.', 429 );
	}
	set_transient( $bucket, $count + 1, 60 );

	$config = wchs_address_validation_config();
	if ( ! $config['enabled'] || empty( $config['api_key'] ) ) {
		wp_send_json_success( [ 'status' => 'skip' ] );
	}

	$street1 = sanitize_text_field( wp_unslash( $_POST['street1'] ?? '' ) );
	$street2 = sanitize_text_field( wp_unslash( $_POST['street2'] ?? '' ) );
	$city    = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
	$state   = sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) );
	$zip     = sanitize_text_field( wp_unslash( $_POST['zip'] ?? '' ) );
	$country = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );

	if ( empty( $street1 ) ) {
		wp_send_json_success( [ 'status' => 'skip' ] );
	}

	// Cache check
	$cache_key = 'wchs_av_' . md5( implode( '|', [ $street1, $street2, $city, $state, $zip, $country ] ) );
	$cached    = get_transient( $cache_key );
	if ( 'valid' === $cached ) {
		wp_send_json_success( [ 'status' => 'success', 'cached' => true ] );
	}

	// Call EasyPost
	$ep_url = 'https://api.easypost.com/v2/addresses?' . http_build_query( [
		'verify[]'          => 'delivery',
		'address[street1]'  => $street1,
		'address[street2]'  => $street2,
		'address[city]'     => $city,
		'address[state]'    => $state,
		'address[zip]'      => $zip,
		'address[country]'  => $country,
	] );

	$response = wp_remote_post( $ep_url, [
		'timeout' => 10,
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode( $config['api_key'] . ':' ),
			'Content-Type'  => 'application/x-www-form-urlencoded',
		],
	] );

	if ( is_wp_error( $response ) ) {
		error_log( 'WCHS AV: EasyPost error — ' . $response->get_error_message() );
		wp_send_json_success( [ 'status' => 'skip' ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! isset( $body['verifications']['delivery'] ) ) {
		wp_send_json_success( [ 'status' => 'skip' ] );
	}

	$delivery = $body['verifications']['delivery'];

	if ( ! empty( $delivery['success'] ) ) {
		// Verified — check if anything was corrected
		$corrected_street = $body['street1'] ?? '';
		$corrected_city   = $body['city'] ?? '';
		$corrected_state  = $body['state'] ?? '';
		$corrected_zip    = $body['zip'] ?? '';

		// Normalize for comparison — EasyPost returns uppercase + abbreviations + ZIP+4
		$norm = function ( $s ) {
			$s = strtolower( trim( $s ) );
			// Common USPS abbreviations
			$abbr = [
				'lane' => 'ln', 'drive' => 'dr', 'street' => 'st', 'avenue' => 'ave',
				'road' => 'rd', 'boulevard' => 'blvd', 'court' => 'ct', 'circle' => 'cir',
				'place' => 'pl', 'terrace' => 'ter', 'highway' => 'hwy', 'parkway' => 'pkwy',
				'way' => 'way', 'trail' => 'trl', 'north' => 'n', 'south' => 's',
				'east' => 'e', 'west' => 'w', 'apartment' => 'apt', 'suite' => 'ste',
			];
			foreach ( $abbr as $full => $short ) {
				$s = preg_replace( '/\b' . $full . '\b/', $short, $s );
			}
			return preg_replace( '/\s+/', ' ', $s );
		};

		$norm_zip = function ( $z ) {
			return explode( '-', trim( $z ) )[0];
		};

		$was_corrected = false;
		if ( $corrected_city && $norm( $city ) !== $norm( $corrected_city ) ) {
			$was_corrected = true;
		}
		if ( $corrected_zip && $norm_zip( $zip ) !== $norm_zip( $corrected_zip ) ) {
			$was_corrected = true;
		}
		if ( $corrected_street && $norm( $street1 ) !== $norm( $corrected_street ) ) {
			$was_corrected = true;
		}

		if ( ! $was_corrected ) {
			set_transient( $cache_key, 'valid', DAY_IN_SECONDS );
		}

		wp_send_json_success( [
			'status'    => 'success',
			'corrected' => $was_corrected,
			'address'   => [
				'street1' => $corrected_street,
				'street2' => $body['street2'] ?? '',
				'city'    => $corrected_city,
				'state'   => $corrected_state,
				'zip'     => $corrected_zip,
				'country' => $body['country'] ?? $country,
			],
		] );
	} else {
		// Failed — extract error details
		$errors  = $delivery['errors'] ?? [];
		$details = [];
		foreach ( $errors as $err ) {
			$code = $err['code'] ?? '';
			if ( $code === 'E.ADDRESS.NOT_FOUND' ) {
				$details[] = 'Address not found';
			} elseif ( $code === 'E.HOUSE_NUMBER.INVALID' ) {
				$details[] = 'Invalid house number';
			} elseif ( $code === 'E.HOUSE_NUMBER.MISSING' ) {
				$details[] = 'Missing house number';
			} elseif ( $code === 'E.STREET.INVALID' ) {
				$details[] = 'Invalid street';
			} elseif ( $code === 'E.CITY_STATE.INVALID' ) {
				$details[] = 'Invalid city or state';
			} elseif ( $code === 'E.ZIP.INVALID' ) {
				$details[] = 'Invalid ZIP code';
			} elseif ( $code === 'E.ADDRESS.INVALID' ) {
				$details[] = 'Invalid address';
			} elseif ( $code === 'E.STREET.MISSING' ) {
				$details[] = 'Missing street';
			} else {
				$details[] = $err['message'] ?? 'Verification failed';
			}
		}

		wp_send_json_success( [
			'status'  => 'failure',
			'errors'  => $details,
			'message' => implode( '. ', $details ) . '.',
		] );
	}
}

// ─── Checkout JS + Modal ────────────────────────────────────────

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
		return;
	}
	$config = wchs_address_validation_config();
	if ( ! $config['enabled'] || empty( $config['api_key'] ) ) {
		return;
	}
	?>
	<!-- Address Validation Modal -->
	<div id="wchs-av-modal" style="display:none">
		<div class="wchs-av-modal__backdrop"></div>
		<div class="wchs-av-modal__dialog">
			<h3 class="wchs-av-modal__title">Verify your address</h3>
			<p class="wchs-av-modal__subtitle" id="wchs-av-subtitle"></p>
			<div class="wchs-av-modal__options" id="wchs-av-options"></div>
			<div class="wchs-av-modal__actions" id="wchs-av-actions"></div>
		</div>
	</div>

	<style>
	.wchs-av-modal__backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:99998; }
	.wchs-av-modal__dialog {
		position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
		z-index:99999; background:var(--bg,#fff); color:var(--fg,#1a1a1a);
		border:1px solid var(--border,#e5e7eb); padding:28px 32px;
		max-width:480px; width:calc(100% - 32px); font-family:var(--font-sans,sans-serif);
		box-shadow:0 8px 32px rgba(0,0,0,0.2);
	}
	.wchs-av-modal__title { font-size:18px; font-weight:600; margin:0 0 4px; letter-spacing:-0.02em; }
	.wchs-av-modal__subtitle { font-size:13px; color:var(--fg-muted,#6b7280); margin:0 0 20px; }
	.wchs-av-modal__options { display:flex; flex-direction:column; gap:8px; margin-bottom:20px; }
	.wchs-av-modal__option {
		display:flex; align-items:flex-start; gap:12px;
		padding:14px 16px; border:1px solid var(--border,#e5e7eb); cursor:pointer;
	}
	.wchs-av-modal__option:has(input:checked) { border-color:var(--fg,#1a1a1a); }
	.wchs-av-modal__option input[type="radio"] { margin-top:2px; accent-color:var(--fg,#1a1a1a); }
	.wchs-av-modal__option-label {
		display:block; font-size:11px; text-transform:uppercase;
		letter-spacing:0.06em; font-weight:600; color:var(--fg-muted,#6b7280); margin-bottom:4px;
	}
	.wchs-av-modal__option-addr { display:block; font-size:14px; line-height:1.4; }
	.wchs-av-modal__btn {
		display:block; width:100%; padding:14px; border:none; cursor:pointer;
		font-family:inherit; font-size:12px; font-weight:600; text-transform:uppercase;
		letter-spacing:0.06em; margin-top:8px;
	}
	.wchs-av-modal__btn--primary { background:var(--fg,#1a1a1a); color:var(--bg,#fff); }
	.wchs-av-modal__btn--primary:hover { opacity:0.85; }
	.wchs-av-modal__btn--secondary {
		background:transparent; color:var(--fg-muted,#6b7280);
		border:1px solid var(--border,#e5e7eb); margin-top:8px;
	}
	.wchs-av-modal__error { color:var(--danger,#dc2626); font-size:14px; line-height:1.5; margin-bottom:16px; }
	</style>

	<script>
	jQuery(function($) {
		var modal = $('#wchs-av-modal');
		var validated = false;
		var avMode = <?php echo wp_json_encode( $config['mode'] ); ?>;

		function getPrefix() {
			return $('#ship-to-different-address-checkbox').is(':checked') ? 'shipping' : 'billing';
		}

		function getAddress(prefix) {
			return {
				street1: $('#' + prefix + '_address_1').val() || '',
				street2: $('#' + prefix + '_address_2').val() || '',
				city:    $('#' + prefix + '_city').val() || '',
				state:   $('#' + prefix + '_state').val() || '',
				zip:     $('#' + prefix + '_postcode').val() || '',
				country: $('#' + prefix + '_country').val() || '',
			};
		}

		function formatAddr(a) {
			var parts = [a.street1];
			if (a.street2) parts.push(a.street2);
			parts.push(a.city + ', ' + a.state + ' ' + a.zip);
			if (a.country && a.country !== 'US') parts.push(a.country);
			return parts.join(', ');
		}

		function showModal(content) {
			$('#wchs-av-subtitle').html(content.subtitle || '');
			$('#wchs-av-options').html(content.options || '');
			$('#wchs-av-actions').html(content.actions || '');
			modal.show();
			$('body').css('overflow', 'hidden');
		}

		function hideModal() {
			modal.hide();
			$('body').css('overflow', '');
		}

		function applyAddress(addr, prefix) {
			$('#' + prefix + '_address_1').val(addr.street1);
			if (addr.street2 !== undefined) $('#' + prefix + '_address_2').val(addr.street2);
			$('#' + prefix + '_city').val(addr.city);
			$('#' + prefix + '_postcode').val(addr.zip);
			var stateEl = document.getElementById(prefix + '_state');
			if (stateEl) {
				stateEl.value = addr.state;
				$(stateEl).trigger('change');
			}
		}

		function submitOrder() {
			validated = true;
			$('#place_order').trigger('click');
		}

		// Intercept place_order BEFORE WC's handler
		$('form.woocommerce-checkout').on('checkout_place_order', function() {
			if (validated) {
				validated = false;
				return true; // Let WC proceed
			}

			var prefix = getPrefix();
			var addr = getAddress(prefix);

			if (!addr.street1 || !addr.city) {
				return true; // Let WC handle empty field validation
			}

			// AJAX to our endpoint
			$.ajax({
				type: 'POST',
				url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
				data: {
					action: 'wchs_validate_address',
					nonce: '<?php echo wp_create_nonce( 'wchs_address_validation' ); ?>',
					street1: addr.street1,
					street2: addr.street2,
					city: addr.city,
					state: addr.state,
					zip: addr.zip,
					country: addr.country,
				},
				success: function(response) {
					if (!response.success || !response.data) {
						submitOrder();
						return;
					}

					var r = response.data;

					// Skip — validation disabled or API error
					if (r.status === 'skip' || r.cached) {
						submitOrder();
						return;
					}

					// Success, no corrections needed
					if (r.status === 'success' && !r.corrected) {
						submitOrder();
						return;
					}

					// Success with corrections — show modal
					if (r.status === 'success' && r.corrected) {
						var suggested = r.address;

						if (avMode === 'strict') {
							// Strict: must use verified address, or go back and change it
							showModal({
								subtitle: 'We corrected your address to match our records. You can use the validated address or go back to enter a different one.',
								options:
									'<div class="wchs-av-modal__option" style="border-color:var(--fg,#1a1a1a)">' +
									'<div><span class="wchs-av-modal__option-label">Validated address</span>' +
									'<span class="wchs-av-modal__option-addr" style="font-weight:500">' + formatAddr(suggested) + '</span></div></div>',
								actions:
									'<button type="button" class="wchs-av-modal__btn wchs-av-modal__btn--primary" id="wchs-av-confirm">Use validated address</button>' +
									'<button type="button" class="wchs-av-modal__btn wchs-av-modal__btn--secondary" id="wchs-av-edit">Enter a different address</button>',
							});

							$('#wchs-av-confirm').off('click').on('click', function() {
								hideModal();
								applyAddress(suggested, prefix);
								submitOrder();
							});
							$('#wchs-av-edit').off('click').on('click', function() {
								hideModal();
							});
						} else {
							// Moderate + Loose: show choice
							showModal({
								subtitle: 'We found a correction for your address. Please choose which to use.',
								options:
									'<label class="wchs-av-modal__option">' +
									'<input type="radio" name="wchs_av_choice" value="suggested" checked />' +
									'<div><span class="wchs-av-modal__option-label">Suggested address</span>' +
									'<span class="wchs-av-modal__option-addr">' + formatAddr(suggested) + '</span></div></label>' +
									'<label class="wchs-av-modal__option">' +
									'<input type="radio" name="wchs_av_choice" value="original" />' +
									'<div><span class="wchs-av-modal__option-label">Address as entered</span>' +
									'<span class="wchs-av-modal__option-addr">' + formatAddr(addr) + '</span></div></label>',
								actions:
									'<button type="button" class="wchs-av-modal__btn wchs-av-modal__btn--primary" id="wchs-av-confirm">Use selected address</button>',
							});

							$('#wchs-av-confirm').off('click').on('click', function() {
								var choice = $('input[name="wchs_av_choice"]:checked').val();
								hideModal();
								if (choice === 'suggested') {
									applyAddress(suggested, prefix);
								}
								submitOrder();
							});
						}
						return;
					}

					// Failure — address not found
					if (r.status === 'failure') {
						var failActions = '<button type="button" class="wchs-av-modal__btn wchs-av-modal__btn--primary" id="wchs-av-fix">Fix my address</button>';
						if (avMode === 'loose') {
							failActions += '<button type="button" class="wchs-av-modal__btn wchs-av-modal__btn--secondary" id="wchs-av-force">Use this address anyway</button>';
						}
						showModal({
							subtitle: '',
							options: '<div class="wchs-av-modal__error">' + (r.message || 'We couldn\'t verify this address.') + '</div>',
							actions: failActions,
						});

						$('#wchs-av-fix').off('click').on('click', function() {
							hideModal();
						});
						$('#wchs-av-force').off('click').on('click', function() {
							hideModal();
							submitOrder();
						});
						return;
					}

					// Fallback
					submitOrder();
				},
				error: function() {
					submitOrder(); // Fail open
				},
			});

			return false; // Block WC submit until AJAX completes
		});

		// Backdrop click closes modal
		modal.on('click', '.wchs-av-modal__backdrop', function() {
			hideModal();
		});
	});
	</script>
	<?php
} );
