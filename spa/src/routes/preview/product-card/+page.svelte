<script lang="ts">
	/**
	 * Admin-only preview: a curated gallery of product cards in every
	 * state a merchant cares about (default, sale, 4-digit price,
	 * variable unpicked, out-of-stock, with-secondary-image).
	 *
	 * Routed when the admin's Design → Product card section is expanded
	 * so merchants tweaking cord_radius / badge_position / button_style
	 * see the effect on an actual card grid rather than the homepage hero.
	 *
	 * Mock products are hand-tuned — not fetched from WC — so the gallery
	 * is deterministic across dev/stage and doesn't need real inventory.
	 */
	import ProductCard from '$lib/components/ProductCard.svelte';

	const IMG_A = 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80';
	const IMG_B = 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80';
	const IMG_C = 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?auto=format&fit=crop&w=800&q=80';
	const IMG_D = 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?auto=format&fit=crop&w=800&q=80';
	const IMG_E = 'https://images.unsplash.com/photo-1560769629-975ec94e6a86?auto=format&fit=crop&w=800&q=80';
	const IMG_F = 'https://images.unsplash.com/photo-1560343090-f0409e92791a?auto=format&fit=crop&w=800&q=80';

	// Shared prices shape to keep the mock compact
	const prices = (p: string, cmu = 2) => ({
		price: p,
		currency_symbol: '$',
		currency_minor_unit: cmu,
		currency_code: 'USD',
	});

	const productDefault = {
		id: 1001, name: 'Baseline Product 5mg', slug: 'baseline', permalink: '#',
		images: [{ src: IMG_A, thumbnail: IMG_A, alt: 'Baseline' }],
		prices: prices('2499'),
		is_in_stock: true,
	};

	const productSale = {
		id: 1002, name: 'Sale Item 10mg', slug: 'sale', permalink: '#',
		images: [{ src: IMG_B, thumbnail: IMG_B, alt: 'Sale' }],
		prices: { ...prices('1999'), regular_price: '2999' },
		is_in_stock: true,
		on_sale: true,
	};

	const product4Digit = {
		id: 1003, name: 'Big-ticket — 4 Digit Price', slug: 'big', permalink: '#',
		images: [{ src: IMG_C, thumbnail: IMG_C, alt: 'Big' }],
		prices: { ...prices('124900'), regular_price: '149900' },
		is_in_stock: true,
		on_sale: true,
	};

	const productVariable = {
		id: 1004, name: 'Variable — Size', slug: 'variable', permalink: '#',
		images: [{ src: IMG_D, thumbnail: IMG_D, alt: 'Variable' }],
		prices: {
			...prices('1500'),
			price_range: { min_amount: '1500', max_amount: '3500' },
		},
		has_options: true,
		is_in_stock: true,
		attributes: [{
			name: 'Size',
			has_variations: true,
			terms: [
				{ id: 1, name: '5mg', slug: '5mg' },
				{ id: 2, name: '10mg', slug: '10mg' },
				{ id: 3, name: '20mg', slug: '20mg' },
			],
		}],
		variations: [
			{ id: 101, attributes: [{ name: 'Size', value: 'small' }] },
			{ id: 102, attributes: [{ name: 'Size', value: 'medium' }] },
			{ id: 103, attributes: [{ name: 'Size', value: 'large' }] },
		],
	};

	const productOOS = {
		id: 1005, name: 'Out of Stock Example 5mg', slug: 'oos', permalink: '#',
		images: [{ src: IMG_E, thumbnail: IMG_E, alt: 'OOS' }],
		prices: prices('8999'),
		is_in_stock: false,
	};

	const productSecondary = {
		id: 1006, name: 'Has Hover-swap Image 10mg', slug: 'secondary', permalink: '#',
		images: [
			{ src: IMG_F, thumbnail: IMG_F, alt: 'Primary' },
			{ src: IMG_A, thumbnail: IMG_A, alt: 'Secondary' },
		],
		prices: prices('4999'),
		is_in_stock: true,
	};

	const cards = [
		{ label: 'Default',         product: productDefault },
		{ label: 'On sale',          product: productSale },
		{ label: '4-digit price',    product: product4Digit },
		{ label: 'Variable (size)',  product: productVariable },
		{ label: 'Out of stock',     product: productOOS },
		{ label: 'Hover-swap image', product: productSecondary },
	];
</script>

<svelte:head>
	<title>Product card preview — WCHS admin</title>
	<meta name="robots" content="noindex, nofollow" />
</svelte:head>

<main class="pc-preview">
	<header class="pc-preview__header">
		<h1>Product card preview</h1>
		<p>A curated gallery showing each product-card state. Change any setting on the left — these cards update live.</p>
	</header>
	<section class="pc-preview__grid">
		{#each cards as card (card.product.id)}
			<article class="pc-preview__cell">
				<span class="pc-preview__label">{card.label}</span>
				<ProductCard product={card.product} cardWidth={280} />
			</article>
		{/each}
	</section>
</main>

<style>
	.pc-preview {
		max-width: 1200px;
		margin: 0 auto;
		padding: 36px 24px 80px;
		background: var(--bg);
	}
	.pc-preview__header {
		margin-bottom: 32px;
	}
	.pc-preview__header h1 {
		margin: 0 0 6px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: 20px;
		font-weight: var(--heading-weight, 600);
		letter-spacing: -0.01em;
		color: var(--fg);
	}
	.pc-preview__header p {
		margin: 0;
		font-size: 13px;
		color: var(--fg-muted);
	}
	.pc-preview__grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
		gap: 28px 24px;
	}
	.pc-preview__cell {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
	.pc-preview__label {
		font-family: var(--font-sans);
		font-size: 10px;
		font-weight: 600;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		color: var(--fg-muted);
	}

	@media (max-width: 640px) {
		.pc-preview {
			padding: 20px 16px 40px;
		}
		.pc-preview__grid {
			grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
			gap: 18px 14px;
		}
	}
</style>
