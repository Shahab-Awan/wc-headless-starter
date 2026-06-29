import type { ReviewsListicleModuleConfig, ReviewsListicleItem } from '$lib/config.svelte';

export const WHY_ALYVE_REVIEWS_DEFAULTS: ReviewsListicleModuleConfig = {
	headline: 'What researchers say after ordering',
	proof_headline: 'Trusted by 10K+ Researchers Worldwide',
	proof_subheadline: 'Real labs. Real protocols. Trusted for consistency.',
	items: [
		{
			quote:
				'COAs matched the batch numbers on our BPC-157 vials. Documentation was clear and easy to file for our lab records.',
			name: 'Vincent R.',
			product: 'BPC-157 5mg',
			rating: 5,
		},
		{
			quote:
				'TB-500 batch purity matched the published COA exactly. Reconstitution notes were clear and shipment arrived tracked within two days.',
			name: 'James T.',
			product: 'TB-500 5mg',
			rating: 5,
		},
		{
			quote:
				'Tirzepatide purity report was posted before checkout — exactly what our QC process requires.',
			name: 'Justin F.',
			product: 'Tirzepatide 10mg',
			rating: 5,
		},
		{
			quote:
				'Consistent Retatrutide quality across reorders — no surprises between batches. Support answered technical questions the same day.',
			name: 'Carlos B.',
			product: 'Retatrutide 5mg',
			rating: 5,
		},
	],
	proof_items: [
		{
			title: 'Purity and documentation matched perfectly',
			quote:
				'Batch number, vial presentation, and stated specifications were all consistent. This level of QC is what keeps my research on track.',
			name: 'K.S.',
			location: 'Sydney',
			rating: 5,
		},
		{
			title: 'Complete Transparency From Packaging to Documentation',
			quote:
				'Material arrived well-documented with clear batch identifiers and intact packaging. Everything matched the specification sheet precisely, which gave me full confidence in proceeding with my protocol.',
			name: 'A.S.',
			location: 'Chicago, IL',
			rating: 5,
		},
		{
			title: 'Consistency and COA Alignment Were Flawless',
			quote:
				'Consistency across every vial was exactly as expected. Labeling, batch traceability, and purity data aligned with the COA without any discrepancies. That reliability is essential for reproducible results.',
			name: 'J.R.',
			location: 'San Diego, CA',
			rating: 5,
		},
		{
			title: 'Accurate Labeling and Reliable Sample Integrity',
			quote:
				'The documentation clarity and sample integrity were excellent. Every detail from concentration to labeling was consistent with what was promised, making the entire process seamless.',
			name: 'L.M.',
			location: 'Miami, FL',
			rating: 5,
		},
		{
			title: 'Strict Quality Control Reflected in Every Detail',
			quote:
				'What stood out most was the traceability and clean presentation of each batch. COA alignment was exact, and the overall quality control standards are clearly very strict.',
			name: 'N.K.',
			location: 'New York, NY',
			rating: 5,
		},
	],
};

const LEGACY_HEADLINE = 'Amazing Reviews with a 4.9 Rating';

function backfillProduct(item: ReviewsListicleItem, defaults: ReviewsListicleItem[]): ReviewsListicleItem {
	if ((item.product ?? '').trim()) return item;
	const byName = defaults.find((d) => d.name === item.name);
	if (byName?.product) return { ...item, product: byName.product };
	const byQuote = defaults.find((d) => d.quote === item.quote);
	if (byQuote?.product) return { ...item, product: byQuote.product };
	return item;
}

function hasProductReviews(items: ReviewsListicleItem[] | undefined): boolean {
	return (items ?? []).some(
		(it) => Boolean((it.product ?? '').trim() && (it.quote ?? '').trim() && (it.name ?? '').trim()),
	);
}

export function mergeWhyAlyveReviewsConfig(
	config: ReviewsListicleModuleConfig | undefined | null,
): ReviewsListicleModuleConfig {
	const base = config ?? {};
	const defaults = WHY_ALYVE_REVIEWS_DEFAULTS;

	const rawItems = (base.items ?? []).length ? base.items! : defaults.items!;
	const items = hasProductReviews(rawItems)
		? rawItems.map((item) => backfillProduct(item, defaults.items ?? []))
		: defaults.items;

	const proofItems =
		(base.proof_items ?? []).filter(
			(it) => (it.title ?? '').trim() && (it.quote ?? '').trim() && (it.name ?? '').trim(),
		).length > 0
			? base.proof_items
			: defaults.proof_items;

	const headline = (base.headline ?? '').trim();
	const mergedHeadline =
		headline && headline !== LEGACY_HEADLINE ? headline : defaults.headline;

	return {
		...defaults,
		...base,
		headline: mergedHeadline,
		proof_headline: (base.proof_headline ?? '').trim() || defaults.proof_headline,
		proof_subheadline: (base.proof_subheadline ?? '').trim() || defaults.proof_subheadline,
		items,
		proof_items: proofItems,
	};
}
