<script lang="ts">
	import { browser } from '$app/environment';
	import { config } from '$lib/config.svelte';
	import { ensureFunnelKitCartShell } from '$lib/funnelkit-cart';

	$effect(() => {
		if (!browser || !config.ready) return;
		if (!config.data.funnelkit_cart?.enabled) return;
		ensureFunnelKitCartShell();
	});
</script>

<style>
	:global(.wchs-fk-cart-shell) {
		position: fixed;
		inset: 0;
		width: 100%;
		height: 100%;
		border: 0;
		z-index: 9998;
		pointer-events: none;
		background: transparent;
	}

	:global(.wchs-fk-cart-shell--active) {
		pointer-events: auto;
	}
</style>
