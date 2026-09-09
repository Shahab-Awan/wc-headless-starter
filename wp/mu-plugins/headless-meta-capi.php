<?php
/**
 * Plugin Name: Headless Meta Conversions API
 * Description: Server-side Meta (Facebook) Conversions API. Sends Purchase on
 *              payment confirmation so conversions are not missed when a
 *              buyer never returns to the thank-you page. Browser Pixel lives
 *              in the SPA (analytics.ts) + headless-pixels-compat.php; both
 *              share the same Pixel ID and event_id for deduplication.
 *
 *              Token: WCHS_META_CAPI_TOKEN (wp-config) or META_CAPI_TOKEN env.
 *              Pixel: WCHS_META_PIXEL_ID (wp-config) or META_PIXEL_ID env,
 *              falling back to wchs_site_settings.meta_pixel_id.
 *
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WCHS_META_CAPI_TOKEN' ) ) {
	$tok = getenv( 'META_CAPI_TOKEN' );
	if ( is_string( $tok ) && $tok !== '' ) {
		define( 'WCHS_META_CAPI_TOKEN', $tok );
	}
}

if ( ! defined( 'WCHS_META_PIXEL_ID' ) ) {
	$pid = getenv( 'META_PIXEL_ID' );
	if ( is_string( $pid ) && $pid !== '' ) {
		define( 'WCHS_META_PIXEL_ID', $pid );
	}
}

/**
 * Resolve Meta Pixel ID — constant/env first, then admin setting.
 */
function wchs_meta_pixel_id(): string {
	if ( defined( 'WCHS_META_PIXEL_ID' ) && is_string( WCHS_META_PIXEL_ID ) && WCHS_META_PIXEL_ID !== '' ) {
		return preg_match( '/^\d{10,20}$/', WCHS_META_PIXEL_ID ) ? WCHS_META_PIXEL_ID : '';
	}
	$s = get_option( 'wchs_site_settings', [] );
	$id = is_array( $s ) ? (string) ( $s['meta_pixel_id'] ?? '' ) : '';
	return preg_match( '/^\d{10,20}$/', $id ) ? $id : '';
}

/**
 * CAPI access token — never expose to SPA / REST / HTML.
 */
function wchs_meta_capi_token(): string {
	if ( defined( 'WCHS_META_CAPI_TOKEN' ) && is_string( WCHS_META_CAPI_TOKEN ) ) {
		return (string) WCHS_META_CAPI_TOKEN;
	}
	return '';
}

/**
 * Stable event_id shared with browser Pixel for Meta deduplication.
 */
function wchs_meta_event_id( string $event_name, int $order_id ): string {
	return 'wchs_meta_' . $event_name . '_' . $order_id;
}

/**
 * SHA256 hash of normalized PII for Meta user_data.
 */
function wchs_meta_hash( string $value ): string {
	$normalized = strtolower( trim( $value ) );
	if ( $normalized === '' ) {
		return '';
	}
	return hash( 'sha256', $normalized );
}

/**
 * Build user_data block from a WC order (+ optional request cookies/IP).
 *
 * @param \WC_Order $order
 * @return array<string, mixed>
 */
function wchs_meta_user_data( \WC_Order $order ): array {
	$user = [];

	$email = (string) $order->get_billing_email();
	if ( $email !== '' ) {
		$h = wchs_meta_hash( $email );
		if ( $h ) {
			$user['em'] = [ $h ];
		}
	}

	$phone = preg_replace( '/\D+/', '', (string) $order->get_billing_phone() );
	if ( is_string( $phone ) && $phone !== '' ) {
		$h = wchs_meta_hash( $phone );
		if ( $h ) {
			$user['ph'] = [ $h ];
		}
	}

	$fn = (string) $order->get_billing_first_name();
	if ( $fn !== '' ) {
		$h = wchs_meta_hash( $fn );
		if ( $h ) {
			$user['fn'] = [ $h ];
		}
	}

	$ln = (string) $order->get_billing_last_name();
	if ( $ln !== '' ) {
		$h = wchs_meta_hash( $ln );
		if ( $h ) {
			$user['ln'] = [ $h ];
		}
	}

	$city = (string) $order->get_billing_city();
	if ( $city !== '' ) {
		$h = wchs_meta_hash( $city );
		if ( $h ) {
			$user['ct'] = [ $h ];
		}
	}

	$state = (string) $order->get_billing_state();
	if ( $state !== '' ) {
		$h = wchs_meta_hash( $state );
		if ( $h ) {
			$user['st'] = [ $h ];
		}
	}

	$zip = (string) $order->get_billing_postcode();
	if ( $zip !== '' ) {
		$h = wchs_meta_hash( $zip );
		if ( $h ) {
			$user['zp'] = [ $h ];
		}
	}

	$country = (string) $order->get_billing_country();
	if ( $country !== '' ) {
		$h = wchs_meta_hash( $country );
		if ( $h ) {
			$user['country'] = [ $h ];
		}
	}

	// Prefer order meta captured at checkout; fall back to current request.
	$ip = (string) $order->get_meta( '_customer_ip_address' );
	if ( $ip === '' && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	if ( $ip !== '' ) {
		$user['client_ip_address'] = $ip;
	}

	$ua = (string) $order->get_meta( '_customer_user_agent' );
	if ( $ua === '' && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}
	if ( $ua !== '' ) {
		$user['client_user_agent'] = $ua;
	}

	$fbp = (string) $order->get_meta( '_wchs_meta_fbp' );
	if ( $fbp === '' && ! empty( $_COOKIE['_fbp'] ) ) {
		$fbp = sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ) );
	}
	if ( $fbp !== '' ) {
		$user['fbp'] = $fbp;
	}

	$fbc = (string) $order->get_meta( '_wchs_meta_fbc' );
	if ( $fbc === '' && ! empty( $_COOKIE['_fbc'] ) ) {
		$fbc = sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ) );
	}
	if ( $fbc === '' && ! empty( $_COOKIE['fbclid'] ) ) {
		$fbclid = sanitize_text_field( wp_unslash( $_COOKIE['fbclid'] ) );
		$fbc    = 'fb.1.' . time() . '.' . $fbclid;
	}
	if ( $fbc !== '' ) {
		$user['fbc'] = $fbc;
	}

	return $user;
}

