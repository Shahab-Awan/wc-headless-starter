/**
 * Cart line bundle badge — exact tier match only (mirrors headless-cro-extension.php).
 */
import { repairBundlePresets, type BogoBundleConfig } from '$lib/pdp/bogo-bundles';

function bundleTitle(paid: number, free: number): string {
	const safeFree = Math.max(0, free);
	if (safeFree > 0) return `Buy ${paid} Get ${safeFree} Free`;
	if (paid === 1) return 'Buy 1';
	return `Buy ${paid}`;
}

/** Short label when qty exactly matches a bundle tier; empty between tiers. */
export function resolveCartBundleLabel(
	qty: number,
	tierThresholds: number[],
	bogo?: BogoBundleConfig | null
): string {
	if (bogo?.enabled === false || !tierThresholds.length) return '';
	if (!tierThresholds.includes(qty)) return '';

	const presets = repairBundlePresets(bogo?.presets?.length ? bogo.presets : []);
	for (const preset of presets) {
		const paid = preset.paid_qty;
		if (paid < 1) continue;
		const free = preset.free_qty !== undefined ? preset.free_qty : paid;
		const total = paid + Math.max(0, free);
		if (total !== qty) continue;
		if (free < 1) return '';
		return bundleTitle(paid, free);
	}
	return '';
}
