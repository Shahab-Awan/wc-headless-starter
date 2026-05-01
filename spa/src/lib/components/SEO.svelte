<script lang="ts">
	/**
	 * SEO — shared <svelte:head> emitter for title, description,
	 * canonical, Open Graph, Twitter Card, and optional JSON-LD
	 * schema.
	 *
	 * All content-bearing routes (homepage, shop, PDP, content pages)
	 * render this component with the appropriate props. Tags are emitted
	 * client-side via <svelte:head>. Google's crawler executes JS and
	 * indexes these — rich results (price, availability, ratings) work.
	 *
	 * Raw HTML for product/shop/content routes is handled by the WordPress
	 * MU plugin `headless-seo-shell.php`, which swaps the static fallback
	 * block before the SPA hydrates. This component owns the hydrated head.
	 */
	import { onMount } from 'svelte';
	import { config } from '$lib/config.svelte';

	type Props = {
		/** Page title. " | {brand_name}" is appended unless already present. */
		title: string;
		/** Meta description. Recommended length ~155 chars. */
		description?: string;
		/** Primary image URL (og:image, twitter:image). 1200×630 ideal. */
		image?: string;
		/** Canonical URL. Defaults to current location (if available). */
		url?: string;
		/** og:type. Default 'website'. Use 'product' on PDP, 'article' for posts. */
		type?: 'website' | 'product' | 'article';
		/** JSON-LD schema object(s). Emitted as <script type="application/ld+json">. */
		schema?: unknown | unknown[];
		/** When true, emit <meta name="googlebot" content="nosnippet, noimageindex"> */
		nosnippet?: boolean;
		/** When true, emit <meta name="robots" content="noindex">. Private pages only. */
		noindex?: boolean;
	};

	let {
		title,
		description = '',
		image = '',
		url = '',
		type = 'website',
		schema,
		nosnippet = false,
		noindex = false,
	}: Props = $props();

	const brand = $derived(config.data.brand_name || '');
	const fullTitle = $derived(
		title && brand && !title.includes(brand) ? `${title} | ${brand}` : title
	);
	const canonicalUrl = $derived(
		url || (typeof window !== 'undefined' ? window.location.href : '')
	);
	const descriptionText = $derived(description ? description.substring(0, 300) : '');

	// Accept a single object or an array; normalize to array for rendering.
	const schemaArray = $derived(
		schema == null ? [] : Array.isArray(schema) ? schema : [schema]
	);

	onMount(() => {
		document.querySelectorAll('[data-static-seo]').forEach((el) => {
			if (el.getAttribute('data-static-seo') === 'icon') return;
			el.remove();
		});
	});
</script>

<svelte:head>
	<title>{fullTitle}</title>
	{#if descriptionText}
		<meta name="description" content={descriptionText} />
	{/if}
	{#if canonicalUrl}
		<link rel="canonical" href={canonicalUrl} />
	{/if}

	<!-- Open Graph -->
	<meta property="og:type" content={type} />
	<meta property="og:title" content={fullTitle} />
	{#if descriptionText}
		<meta property="og:description" content={descriptionText} />
	{/if}
	{#if canonicalUrl}
		<meta property="og:url" content={canonicalUrl} />
	{/if}
	{#if image}
		<meta property="og:image" content={image} />
	{/if}
	{#if brand}
		<meta property="og:site_name" content={brand} />
	{/if}

	<!-- Twitter Card -->
	<meta name="twitter:card" content={image ? 'summary_large_image' : 'summary'} />
	<meta name="twitter:title" content={fullTitle} />
	{#if descriptionText}
		<meta name="twitter:description" content={descriptionText} />
	{/if}
	{#if image}
		<meta name="twitter:image" content={image} />
	{/if}

	<!-- Robots -->
	{#if noindex}
		<meta name="robots" content="noindex" />
	{/if}
	{#if nosnippet}
		<meta name="googlebot" content="nosnippet, noimageindex" />
	{/if}

	<!-- JSON-LD schema (Product, Article, etc.) -->
	{#each schemaArray as obj}
		{@html `<script type="application/ld+json">${JSON.stringify(obj)
			.replace(/</g, '\\u003c')
			.replace(/\u2028/g, '\\u2028')
			.replace(/\u2029/g, '\\u2029')}</script>`}
	{/each}
</svelte:head>
