<?php
/**
 * Plugin Name: Headless CRO Extension
 * Description: Exposes CRO metadata (tier pricing rules, cross-sells, per-
 *              line savings) on the WC Store API product + cart endpoints
 *              under `extensions.wchs_cro` so the SPA can render "was/now",
 *              "you saved $X", and upsell cards without parsing price_html.
 * Version:     0.1.0
 * Author:      WCHS Contributors
*
 * Shape (product endpoint):
 *   extensions.wchs_cro.regular_price       — base price in minor units (cents)
 *   extensions.wchs_cro.tier_type           — 'fixed' | 'percentage' | null
 *   extensions.wchs_cro.tiers               — [ { min_qty, unit_price, savings_per_unit, savings_pct, line_total } ]
 *   extensions.wchs_cro.cross_sell_ids      — product ids
 *
 * Shape (cart item):
 *   extensions.wchs_cro.regular_unit_price  — base unit price in minor units
 *   extensions.wchs_cro.effective_unit_price — current unit price after tiers
 *   extensions.wchs_cro.savings_per_unit    — minor units
 *   extensions.wchs_cro.savings_line_total  — minor units
 *   extensions.wchs_cro.savings_pct         — float 0-100
 *   extensions.wchs_cro.bundle_label        — optional drawer badge when site BOGO tiers apply
 *   extensions.wchs_cro.next_tier           — { qty_needed, next_unit_price, additional_savings_pct } | null
 *
 * Shape (cart top-level):
 *   extensions.wchs_cro.total_savings       — sum of savings_line_total across items
 *   extensions.wchs_cro.cross_sell_ids      — dedup set of cross_sell_ids from cart items
 *
 * All monetary fields are integer minor units (cents) so the SPA never
 * has to do float arithmetic — matches the Store API's own convention.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_blocks_loaded', function () {
	if ( ! class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
		return;
	}

	$extend = \Automattic\WooCommerce\StoreApi\StoreApi::container()
		->get( \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class );

	// -----------------------------------------------------------------
	// Product endpoint extension
	// -----------------------------------------------------------------
	$extend->register_endpoint_data( [
		'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema::IDENTIFIER,
		'namespace'       => 'wchs_cro',
		'data_callback'   => 'wchs_cro_product_data',
		'schema_callback' => 'wchs_cro_product_schema',
	] );

	// -----------------------------------------------------------------
	// Cart item extension
	// -----------------------------------------------------------------
	$extend->register_endpoint_data( [
		'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
		'namespace'       => 'wchs_cro',
		'data_callback'   => 'wchs_cro_cart_item_data',
		'schema_callback' => 'wchs_cro_cart_item_schema',
	] );

	// -----------------------------------------------------------------
	// Cart top-level extension
	// -----------------------------------------------------------------
	$extend->register_endpoint_data( [
		'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
		'namespace'       => 'wchs_cro',
		'data_callback'   => 'wchs_cro_cart_data',
		'schema_callback' => 'wchs_cro_cart_schema',
	] );
} );

/**
 * Site-wide bundle presets: paid_qty + optional free_qty (missing free_qty ⇒ legacy Buy-N-Get-N).
 */
function wchs_cro_bogo_normalize_preset_row( array $row ): ?array {
	$paid = (int) ( $row['paid_qty'] ?? 0 );
	if ( $paid < 1 ) {
		return null;
	}
	$has_explicit_free = array_key_exists( 'free_qty', $row );
	$free              = $has_explicit_free ? max( 0, (int) $row['free_qty'] ) : $paid;
	return [
		'paid_qty' => $paid,
		'free_qty' => $free,
		'flag'     => sanitize_text_field( (string) ( $row['flag'] ?? '' ) ),
	];
}

