<script lang="ts">
	import { theme } from '$lib/theme.svelte';
</script>

<button
	type="button"
	class="theme-toggle"
	onclick={() => theme.toggle()}
	aria-label={theme.current === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
	title={theme.current === 'dark' ? 'Light' : 'Dark'}
>
	{#if theme.current === 'dark'}
		<!-- sun -->
		<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
			<circle cx="12" cy="12" r="4" />
			<path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
		</svg>
	{:else}
		<!-- moon -->
		<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
			<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
		</svg>
	{/if}
</button>

<style>
	.theme-toggle {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		padding: 0;
		background: transparent;
		color: var(--fg);
		border: 1px solid var(--border);
		border-radius: 0;
		cursor: pointer;
		transition:
			background 150ms ease,
			border-color 150ms ease,
			color 150ms ease;
	}
	.theme-toggle:hover {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}
	.theme-toggle svg {
		display: block;
	}
</style>
