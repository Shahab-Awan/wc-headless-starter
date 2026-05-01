<script lang="ts">
	import type { LogoStripModuleConfig, SpacingPreset } from '$lib/config.svelte';

	let { config, spacing_v = 'normal', spacing_h = 'normal', center_header = false }: {
		config: LogoStripModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
	} = $props();

	const items = $derived((config.items || []).filter((i) => i.src));
	const grayscale = $derived(config.grayscale !== false);
</script>

{#if items.length}
	<section
		class="lgs"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
	>
		{#if config.title}
			<h3 class="lgs__title" class:is-centered={center_header}>{config.title}</h3>
		{/if}
		<div class="lgs__row" class:is-grayscale={grayscale}>
			{#each items as item}
				{#if item.link_url}
					<a class="lgs__item" href={item.link_url} target="_blank" rel="noopener noreferrer">
						<img src={item.src} alt={item.alt || ''} loading="lazy" />
					</a>
				{:else}
					<div class="lgs__item">
						<img src={item.src} alt={item.alt || ''} loading="lazy" />
					</div>
				{/if}
			{/each}
		</div>
	</section>
{/if}

<style>
	.lgs {
		--mod-pt: 32px;
		--mod-pb: 32px;
		--mod-px: 28px;
		--mod-max-w: 1200px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.lgs.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.lgs.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.lgs.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.lgs.is-h-spacious { --mod-max-w: 960px; --mod-px: 40px; }

	.lgs__title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 13px;
		font-weight: var(--heading-weight, 600);
		letter-spacing: 0.1em;
		text-transform: uppercase;
		color: var(--fg-muted);
		margin: 0 0 20px;
	}
	.lgs__title.is-centered {
		text-align: center;
	}

	.lgs__row {
		display: flex;
		flex-wrap: wrap;
		justify-content: center;
		align-items: center;
		gap: 32px 48px;
	}
	.lgs__item {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		max-height: 44px;
		transition: opacity 0.2s ease, filter 0.25s ease;
	}
	.lgs__item img {
		max-height: 44px;
		max-width: 160px;
		object-fit: contain;
		display: block;
	}
	.lgs__row.is-grayscale .lgs__item {
		filter: grayscale(1);
		opacity: 0.6;
	}
	.lgs__row.is-grayscale .lgs__item:hover {
		filter: grayscale(0);
		opacity: 1;
	}

	@media (max-width: 600px) {
		.lgs__row { gap: 20px 28px; }
		.lgs__item, .lgs__item img { max-height: 36px; }
	}
</style>
