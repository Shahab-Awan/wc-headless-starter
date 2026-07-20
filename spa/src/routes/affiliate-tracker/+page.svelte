<script lang="ts">
	import { config } from '$lib/config.svelte';
	import AffiliateTracker from '$lib/components/AffiliateTracker.svelte';
	import SEO from '$lib/components/SEO.svelte';

	const title = 'Affiliate Tracker';
	const description = 'Look up your affiliate coupon code to see paid-order usage and totals.';

	const pageUrl = $derived.by(() => {
		const origin =
			typeof window !== 'undefined'
				? window.location.origin
				: ((config.data as { spa_origin?: string }).spa_origin || '');
		return `${origin}/affiliate-tracker`;
	});
</script>

<SEO {title} {description} url={pageUrl} noindex={true} />

<AffiliateTracker />