function wchs_cro_bogo_settings(): array {
	$bogo = [];
	if ( class_exists( '\WCHS\Admin\AdminPage' ) ) {
		$pdp  = \WCHS\Admin\AdminPage::get_pdp_config();
		$bogo = is_array( $pdp['bundle_bogo'] ?? null ) ? $pdp['bundle_bogo'] : [];
	}

	$default_presets = [
		[ 'paid_qty' => 1, 'free_qty' => 0, 'flag' => '' ],
		[ 'paid_qty' => 2, 'free_qty' => 1, 'flag' => 'MOST POPULAR' ],
		[ 'paid_qty' => 3, 'free_qty' => 2, 'flag' => 'BEST VALUE' ],
	];

	$presets_out = [];
	if ( ! empty( $bogo['presets'] ) && is_array( $bogo['presets'] ) ) {
		foreach ( $bogo['presets'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$norm = wchs_cro_bogo_normalize_preset_row( $row );
			if ( ! $norm ) {
				continue;
			}
			$presets_out[] = $norm;
		}
	}
	if ( empty( $presets_out ) ) {
		foreach ( $default_presets as $row ) {
			$presets_out[] = wchs_cro_bogo_normalize_preset_row( $row );
		}
	}
	usort(
		$presets_out,
		static function ( array $a, array $b ): int {
			return $a['paid_qty'] <=> $b['paid_qty'];
		}
	);

	return [
		'enabled'     => ! array_key_exists( 'enabled', $bogo ) || ! empty( $bogo['enabled'] ),
		'savings_pct' => (float) ( $bogo['savings_pct'] ?? 50 ),
		'presets'     => $presets_out,
	];
}

/**
 * Tier rules saved on the product in WooCommerce admin.
 */
function wchs_cro_get_native_tier_rules( \WC_Product $product ): array {
	$id = $product->get_parent_id() ?: $product->get_id();

	$type = get_post_meta( $id, '_tiered_price_rules_type', true );
	if ( ! in_array( $type, [ 'fixed', 'percentage' ], true ) ) {
		return [ 'type' => null, 'rules' => [] ];
	}

	$meta_key = $type === 'fixed' ? '_fixed_price_rules' : '_percentage_price_rules';
	$raw      = (array) get_post_meta( $id, $meta_key, true );
	if ( empty( $raw ) ) {
		return [ 'type' => null, 'rules' => [] ];
	}

	$rules = [];
	foreach ( $raw as $qty => $val ) {
		$qty_int = (int) $qty;
		if ( $qty_int < 2 ) {
			continue;
		}
		$rules[ $qty_int ] = (float) $val;
	}
	ksort( $rules );

	return [ 'type' => $type, 'rules' => $rules ];
}

/**
 * Resolve a product's tier rules. Falls back to site bundle presets (percentage
 * thresholds at paid+free qty) when the product has no native tiers.
 */
function wchs_cro_get_tier_rules( \WC_Product $product ): array {
	$native = wchs_cro_get_native_tier_rules( $product );
	if ( ! empty( $native['rules'] ) ) {
		return $native;
	}
	$bogo = wchs_cro_bogo_settings();
	if ( ! $bogo['enabled'] ) {
		return [ 'type' => null, 'rules' => [] ];
	}
	$rules = [];
	foreach ( $bogo['presets'] as $preset ) {
		$paid = (int) ( $preset['paid_qty'] ?? 0 );
		$free = (int) ( $preset['free_qty'] ?? $paid );
		if ( $paid < 1 || $free < 1 ) {
			continue;
		}
		$total = $paid + $free;
		if ( $total < 2 ) {
			continue;
		}
		$rules[ $total ] = round( ( 100 * $free ) / $total, 4 );
	}
	if ( empty( $rules ) ) {
		return [ 'type' => null, 'rules' => [] ];
	}
	ksort( $rules, SORT_NUMERIC );
	return [
		'type'  => 'percentage',
		'rules' => $rules,
	];
}

/**
 * PDP tier rows from bundle presets (pay paid_qty × regular, receive paid+free units).
 *
 * @return list<array<string, int|float>>
 */
function wchs_cro_build_bogo_bundle_rows( int $regular_minor ): array {
	$bogo = wchs_cro_bogo_settings();
	$rows = [];
	foreach ( $bogo['presets'] as $preset ) {
		$paid = (int) $preset['paid_qty'];
		if ( $paid < 1 ) {
			continue;
		}
		$free = (int) ( $preset['free_qty'] ?? $paid );
		if ( $free < 0 ) {
			$free = 0;
		}
		$total      = $paid + $free;
		$pct        = $free > 0 && $total > 0 ? min( 100, ( 100 * $free ) / $total ) : 0.0;
		$unit_minor = $free > 0
			? (int) round( $regular_minor * $paid / $total )
			: $regular_minor;
		$rows[] = [
			'min_qty'               => $total,
			'unit_price'            => $unit_minor,
			'savings_per_unit'      => max( 0, $regular_minor - $unit_minor ),
			'savings_pct'           => round( $pct, 1 ),
			'line_total_at_min_qty' => $paid * $regular_minor,
		];
	}
	return $rows;
}

/**
 * Compute effective unit price (in minor units) for a given qty against a
 * resolved rule-set. Falls back to the product regular price when no
 * tier applies.
 */
function wchs_cro_unit_price_for_qty(
	\WC_Product $product,
	int $qty,
	array $rules_data
): int {
	$minor = (int) wc_get_price_decimals() >= 0 ? pow( 10, wc_get_price_decimals() ) : 100;
	$regular_major = (float) $product->get_regular_price();
	$regular_minor = (int) round( $regular_major * $minor );

	if ( empty( $rules_data['rules'] ) ) {
		return $regular_minor;
	}

	$best_unit_minor = $regular_minor;
	foreach ( $rules_data['rules'] as $min_qty => $val ) {
		if ( $qty < $min_qty ) {
			continue;
		}
		if ( $rules_data['type'] === 'fixed' ) {
			$best_unit_minor = (int) round( $val * $minor );
		} elseif ( $rules_data['type'] === 'percentage' ) {
			$discounted = $regular_major * ( 1 - ( $val / 100 ) );
			$best_unit_minor = (int) round( $discounted * $minor );
		}
	}
	return $best_unit_minor;
}

/**
 * Sorted bundle tier thresholds (min_qty keys) for BOGO / percentage rules.
 *
 * @return int[]
 */
function wchs_cro_tier_threshold_qtys( array $rules_data ): array {
	if ( empty( $rules_data['rules'] ) ) {
		return [];
	}
	$keys = array_map( 'intval', array_keys( $rules_data['rules'] ) );
	sort( $keys, SORT_NUMERIC );
	return array_values( array_filter( $keys, static fn( int $q ): bool => $q >= 1 ) );
}

/**
 * Largest tier threshold the cart qty satisfies (bundle anchor for mixed lines).
 */
function wchs_cro_active_bundle_min_qty( int $qty, array $rules_data ): int {
	$active = 0;
	foreach ( wchs_cro_tier_threshold_qtys( $rules_data ) as $min_qty ) {
		if ( $qty >= $min_qty ) {
			$active = $min_qty;
		}
	}
	return $active;
}

/**
 * Line total in minor units: bundle block at the matched tier + extras at regular.
 */
function wchs_cro_line_total_minor_for_qty( \WC_Product $product, int $qty, array $rules_data ): int {
	$minor          = pow( 10, (int) wc_get_price_decimals() );
	$regular_minor  = (int) round( (float) $product->get_regular_price() * $minor );
	if ( $qty < 1 || $regular_minor <= 0 || empty( $rules_data['rules'] ) ) {
		return max( 0, $regular_minor * max( 0, $qty ) );
	}

	$anchor = wchs_cro_active_bundle_min_qty( $qty, $rules_data );
	if ( $anchor < 1 ) {
		return $regular_minor * $qty;
	}

	$bundle_unit_minor = wchs_cro_unit_price_for_qty( $product, $anchor, $rules_data );
	$bundle_line       = $bundle_unit_minor * $anchor;
	$overage           = max( 0, $qty - $anchor );

	return $bundle_line + ( $overage * $regular_minor );
}

/**
 * Snap cart qty on +/- so partial steps between bundles resolve to the next/previous tier.
 */
function wchs_cro_resolve_cart_qty_change( int $current_qty, int $proposed_qty, array $rules_data ): int {
	$tiers = wchs_cro_tier_threshold_qtys( $rules_data );
	if ( empty( $tiers ) ) {
		return max( 1, $proposed_qty );
	}

	$current  = max( 1, $current_qty );
	$proposed = max( 1, $proposed_qty );

	if ( $proposed > $current ) {
		$tier_at_current = 0;
		$next_tier       = null;
		foreach ( $tiers as $min_qty ) {
			if ( $min_qty <= $current ) {
				$tier_at_current = $min_qty;
			}
			if ( $min_qty > $current && null === $next_tier ) {
				$next_tier = $min_qty;
			}
		}
		if ( $tier_at_current > 0 && $current === $tier_at_current && null !== $next_tier && $proposed > $current && $proposed < $next_tier ) {
			return $next_tier;
		}
		return $proposed;
	}

	if ( $proposed < $current && in_array( $current, $tiers, true ) ) {
		$lower = 0;
		foreach ( array_reverse( $tiers ) as $min_qty ) {
			if ( $min_qty < $current ) {
				$lower = $min_qty;
				break;
			}
		}
		if ( $lower > 0 && $proposed < $current && $proposed > $lower ) {
			return $lower;
		}
	}

	return $proposed;
}

/**
 * Build a display-ready tier rows array:
 *   [ { min_qty, unit_price, savings_per_unit, savings_pct, line_total_at_min_qty } ]
 * All monetary fields are integer minor units.
 */
function wchs_cro_build_tier_rows( \WC_Product $product ): array {
	$minor         = pow( 10, (int) wc_get_price_decimals() );
	$regular_major = (float) $product->get_regular_price();
	$regular_minor = (int) round( $regular_major * $minor );

	$native = wchs_cro_get_native_tier_rules( $product );
	if ( empty( $native['rules'] ) ) {
		if ( $regular_minor > 0 && wchs_cro_bogo_settings()['enabled'] ) {
			return wchs_cro_build_bogo_bundle_rows( $regular_minor );
		}
		return [];
	}

	$rows = [];
	foreach ( $native['rules'] as $min_qty => $val ) {
		$unit_minor             = wchs_cro_unit_price_for_qty( $product, $min_qty, $native );
		$savings_per_unit_minor = max( 0, $regular_minor - $unit_minor );
		if ( $native['type'] === 'percentage' ) {
			$savings_pct = round( (float) $val, 1 );
		} else {
			$savings_pct = $regular_minor > 0
				? round( ( $savings_per_unit_minor / $regular_minor ) * 100, 1 )
				: 0;
		}
		$rows[] = [
			'min_qty'               => (int) $min_qty,
			'unit_price'            => $unit_minor,
			'savings_per_unit'      => $savings_per_unit_minor,
			'savings_pct'           => $savings_pct,
			'line_total_at_min_qty' => $unit_minor * (int) $min_qty,
		];
	}
	return $rows;
}

/**
 * Read COA post meta, falling back to the parent product for variations.
 */
function wchs_cro_coa_meta( int $product_id, string $key, int $parent_id = 0 ): string {
	$val = (string) get_post_meta( $product_id, $key, true );
	if ( $val !== '' ) {
		return $val;
	}
	if ( $parent_id > 0 ) {
		return (string) get_post_meta( $parent_id, $key, true );
	}
	return '';
}

/**
 * @return list<array{label: string, value: string}>
 */
function wchs_cro_coa_metrics( int $product_id, int $parent_id = 0 ): array {
	foreach ( [ $product_id, $parent_id ] as $pid ) {
		if ( $pid <= 0 ) {
			continue;
		}
		$raw = get_post_meta( $pid, '_wchs_coa_metrics', true );
		if ( ! is_string( $raw ) || $raw === '' ) {
			continue;
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			continue;
		}
		$rows = [];
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$value = isset( $row['value'] ) ? sanitize_text_field( (string) $row['value'] ) : '';
			if ( $label !== '' && $value !== '' ) {
				$rows[] = [ 'label' => $label, 'value' => $value ];
			}
		}
		if ( $rows ) {
			return $rows;
		}
	}
	return [];
}

