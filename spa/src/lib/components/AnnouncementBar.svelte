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

	const loop = $derived([...items, ...items]);

	let slideIndex = $state(0);

	$effect(() => {
		items.length;
		slideIndex = 0;
	});

	$effect(() => {
		if (!useSlides || items.length <= 1) return;
		const id = setInterval(() => {
			slideIndex = (slideIndex + 1) % items.length;
		}, 4500);
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
					<span class="site-announcement__item" in:fade={{ duration: 280 }}>
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
				{/key}
			</div>
		{:else}
			<div class="site-announcement__track">
				{#each loop as item, i (i)}
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
		{/if}
	</div>
{/if}

<style>
	.site-announcement--slides {
		overflow: hidden;
	}

	.site-announcement__slider {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 34px;
		padding: 8px 16px;
	}

	.site-announcement--slides .site-announcement__item {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		padding: 0;
		white-space: nowrap;
	}
</style>
