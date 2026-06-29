import type { OrderHandlingModuleConfig, OrderHandlingStep } from '$lib/config.svelte';

export const WHY_ALYVE_PROCESS_DEFAULTS: OrderHandlingModuleConfig = {
	badge_text: '',
	headline: 'Order Process',
	subheadline: '',
	bg_color: '',
	steps: [
		{
			variant: 'verified',
			headline: 'Browse & Verify',
			description:
				'Browse the catalog. Every product has a downloadable COA. Verify purity before you buy.',
		},
		{
			variant: 'lab',
			headline: 'Order — Discount Auto-Applied',
			description:
				'Add to cart. Your discount applies automatically at checkout. No code needed.',
		},
		{
			variant: 'shipping',
			headline: 'Fast-Track Fulfillment',
			description:
				'Orders before 2PM EST ship same day. Tracked, discreet, 2-3 business days.',
		},
	],
	metrics_title: '',
	metrics: [],
};

const LEGACY_STEP_HEADLINES = new Set([
	'Verified Batches',
	'3rd Party Testing',
	'Ships Same Day',
	'24/7 Support',
]);

export function mergeWhyAlyveProcessConfig(
	config: OrderHandlingModuleConfig | undefined | null,
): OrderHandlingModuleConfig {
	const base: Partial<OrderHandlingModuleConfig> = config ?? {};
	const defaults = WHY_ALYVE_PROCESS_DEFAULTS;

	const steps = (base.steps ?? []).filter((step: OrderHandlingStep) =>
		(step.headline ?? '').trim(),
	);
	const hasWhyAlyveSteps = steps.some(
		(step) => (step.headline ?? '').trim() === 'Browse & Verify',
	);
	const isLegacy = steps.some((step) =>
		LEGACY_STEP_HEADLINES.has((step.headline ?? '').trim()),
	);

	return {
		...defaults,
		...base,
		badge_text: '',
		headline: (() => {
			const h = (base.headline ?? '').trim();
			if (h && h !== 'How Every Order Is Handled') return h;
			return defaults.headline;
		})(),
		subheadline: '',
		metrics_title: '',
		metrics: [],
		steps: hasWhyAlyveSteps ? steps : isLegacy || steps.length === 0 ? defaults.steps : steps,
	};
}
