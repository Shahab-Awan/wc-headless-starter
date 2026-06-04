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
	{ paid_qty: 2, free_qty: 1, flag: 'MOST POPULAR' },
	{ paid_qty: 3, free_qty: 2, flag: 'BEST VALUE' },
];

function bundleVialCount(paid: number, free: number): number {
	return paid + Math.max(0, free);
}

function bundleTitle(paid: number, free: number): string {
	const safeFree = Math.max(0, free);
	if (safeFree > 0) return `Buy ${paid} Get ${safeFree} Free`;
	if (paid === 1) return 'Buy 1';
	return `Buy ${paid}`;
}

function rowFromPreset(regularMinor: number, preset: BogoBundlePreset): BundleDisplayRow {
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
		title: bundleTitle(paid, safeFree),
		compare_line_total: compareTotal,
	};
}

/** Volume-% era stored paid 1/2/3 with free_qty 0 — upgrade to BOGO totals 1/3/5. */
export function repairBundlePresets(presets: BogoBundlePreset[]): BogoBundlePreset[] {
	if (!presets.length) return DEFAULT_PRESETS;

	const normalized = presets
		.filter((p) => p.paid_qty >= 1)
		.map((p) => ({
			paid_qty: p.paid_qty,
			free_qty: p.free_qty ?? 0,
			flag: p.flag ?? '',
		}))
		.sort((a, b) => a.paid_qty - b.paid_qty);

	const hasBundleFree = normalized.some((p) => (p.free_qty ?? 0) > 0);
	if (!hasBundleFree && normalized.length >= 3) {
		const paidSeq = normalized.slice(0, 3).map((p) => p.paid_qty);
		if (paidSeq[0] === 1 && paidSeq[1] === 2 && paidSeq[2] === 3) {
			return DEFAULT_PRESETS.map((row, i) => ({
				...row,
				flag: normalized[i]?.flag || row.flag,
			}));
		}
	}

	return normalized.length ? normalized : DEFAULT_PRESETS;
}

export function buildBogoBundleRows(
	regularMinor: number,
	presets: BogoBundlePreset[] = DEFAULT_PRESETS
): BundleDisplayRow[] {
	if (regularMinor <= 0) return [];

	return repairBundlePresets(presets)
		.filter((preset) => preset.paid_qty >= 1)
		.map((preset) => rowFromPreset(regularMinor, preset));
}

/** Native WC tier rows on a product (not site-wide BOGO). */
export function enrichTierRows(
	tiers: WchsCroTierRow[],
	regularMinor: number,
	presets: BogoBundlePreset[] = DEFAULT_PRESETS
): BundleDisplayRow[] {
	const repaired = repairBundlePresets(presets);

	return tiers.map((tier, i) => {
		const preset = repaired[i];
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
			title: bundleTitle(paid, safeFree),
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
		bogo?.presets?.length ? bogo.presets : DEFAULT_PRESETS
	);

	if (!enabled || regularMinor <= 0) return [];

	// Site BOGO always drives the three PDP chips when enabled.
	return buildBogoBundleRows(regularMinor, presets);
}
