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
		'cross_sell_ids' => array_values( array_map( 'intval', (array) $product->get_cross_sell_ids() ) ),
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
	return $best['flag'] !== '' ? $title . ' · ' . $best['flag'] : $title;
}

function wchs_cro_cart_item_data( $cart_item ) {
	if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
		return [];
	}
	$product = $cart_item['data'];
	$qty = (int) $cart_item['quantity'];

	$minor = pow( 10, (int) wc_get_price_decimals() );
	$regular_major = (float) $product->get_regular_price();
	$regular_unit_minor = (int) round( $regular_major * $minor );

	$native_rules = wchs_cro_get_native_tier_rules( $product );
	$rules_data = wchs_cro_get_tier_rules( $product );
	$effective_unit_minor = wchs_cro_unit_price_for_qty( $product, $qty, $rules_data );

	$savings_per_unit = max( 0, $regular_unit_minor - $effective_unit_minor );
	$savings_line = $savings_per_unit * $qty;
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
		'savings_per_unit'     => $savings_per_unit,
		'savings_line_total'   => $savings_line,
		'savings_pct'          => $savings_pct,
		'next_tier'            => $next_tier,
		'bundle_label'         => wchs_cro_cart_item_bundle_label( $qty, $savings_line, $native_rules, $rules_data ),
		'cross_sell_ids'       => array_values( array_map( 'intval', (array) $product->get_cross_sell_ids() ) ),
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
		'bundle_label'         => [ 'type' => 'string', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
		'cross_sell_ids'       => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'context' => [ 'view', 'edit' ], 'readonly' => true ],
	];
}

function wchs_cro_cart_data() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return [ 'total_savings' => 0, 'cross_sell_ids' => [] ];
	}

	$total_savings = 0;
	$cross_sell_ids = [];
	$minor = pow( 10, (int) wc_get_price_decimals() );

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
			continue;
		}
		$product = $cart_item['data'];
		$qty = (int) $cart_item['quantity'];
		$regular_major = (float) $product->get_regular_price();
		$regular_unit_minor = (int) round( $regular_major * $minor );
		$rules_data = wchs_cro_get_tier_rules( $product );
		$eff_unit_minor = wchs_cro_unit_price_for_qty( $product, $qty, $rules_data );
		$total_savings += max( 0, ( $regular_unit_minor - $eff_unit_minor ) * $qty );

		foreach ( (array) $product->get_cross_sell_ids() as $id ) {
			$cross_sell_ids[ (int) $id ] = true;
		}
	}

	// Remove products already in the cart from the cross-sell list
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( ! empty( $cart_item['product_id'] ) ) {
			unset( $cross_sell_ids[ (int) $cart_item['product_id'] ] );
		}
	}

	return [
		'total_savings'  => $total_savings,
		'cross_sell_ids' => array_values( array_map( 'intval', array_keys( $cross_sell_ids ) ) ),
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
			'description' => 'Union of cross-sell ids from every cart item, excluding items already in the cart.',
			'type'        => 'array',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
			'items'       => [ 'type' => 'integer' ],
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

			$decimals        = max( 0, (int) wc_get_price_decimals() );
			$minor           = pow( 10, $decimals );
			$effective_minor = wchs_cro_unit_price_for_qty( $product, $qty, $rules );
			$effective_major = (float) wc_format_decimal( $effective_minor / $minor );

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
