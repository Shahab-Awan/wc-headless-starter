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

export function buildBogoBundleRows(
	regularMinor: number,
	presets: BogoBundlePreset[] = DEFAULT_PRESETS
): BundleDisplayRow[] {
	if (regularMinor <= 0) return [];

	return presets
		.filter((preset) => preset.paid_qty >= 1)
		.map((preset) => {
			const paid = preset.paid_qty;
			const free = preset.free_qty !== undefined ? preset.free_qty : paid;
			const safeFree = Math.max(0, free);
			const total = paid + safeFree;
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
				title:
					safeFree > 0
						? `Buy ${paid} Get ${safeFree} Free`
						: paid === 1
							? 'Buy 1'
							: `Buy ${paid}`,
				compare_line_total: compareTotal,
			};
		});
}

export function enrichTierRows(
	tiers: WchsCroTierRow[],
	regularMinor: number,
	presets: BogoBundlePreset[] = DEFAULT_PRESETS
): BundleDisplayRow[] {
	return tiers.map((tier, i) => {
		const preset = presets[i];
		const paid =
			preset?.paid_qty ??
			(regularMinor > 0 ? Math.round(tier.line_total_at_min_qty / regularMinor) : 1);
		const free =
			preset?.free_qty !== undefined ? preset.free_qty : preset ? paid : Math.max(0, tier.min_qty - paid);
		const safeFree = Math.max(0, free);
		const total = paid + safeFree;
		const compare =
			regularMinor > 0 ? total * regularMinor : tier.min_qty * regularMinor;

		return {
			...tier,
			paid_qty: paid,
			flag: preset?.flag ?? (i === 1 ? 'MOST POPULAR' : i === 2 ? 'BEST VALUE' : ''),
			title:
				safeFree > 0
					? `Buy ${paid} Get ${safeFree} Free`
					: paid === 1
						? 'Buy 1'
						: `Buy ${paid}`,
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
	const presets = bogo?.presets?.length ? bogo.presets : DEFAULT_PRESETS;

	if (apiTiers.length > 0) {
		return enrichTierRows(apiTiers, regularMinor, presets);
	}
	if (!enabled || regularMinor <= 0) return [];
	return buildBogoBundleRows(regularMinor, presets);
}
