<?php
/**
 * Plugin Name: Headless Back In Stock Badge
 * Description: Product checkbox to show a "Back in stock" card badge for 20 days.
 * Version:     1.1.0
 */

defined( 'ABSPATH' ) || exit;

const WCHS_BACK_IN_STOCK_FLAG  = '_wchs_show_back_in_stock';
const WCHS_BACK_IN_STOCK_UNTIL = '_wchs_back_in_stock_until';
const WCHS_BACK_IN_STOCK_DAYS  = 20;

/**
 * Resolve parent product id for variations.
 */
function wchs_back_in_stock_parent_id( int $product_id ): int {
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return $product_id;
	}
	$parent = (int) $product->get_parent_id();
	return $parent > 0 ? $parent : $product_id;
}

/**
 * True when the product (or any variation) can currently be sold from stock.
 */
function wchs_product_has_sellable_stock( int $product_id ): bool {
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return false;
	}

	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( (int) $child_id );
			if ( $variation && wchs_product_unit_has_sellable_stock( $variation ) ) {
				return true;
			}
		}
		return false;
	}

	return wchs_product_unit_has_sellable_stock( $product );
}

/**
 * @param \WC_Product $product
 */
function wchs_product_unit_has_sellable_stock( \WC_Product $product ): bool {
	if ( ! $product->is_in_stock() ) {
		return false;
	}
	if ( ! $product->managing_stock() ) {
		return true;
	}
	return (int) $product->get_stock_quantity() > 0;
}

/**
 * Clear expired back-in-stock meta so the admin checkbox unchecks itself.
 */
function wchs_product_clear_expired_back_in_stock( int $product_id ): void {
	$product_id = wchs_back_in_stock_parent_id( $product_id );
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
 * Clear the badge when nothing is in stock anymore.
 */
function wchs_product_clear_back_in_stock_without_stock( int $product_id ): void {
	$product_id = wchs_back_in_stock_parent_id( $product_id );
	if ( $product_id <= 0 || wchs_product_has_sellable_stock( $product_id ) ) {
		return;
	}
	if ( get_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG, true ) !== 'yes' ) {
		return;
	}
	delete_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG );
	delete_post_meta( $product_id, WCHS_BACK_IN_STOCK_UNTIL );
}

/**
 * Enable the 20-day back-in-stock window on the parent product.
 */
function wchs_product_enable_back_in_stock( int $product_id ): void {
	$product_id = wchs_back_in_stock_parent_id( $product_id );
	if ( $product_id <= 0 || ! wchs_product_has_sellable_stock( $product_id ) ) {
		return;
	}
	update_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG, 'yes' );
	update_post_meta(
		$product_id,
		WCHS_BACK_IN_STOCK_UNTIL,
		time() + ( WCHS_BACK_IN_STOCK_DAYS * DAY_IN_SECONDS )
	);
}

/**
 * Whether the product should currently show the Back in stock badge.
 */
function wchs_product_back_in_stock_active( int $product_id ): bool {
	$product_id = wchs_back_in_stock_parent_id( $product_id );
	if ( $product_id <= 0 ) {
		return false;
	}
	wchs_product_clear_expired_back_in_stock( $product_id );
	wchs_product_clear_back_in_stock_without_stock( $product_id );
	if ( get_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG, true ) !== 'yes' ) {
		return false;
	}
	if ( ! wchs_product_has_sellable_stock( $product_id ) ) {
		return false;
	}
	$until = (int) get_post_meta( $product_id, WCHS_BACK_IN_STOCK_UNTIL, true );
	return $until > time();
}

/**
 * Auto-enable when qty moves from 0 → >0.
 *
 * @param \WC_Product $product
 */
function wchs_back_in_stock_maybe_enable_from_qty( \WC_Product $product, int $previous_qty ): void {
	$new_qty = (int) $product->get_stock_quantity();
	if ( $previous_qty <= 0 && $new_qty > 0 ) {
		wchs_product_enable_back_in_stock( (int) $product->get_id() );
		return;
	}
	if ( $new_qty <= 0 ) {
		wchs_product_clear_back_in_stock_without_stock( (int) $product->get_id() );
	}
}

/**
 * Auto-enable when status moves outofstock → instock.
 *
 * @param \WC_Product $product
 */
function wchs_back_in_stock_maybe_enable_from_status( \WC_Product $product, string $previous_status ): void {
	$status = (string) $product->get_stock_status();
	if ( $previous_status === 'outofstock' && $status === 'instock' ) {
		wchs_product_enable_back_in_stock( (int) $product->get_id() );
		return;
	}
	if ( $status === 'outofstock' ) {
		wchs_product_clear_back_in_stock_without_stock( (int) $product->get_id() );
	}
}

add_action(
	'woocommerce_product_before_set_stock',
	static function ( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$GLOBALS['wchs_bis_prev_qty'][ (int) $product->get_id() ] = (int) $product->get_stock_quantity();
	}
);

add_action(
	'woocommerce_product_set_stock',
	static function ( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$id   = (int) $product->get_id();
		$prev = isset( $GLOBALS['wchs_bis_prev_qty'][ $id ] )
			? (int) $GLOBALS['wchs_bis_prev_qty'][ $id ]
			: 0;
		unset( $GLOBALS['wchs_bis_prev_qty'][ $id ] );
		wchs_back_in_stock_maybe_enable_from_qty( $product, $prev );
	}
);

