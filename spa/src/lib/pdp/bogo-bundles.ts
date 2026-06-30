import type { WchsCroTierRow } from '$lib/wc/products';

export const VOLUME_DISCOUNT_CAP_QTY = 15;
export const VOLUME_DISCOUNT_MAX_PCT = 40;

export type BogoBundlePreset = {
	paid_qty: number;
	free_qty?: number;
	/** Volume-discount mode: percent off regular at this vial count. */
	discount_pct?: number;
	flag?: string;
	/** Hidden from PDP bundle chips; still applies in cart pricing (e.g. 15 @ 40%). */
	pdp_hidden?: boolean;
};

export type BogoBundleConfig = {
	enabled?: boolean;
	savings_pct?: number;
	presets?: BogoBundlePreset[];
};

export type BundleDisplayRow = WchsCroTierRow & {
	paid_qty: number;
	flag: string;
	title: string;
	compare_line_total: number;
};

export const DEFAULT_VOLUME_PRESETS: BogoBundlePreset[] = [
	{ paid_qty: 1, discount_pct: 0, flag: '' },
	{ paid_qty: 3, discount_pct: 15, flag: 'POPULAR' },
	{ paid_qty: 5, discount_pct: 23, flag: 'BEST VALUE' },
	{ paid_qty: 6, discount_pct: 25, flag: '', pdp_hidden: true },
	{ paid_qty: 7, discount_pct: 27, flag: '', pdp_hidden: true },
	{ paid_qty: 8, discount_pct: 29, flag: '', pdp_hidden: true },
	{ paid_qty: 9, discount_pct: 30, flag: '', pdp_hidden: true },
	{ paid_qty: 10, discount_pct: 31, flag: 'BULK' },
	{ paid_qty: 11, discount_pct: 33, flag: '', pdp_hidden: true },
	{ paid_qty: 12, discount_pct: 35, flag: '', pdp_hidden: true },
	{ paid_qty: 13, discount_pct: 37, flag: '', pdp_hidden: true },
	{ paid_qty: 14, discount_pct: 38, flag: '', pdp_hidden: true },
	{ paid_qty: 15, discount_pct: 40, flag: '', pdp_hidden: true },
];

const LEGACY_BOGO_PRESETS: BogoBundlePreset[] = [
	{ paid_qty: 1, free_qty: 0, flag: '' },
	{ paid_qty: 2, free_qty: 1, flag: 'MOST POPULAR' },
	{ paid_qty: 3, free_qty: 2, flag: 'BEST VALUE' },
];

export function presetUsesVolumeDiscount(preset: BogoBundlePreset): boolean {
	return typeof preset.discount_pct === 'number';
}

export function bogoUsesVolumePresets(presets: BogoBundlePreset[]): boolean {
	return repairBundlePresets(presets).some(presetUsesVolumeDiscount);
}

function clampDiscountPct(pct: number): number {
	return Math.min(VOLUME_DISCOUNT_MAX_PCT, Math.max(0, pct));
}

function bundleVialCount(paid: number, free: number): number {
	return paid + Math.max(0, free);
}

function volumeTitle(minQty: number): string {
	return minQty === 1 ? '1 Vial' : `${minQty} Vials`;
}

function legacyBundleTitle(paid: number, free: number): string {
	const safeFree = Math.max(0, free);
	if (safeFree > 0) return `Buy ${paid} Get ${safeFree} Free`;
	if (paid === 1) return 'Buy 1';
	return `Buy ${paid}`;
}

function rowFromVolumePreset(regularMinor: number, preset: BogoBundlePreset): BundleDisplayRow {
	const minQty = preset.paid_qty;
	const pct = clampDiscountPct(preset.discount_pct ?? 0);
	const unitMinor = Math.round(regularMinor * (1 - pct / 100));
	const lineTotal = unitMinor * minQty;
	const compareTotal = regularMinor * minQty;

	return {
		min_qty: minQty,
		unit_price: unitMinor,
		savings_per_unit: regularMinor - unitMinor,
		savings_pct: Math.round(pct * 10) / 10,
		line_total_at_min_qty: lineTotal,
		paid_qty: minQty,
		flag: preset.flag ?? '',
		title: volumeTitle(minQty),
		compare_line_total: compareTotal,
	};
}