function wchs_cro_product_data( $product ) {
	if ( ! $product instanceof \WC_Product ) {
		return [];
	}
	$minor = pow( 10, (int) wc_get_price_decimals() );
	$regular_major = (float) $product->get_regular_price();

	$product_id = (int) $product->get_id();
	$parent_id  = (int) $product->get_parent_id();

	$coa_url = wchs_cro_coa_meta( $product_id, '_wchs_coa_url', $parent_id );
	if ( $coa_url === '' ) {
		$coa_url = wchs_cro_coa_meta( $product_id, 'coa_url', $parent_id );
	}

	return [
		'regular_price'  => (int) round( $regular_major * $minor ),
		'tier_type'      => wchs_cro_get_tier_rules( $product )['type'],
		'tiers'          => wchs_cro_build_tier_rows( $product ),
		'cross_sell_ids' => wchs_cro_pdp_cross_sell_ids( $product_id ),
		'coa_url'        => $coa_url ? esc_url_raw( $coa_url ) : '',
		'coa_batch'      => wchs_cro_coa_meta( $product_id, '_wchs_coa_batch', $parent_id ),
		'coa_lab'        => wchs_cro_coa_meta( $product_id, '_wchs_coa_lab', $parent_id ),
		'coa_metrics'    => wchs_cro_coa_metrics( $product_id, $parent_id ),
	];
}

