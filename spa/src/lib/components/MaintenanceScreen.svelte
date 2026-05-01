<script lang="ts">
	import { config } from '$lib/config.svelte';
</script>

<div class="maintenance">
	<div class="maintenance__inner">
		<p class="maintenance__eyebrow">Maintenance</p>
		<h1 class="maintenance__title">We'll be back soon</h1>
		<p class="maintenance__body">
			This site is undergoing scheduled maintenance. Please check back shortly.
		</p>
		{#if config.data.brand_name}
			<p class="maintenance__brand">{config.data.brand_name}</p>
		{/if}
	</div>
</div>

<style>
	.maintenance {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 100vh;
		padding: 48px 24px;
		background: var(--bg);
	}
	.maintenance__inner {
		max-width: 480px;
		text-align: center;
	}
	.maintenance__eyebrow {
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.14em;
		color: var(--fg-muted);
		margin: 0 0 16px;
	}
	.maintenance__title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 32px;
		font-weight: var(--heading-weight, 500);
		letter-spacing: -0.03em;
		line-height: 1.2;
		margin: 0 0 16px;
		color: var(--fg);
	}
	.maintenance__body {
		font-size: 14px;
		line-height: 1.6;
		color: var(--fg-muted);
		margin: 0 0 32px;
	}
	.maintenance__brand {
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.14em;
		color: var(--fg-faint);
		margin: 0;
	}
</style>
