<script lang="ts">
	import { onMount } from 'svelte';
	import type { CategoryGridModuleConfig, SpacingPreset } from '$lib/config.svelte';

	let { config, spacing_v = 'normal', spacing_h = 'normal', center_header = false }: {
		config: CategoryGridModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
	} = $props();

	type WcCategory = {
		id: number;
		name: string;
		slug: string;
		description: string;
		count: number;
		image: { src: string } | null;
	};

	let categories = $state<WcCategory[]>([]);

	const cols = $derived(Math.max(1, Math.min(6, config.columns || 3)));
	const gap = $derived(Math.max(0, Math.min(32, config.gap ?? 8)));

	// Match config items to fetched category data
	const tiles = $derived(
		config.items
			.map(item => {
				const cat = categories.find(c => c.id === item.category_id);
				if (!cat) return null;
				const image = item.image || cat.image?.src || '';
				return { ...cat, image };
			})
			.filter((t): t is WcCategory & { image: string } => t !== null)
	);

	onMount(async () => {
		try {
			const res = await fetch('/wp-json/wc/store/v1/products/categories?per_page=50');
			if (res.ok) {
				categories = await res.json();
			}
		} catch { /* non-critical */ }
	});
</script>

{#if tiles.length > 0}
	<section class="cat-grid" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'}>
		{#if config.title}
			<p class="cat-grid__label" class:is-centered={center_header}>{config.title}</p>
		{/if}
		<div class="cat-grid__grid" style="--cols: {cols}; --gap: {gap}px;">
			{#each tiles as tile}
				<a
					class="cat-grid__tile"
					href="/shop/{tile.slug}"
				>
					{#if tile.image}
						<img
							class="cat-grid__img"
							src={tile.image}
							alt={tile.name}
							loading="lazy"
							draggable="false"
						/>
					{/if}
					<div class="cat-grid__overlay">
						<span class="cat-grid__name">{tile.name}</span>
						{#if tile.description}
							<span class="cat-grid__desc">{tile.description}</span>
						{/if}
						{#if tile.count > 0}
							<span class="cat-grid__count">{tile.count} {tile.count === 1 ? 'product' : 'products'}</span>
						{/if}
					</div>
				</a>
			{/each}
		</div>
	</section>
{/if}

<style>
	.cat-grid {
		--mod-pt: 32px;
		--mod-pb: 40px;
		--mod-px: 28px;
		--mod-max-w: 960px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.cat-grid.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.cat-grid.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.cat-grid.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.cat-grid.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }
	.cat-grid__label {
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg-muted);
		margin: 0 0 20px;
	}
	.cat-grid__label.is-centered {
		text-align: center;
	}
	.cat-grid__grid {
		display: flex;
		flex-wrap: wrap;
		justify-content: center;
		gap: var(--gap, 8px);
	}
	.cat-grid__tile {
		flex: 0 0 calc((100% - var(--gap, 8px) * (var(--cols, 3) - 1)) / var(--cols, 3));
		max-width: calc((100% - var(--gap, 8px) * (var(--cols, 3) - 1)) / var(--cols, 3));
		position: relative;
		aspect-ratio: 4 / 3;
		overflow: hidden;
		border-radius: var(--radius-md);
		background: var(--bg-muted);
		text-decoration: none;
		color: #fff;
		display: flex;
		align-items: flex-end;
		transition: transform 0.2s var(--ease), box-shadow 0.2s var(--ease);
	}
	.cat-grid__tile:hover {
		transform: scale(1.015);
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
	}
	.cat-grid__img {
		position: absolute;
		inset: 0;
		width: 100%;
		height: 100%;
		object-fit: cover;
		user-select: none;
		-webkit-user-drag: none;
		transition: transform 0.3s var(--ease);
	}
	.cat-grid__tile:hover .cat-grid__img {
		transform: scale(1.04);
	}
	.cat-grid__overlay {
		position: relative;
		z-index: 1;
		width: 100%;
		padding: 20px 14px 12px;
		background: linear-gradient(to top, rgba(0, 0, 0, 0.6) 0%, transparent 100%);
		display: flex;
		flex-direction: column;
		gap: 2px;
	}
	.cat-grid__name {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 14px;
		font-weight: 600;
		letter-spacing: -0.01em;
	}
	.cat-grid__desc {
		font-size: 11px;
		color: rgba(255, 255, 255, 0.75);
		line-height: 1.4;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}
	.cat-grid__count {
		font-size: 10px;
		color: rgba(255, 255, 255, 0.5);
		margin-top: 2px;
	}

	@media (max-width: 860px) {
		.cat-grid__tile {
			flex: 0 0 calc((100% - var(--gap, 8px) * (min(var(--cols, 3), 3) - 1)) / min(var(--cols, 3), 3));
			max-width: calc((100% - var(--gap, 8px) * (min(var(--cols, 3), 3) - 1)) / min(var(--cols, 3), 3));
		}
	}
	/* Mobile: one tile per row regardless of admin's --cols setting.
	   Stacked full-width cards with a flatter aspect ratio so the imagery +
	   name + count have room to breathe — three across at 414px is unreadable. */
	@media (max-width: 639px) {
		.cat-grid__tile {
			flex: 0 0 100%;
			max-width: 100%;
			aspect-ratio: 16 / 9;
		}
	}
</style>
