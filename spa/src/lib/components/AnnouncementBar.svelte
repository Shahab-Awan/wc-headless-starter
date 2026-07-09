<script lang="ts">
	import { fade } from 'svelte/transition';
	import { page } from '$app/state';
	import { config } from '$lib/config.svelte';
	import {
		isHome1LandingPath,
		normalizeHome1AnnouncementItems,
	} from '$lib/home-1-landing';

	const onHome1 = $derived(isHome1LandingPath(page.url.pathname));
	const useSlides = $derived(onHome1);

	const items = $derived.by(() => {
		if (onHome1 && config.data.home_1) {
			return normalizeHome1AnnouncementItems(
				config.data.home_1.announcement_bar_items ?? []
			);
		}
		return config.data.announcement_bar_items ?? [];
	});

	const enabled = $derived.by(() => {
		if (onHome1 && config.data.home_1) {
			return Boolean(config.data.home_1.announcement_bar_enabled) && items.length > 0;
		}
		return Boolean(config.data.announcement_bar_enabled) && items.length > 0;
	});

	const SLIDE_MS = 4500;
	const FADE_MS = 320;

	/** Rough px width of one item set — avoids DOM measure loops that restart the animation. */
	function estimateSetWidth(list: string[]): number {
		return list.reduce((sum, text) => sum + Math.max(172, text.length * 8.5 + 72), 0);
	}

	const groupRepeats = $derived.by(() => {
		const setWidth = estimateSetWidth(items);
		if (setWidth <= 0) return 2;
		return Math.min(10, Math.max(2, Math.ceil(2600 / setWidth)));
	});

	const groupItems = $derived(
		Array.from({ length: groupRepeats }, () => items).flat()
	);

	let slideIndex = $state(0);
	let slideTick = $state(0);

	$effect(() => {
		items.length;
		slideIndex = 0;
		slideTick = 0;
	});

	$effect(() => {
		if (!useSlides || items.length <= 1) return;
		const count = items.length;
		const id = setInterval(() => {
			slideTick += 1;
			slideIndex = slideTick % count;
		}, SLIDE_MS);
		return () => clearInterval(id);
	});

	const activeSlide = $derived(items[slideIndex] ?? '');
</script>

{#if enabled}
	<div
		class="site-announcement"
		class:site-announcement--slides={useSlides}
		role="region"
		aria-label="Promotions and shipping"
	>
		{#if useSlides}
			<div class="site-announcement__slider" aria-live="polite">
				{#key slideIndex}
					<span
						class="site-announcement__slide"
						in:fade={{ duration: FADE_MS }}
						out:fade={{ duration: FADE_MS }}
					>
						<span class="site-announcement__item">
							<svg
								class="site-announcement__check"
								viewBox="0 0 12 12"
								width="12"
								height="12"
								aria-hidden="true"
							>
								<polyline
									points="2 6 5 9 10 3"
									fill="none"
									stroke="currentColor"
									stroke-width="1.6"
									stroke-linecap="round"
									stroke-linejoin="round"
								/>
							</svg>
							<span>{activeSlide}</span>
						</span>
					</span>
				{/key}
			</div>
		{:else}
			<div class="site-announcement__track">
				{#each [0, 1] as groupIndex (groupIndex)}
					<div class="site-announcement__group" aria-hidden={groupIndex === 1 ? 'true' : undefined}>
						{#each groupItems as item, itemIndex (`${groupIndex}-${itemIndex}`)}
							<span class="site-announcement__item">
								<svg
									class="site-announcement__check"
									viewBox="0 0 12 12"
									width="12"
									height="12"
									aria-hidden="true"
								>
									<polyline
										points="2 6 5 9 10 3"
										fill="none"
										stroke="currentColor"
										stroke-width="1.6"
										stroke-linecap="round"
										stroke-linejoin="round"
									/>
								</svg>
								<span>{item}</span>
							</span>
						{/each}
					</div>
				{/each}
			</div>
		{/if}
	</div>
{/if}

<style>
	.site-announcement--slides {
		overflow: hidden;
	}

	.site-announcement__slider {
		position: relative;
		display: grid;
		place-items: center;
		min-height: 34px;
		padding: 8px 16px;
		overflow: hidden;
	}

	.site-announcement__slide {
		grid-area: 1 / 1;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
	}

	.site-announcement--slides .site-announcement__item {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		padding: 0;
		white-space: nowrap;
	}

	@media (prefers-reduced-motion: reduce) {
		.site-announcement__slide {
			transition: none;
		}
	}
</style>
