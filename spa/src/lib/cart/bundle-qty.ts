/** Clamp cart line qty to Store API limits — steppers use ±1; tier pricing applies at checkout. */
export function clampCartLineQty(
	proposed: number,
	limits?: { minimum?: number; maximum?: number }
): number {
	const min = Math.max(1, limits?.minimum ?? 1);
	let qty = Math.max(min, proposed);
	const max = limits?.maximum;
	if (typeof max === 'number' && max > 0) {
		qty = Math.min(max, qty);
	}
	return qty;
}

/** @deprecated Use clampCartLineQty — tier thresholds no longer snap stepper qty. */
export function resolveCartLineQty(
	_thresholds: number[],
	_current: number,
	proposed: number,
	limits?: { minimum?: number; maximum?: number }
): number {
	return clampCartLineQty(proposed, limits);
}