function wchs_cro_product_schema() {
	return [
		'regular_price' => [
			'description' => 'Regular price in minor units (e.g. cents).',
			'type'        => 'integer',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
		],
		'tier_type' => [
			'description' => 'Tier pricing type: fixed, percentage, or null.',
			'type'        => [ 'string', 'null' ],
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
		],
		'tiers' => [
			'description' => 'Volume discount tiers.',
			'type'        => 'array',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
			'items'       => [
				'type'       => 'object',
				'properties' => [
					'min_qty'               => [ 'type' => 'integer' ],
					'unit_price'            => [ 'type' => 'integer' ],
					'savings_per_unit'      => [ 'type' => 'integer' ],
					'savings_pct'           => [ 'type' => 'number' ],
					'line_total_at_min_qty' => [ 'type' => 'integer' ],
				],
			],
		],
		'cross_sell_ids' => [
			'description' => 'WooCommerce cross-sell product IDs.',
			'type'        => 'array',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
			'items'       => [ 'type' => 'integer' ],
		],
		'coa_url' => [
			'description' => 'Certificate of analysis download URL for this product.',
			'type'        => 'string',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
		],
		'coa_batch' => [
			'description' => 'COA batch identifier.',
			'type'        => 'string',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
		],
		'coa_lab' => [
			'description' => 'COA testing laboratory name.',
			'type'        => 'string',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
		],
		'coa_metrics' => [
			'description' => 'COA result rows for the PDP transparency card.',
			'type'        => 'array',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
			'items'       => [
				'type'       => 'object',
				'properties' => [
					'label' => [ 'type' => 'string' ],
					'value' => [ 'type' => 'string' ],
				],
			],
		],
	];
}

function wchs_cro_cart_item_bundle_label(
	int $qty,
	int $savings_line,
	array $native_rules,
	array $rules_data
): string {
	if ( $savings_line <= 0 ) {
		return '';
	}
	if ( ! empty( $native_rules['rules'] ) ) {
		return '';
	}
	if ( empty( $rules_data['rules'] ) ) {
		return '';
	}
	$bogo = wchs_cro_bogo_settings();
	if ( empty( $bogo['enabled'] ) ) {
		return '';
	}
	$presets = $bogo['presets'] ?? [];
	if ( empty( $presets ) ) {
		return '';
	}

	$candidates = [];
	foreach ( $presets as $preset ) {
		if ( ! is_array( $preset ) ) {
			continue;
		}
		$paid = (int) ( $preset['paid_qty'] ?? 0 );
		if ( $paid < 1 ) {
			continue;
		}
		$free = array_key_exists( 'free_qty', $preset ) ? (int) $preset['free_qty'] : $paid;
		if ( $free < 1 ) {
			continue;
		}
		$total = $paid + $free;
		if ( $qty < $total ) {
			continue;
		}
		$candidates[] = [
			'paid'  => $paid,
			'free'  => $free,
			'total' => $total,
			'flag'  => sanitize_text_field( (string) ( $preset['flag'] ?? '' ) ),
		];
	}
	if ( empty( $candidates ) ) {
		return '';
	}
	usort(
		$candidates,
		static function ( array $a, array $b ): int {
			return $b['total'] <=> $a['total'];
		}
	);
	$best = $candidates[0];
	$title = sprintf( 'Buy %d Get %d Free', $best['paid'], $best['free'] );
	if ( $best['free'] < 1 ) {
		return '';
	}
	return $best['flag'] !== '' ? $title . ' · ' . $best['flag'] : $title;
}

function wchs_cro_cart_item_data( $cart_item ) {
	if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
		return [];
	}
	$product = $cart_item['data'];
	if ( wchs_shipping_protection_product_id() > 0
		&& (int) ( $cart_item['product_id'] ?? 0 ) === wchs_shipping_protection_product_id() ) {
		$minor = pow( 10, (int) wc_get_price_decimals() );
		$cart  = function_exists( 'WC' ) ? WC()->cart : null;
		$fee_major = $cart instanceof \WC_Cart
			? wchs_shipping_protection_fee_major( wchs_shipping_protection_cart_subtotal_major( $cart ) )
			: (float) $product->get_price();
		return [
			'is_shipping_protection' => true,
			'fee_minor'              => (int) round( $fee_major * $minor ),
		];
	}
	$qty = (int) $cart_item['quantity'];

	$minor = pow( 10, (int) wc_get_price_decimals() );
	$regular_major = (float) $product->get_regular_price();
	$regular_unit_minor = (int) round( $regular_major * $minor );

	$native_rules = wchs_cro_get_native_tier_rules( $product );
	$rules_data = wchs_cro_get_tier_rules( $product );
	$uses_bogo_mixed = empty( $native_rules['rules'] ) && ! empty( $rules_data['rules'] );
	$line_total_minor  = $uses_bogo_mixed
		? wchs_cro_line_total_minor_for_qty( $product, $qty, $rules_data )
		: wchs_cro_unit_price_for_qty( $product, $qty, $rules_data ) * $qty;
	$effective_unit_minor = $qty > 0 ? (int) round( $line_total_minor / $qty ) : $regular_unit_minor;

	$savings_per_unit = max( 0, $regular_unit_minor - $effective_unit_minor );
	$savings_line     = max( 0, ( $regular_unit_minor * $qty ) - $line_total_minor );
	$savings_pct = $regular_unit_minor > 0
		? round( ( $savings_per_unit / $regular_unit_minor ) * 100, 1 )
		: 0;

	// Next tier prompt
	$next_tier = null;
	if ( ! empty( $rules_data['rules'] ) ) {
		foreach ( $rules_data['rules'] as $min_qty => $val ) {
			if ( $min_qty > $qty ) {
				$next_unit_minor = wchs_cro_unit_price_for_qty( $product, $min_qty, $rules_data );
				$extra_savings_per_unit = max( 0, $effective_unit_minor - $next_unit_minor );
				$next_savings_pct = $regular_unit_minor > 0
					? round( ( max( 0, $regular_unit_minor - $next_unit_minor ) / $regular_unit_minor ) * 100, 1 )
					: 0;
				$next_tier = [
					'qty_needed'             => (int) $min_qty - $qty,
					'next_min_qty'           => (int) $min_qty,
					'next_unit_price'        => $next_unit_minor,
					'next_savings_pct'       => $next_savings_pct,
					'additional_savings_per_unit' => $extra_savings_per_unit,
				];
				break;
			}
		}
	}

	return [
		'regular_unit_price'   => $regular_unit_minor,
		'effective_unit_price' => $effective_unit_minor,
		'line_total_minor'     => $line_total_minor,
		'compare_line_minor'   => $regular_unit_minor * $qty,
		'savings_per_unit'     => $savings_per_unit,
		'savings_line_total'   => $savings_line,
		'savings_pct'          => $savings_pct,
		'next_tier'            => $next_tier,
		'bundle_label'         => wchs_cro_cart_item_bundle_label( $qty, $savings_line, $native_rules, $rules_data ),
		'tier_qty_thresholds'  => wchs_cro_tier_threshold_qtys( $rules_data ),
		'active_bundle_min_qty' => wchs_cro_active_bundle_min_qty( $qty, $rules_data ),
		'cross_sell_ids'       => wchs_cro_filter_cart_cross_sell_ids(
			array_values( array_map( 'intval', (array) $product->get_cross_sell_ids() ) )
		),
	];
}

