/**
 * BOGO bundle quantity snapping for slide-cart steppers.
 * Mirrors wchs_cro_resolve_cart_qty_change() in headless-cro-extension.php.
 */
export function resolveCartLineQty(thresholds: number[], current: number, proposed: number): number {
	const tiers = [...thresholds].filter((t) => t >= 1).sort((a, b) => a - b);
	if (!tiers.length) return Math.max(1, proposed);

	const next = Math.max(1, proposed);
	if (next > current) {
		const tierAtCurrent = tiers.filter((t) => t <= current).pop();
		const nextTier = tiers.find((t) => t > current);
		if (
			tierAtCurrent !== undefined &&
			current === tierAtCurrent &&
			nextTier &&
			next > current &&
			next < nextTier
		) {
			return nextTier;
		}
		return next;
	}

	if (next < current && tiers.includes(current)) {
		const lower = [...tiers].reverse().find((t) => t < current);
		if (lower !== undefined && next < current && next > lower) {
			return lower;
		}
	}

	return next;
}
