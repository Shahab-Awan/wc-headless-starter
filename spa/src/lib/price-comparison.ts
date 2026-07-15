import type {
	PriceComparisonCompetitor,
	PriceComparisonModuleConfig,
	PriceComparisonSheet,
} from '$lib/config.svelte';

export const DEFAULT_PRICE_COMPARISON_SHEETS: PriceComparisonSheet[] = [
	{
		tab_label: 'GLP Reta',
		product_label: 'GLP Reta',
		variation_label: '10 MG',
		brand_price: '89.00',
		brand_tags: 'IN STOCK · SHIPS FAST · COA ON FILE',
		competitors: [
			{ letter: 'A', name: 'Modern Aminos', price: '109.00' },
			{ letter: 'B', name: 'Soma Chems', price: '119.00' },
			{ letter: 'C', name: 'Onyx Research', price: '125.00' },
			{ letter: 'D', name: 'Ascension Peptides', price: '135.00' },
		],
	},
	{
		tab_label: 'BPC-157',
		product_label: 'BPC-157',
		variation_label: '5MG',
		brand_price: '28.00',
		brand_tags: 'IN STOCK · SHIPS FAST · COA ON FILE',
		competitors: [
			{ letter: 'A', name: 'Modern Aminos', price: '34.00' },
			{ letter: 'B', name: 'Soma Chems', price: '39.99' },
			{ letter: 'C', name: 'Onyx Research', price: '45.00' },
			{ letter: 'D', name: 'Ascension Peptides', price: '55.00' },
		],
	},
];

function normCompetitors(rows: PriceComparisonCompetitor[] | undefined) {
	return (rows ?? [])
		.map((row, i) => ({
			letter: (row.letter?.trim() || String.fromCharCode(65 + i)).toUpperCase(),
			name: row.name?.trim() || '',
			price: (row.price ?? '').trim(),
		}))
		.filter((row) => row.name !== '' && row.price !== '');
}

function normSheet(raw: Partial<PriceComparisonSheet>, fallbackTab = 'Product'): PriceComparisonSheet | null {
	const product_label = (raw.product_label ?? '').trim();
	const variation_label = (raw.variation_label ?? '').trim();
	const brand_price = (raw.brand_price ?? '').trim();
	const competitors = normCompetitors(raw.competitors);
	const tab_label = (raw.tab_label ?? '').trim() || product_label.split(/\s+/).slice(0, 2).join(' ') || fallbackTab;
	const brand_tags = (raw.brand_tags ?? '').trim();

	if (!product_label && !brand_price && !competitors.length) return null;

	return {
		tab_label,
		product_label,
		variation_label,
		brand_price,
		brand_tags,
		competitors,
	};
}

/** Resolve product tabs from `sheets` or legacy flat fields. */
export function normalizePriceComparisonSheets(
	config: PriceComparisonModuleConfig
): PriceComparisonSheet[] {
	if (Array.isArray(config.sheets) && config.sheets.length > 0) {
		const out = config.sheets
			.map((sheet, i) => normSheet(sheet, `Product ${i + 1}`))
			.filter((sheet): sheet is PriceComparisonSheet => sheet !== null);
		if (out.length) return out;
	}

	const legacy = normSheet({
		tab_label: config.product_label?.trim() || 'Product',
		product_label: config.product_label ?? '',
		variation_label: '',
		brand_price: config.brand_price ?? '',
		brand_tags: config.brand_tags ?? '',
		competitors: config.competitors ?? [],
	});
	if (legacy) return [legacy];

	return DEFAULT_PRICE_COMPARISON_SHEETS;
}