function wchs_cro_cart_item_schema() {
	return [
		'regular_unit_price'   => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'effective_unit_price' => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'savings_per_unit'     => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'savings_line_total'   => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'savings_pct'          => [ 'type' => 'number',  'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'next_tier'            => [ 'type' => [ 'object', 'null' ], 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'bundle_label'          => [ 'type' => 'string', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'line_total_minor'      => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'compare_line_minor'    => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'tier_qty_thresholds'   => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'active_bundle_min_qty' => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'cross_sell_ids'        => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'context' => [ 'view', 'edit' ], 'readonly' => true ],
	];
}

/**
 * Default product slugs never recommended in cart/PDP cross-sells.
 *
 * @return string[]
 */
function wchs_cro_cart_cross_sell_default_exclude_slugs(): array {
	return [ 'bac-water-10ml', 'shipping-protection' ];
}

/**
 * Published WooCommerce product ID for a slug (works for hidden catalog items).
 */
function wchs_cro_product_id_from_slug( string $slug ): int {
	$slug = sanitize_title( $slug );
	if ( $slug === '' ) {
		return 0;
	}
	$posts = get_posts(
		[
			'post_type'      => 'product',
			'name'           => $slug,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		]
	);
	return isset( $posts[0] ) ? (int) $posts[0] : 0;
}

/**
 * Shipping protection product ID (hidden ancillary).
 */
function wchs_shipping_protection_product_id(): int {
	static $id = null;
	if ( is_int( $id ) ) {
		return $id;
	}
	$id = wchs_cro_product_id_from_slug( 'shipping-protection' );
	return $id;
}

/**
 * BAC water ancillary product (slide-cart + PDP cross-sell rail anchor).
 */
function wchs_bac_water_product_id(): int {
	static $id = null;
	if ( is_int( $id ) ) {
		return $id;
	}
	$id = wchs_cro_product_id_from_slug( 'bac-water-10ml' );
	return $id;
}

/**
 * Tiered shipping protection fees by cart subtotal (major units, excludes protection line).
 *
 * @return array<int, array{up_to: float|null, fee: float}>
 */
function wchs_shipping_protection_tiers(): array {
	return [
		[ 'up_to' => 100.0, 'fee' => 8.0 ],
		[ 'up_to' => 300.0, 'fee' => 12.0 ],
		[ 'up_to' => null, 'fee' => 16.0 ],
	];
}

/**
 * @param float $subtotal_major Cart subtotal excluding shipping protection.
 */
function wchs_shipping_protection_fee_major( float $subtotal_major ): float {
	$subtotal_major = max( 0.0, $subtotal_major );
	foreach ( wchs_shipping_protection_tiers() as $tier ) {
		if ( null === $tier['up_to'] || $subtotal_major < (float) $tier['up_to'] ) {
			return (float) $tier['fee'];
		}
	}
	$tiers = wchs_shipping_protection_tiers();
	$last  = end( $tiers );
	return is_array( $last ) ? (float) $last['fee'] : 16.0;
}

/**
 * @param \WC_Cart|null $cart
 */
function wchs_shipping_protection_cart_subtotal_major( $cart = null ): float {
	if ( ! function_exists( 'WC' ) ) {
		return 0.0;
	}
	if ( ! $cart instanceof \WC_Cart ) {
		$cart = WC()->cart;
	}
	if ( ! $cart || $cart->is_empty() ) {
		return 0.0;
	}
	$protect_id = wchs_shipping_protection_product_id();
	$sum        = 0.0;
	foreach ( $cart->get_cart() as $item ) {
		$pid = (int) ( $item['product_id'] ?? 0 );
		if ( $protect_id > 0 && $pid === $protect_id ) {
			continue;
		}
		if ( ! empty( $item['line_subtotal'] ) ) {
			$sum += (float) $item['line_subtotal'];
			continue;
		}
		if ( empty( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) {
			continue;
		}
		$sum += (float) $item['data']->get_price() * (int) ( $item['quantity'] ?? 1 );
	}
	return max( 0.0, $sum );
}

/**
 * Product IDs excluded from Store API catalog listings (shipping protection only).
 * BAC water stays purchasable on /shop; cross-sell exclusions are separate.
 *
 * @return int[]
 */
function wchs_cro_catalog_excluded_product_ids(): array {
	$protect_id = wchs_shipping_protection_product_id();
	return $protect_id > 0 ? [ $protect_id ] : [];
}

/**
 * True when a product slug matches ancillary items (BAC water, shipping protection).
 */
function wchs_cro_product_slug_is_cart_cross_sell_blocked( string $slug ): bool {
	$slug = strtolower( trim( $slug ) );
	if ( '' === $slug ) {
		return false;
	}
	foreach ( wchs_cro_cart_cross_sell_default_exclude_slugs() as $blocked ) {
		if ( $slug === $blocked || str_starts_with( $slug, $blocked . '-' ) ) {
			return true;
		}
	}
	if ( preg_match( '/bac[-_]?water|bacteriostatic[-_]?water/', $slug ) ) {
		return true;
	}
	if ( preg_match( '/shipping[-_]?protection|protected[-_]?shipping/', $slug ) ) {
		return true;
	}
	return false;
}

/**
 * Product IDs excluded from slide-cart cross-sells (admin config + slug defaults).
 *
 * @return int[]
 */
function wchs_cro_cart_cross_sell_excluded_product_ids(): array {
	static $cache = null;
	if ( is_array( $cache ) ) {
		return $cache;
	}

	$blocked = [];
	$slugs   = wchs_cro_cart_cross_sell_default_exclude_slugs();

	if ( class_exists( '\\WCHS\\Admin\\AdminPage' ) ) {
		$pdp = \WCHS\Admin\AdminPage::get_pdp_config();
		$sc  = is_array( $pdp['slide_cart'] ?? null ) ? $pdp['slide_cart'] : [];
		foreach ( (array) ( $sc['cross_sell_exclude_product_ids'] ?? [] ) as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$blocked[ $id ] = true;
			}
		}
		$config_slugs = (array) ( $sc['cross_sell_exclude_slugs'] ?? [] );
		$slugs        = array_values(
			array_unique(
				array_filter(
					array_map(
						'sanitize_title',
						array_merge( $slugs, $config_slugs )
					)
				)
			)
		);
	}

	foreach ( $slugs as $slug ) {
		$post = get_page_by_path( $slug, OBJECT, 'product' );
		if ( $post instanceof \WP_Post ) {
			$blocked[ (int) $post->ID ] = true;
			continue;
		}
		if ( function_exists( 'wc_get_products' ) ) {
			$found = wc_get_products(
				[
					'status' => 'publish',
					'limit'  => 1,
					'slug'   => $slug,
					'return' => 'ids',
				]
			);
			if ( ! empty( $found[0] ) ) {
				$blocked[ (int) $found[0] ] = true;
			}
		}
	}

	$cache = array_values( array_map( 'intval', array_keys( $blocked ) ) );
	return $cache;
}

/**
 * @param int $product_id
 */
function wchs_cro_is_cart_cross_sell_blocked_product_id( int $product_id ): bool {
	if ( $product_id < 1 ) {
		return true;
	}
	if ( in_array( $product_id, wchs_cro_cart_cross_sell_excluded_product_ids(), true ) ) {
		return true;
	}
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return false;
	}
	if ( wchs_cro_product_slug_is_cart_cross_sell_blocked( $product->get_slug() ) ) {
		return true;
	}
	if ( $product->is_type( 'variation' ) ) {
		$parent_id = (int) $product->get_parent_id();
		if ( $parent_id > 0 ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent && wchs_cro_product_slug_is_cart_cross_sell_blocked( $parent->get_slug() ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * @param int[] $ids
 * @return int[]
 */
function wchs_cro_filter_cart_cross_sell_ids( array $ids ): array {
	$out = [];
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 && ! wchs_cro_is_cart_cross_sell_blocked_product_id( $id ) ) {
			$out[] = $id;
		}
	}
	return array_values( $out );
}

/**
 * Slide-cart cross-sell rail size (always pad to this count when the catalog allows).
 */
function wchs_cro_cart_cross_sell_target_count(): int {
	return 4;
}

/**
 * Product IDs that must never appear as cart cross-sells (in cart, blocked, or both).
 *
 * @return int[]
 */
function wchs_cro_cart_cross_sell_reserved_ids(): array {
	$reserved = wchs_cro_cart_cross_sell_excluded_product_ids();
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array_values( array_unique( array_map( 'intval', $reserved ) ) );
	}
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['product_id'] ) ) {
			$reserved[] = (int) $cart_item['product_id'];
		}
		if ( ! empty( $cart_item['variation_id'] ) ) {
			$reserved[] = (int) $cart_item['variation_id'];
		}
	}
	return array_values( array_unique( array_filter( array_map( 'intval', $reserved ) ) ) );
}

