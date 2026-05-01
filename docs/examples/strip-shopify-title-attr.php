<?php
/**
 * One-off: strip the `title=Default Title` pseudo-attribute from
 * products migrated from Shopify. Shopify always emits a `title`
 * attribute with value "Default Title" on single-variant products;
 * that's meaningless in WooCommerce and causes our ProductCard to
 * render a useless "Title" stepper row.
 *
 * Safe to re-run: only removes attributes that have exactly one option
 * named "Default Title" (case-insensitive).
 *
 * Run via `wp eval-file scripts/strip-shopify-title-attr.php`.
 */

if ( ! function_exists( 'wc_get_products' ) ) {
	echo "WooCommerce not loaded — aborting.\n";
	exit( 1 );
}

$touched = 0;

foreach ( wc_get_products( [ 'limit' => -1, 'status' => [ 'publish', 'draft', 'private' ] ] ) as $p ) {
	$attrs   = $p->get_attributes();
	$changed = false;
	foreach ( $attrs as $key => $a ) {
		$opts = $a->get_options();
		$only_default_title = count( $opts ) === 1
			&& strcasecmp( trim( (string) $opts[0] ), 'default title' ) === 0;
		if ( $only_default_title ) {
			unset( $attrs[ $key ] );
			$changed = true;
			printf( "  #%-5d %s — removed attribute `%s`\n", $p->get_id(), $p->get_name(), $key );
		}
	}
	if ( $changed ) {
		$p->set_attributes( $attrs );
		$p->save();
		$touched++;
	}
}

printf( "\n%d products cleaned.\n", $touched );