add_action(
	'woocommerce_variation_before_set_stock',
	static function ( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$GLOBALS['wchs_bis_prev_qty'][ (int) $product->get_id() ] = (int) $product->get_stock_quantity();
	}
);

add_action(
	'woocommerce_variation_set_stock',
	static function ( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$id   = (int) $product->get_id();
		$prev = isset( $GLOBALS['wchs_bis_prev_qty'][ $id ] )
			? (int) $GLOBALS['wchs_bis_prev_qty'][ $id ]
			: 0;
		unset( $GLOBALS['wchs_bis_prev_qty'][ $id ] );
		wchs_back_in_stock_maybe_enable_from_qty( $product, $prev );
	}
);

add_action(
	'woocommerce_product_object_updated_props',
	static function ( $product, $updated_props ): void {
		if ( ! $product instanceof \WC_Product || ! is_array( $updated_props ) ) {
			return;
		}
		$id = (int) $product->get_id();

		if ( in_array( 'stock_quantity', $updated_props, true ) ) {
			$changes = $product->get_changes();
			// After save, changes may already be empty; use meta snapshot when needed.
			$prev_qty = null;
			if ( isset( $GLOBALS['wchs_bis_prev_qty'][ $id ] ) ) {
				$prev_qty = (int) $GLOBALS['wchs_bis_prev_qty'][ $id ];
			}
			if ( $prev_qty === null ) {
				// Fallback: treat non-positive previous as restock candidate only via set_stock hooks.
				wchs_product_clear_back_in_stock_without_stock( $id );
			} else {
				wchs_back_in_stock_maybe_enable_from_qty( $product, $prev_qty );
			}
		}

		if ( in_array( 'stock_status', $updated_props, true ) ) {
			$prev_status = $GLOBALS['wchs_bis_prev_status'][ $id ] ?? null;
			if ( is_string( $prev_status ) ) {
				wchs_back_in_stock_maybe_enable_from_status( $product, $prev_status );
			} else {
				wchs_product_clear_back_in_stock_without_stock( $id );
			}
		}
	},
	20,
	2
);

add_action(
	'woocommerce_before_product_object_save',
	static function ( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$id = (int) $product->get_id();
		if ( $id <= 0 ) {
			return;
		}
		// Capture pre-save stock for admin edits that bypass set_stock hooks.
		$existing = wc_get_product( $id );
		if ( ! $existing ) {
			return;
		}
		$GLOBALS['wchs_bis_prev_qty'][ $id ]    = (int) $existing->get_stock_quantity();
		$GLOBALS['wchs_bis_prev_status'][ $id ] = (string) $existing->get_stock_status();
	},
	5
);

add_action(
	'woocommerce_product_options_sku',
	static function (): void {
		global $post;
		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		if ( $product_id ) {
			wchs_product_clear_expired_back_in_stock( $product_id );
			wchs_product_clear_back_in_stock_without_stock( $product_id );
		}
		$has_stock = $product_id && wchs_product_has_sellable_stock( $product_id );
		$checked   = $product_id && get_post_meta( $product_id, WCHS_BACK_IN_STOCK_FLAG, true ) === 'yes';
		$until     = $product_id ? (int) get_post_meta( $product_id, WCHS_BACK_IN_STOCK_UNTIL, true ) : 0;
		$hint      = '';
		if ( $checked && $until > time() && $has_stock ) {
			$hint = sprintf(
				/* translators: %s: localized date */
				__( 'Active until %s. Unchecks automatically after that, or if stock returns to 0.', 'wchs' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $until )
			);
		} elseif ( ! $has_stock ) {
			$hint = __( 'Unavailable while stock is 0 on the product and all variations. It auto-checks when stock increases from 0.', 'wchs' );
		}

		$field = [
			'id'            => 'wchs_show_back_in_stock',
			'label'         => __( 'Show back in stock badge on PDP (activate for next 20 days)', 'wchs' ),
			'description'   => __( 'Auto-checks when stock increases from 0 (product or any variation). Requires available stock. Clears after 20 days or when stock is 0 again.', 'wchs' ),
			'desc_tip'      => true,
			'value'         => ( $checked && $has_stock ) ? 'yes' : 'no',
			'wrapper_class' => 'show_if_simple show_if_variable',
		];
		if ( ! $has_stock ) {
			$field['custom_attributes'] = [ 'disabled' => 'disabled' ];
		}
		woocommerce_wp_checkbox( $field );
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

		// Disabled checkboxes are omitted from POST — preserve an active in-stock window.
		if ( ! isset( $_POST['wchs_show_back_in_stock'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			&& $product->get_meta( WCHS_BACK_IN_STOCK_FLAG ) === 'yes'
			&& wchs_product_has_sellable_stock( (int) $product->get_id() )
		) {
			return;
		}

		if ( ! $enabled ) {
			$product->delete_meta_data( WCHS_BACK_IN_STOCK_FLAG );
			$product->delete_meta_data( WCHS_BACK_IN_STOCK_UNTIL );
			return;
		}

		if ( ! wchs_product_has_sellable_stock( (int) $product->get_id() ) ) {
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
	},
	20
);
