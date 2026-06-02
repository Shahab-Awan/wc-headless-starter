import type { WchsCroTierRow } from '$lib/wc/products';

export type BogoBundlePreset = {
	paid_qty: number;
	free_qty?: number;
	flag?: string;
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

const DEFAULT_PRESETS: BogoBundlePreset[] = [
	{ paid_qty: 1, free_qty: 0, flag: '' },
	{ paid_qty: 2, free_qty: 0, flag: 'MOST POPULAR' },
	{ paid_qty: 3, free_qty: 0, flag: 'BEST VALUE' },
];

export const VOLUME_DISCOUNT_MAX_QTY = 10;

/** Site volume schedule (qty → % off). Matches headless-cro-extension.php. */
export function volumeDiscountSchedule(maxPct = 50): Record<number, number> {
	const cap = Math.min(50, Math.max(0, maxPct));
	const schedule: Record<number, number> = { 1: 0, 2: 15, 3: 30 };
	let pct = 35;
	for (let q = 4; q <= VOLUME_DISCOUNT_MAX_QTY; q++) {
		schedule[q] = Math.min(cap, pct);
		if (pct < cap) pct += 5;
	}
	return schedule;
}

export function volumeDiscountPct(qty: number, maxPct = 50): number {
	if (qty < 1) return 0;
	const schedule = volumeDiscountSchedule(maxPct);
	if (qty > VOLUME_DISCOUNT_MAX_QTY) return Math.min(50, maxPct);
	return schedule[qty] ?? 0;
}

/** Line total in minor units; caps discounted vials at maxQty (extras full price). */
export function volumeLineTotalMinor(
	regularMinor: number,
	qty: number,
	maxPct = 50,
	maxQty = VOLUME_DISCOUNT_MAX_QTY
): number {
	if (regularMinor <= 0 || qty < 1) return 0;
	const cap = Math.min(50, Math.max(0, maxPct));

	if (qty <= maxQty) {
		const pct = volumeDiscountPct(qty, cap);
		const unit = Math.round(regularMinor * (1 - pct / 100));
		return unit * qty;
	}

	const discUnit = Math.round(regularMinor * (1 - cap / 100));
	return maxQty * discUnit + (qty - maxQty) * regularMinor;
}

function bundleTitle(paid: number, pct: number): string {
	if (paid <= 1 || pct <= 0) return 'Buy 1';
	const pctLabel = Number.isInteger(pct) ? `${pct}%` : `${pct.toFixed(1)}%`;
	return `Buy ${paid} · ${pctLabel} off`;
}

export function buildBogoBundleRows(
	regularMinor: number,
	presets: BogoBundlePreset[] = DEFAULT_PRESETS,
	maxPct = 50
): BundleDisplayRow[] {
	if (regularMinor <= 0) return [];

	return presets
		.filter((preset) => preset.paid_qty >= 1)
		.map((preset) => {
			const paid = preset.paid_qty;
			const pct = volumeDiscountPct(paid, maxPct);
			const unitMinor = Math.round(regularMinor * (1 - pct / 100));
			const lineTotal = volumeLineTotalMinor(regularMinor, paid, maxPct);

			return {
				min_qty: paid,
				unit_price: unitMinor,
				savings_per_unit: regularMinor - unitMinor,
				savings_pct: Math.round(pct * 10) / 10,
				line_total_at_min_qty: lineTotal,
				paid_qty: paid,
				flag: preset.flag ?? '',
				title: bundleTitle(paid, pct),
				compare_line_total: paid * regularMinor,
			};
		});
}

export function enrichTierRows(
	tiers: WchsCroTierRow[],
	regularMinor: number,
	presets: BogoBundlePreset[] = DEFAULT_PRESETS,
	maxPct = 50
): BundleDisplayRow[] {
	const presetQtys = new Set(presets.map((p) => p.paid_qty));
	const tierByQty = new Map(tiers.map((t) => [t.min_qty, t]));

	return presets
		.filter((p) => p.paid_qty >= 1)
		.map((preset, i) => {
			const paid = preset.paid_qty;
			const tier = tierByQty.get(paid);
			const pct = tier?.savings_pct ?? volumeDiscountPct(paid, maxPct);
			const unitMinor =
				tier?.unit_price ??
				Math.round(regularMinor * (1 - pct / 100));
			const lineTotal =
				tier?.line_total_at_min_qty ??
				volumeLineTotalMinor(regularMinor, paid, maxPct);

			return {
				min_qty: paid,
				unit_price: unitMinor,
				savings_per_unit: tier?.savings_per_unit ?? regularMinor - unitMinor,
				savings_pct: tier?.savings_pct ?? pct,
				line_total_at_min_qty: lineTotal,
				paid_qty: paid,
				flag: preset.flag ?? (i === 1 ? 'MOST POPULAR' : i === 2 ? 'BEST VALUE' : ''),
				title: bundleTitle(paid, pct),
				compare_line_total: paid * regularMinor,
			};
		});
}

export function resolveBundleRows(
	apiTiers: WchsCroTierRow[],
	regularMinor: number,
	bogo?: BogoBundleConfig | null
): BundleDisplayRow[] {
	const enabled = bogo?.enabled !== false;
	const presets = bogo?.presets?.length ? bogo.presets : DEFAULT_PRESETS;
	const maxPct = bogo?.savings_pct ?? 50;

	if (apiTiers.length > 0) {
		return enrichTierRows(apiTiers, regularMinor, presets, maxPct);
	}
	if (!enabled || regularMinor <= 0) return [];
	return buildBogoBundleRows(regularMinor, presets, maxPct);
}
