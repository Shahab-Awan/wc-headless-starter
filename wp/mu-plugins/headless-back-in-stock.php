<?php
/**
 * Plugin Name: Headless Back In Stock Badge
 * Description: Product checkbox to show a "Back in stock" card badge for 20 days.
 * Version:     1.0.3
 */

defined( 'ABSPATH' ) || exit;

const WCHS_BACK_IN_STOCK_FLAG  = '_wchs_show_back_in_stock';
const WCHS_BACK_IN_STOCK_UNTIL = '_wchs_back_in_stock_until';
const WCHS_BACK_IN_STOCK_DAYS  = 20;

/**
 * Clear expired back-in-stock meta so the admin checkbox unchecks itself.
 */
function wchs_product_clear_expired_back_in_stock( int $product_id ): void {
	if ( $product_id <= 0 ) {
		return;
	}
	if ( get_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG, true ) !== 'yes' ) {
		return;
	}
	$until = (int) get_post_meta( $product_id, WCHS_BACK_IN_STOCK_UNTIL, true );
	if ( $until > time() ) {
		return;
	}
	delete_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG );
	delete_post_meta( $product_id, WCHS_BACK_IN_STOCK_UNTIL );
}

/**
 * Whether the product should currently show the Back in stock badge.
 */
function wchs_product_back_in_stock_active( int $product_id ): bool {
	if ( $product_id <= 0 ) {
		return false;
	}
	wchs_product_clear_expired_back_in_stock( $product_id );
	if ( get_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG, true ) !== 'yes' ) {
		return false;
	}
	$until = (int) get_post_meta( $product_id, WCHS_BACK_IN_STOCK_UNTIL, true );
	return $until > time();
}

add_action(
	'woocommerce_product_options_sku',
	static function (): void {
		global $post;
		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		if ( $product_id ) {
			wchs_product_clear_expired_back_in_stock( $product_id );
		}
		$checked = $product_id && get_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG, true ) === 'yes';
		$until   = $product_id ? (int) get_post_meta( $product_id, WCHS_BACK_IN_STOCK_UNTIL, true ) : 0;
		$hint    = '';
		if ( $checked && $until > time() ) {
			$hint = sprintf(
				/* translators: %s: localized date */
				__( 'Active until %s. Unchecks automatically after that.', 'wchs' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $until )
			);
		}

		woocommerce_wp_checkbox(
			[
				'id'            => 'wchs_show_back_in_stock',
				'label'         => __( 'Show back in stock badge on PDP (activate for next 20 days)', 'wchs' ),
				'description'   => __( 'Shows a black “Back in stock” badge on product cards for 20 days. The checkbox unchecks itself when that window ends.', 'wchs' ),
				'desc_tip'      => true,
				'value'         => $checked ? 'yes' : 'no',
				'wrapper_class' => 'show_if_simple show_if_variable',
			]
		);
		if ( $hint !== '' ) {
			echo '<p class="form-field" style="margin-top:-8px"><span class="description">' . esc_html( $hint ) . '</span></p>';
		}
	},
	1
);

add_action(
	'woocommerce_admin_process_product_object',
	static function ( \WC_Product $product ): void {
		$enabled = isset( $_POST['wchs_show_back_in_stock'] ) && $_POST['wchs_show_back_in_stock'] === 'yes'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $enabled ) {
			$product->delete_meta_data( WCHS_BACK_IN_STOCK_FLAG );
			$product->delete_meta_data( WCHS_BACK_IN_STOCK_UNTIL );
			return;
		}

		$product->update_meta_data( WCHS_BACK_IN_STOCK_FLAG, 'yes' );
		$until = (int) $product->get_meta( WCHS_BACK_IN_STOCK_UNTIL, true );
		if ( $until <= time() ) {
			$product->update_meta_data(
				WCHS_BACK_IN_STOCK_UNTIL,
				time() + ( WCHS_BACK_IN_STOCK_DAYS * DAY_IN_SECONDS )
			);
		}
	}
);
