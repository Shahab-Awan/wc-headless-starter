export type BrandComparisonRow = {
	feature: string;
	brand?: string;
	competitor?: string;
	competitor_2?: string;
};

export const DEFAULT_WHY_ALYVE_COMPARE_ROWS: BrandComparisonRow[] = [
	{
		feature: '🧬 Endotoxin Testing',
		brand: 'LAL tested every batch, pharma-grade low',
		competitor: 'Skipped entirely',
		competitor_2: 'Unknown, never tested',
	},
	{
		feature: '🧪 Purity',
		brand: '99%+ HPLC-verified at manufacture',
		competitor: 'Estimated, not proven',
		competitor_2: 'Label claim only',
	},
	{
		feature: '📄 Third-Party Verification',
		brand: "Accredited labs, COA per batch, test it yourself and we'll reimburse",
		competitor: 'In-house claims only',
		competitor_2: 'Redacted or none',
	},
	{
		feature: '🚚 Shipping',
		brand: 'Same-day, tracked, discreet, 2–3 days',
		competitor: 'Slow, sometimes tracked',
		competitor_2: '2–6 weeks, customs risk',
	},
];

export function comparisonRowsHaveText(rows: BrandComparisonRow[]): boolean {
	return rows.some(
		(row) =>
			Boolean(row.brand?.trim()) ||
			Boolean(row.competitor?.trim()) ||
			Boolean(row.competitor_2?.trim())
	);
}
