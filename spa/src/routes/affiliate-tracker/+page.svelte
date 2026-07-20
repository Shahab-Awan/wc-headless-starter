<script lang="ts">
	import { config } from '$lib/config.svelte';
	import AffiliateTracker from '$lib/components/AffiliateTracker.svelte';
	import SEO from '$lib/components/SEO.svelte';

	const title = 'Affiliate Portal';
	const description = 'Become an affiliate or log in to track coupon sales and view your details.';

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
