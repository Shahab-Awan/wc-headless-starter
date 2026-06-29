/**
 * Client-side bundle line pricing for optimistic cart qty updates.
 * Mirrors wchs_cro_line_total_minor_for_qty() in headless-cro-extension.php.
 */
import {
	repairBundlePresets,
	VOLUME_DISCOUNT_CAP_QTY,
	type BogoBundleConfig,
	unitPriceAtTier,
	bogoUsesVolumePresets,
} from '$lib/pdp/bogo-bundles';
import type { WchsCroCartItem } from '$lib/wc/cart.svelte';
import { resolveCartBundleLabel } from './bundle-label';

function activeBundleAnchor(qty: number, thresholds: number[]): number {
	let anchor = 0;
	for (const t of thresholds) {
		if (qty >= t) anchor = t;
	}
	return anchor;
}

function estimateLineTotalUncapped(
	qty: number,
	regularMinor: number,
	thresholds: number[],
	presets: ReturnType<typeof repairBundlePresets>
): number {
	if (qty < 1 || regularMinor <= 0) return 0;
	if (!thresholds.length) return regularMinor * qty;

	const anchor = activeBundleAnchor(qty, thresholds);
	if (anchor < 1) return regularMinor * qty;

	const bundleUnit = unitPriceAtTier(anchor, regularMinor, presets);
	const bundleLine = bundleUnit * anchor;
	const overage = Math.max(0, qty - anchor);
	return bundleLine + overage * regularMinor;
}

export function estimateLineTotalMinor(
	qty: number,
	regularMinor: number,
	thresholds: number[],
	bogo?: BogoBundleConfig | null
): number {
	if (qty < 1 || regularMinor <= 0) return 0;

	const presets = repairBundlePresets(bogo?.presets?.length ? bogo.presets : []);
	if (bogoUsesVolumePresets(presets) && qty > VOLUME_DISCOUNT_CAP_QTY) {
		return (
			estimateLineTotalUncapped(VOLUME_DISCOUNT_CAP_QTY, regularMinor, thresholds, presets) +
			(qty - VOLUME_DISCOUNT_CAP_QTY) * regularMinor
		);
	}

	return estimateLineTotalUncapped(qty, regularMinor, thresholds, presets);
}

/** Optimistic wchs_cro fields after a qty change (before server round-trip). */
export function estimateCartLineCro(
	qty: number,
	cro: WchsCroCartItem,
	bogo?: BogoBundleConfig | null
): WchsCroCartItem {
	const regular = cro.regular_unit_price;
	const thresholds = cro.tier_qty_thresholds ?? [];
	const lineTotal = estimateLineTotalMinor(qty, regular, thresholds, bogo);
	const effectiveUnit = qty > 0 ? Math.round(lineTotal / qty) : regular;
	const compareLine = regular * qty;
	const savingsLine = Math.max(0, compareLine - lineTotal);
	const savingsPerUnit = Math.max(0, regular - effectiveUnit);
	const savingsPct = regular > 0 ? Math.round((savingsPerUnit / regular) * 1000) / 10 : 0;

	return {
		...cro,
		effective_unit_price: effectiveUnit,
		line_total_minor: lineTotal,
		compare_line_minor: compareLine,
		savings_per_unit: savingsPerUnit,
		savings_line_total: savingsLine,
		savings_pct: savingsPct,
		bundle_label: resolveCartBundleLabel(qty, thresholds, bogo),
		active_bundle_min_qty: thresholds.includes(qty) ? qty : activeBundleAnchor(qty, thresholds),
	};
}