/**
 * @return int[]
 */
function wchs_cro_cart_product_category_ids(): array {
	$cat_ids = [];
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $cat_ids;
	}
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product_id = (int) ( $cart_item['product_id'] ?? 0 );
		if ( $product_id < 1 ) {
			continue;
		}
		$terms = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );
		if ( is_array( $terms ) ) {
			$cat_ids = array_merge( $cat_ids, $terms );
		}
	}
	return array_values( array_unique( array_map( 'intval', $cat_ids ) ) );
}

/**
 * @param array{exclude?: int[], limit?: int, category?: int[]} $args
 * @return int[]
 */
function wchs_cro_query_cart_cross_sell_candidates( array $args ): array {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return [];
	}
	$exclude = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $args['exclude'] ?? [] ) ) ) ) );
	$limit     = max( 1, (int) ( $args['limit'] ?? wchs_cro_cart_cross_sell_target_count() ) );
	$orderby   = isset( $args['orderby'] ) && 'rand' === $args['orderby'] ? 'rand' : 'meta_value_num';
	$query     = [
		'status'       => 'publish',
		'limit'        => $limit + count( $exclude ) + 4,
		'orderby'      => $orderby,
		'exclude'      => $exclude,
		'stock_status' => 'instock',
		'type'         => [ 'simple', 'variable' ],
		'return'       => 'ids',
	];
	if ( 'meta_value_num' === $orderby ) {
		$query['meta_key'] = 'total_sales';
		$query['order']    = 'DESC';
	}
	$categories = array_values( array_filter( array_map( 'intval', (array) ( $args['category'] ?? [] ) ) ) );
	if ( ! empty( $categories ) ) {
		$query['category'] = $categories;
	}
	$found = wc_get_products( $query );
	if ( ! is_array( $found ) ) {
		return [];
	}
	$out = [];
	foreach ( $found as $id ) {
		$id = (int) $id;
		if ( $id < 1 || in_array( $id, $exclude, true ) ) {
			continue;
		}
		if ( wchs_cro_is_cart_cross_sell_blocked_product_id( $id ) ) {
			continue;
		}
		$product = wc_get_product( $id );
		if ( ! $product || ! $product->is_purchasable() ) {
			continue;
		}
		$out[] = $id;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * BAC water first, then random in-stock products (slide cart or PDP rails).
 *
 * @param int   $random_count Slots after BAC water.
 * @param int[] $exclude      Product IDs to omit.
 * @return int[]
 */
function wchs_cro_build_bac_first_cross_sell_ids( int $random_count, array $exclude = [] ): array {
	$random_count = max( 0, $random_count );
	$exclude      = array_values( array_unique( array_filter( array_map( 'intval', $exclude ) ) ) );
	$bac_id       = wchs_bac_water_product_id();
	$out          = [];

	if ( $bac_id > 0 && ! in_array( $bac_id, $exclude, true ) ) {
		$bac = wc_get_product( $bac_id );
		if ( $bac && $bac->is_purchasable() && $bac->is_in_stock() ) {
			$out[] = $bac_id;
		}
	}

	$exclude = array_values( array_unique( array_merge( $exclude, $out ) ) );
	if ( $random_count < 1 ) {
		return $out;
	}

	$random = wchs_cro_query_cart_cross_sell_candidates(
		[
			'exclude' => $exclude,
			'limit'   => $random_count,
			'orderby' => 'rand',
		]
	);

	return array_values( array_merge( $out, $random ) );
}

/**
 * PDP “Often ordered with” rail: BAC water + 2 random products.
 *
 * @param int $product_id Viewed product (simple, variable parent, or variation).
 * @return int[]
 */
function wchs_cro_pdp_cross_sell_ids( int $product_id ): array {
	$exclude = [ $product_id ];
	$product = wc_get_product( $product_id );
	if ( $product && $product->is_type( 'variation' ) ) {
		$parent_id = (int) $product->get_parent_id();
		if ( $parent_id > 0 ) {
			$exclude[] = $parent_id;
		}
	}
	return wchs_cro_build_bac_first_cross_sell_ids( 2, $exclude );
}

/**
 * Product IDs in cart (and shipping protection) — omit from cross-sell rails.
 *
 * @return int[]
 */
function wchs_cro_cart_cross_sell_context_exclude_ids(): array {
	$exclude = [];
	$protect = wchs_shipping_protection_product_id();
	if ( $protect > 0 ) {
		$exclude[] = $protect;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array_values( array_unique( array_map( 'intval', $exclude ) ) );
	}
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['product_id'] ) ) {
			$exclude[] = (int) $cart_item['product_id'];
		}
		if ( ! empty( $cart_item['variation_id'] ) ) {
			$exclude[] = (int) $cart_item['variation_id'];
		}
	}
	return array_values( array_unique( array_filter( array_map( 'intval', $exclude ) ) ) );
}

