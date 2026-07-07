/**
 * Cart line bundle badge — vial count for every qty; tier flags at offer breakpoints.
 */
import {
	presetUsesVolumeDiscount,
	repairBundlePresets,
	bogoUsesVolumePresets,
	type BogoBundleConfig,
} from '$lib/pdp/bogo-bundles';

export function formatVialCount(qty: number): string {
	if (qty < 1) return '';
	return qty === 1 ? '1 Vial' : `${qty} Vials`;
}

function formatTierBadge(flag: string, qty: number): string {
	const trimmed = flag.trim();
	if (!trimmed) return '';
	if (trimmed === 'BULK' || qty === 10) return '(bulk offer)';
	return `[${trimmed}]`;
}

function tierBadgeForQty(
	qty: number,
	presets: ReturnType<typeof repairBundlePresets>,
	volume: boolean
): string {
	for (const preset of presets) {
		if (volume) {
			if (!presetUsesVolumeDiscount(preset) || preset.paid_qty !== qty) continue;
		} else {
			if (presetUsesVolumeDiscount(preset)) continue;
			const paid = preset.paid_qty;
			const free = preset.free_qty !== undefined ? preset.free_qty : paid;
			const total = paid + Math.max(0, free);
			if (total !== qty) continue;
		}
		return formatTierBadge(preset.flag ?? '', qty);
	}
	return '';
}

/** Cart drawer label: always vial count; [POPULAR] / [BEST VALUE] / (bulk offer) on offer tiers. */
export function resolveCartBundleLabel(
	qty: number,
	tierThresholds: number[],
	bogo?: BogoBundleConfig | null
): string {
	if (qty < 1 || bogo?.enabled === false) return '';
	if (!tierThresholds.length) return '';

	const presets = repairBundlePresets(bogo?.presets?.length ? bogo.presets : []);
	const volume = bogoUsesVolumePresets(presets);
	const base = formatVialCount(qty);
	if (!base) return '';

	if (!tierThresholds.includes(qty)) return base;

	const badge = tierBadgeForQty(qty, presets, volume);
	return badge ? `${base} ${badge}` : base;
}