function rowFromLegacyPreset(regularMinor: number, preset: BogoBundlePreset): BundleDisplayRow {
	const paid = preset.paid_qty;
	const free = preset.free_qty !== undefined ? preset.free_qty : paid;
	const safeFree = Math.max(0, free);
	const total = bundleVialCount(paid, safeFree);
	const pct = safeFree > 0 && total > 0 ? (100 * safeFree) / total : 0;
	const unitMinor =
		safeFree > 0 ? Math.round((regularMinor * paid) / total) : regularMinor;
	const lineTotal = paid * regularMinor;
	const compareTotal = total * regularMinor;

	return {
		min_qty: total,
		unit_price: unitMinor,
		savings_per_unit: regularMinor - unitMinor,
		savings_pct: Math.round(pct * 10) / 10,
		line_total_at_min_qty: lineTotal,
		paid_qty: paid,
		flag: preset.flag ?? '',
		title: legacyBundleTitle(paid, safeFree),
		compare_line_total: compareTotal,
	};
}

function normalizePreset(preset: BogoBundlePreset): BogoBundlePreset | null {
	if (preset.paid_qty < 1) return null;
	if (presetUsesVolumeDiscount(preset)) {
		return {
			paid_qty: preset.paid_qty,
			discount_pct: clampDiscountPct(preset.discount_pct ?? 0),
			flag: preset.flag ?? '',
			pdp_hidden: preset.pdp_hidden === true,
		};
	}
	return {
		paid_qty: preset.paid_qty,
		free_qty: preset.free_qty ?? 0,
		flag: preset.flag ?? '',
	};
}

/** Upgrade legacy BOGO presets to volume tiers; keep custom volume presets when present. */
export function repairBundlePresets(presets: BogoBundlePreset[]): BogoBundlePreset[] {
	if (!presets.length) return DEFAULT_VOLUME_PRESETS;

	const normalized = presets
		.map(normalizePreset)
		.filter((p): p is BogoBundlePreset => p !== null)
		.sort((a, b) => a.paid_qty - b.paid_qty);

	if (!normalized.length) return DEFAULT_VOLUME_PRESETS;

	if (normalized.some(presetUsesVolumeDiscount)) {
		return mergeCanonicalVolumeTiers(normalized);
	}

	const hasBundleFree = normalized.some((p) => (p.free_qty ?? 0) > 0);
	if (!hasBundleFree && normalized.length >= 3) {
		const paidSeq = normalized.slice(0, 3).map((p) => p.paid_qty);
		if (paidSeq[0] === 1 && paidSeq[1] === 2 && paidSeq[2] === 3) {
			return DEFAULT_VOLUME_PRESETS;
		}
	}

	if (hasBundleFree && normalized.length >= 3) {
		const paidSeq = normalized.slice(0, 3).map((p) => p.paid_qty);
		const freeSeq = normalized.slice(0, 3).map((p) => p.free_qty ?? 0);
		if (
			paidSeq[0] === 1 &&
			paidSeq[1] === 2 &&
			paidSeq[2] === 3 &&
			freeSeq[0] === 0 &&
			freeSeq[1] === 1 &&
			freeSeq[2] === 2
		) {
			return DEFAULT_VOLUME_PRESETS;
		}
	}

	return normalized.length ? normalized : DEFAULT_VOLUME_PRESETS;
}

/** Ensure cart/pricing tiers 6–14 exist; keep admin flags on visible tiers. */
function mergeCanonicalVolumeTiers(presets: BogoBundlePreset[]): BogoBundlePreset[] {
	const byQty = new Map<number, BogoBundlePreset>();
	for (const preset of presets) {
		byQty.set(preset.paid_qty, preset);
	}
	for (const canonical of DEFAULT_VOLUME_PRESETS) {
		const existing = byQty.get(canonical.paid_qty);
		if (!existing) {
			byQty.set(canonical.paid_qty, { ...canonical });
			continue;
		}
		byQty.set(canonical.paid_qty, {
			...existing,
			discount_pct: canonical.discount_pct,
			pdp_hidden: existing.pdp_hidden ?? canonical.pdp_hidden,
		});
	}
	return [...byQty.values()].sort((a, b) => a.paid_qty - b.paid_qty);
}

