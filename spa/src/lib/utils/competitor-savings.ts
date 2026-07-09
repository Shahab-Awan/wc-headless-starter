/**
 * Stable pseudo-random "below competitors" % for product cards.
 * Higher prices skew toward the top of the range; product id adds jitter.
 */
export function competitorSavingsPct(productId: number, priceCents: number): number {
	const MIN = 10;
	const MAX = 30;
	const PRICE_FLOOR = 1500;
	const PRICE_CEIL = 25000;

	const price = Math.max(0, priceCents);
	const t =
		price <= PRICE_FLOOR ? 0 : price >= PRICE_CEIL ? 1 : (price - PRICE_FLOOR) / (PRICE_CEIL - PRICE_FLOOR);

	let h = productId | 0;
	h = Math.imul(h ^ (h >>> 16), 0x7feb352d);
	h = Math.imul(h ^ (h >>> 15), 0x846ca68b);
	h = (h ^ (h >>> 16)) >>> 0;
	const jitter = (h % 5) - 2;

	return Math.round(Math.min(MAX, Math.max(MIN, MIN + t * (MAX - MIN) + jitter)));
}