/**
 * Slide-cart cross-sell rail: BAC water + 3 random products.
 *
 * @param int[] $ids Unused; kept for call-site compatibility.
 * @return int[]
 */
function wchs_cro_pad_cart_cross_sell_ids( array $ids ): array {
	unset( $ids );
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return [];
	}
	return wchs_cro_build_bac_first_cross_sell_ids( 3, wchs_cro_cart_cross_sell_context_exclude_ids() );
}

function wchs_cro_cart_data() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return [ 'total_savings' => 0, 'cross_sell_ids' => [] ];
	}

	$total_savings = 0;
	$cross_sell_ids = [];
	$minor = pow( 10, (int) wc_get_price_decimals() );

	$protect_id = wchs_shipping_protection_product_id();
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( $protect_id > 0 && (int) ( $cart_item['product_id'] ?? 0 ) === $protect_id ) {
			continue;
		}
		if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
			continue;
		}
		$product = $cart_item['data'];
		$qty = (int) $cart_item['quantity'];
		$regular_major = (float) $product->get_regular_price();
		$regular_unit_minor = (int) round( $regular_major * $minor );
		$rules_data = wchs_cro_get_tier_rules( $product );
		$native_rules = wchs_cro_get_native_tier_rules( $product );
		$line_minor   = empty( $native_rules['rules'] ) && ! empty( $rules_data['rules'] )
			? wchs_cro_line_total_minor_for_qty( $product, $qty, $rules_data )
			: wchs_cro_unit_price_for_qty( $product, $qty, $rules_data ) * $qty;
		$total_savings += max( 0, ( $regular_unit_minor * $qty ) - $line_minor );

		foreach ( (array) $product->get_cross_sell_ids() as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! wchs_cro_is_cart_cross_sell_blocked_product_id( $id ) ) {
				$cross_sell_ids[ $id ] = true;
			}
		}
	}

	// Remove products already in the cart from the cross-sell list
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['product_id'] ) ) {
			unset( $cross_sell_ids[ (int) $cart_item['product_id'] ] );
		}
	}

	$basis_major = wchs_shipping_protection_cart_subtotal_major( WC()->cart );
	$fee_major   = wchs_shipping_protection_fee_major( $basis_major );
	$minor       = pow( 10, (int) wc_get_price_decimals() );

	return [
		'total_savings'       => $total_savings,
		'cross_sell_ids'      => wchs_cro_pad_cart_cross_sell_ids( [] ),
		'shipping_protection' => [
			'subtotal_basis_minor' => (int) round( $basis_major * $minor ),
			'fee_minor'            => (int) round( $fee_major * $minor ),
			'tiers'                => array_map(
				static function ( $tier ) use ( $minor ) {
					return [
						'up_to' => null === $tier['up_to'] ? null : (float) $tier['up_to'],
						'fee'   => (int) round( (float) $tier['fee'] * $minor ),
					];
				},
				wchs_shipping_protection_tiers()
			),
		],
	];
}

