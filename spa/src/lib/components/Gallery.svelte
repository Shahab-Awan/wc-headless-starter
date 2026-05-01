<script lang="ts">
	import { fade, fly } from 'svelte/transition';
	import type { GalleryModuleConfig, SpacingPreset } from '$lib/config.svelte';

	let { config, spacing_v = 'normal', spacing_h = 'normal', center_header = false }: {
		config: GalleryModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
	} = $props();

	const cols = $derived(Math.max(1, Math.min(6, config.columns || 3)));
	const gap = $derived(Math.max(0, Math.min(32, config.gap ?? 8)));
	const aspect = $derived(config.aspect_ratio || '1/1');
	const hasText = $derived(config.items.some(i => i.title || i.description));

	// Lightbox
	let lbOpen = $state(false);
	let lbIndex = $state(0);
	let lbEl = $state<HTMLElement | undefined>();

	function openLb(i: number) { lbIndex = i; lbOpen = true; }
	function closeLb() { lbOpen = false; }
	function lbPrev() { lbIndex = (lbIndex - 1 + config.items.length) % config.items.length; }
	function lbNext() { lbIndex = (lbIndex + 1) % config.items.length; }

	function lbKey(e: KeyboardEvent) {
		if (e.key === 'Escape') closeLb();
		else if (e.key === 'ArrowLeft') lbPrev();
		else if (e.key === 'ArrowRight') lbNext();
	}

	$effect(() => {
		if (lbOpen && lbEl) lbEl.focus();
	});
</script>

