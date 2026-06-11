<script lang="ts">
	import { config } from '$lib/config.svelte';
	import { FATHERS_DAY_HERO_CONTENT } from '$lib/fathers-day-hero';

	const fathersDayMode = $derived(config.data.homepage.fathers_day_mode !== false);

	const items = $derived.by(() => {
		const base = config.data.announcement_bar_items ?? [];
		if (!fathersDayMode) return base;
		const fd = FATHERS_DAY_HERO_CONTENT.announcement;
		if (base.includes(fd)) return base;
		return [fd, ...base];
	});

	const enabled = $derived(
		fathersDayMode
			? true
			: Boolean(config.data.announcement_bar_enabled) && items.length > 0
	);

	const loop = $derived([...items, ...items]);
</script>

{#if enabled}
	<div class="site-announcement" role="region" aria-label="Promotions and shipping">
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
	</div>
{/if}
