<?php
/**
 * One-off: rebuild _wc_review_count + _wc_average_rating for every product.
 *
 * Run once after any SQL-level review import (e.g. migration from another
 * WP site) because SQL imports bypass WC's comment hooks that normally
 * populate those postmetas. Without this, $product->get_review_count()
 * returns 0 on every product, which cascades into:
 *   - PDP star strip says "(0 reviews)" everywhere
 *   - WC Store API's review_count field = 0
 *   - Our PDP per-product review block (gated on review_count > 0) never renders
 *
 * Usage:
 *   scp scripts/review-count-rebuild.php HOST:/tmp/
 *   ssh HOST "cd /path/to/public_html && wp eval-file /tmp/review-count-rebuild.php"
 *   # then: wp sg purge (or similar cache purge for the host)
 *
 * Idempotent: safe to re-run any time.
 */

if ( ! class_exists( 'WC_Comments' ) ) {
	echo "WooCommerce not loaded — aborting.\n";
	exit( 1 );
}

$touched = 0;
$total_reviews = 0;

foreach ( wc_get_products( [ 'limit' => -1, 'status' => [ 'publish', 'private' ] ] ) as $p ) {
	$pid = $p->get_id();

	// Count approved review rows directly from wp_comments — the source of truth.
	$count = (int) get_comments( [
		'post_id' => $pid,
		'type'    => 'review',
		'status'  => 'approve',
		'count'   => true,
	] );

	// Compute average rating from the `rating` comment meta on each approved review.
	$ids = get_comments( [
		'post_id' => $pid,
		'type'    => 'review',
		'status'  => 'approve',
		'fields'  => 'ids',
	] );
	$sum = 0;
	$n   = 0;
	foreach ( $ids as $cid ) {
		$r = (int) get_comment_meta( $cid, 'rating', true );
		if ( $r >= 1 && $r <= 5 ) {
			$sum += $r;
			$n++;
		}
	}
	$avg = $n > 0 ? round( $sum / $n, 2 ) : 0;

	// Write both cache fields. update_post_meta is idempotent.
	update_post_meta( $pid, '_wc_review_count', $count );
	update_post_meta( $pid, '_wc_average_rating', $avg );

	// Per-rating count meta (_wc_rating_count) — used by some WC rating UIs.
	$rating_counts = [ 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
	foreach ( $ids as $cid ) {
		$r = (int) get_comment_meta( $cid, 'rating', true );
		if ( isset( $rating_counts[ $r ] ) ) {
			$rating_counts[ $r ]++;
		}
	}
	update_post_meta( $pid, '_wc_rating_count', $rating_counts );

	// Bust WC's per-product transients so get_review_count() sees the fresh meta.
	WC_Comments::clear_transients( $pid );

	if ( $count > 0 ) {
		printf( "  #%-5d %-50s  count=%2d avg=%.2f\n", $pid, substr( $p->get_name(), 0, 50 ), $count, $avg );
		$touched++;
		$total_reviews += $count;
	}
}

printf( "\n%d products with reviews rebuilt. %d total review rows accounted for.\n", $touched, $total_reviews );