/**
 * Persist Meta cookies onto the order at checkout so CAPI can use them
 * even if payment completes asynchronously (webhook) without cookies.
 */
add_action(
	'woocommerce_checkout_order_created',
	static function ( $order ) {
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}
		if ( ! empty( $_COOKIE['_fbp'] ) ) {
			$order->update_meta_data( '_wchs_meta_fbp', sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ) ) );
		}
		if ( ! empty( $_COOKIE['_fbc'] ) ) {
			$order->update_meta_data( '_wchs_meta_fbc', sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ) ) );
		}
		$order->save();
	},
	20
);

/**
 * POST one event to Meta Conversions API.
 *
 * @param string               $event_name
 * @param array<string, mixed> $event
 * @return bool
 */
function wchs_meta_capi_send( string $event_name, array $event ): bool {
	$pixel = wchs_meta_pixel_id();
	$token = wchs_meta_capi_token();
	if ( $pixel === '' || $token === '' ) {
		return false;
	}

	$url  = 'https://graph.facebook.com/v21.0/' . rawurlencode( $pixel ) . '/events';
	$body = [
		'data'         => [ $event ],
		'access_token' => $token,
	];

	$response = wp_remote_post(
		$url,
		[
			'timeout' => 8,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $body ),
		]
	);

	if ( is_wp_error( $response ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WCHS Meta CAPI ' . $event_name . ' error: ' . $response->get_error_message() );
		}
		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WCHS Meta CAPI ' . $event_name . ' HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
		}
		return false;
	}

	return true;
}

/**
 * Fire CAPI Purchase once per order when payment is confirmed.
 *
 * @param \WC_Order $order
 */
function wchs_meta_capi_send_purchase( \WC_Order $order ): void {
	$order_id = (int) $order->get_id();
	if ( $order_id < 1 ) {
		return;
	}

	if ( $order->get_meta( '_wchs_meta_capi_purchase_sent' ) === '1' ) {
		return;
	}

	$content_ids = [];
	$contents    = [];
	$num_items   = 0;
	foreach ( $order->get_items() as $item ) {
		if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
			continue;
		}
		$product = $item->get_product();
		$pid     = $product ? (int) $product->get_id() : (int) $item->get_product_id();
		if ( $pid <= 0 ) {
			continue;
		}
		$qty        = max( 1, (int) $item->get_quantity() );
		$line_total = (float) $item->get_total();
		$unit       = $qty > 0 ? round( $line_total / $qty, 2 ) : round( $line_total, 2 );
		$id_str     = (string) $pid;
		$content_ids[] = $id_str;
		$contents[]    = [
			'id'         => $id_str,
			'quantity'   => $qty,
			'item_price' => $unit,
		];
		$num_items += $qty;
	}

	$event = [
		'event_name'       => 'Purchase',
		'event_time'       => time(),
		'event_id'         => wchs_meta_event_id( 'Purchase', $order_id ),
		'event_source_url' => $order->get_checkout_order_received_url(),
		'action_source'    => 'website',
		'user_data'        => wchs_meta_user_data( $order ),
		'custom_data'      => [
			'currency'     => $order->get_currency(),
			'value'        => round( (float) $order->get_total(), 2 ),
			'content_ids'  => $content_ids,
			'content_type' => 'product',
			'contents'     => $contents,
			'num_items'    => $num_items,
			'order_id'     => (string) $order_id,
		],
	];

	if ( ! wchs_meta_capi_send( 'Purchase', $event ) ) {
		return;
	}

	$order->update_meta_data( '_wchs_meta_capi_purchase_sent', '1' );
	$order->save();
}

/**
 * @param int $order_id
 */
function wchs_meta_capi_maybe_purchase( $order_id ): void {
	$order_id = absint( $order_id );
	if ( $order_id < 1 ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! ( $order instanceof \WC_Order ) ) {
		return;
	}
	// Only paid / processing / completed — skip pending/failed/cancelled.
	if ( ! $order->is_paid() && ! $order->has_status( [ 'processing', 'completed' ] ) ) {
		return;
	}
	wchs_meta_capi_send_purchase( $order );
}

add_action( 'woocommerce_payment_complete', 'wchs_meta_capi_maybe_purchase', 20 );
add_action( 'woocommerce_order_status_processing', 'wchs_meta_capi_maybe_purchase', 20 );
add_action( 'woocommerce_order_status_completed', 'wchs_meta_capi_maybe_purchase', 20 );