function wchs_cro_cart_schema() {
	return [
		'total_savings' => [
			'description' => 'Sum of per-line tier savings across cart in minor units.',
			'type'        => 'integer',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
		],
		'cross_sell_ids' => [
			'description' => 'Up to four cross-sell product ids for the slide-cart rail (WC cross-sells, backfilled with best sellers when needed).',
			'type'        => 'array',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
			'items'       => [ 'type' => 'integer' ],
		],
		'shipping_protection' => [
			'description' => 'Tiered shipping protection fee for the current cart.',
			'type'        => 'object',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
			'properties'  => [
				'subtotal_basis_minor' => [ 'type' => 'integer' ],
				'fee_minor'            => [ 'type' => 'integer' ],
				'tiers'                => [ 'type' => 'array' ],
			],
		],
	];
}

add_action(
	'woocommerce_before_calculate_totals',
	function ( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) {
				continue;
			}
			$product = $item['data'];
			$qty     = (int) $item['quantity'];

			if ( ! empty( wchs_cro_get_native_tier_rules( $product )['rules'] ) ) {
				continue;
			}

			if ( ! wchs_cro_bogo_settings()['enabled'] ) {
				continue;
			}

			$rules = wchs_cro_get_tier_rules( $product );
			if ( empty( $rules['rules'] ) ) {
				continue;
			}

			$decimals       = max( 0, (int) wc_get_price_decimals() );
			$minor          = pow( 10, $decimals );
			$line_minor     = wchs_cro_line_total_minor_for_qty( $product, $qty, $rules );
			$effective_major = $qty > 0
				? (float) wc_format_decimal( ( $line_minor / $minor ) / $qty )
				: 0.0;

			$regular_major = (float) wc_format_decimal( (float) $product->get_regular_price() );
			if ( $effective_major <= 0 || $regular_major <= 0 ) {
				continue;
			}

			if ( abs( $effective_major - $regular_major ) > 0.00001 ) {
				$product->set_price( $effective_major );
			}
		}
	},
	15,
	1
);

/**
 * Apply tiered shipping-protection fee to the hidden ancillary line item.
 * Must run on every calculate_totals pass — WC often invokes it twice per
 * Store API request; the legacy did_action>=2 guard left the catalog price
 * (e.g. $2.99) on the line after auto-add in the slide cart.
 *
 * @param \WC_Cart $cart Cart instance.
 */
function wchs_apply_shipping_protection_cart_prices( $cart ): void {
	if ( ! $cart instanceof \WC_Cart ) {
		return;
	}
	$protect_id = wchs_shipping_protection_product_id();
	if ( $protect_id < 1 ) {
		return;
	}
	$fee_major = wchs_shipping_protection_fee_major( wchs_shipping_protection_cart_subtotal_major( $cart ) );
	foreach ( $cart->get_cart() as $item ) {
		if ( (int) ( $item['product_id'] ?? 0 ) !== $protect_id ) {
			continue;
		}
		if ( empty( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) {
			continue;
		}
		$item['data']->set_price( wc_format_decimal( $fee_major ) );
		break;
	}
}

add_action(
	'woocommerce_before_calculate_totals',
	static function ( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		wchs_apply_shipping_protection_cart_prices( $cart );
	},
	99,
	1
);

/**
 * Drop shipping protection when no purchasable products remain in the cart.
 */
function wchs_prune_orphan_shipping_protection(): void {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	$protect_id = wchs_shipping_protection_product_id();
	if ( $protect_id < 1 ) {
		return;
	}
	$has_regular = false;
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( (int) ( $item['product_id'] ?? 0 ) !== $protect_id ) {
			$has_regular = true;
			break;
		}
	}
	if ( $has_regular ) {
		return;
	}
	foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
		if ( (int) ( $item['product_id'] ?? 0 ) === $protect_id ) {
			WC()->cart->remove_cart_item( $cart_item_key );
			break;
		}
	}
}

add_action( 'woocommerce_cart_item_removed', 'wchs_prune_orphan_shipping_protection', 20 );
add_action( 'woocommerce_after_cart_item_quantity_update', 'wchs_prune_orphan_shipping_protection', 20 );

add_action(
	'woocommerce_add_to_cart',
	static function ( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		unset( $cart_item_key, $quantity, $variation_id, $variation, $cart_item_data );
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( (int) $product_id !== wchs_shipping_protection_product_id() ) {
			return;
		}
		wchs_apply_shipping_protection_cart_prices( WC()->cart );
	},
	20,
	6
);

/**
 * Keep shipping protection out of Store API catalog queries (BAC water is shop-visible).
 */
add_filter(
	'woocommerce_product_data_store_cpt_get_products_query',
	static function ( $query, $query_vars ) {
		unset( $query_vars );
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $query;
		}
		$exclude = wchs_cro_catalog_excluded_product_ids();
		if ( empty( $exclude ) ) {
			return $query;
		}
		$existing              = isset( $query['post__not_in'] ) ? (array) $query['post__not_in'] : [];
		$query['post__not_in'] = array_values( array_unique( array_merge( $existing, $exclude ) ) );
		return $query;
	},
	10,
	2
);

add_filter(
	'woocommerce_rest_product_object_query',
	static function ( $args, $request ) {
		unset( $request );
		$exclude = wchs_cro_catalog_excluded_product_ids();
		if ( empty( $exclude ) ) {
			return $args;
		}
		$existing           = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : [];
		$args['post__not_in'] = array_values( array_unique( array_merge( $existing, $exclude ) ) );
		return $args;
	},
	10,
	2
);
