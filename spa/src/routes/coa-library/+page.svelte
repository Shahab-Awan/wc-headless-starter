<script lang="ts">
	import { config } from '$lib/config.svelte';
	import CoaLibrary from '$lib/components/CoaLibrary.svelte';
	import SEO from '$lib/components/SEO.svelte';

	const title = 'COA Library';
	const description =
		'Browse and download Certificates of Analysis (COA) for every batch we publish.';

	const pageUrl = $derived.by(() => {
		const origin =
			typeof window !== 'undefined'
				? window.location.origin
				: ((config.data as { spa_origin?: string }).spa_origin || '');
		return `${origin}/coa-library`;
	});

	const breadcrumb = $derived.by(() => {
		const origin =
			typeof window !== 'undefined'
				? window.location.origin
				: ((config.data as { spa_origin?: string }).spa_origin || '');
		return {
			'@context': 'https://schema.org',
			'@type': 'BreadcrumbList',
			itemListElement: [
				{
					'@type': 'ListItem',
					position: 1,
					name: config.data.brand_name,
					item: `${origin}/`,
				},
				{
					'@type': 'ListItem',
					position: 2,
					name: title,
					item: `${origin}/coa-library`,
				},
			],
		};
	});
</script>

<SEO {title} {description} url={pageUrl} schema={breadcrumb} />

<CoaLibrary />
