<?php
/**
 * Plugin Name: Headless Tier Pricing
 * Description: Quantity-based volume discounts on WC products. Supports fixed
 *              price tiers ("buy 4+ for $32 each") and percentage tiers ("buy
 *              4+ for 10% off"). Admin configures via the standard WC product
 *              data panel (new "Tier Pricing" tab).
 *
 * Meta format (compatible with CRO extension):
 *   _tiered_price_rules_type  = 'fixed' | 'percentage'
 *   _fixed_price_rules        = { "2": "35.00", "4": "32.00", "8": "28.00" }
 *   _percentage_price_rules   = { "2": "5", "4": "10", "8": "15" }
 *
 * The headless-cro-extension.php reads these exact meta keys to expose
 * tier data to the SPA via the Store API extensions.wchs_cro object.

 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

// ─── Product data tab ───────────────────────────────────────────

add_filter( 'woocommerce_product_data_tabs', function ( $tabs ) {
	$tabs['wchs_tier_pricing'] = [
		'label'    => 'Tier Pricing',
		'target'   => 'wchs_tier_pricing_data',
		'class'    => [ 'show_if_simple', 'show_if_variable' ],
		'priority' => 65,
	];
	return $tabs;
} );

add_action( 'woocommerce_product_data_panels', function () {
	global $post;
	$product_id = $post->ID;
	$type       = get_post_meta( $product_id, '_tiered_price_rules_type', true ) ?: '';
	$fixed      = (array) get_post_meta( $product_id, '_fixed_price_rules', true );
	$percentage = (array) get_post_meta( $product_id, '_percentage_price_rules', true );

	$rules = [];
	if ( 'fixed' === $type && ! empty( $fixed ) ) {
		foreach ( $fixed as $qty => $val ) {
			$rules[] = [ 'qty' => (int) $qty, 'value' => (string) $val ];
		}
	} elseif ( 'percentage' === $type && ! empty( $percentage ) ) {
		foreach ( $percentage as $qty => $val ) {
			$rules[] = [ 'qty' => (int) $qty, 'value' => (string) $val ];
		}
	}
	// Sort by qty
	usort( $rules, fn( $a, $b ) => $a['qty'] <=> $b['qty'] );
	?>
	<div id="wchs_tier_pricing_data" class="panel woocommerce_options_panel">
		<div class="options_group">
			<p class="form-field">
				<label for="wchs_tier_type">Discount Type</label>
				<select id="wchs_tier_type" name="wchs_tier_type" style="width:auto;">
					<option value="" <?php selected( $type, '' ); ?>>None (disabled)</option>
					<option value="percentage" <?php selected( $type, 'percentage' ); ?>>Percentage off regular price</option>
					<option value="fixed" <?php selected( $type, 'fixed' ); ?>>Fixed price per unit</option>
				</select>
			</p>
		</div>

		<div class="options_group" id="wchs_tier_rules_wrap" style="<?php echo empty( $type ) ? 'display:none' : ''; ?>">
			<p class="form-field">
				<label>Quantity tiers</label>
				<span class="description" style="display:block;margin-bottom:8px;">
					Set the minimum quantity and the discount value for each tier. For percentage: enter the % off (e.g. 10 = 10% off). For fixed: enter the unit price.
				</span>
			</p>
			<table class="widefat" id="wchs_tier_table" style="max-width:500px;">
				<thead>
					<tr>
						<th style="width:100px;">Min Qty</th>
						<th id="wchs_tier_val_label"><?php echo 'percentage' === $type ? '% Off' : 'Unit Price'; ?></th>
						<th style="width:40px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr class="wchs-tier-row">
							<td><input type="number" name="wchs_tier_qty[]" value="" min="2" step="1" style="width:80px;" /></td>
							<td><input type="text" name="wchs_tier_val[]" value="" style="width:100px;" /></td>
							<td><button type="button" class="button wchs-tier-remove" style="color:#a00;">&times;</button></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rules as $r ) : ?>
							<tr class="wchs-tier-row">
								<td><input type="number" name="wchs_tier_qty[]" value="<?php echo esc_attr( $r['qty'] ); ?>" min="2" step="1" style="width:80px;" /></td>
								<td><input type="text" name="wchs_tier_val[]" value="<?php echo esc_attr( $r['value'] ); ?>" style="width:100px;" /></td>
								<td><button type="button" class="button wchs-tier-remove" style="color:#a00;">&times;</button></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="wchs_tier_add">+ Add Tier</button></p>
		</div>

		<script>
		(function() {
			var typeSelect = document.getElementById('wchs_tier_type');
			var rulesWrap = document.getElementById('wchs_tier_rules_wrap');
			var valLabel = document.getElementById('wchs_tier_val_label');
			var table = document.getElementById('wchs_tier_table');

			typeSelect.addEventListener('change', function() {
				rulesWrap.style.display = this.value ? '' : 'none';
				valLabel.textContent = this.value === 'percentage' ? '% Off' : 'Unit Price';
			});

			document.getElementById('wchs_tier_add').addEventListener('click', function() {
				var tbody = table.querySelector('tbody');
				var tr = document.createElement('tr');
				tr.className = 'wchs-tier-row';
				tr.innerHTML = '<td><input type="number" name="wchs_tier_qty[]" value="" min="2" step="1" style="width:80px;" /></td>'
					+ '<td><input type="text" name="wchs_tier_val[]" value="" style="width:100px;" /></td>'
					+ '<td><button type="button" class="button wchs-tier-remove" style="color:#a00;">&times;</button></td>';
				tbody.appendChild(tr);
			});

			table.addEventListener('click', function(e) {
				if (e.target.classList.contains('wchs-tier-remove')) {
					var row = e.target.closest('tr');
					if (table.querySelectorAll('.wchs-tier-row').length > 1) row.remove();
				}
			});
		})();
		</script>
	</div>
	<?php
} );

// ─── Save ───────────────────────────────────────────────────────

add_action( 'woocommerce_process_product_meta', function ( $product_id ) {
	$type = sanitize_text_field( $_POST['wchs_tier_type'] ?? '' );
	if ( ! in_array( $type, [ 'fixed', 'percentage' ], true ) ) {
		delete_post_meta( $product_id, '_tiered_price_rules_type' );
		delete_post_meta( $product_id, '_fixed_price_rules' );
		delete_post_meta( $product_id, '_percentage_price_rules' );
		return;
	}

	$qtys = array_map( 'absint', (array) ( $_POST['wchs_tier_qty'] ?? [] ) );
	$vals = array_map( 'sanitize_text_field', (array) ( $_POST['wchs_tier_val'] ?? [] ) );

	$rules = [];
	$seen_qtys = [];
	$max_tiers = 10;
	for ( $i = 0; $i < count( $qtys ); $i++ ) {
		$q = $qtys[ $i ];
		$v = $vals[ $i ] ?? '';
		if ( $q < 2 || '' === $v ) continue;
		// Must be numeric and positive
		if ( ! is_numeric( $v ) || (float) $v <= 0 ) continue;
		// Skip duplicate quantities (keep first)
		if ( in_array( $q, $seen_qtys, true ) ) continue;
		// For percentage: cap at 99%
		if ( 'percentage' === $type && (float) $v > 99 ) {
			$v = '99';
		}
		// Max tier count
		if ( count( $rules ) >= $max_tiers ) break;
		$seen_qtys[] = $q;
		$rules[ (string) $q ] = $v;
	}
	ksort( $rules );

	update_post_meta( $product_id, '_tiered_price_rules_type', $type );

	if ( 'fixed' === $type ) {
		update_post_meta( $product_id, '_fixed_price_rules', $rules );
		delete_post_meta( $product_id, '_percentage_price_rules' );
	} else {
		update_post_meta( $product_id, '_percentage_price_rules', $rules );
		delete_post_meta( $product_id, '_fixed_price_rules' );
	}
} );

// ─── Cart price adjustment ──────────────────────────────────────

add_action(
	'woocommerce_before_calculate_totals',
	function ( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $item ) {
			$product    = $item['data'];
			$product_id = (int) $item['product_id'];
			$qty        = (int) $item['quantity'];

			if ( function_exists( 'wchs_cart_line_locked_unit_price_major' ) ) {
				$locked = wchs_cart_line_locked_unit_price_major( $item, $qty );
				if ( null !== $locked ) {
					$product->set_price( wc_format_decimal( $locked ) );
					continue;
				}
			}

			$type = get_post_meta( $product_id, '_tiered_price_rules_type', true );
			if ( ! in_array( $type, [ 'fixed', 'percentage' ], true ) ) {
				continue;
			}

			$meta_key = 'fixed' === $type ? '_fixed_price_rules' : '_percentage_price_rules';
			$rules    = (array) get_post_meta( $product_id, $meta_key, true );
			if ( empty( $rules ) ) {
				continue;
			}

			$_product = wc_get_product( $product_id );
			if ( ! $_product ) {
				continue;
			}
			$regular = (float) $_product->get_regular_price();
			$best    = $regular;

			foreach ( $rules as $min_qty => $val ) {
				if ( $qty < (int) $min_qty ) {
					continue;
				}
				if ( 'fixed' === $type ) {
					$best = (float) $val;
				} else {
					$best = $regular * ( 1 - ( (float) $val / 100 ) );
				}
			}

			if ( abs( $best - $regular ) > 0.001 ) {
				$product->set_price( $best );
				if ( function_exists( 'wchs_cart_line_store_unit_price_lock' ) ) {
					wchs_cart_line_store_unit_price_lock( $cart, $cart_item_key, $best, $qty );
				}
			}
		}
	},
	98,
	1
);

// ─── Show tier discount in checkout order review ──────────────
// Appends a strikethrough regular price and savings badge to the
// subtotal column when tier pricing reduced the unit price.

add_filter( 'woocommerce_cart_item_subtotal', function ( string $subtotal, array $cart_item, string $cart_item_key ): string {
	$product    = $cart_item['data']; // variation object for variable products
	$product_id = $cart_item['product_id']; // always the parent ID
	$qty        = (int) $cart_item['quantity'];

	$type = get_post_meta( $product_id, '_tiered_price_rules_type', true );
	if ( ! in_array( $type, [ 'fixed', 'percentage' ], true ) ) {
		return $subtotal;
	}

	// For variable products, get regular price from the variation, not the parent.
	// The parent's get_regular_price() returns empty or min price.
	$variation_id = $cart_item['variation_id'] ?? 0;
	$source = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
	if ( ! $source ) return $subtotal;
	$regular = (float) $source->get_regular_price();
	$current = (float) $product->get_price();

	if ( $regular <= 0 || abs( $regular - $current ) < 0.01 ) {
		return $subtotal;
	}

	$regular_line = $regular * $qty;
	$savings_pct  = round( ( 1 - $current / $regular ) * 100 );

	$was = '<del style="color:var(--fg-muted,#767d88);font-weight:400;font-size:12px;margin-right:4px">'
		. wc_price( $regular_line ) . '</del>';
	$badge = '<span style="display:inline-block;font-size:10px;font-weight:600;letter-spacing:0.06em;'
		. 'text-transform:uppercase;color:var(--success,#4ade80);margin-left:6px">'
		. $savings_pct . '% off</span>';

	return $was . $subtotal . $badge;
}, 10, 3 );
