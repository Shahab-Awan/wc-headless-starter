<script lang="ts">
	import type { SpacerModuleConfig } from '$lib/config.svelte';

	let { config }: { config: SpacerModuleConfig } = $props();

	const h = $derived(Math.max(8, Math.min(160, Number(config.height) || 40)));
</script>

<div class="spacer" style="height: {h}px" aria-hidden="true"></div>

<style>
	.spacer {
		width: 100%;
	}
</style>