export function unitPriceAtLineQty(
	lineQty: number,
	regularMinor: number,
	presets: BogoBundlePreset[]
): number {
	if (lineQty < 1 || regularMinor <= 0) return regularMinor;
	const repaired = repairBundlePresets(presets);
	let unit = regularMinor;
	for (const preset of repaired) {
		if (!presetUsesVolumeDiscount(preset)) continue;
		if (lineQty < preset.paid_qty) continue;
		const pct = clampDiscountPct(preset.discount_pct ?? 0);
		unit = Math.round(regularMinor * (1 - pct / 100));
	}
	return unit;
}

export function buildBogoBundleRows(
	regularMinor: number,
	presets: BogoBundlePreset[] = DEFAULT_VOLUME_PRESETS
): BundleDisplayRow[] {
	if (regularMinor <= 0) return [];

	return repairBundlePresets(presets)
		.filter((preset) => preset.paid_qty >= 1 && !preset.pdp_hidden)
		.map((preset) =>
			presetUsesVolumeDiscount(preset)
				? rowFromVolumePreset(regularMinor, preset)
				: rowFromLegacyPreset(regularMinor, preset)
		);
}

export function defaultBundleMinQty(rows: BundleDisplayRow[]): number | null {
	if (!rows.length) return null;
	return rows.find((r) => r.min_qty === 5)?.min_qty ?? rows[0]?.min_qty ?? null;
}

/** Native WC tier rows on a product (not site-wide BOGO). */
export function enrichTierRows(
	tiers: WchsCroTierRow[],
	regularMinor: number,
	presets: BogoBundlePreset[] = DEFAULT_VOLUME_PRESETS
): BundleDisplayRow[] {
	const repaired = repairBundlePresets(presets);

	return tiers.map((tier, i) => {
		const preset = repaired[i];
		if (preset && presetUsesVolumeDiscount(preset)) {
			return rowFromVolumePreset(regularMinor, preset);
		}
		const paid =
			preset?.paid_qty ??
			(regularMinor > 0 ? Math.round(tier.line_total_at_min_qty / regularMinor) : 1);
		const free =
			preset?.free_qty !== undefined
				? preset.free_qty
				: preset
					? paid
					: Math.max(0, tier.min_qty - paid);
		const safeFree = Math.max(0, free);
		const total = bundleVialCount(paid, safeFree);
		const compare =
			regularMinor > 0 ? total * regularMinor : tier.min_qty * regularMinor;

		return {
			...tier,
			paid_qty: paid,
			flag: preset?.flag ?? (i === 1 ? 'MOST POPULAR' : i === 2 ? 'BEST VALUE' : ''),
			title: legacyBundleTitle(paid, safeFree),
			compare_line_total: compare,
		};
	});
}

export function resolveBundleRows(
	apiTiers: WchsCroTierRow[],
	regularMinor: number,
	bogo?: BogoBundleConfig | null
): BundleDisplayRow[] {
	const enabled = bogo?.enabled !== false;
	const presets = repairBundlePresets(
		bogo?.presets?.length ? bogo.presets : DEFAULT_VOLUME_PRESETS
	);

	if (!enabled || regularMinor <= 0) return [];

	return buildBogoBundleRows(regularMinor, presets);
}

export function unitPriceAtTier(
	tierQty: number,
	regularMinor: number,
	presets: BogoBundlePreset[]
): number {
	const repaired = repairBundlePresets(presets);
	for (const preset of repaired) {
		if (presetUsesVolumeDiscount(preset)) {
			if (preset.paid_qty !== tierQty) continue;
			return Math.round(regularMinor * (1 - clampDiscountPct(preset.discount_pct ?? 0) / 100));
		}
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