{#if config.items.length > 0}
	<section class="gallery" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'}>
		{#if config.title}
			<p class="gallery__label" class:is-centered={center_header}>{config.title}</p>
		{/if}
		<div
			class="gallery__grid"
			style="--cols: {cols}; --gap: {gap}px; --aspect: {aspect};"
		>
			{#each config.items as item, i}
				<button
					type="button"
					class="gallery__cell"
					class:has-text={item.title || item.description}
					onclick={() => openLb(i)}
					aria-label="View {item.title || `image ${i + 1}`} full size"
				>
					<div class="gallery__img-wrap">
						<img
							src={item.src}
							alt={item.title || ''}
							loading="lazy"
							draggable="false"
						/>
					</div>
					{#if item.title || item.description}
						<div class="gallery__caption">
							{#if item.title}
								<span class="gallery__caption-title">{item.title}</span>
							{/if}
							{#if item.description}
								<span class="gallery__caption-desc">{item.description}</span>
							{/if}
						</div>
					{/if}
				</button>
			{/each}
		</div>
	</section>
{/if}

<!-- Lightbox -->
{#if lbOpen}
	<!-- svelte-ignore a11y_no_static_element_interactions -->
	<div
		class="gallery-lb"
		bind:this={lbEl}
		transition:fade={{ duration: 150 }}
		onkeydown={lbKey}
		role="dialog"
		aria-label="Gallery image viewer"
		tabindex="-1"
	>
		<!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
		<div class="gallery-lb__backdrop" onclick={closeLb}></div>
		<button class="gallery-lb__close" onclick={closeLb} aria-label="Close">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
		</button>
		{#if config.items.length > 1}
			<button class="gallery-lb__nav gallery-lb__nav--prev" onclick={lbPrev} aria-label="Previous">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
			</button>
			<button class="gallery-lb__nav gallery-lb__nav--next" onclick={lbNext} aria-label="Next">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
			</button>
		{/if}
		{#key lbIndex}
			<div class="gallery-lb__content" in:fade={{ duration: 120 }}>
				<img
					src={config.items[lbIndex].src}
					alt={config.items[lbIndex].title || ''}
				/>
				{#if config.items[lbIndex].title || config.items[lbIndex].description}
					<div class="gallery-lb__info">
						{#if config.items[lbIndex].title}
							<p class="gallery-lb__info-title">{config.items[lbIndex].title}</p>
						{/if}
						{#if config.items[lbIndex].description}
							<p class="gallery-lb__info-desc">{config.items[lbIndex].description}</p>
						{/if}
					</div>
				{/if}
			</div>
		{/key}
		{#if config.items.length > 1}
			<div class="gallery-lb__counter">{lbIndex + 1} / {config.items.length}</div>
		{/if}
	</div>
{/if}

<style>
	.gallery {
		--mod-pt: 32px;
		--mod-pb: 40px;
		--mod-px: 28px;
		--mod-max-w: 960px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.gallery.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.gallery.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	/* Full-bleed images when h-compact: zero out horizontal padding on
	   the section so the grid hits the viewport edge. The label retains
	   its own inline padding below. */
	.gallery.is-h-compact  { --mod-max-w: 100%; --mod-px: 0; }
	.gallery.is-h-compact .gallery__label {
		padding-left: 16px;
		padding-right: 16px;
	}
	.gallery.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }
	.gallery__label {
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg-muted);
		margin: 0 0 20px;
	}
	.gallery__label.is-centered {
		text-align: center;
	}

	/* Flex-wrap grid — centers the last incomplete row */
	.gallery__grid {
		display: flex;
		flex-wrap: wrap;
		justify-content: center;
		gap: var(--gap, 8px);
	}
	.gallery__cell {
		flex: 0 0 calc((100% - var(--gap, 8px) * (var(--cols, 3) - 1)) / var(--cols, 3));
		max-width: calc((100% - var(--gap, 8px) * (var(--cols, 3) - 1)) / var(--cols, 3));
		padding: 0;
		border: 0;
		background: transparent;
		cursor: zoom-in;
		overflow: hidden;
		position: relative;
		border-radius: var(--radius-sm);
		transition: transform 0.2s var(--ease), box-shadow 0.2s var(--ease);
	}
	.gallery__cell:hover {
		transform: scale(1.015);
		box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
	}
	.gallery__img-wrap {
		aspect-ratio: var(--aspect, 1/1);
		overflow: hidden;
		background: var(--bg-muted);
	}
	.gallery__img-wrap img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
		user-select: none;
		-webkit-user-drag: none;
		transition: transform 0.3s var(--ease);
	}
	.gallery__cell:hover .gallery__img-wrap img {
		transform: scale(1.04);
	}

	/* Caption overlay */
	.gallery__caption {
		position: absolute;
		inset: auto 0 0 0;
		padding: 24px 12px 10px;
		background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, transparent 100%);
		display: flex;
		flex-direction: column;
		gap: 2px;
		opacity: 0;
		transition: opacity 0.2s var(--ease);
	}
	.gallery__cell:hover .gallery__caption {
		opacity: 1;
	}
	.gallery__caption-title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 12px;
		font-weight: 600;
		color: #fff;
		letter-spacing: -0.01em;
	}
	.gallery__caption-desc {
		font-size: 11px;
		color: rgba(255,255,255,0.75);
		line-height: 1.4;
	}

	/* Responsive column collapse */
	@media (max-width: 860px) {
		.gallery__cell {
			flex: 0 0 calc((100% - var(--gap, 8px) * (min(var(--cols, 3), 3) - 1)) / min(var(--cols, 3), 3));
			max-width: calc((100% - var(--gap, 8px) * (min(var(--cols, 3), 3) - 1)) / min(var(--cols, 3), 3));
		}
	}
	@media (max-width: 480px) {
		.gallery__cell {
			flex: 0 0 calc((100% - var(--gap, 8px)) / 2);
			max-width: calc((100% - var(--gap, 8px)) / 2);
		}
		.gallery__caption {
			opacity: 1;
		}
	}

	/* ── Lightbox ── */
	.gallery-lb {
		position: fixed;
		inset: 0;
		z-index: 10000;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
	}
	.gallery-lb__backdrop {
		position: absolute;
		inset: 0;
		background: rgba(0, 0, 0, 0.9);
	}
	.gallery-lb__close {
		position: absolute;
		top: 16px;
		right: 16px;
		z-index: 2;
		width: 44px;
		height: 44px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: transparent;
		border: 1px solid rgba(255, 255, 255, 0.2);
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
		transition: border-color 0.15s, background 0.15s;
	}
	.gallery-lb__close:hover {
		background: rgba(255, 255, 255, 0.1);
		border-color: rgba(255, 255, 255, 0.5);
	}
	.gallery-lb__nav {
		position: absolute;
		top: 50%;
		transform: translateY(-50%);
		z-index: 2;
		width: 44px;
		height: 44px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: transparent;
		border: 1px solid rgba(255, 255, 255, 0.2);
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
		transition: border-color 0.15s, background 0.15s;
	}
	.gallery-lb__nav:hover {
		background: rgba(255, 255, 255, 0.1);
		border-color: rgba(255, 255, 255, 0.5);
	}
	.gallery-lb__nav--prev { left: 16px; }
	.gallery-lb__nav--next { right: 16px; }
	.gallery-lb__content {
		position: relative;
		z-index: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		max-width: calc(100vw - 120px);
		max-height: calc(100vh - 100px);
	}
	.gallery-lb__content img {
		max-width: 100%;
		max-height: calc(100vh - 140px);
		object-fit: contain;
		border-radius: var(--radius-sm);
	}
	.gallery-lb__info {
		margin-top: 12px;
		text-align: center;
		max-width: 560px;
	}
	.gallery-lb__info-title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 14px;
		font-weight: 600;
		color: #fff;
		margin: 0 0 4px;
	}
	.gallery-lb__info-desc {
		font-size: 12px;
		color: rgba(255, 255, 255, 0.65);
		margin: 0;
		line-height: 1.5;
	}
	.gallery-lb__counter {
		position: absolute;
		bottom: 16px;
		left: 50%;
		transform: translateX(-50%);
		z-index: 2;
		font-size: 12px;
		font-weight: 500;
		letter-spacing: 0.06em;
		color: rgba(255, 255, 255, 0.6);
	}
	@media (max-width: 860px) {
		.gallery-lb__nav--prev { left: 8px; }
		.gallery-lb__nav--next { right: 8px; }
		.gallery-lb__content { max-width: calc(100vw - 32px); }
	}
</style>
