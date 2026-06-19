/**
 * Client-side BOGO line pricing for optimistic cart qty updates.
 * Mirrors wchs_cro_line_total_minor_for_qty() in headless-cro-extension.php.
 */
import { repairBundlePresets, type BogoBundleConfig } from '$lib/pdp/bogo-bundles';
import type { WchsCroCartItem } from '$lib/wc/cart.svelte';
import { resolveCartBundleLabel } from './bundle-label';

function activeBundleAnchor(qty: number, thresholds: number[]): number {
	let anchor = 0;
	for (const t of thresholds) {
		if (qty >= t) anchor = t;
	}
	return anchor;
}

function unitPriceAtTier(
	tierQty: number,
	regularMinor: number,
	bogo?: BogoBundleConfig | null
): number {
	if (tierQty <= 1) return regularMinor;
	const presets = repairBundlePresets(bogo?.presets?.length ? bogo.presets : []);
	for (const preset of presets) {
		const paid = preset.paid_qty;
		if (paid < 1) continue;
		const free = preset.free_qty !== undefined ? preset.free_qty : paid;
		const total = paid + Math.max(0, free);
		if (total !== tierQty) continue;
		if (free < 1) return regularMinor;
		return Math.round((regularMinor * paid) / total);
	}
	return regularMinor;
}

export function estimateLineTotalMinor(
	qty: number,
	regularMinor: number,
	thresholds: number[],
	bogo?: BogoBundleConfig | null
): number {
	if (qty < 1 || regularMinor <= 0) return 0;
	if (!thresholds.length) return regularMinor * qty;

	const anchor = activeBundleAnchor(qty, thresholds);
	if (anchor < 1) return regularMinor * qty;

	const bundleUnit = unitPriceAtTier(anchor, regularMinor, bogo);
	const bundleLine = bundleUnit * anchor;
	const overage = Math.max(0, qty - anchor);
	return bundleLine + overage * regularMinor;
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
		active_bundle_min_qty: thresholds.includes(qty) ? qty : activeBundleAnchor(qty, thresholds)
	};
}
