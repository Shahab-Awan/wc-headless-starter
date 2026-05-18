<script lang="ts">
	import { config } from '$lib/config.svelte';

	const items = $derived(config.data.announcement_bar_items ?? []);
	const enabled = $derived(
		Boolean(config.data.announcement_bar_enabled) && items.length > 0
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
