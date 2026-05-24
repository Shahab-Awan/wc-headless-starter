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
	/* Collapsed until the header cart opens the drawer — hides FunnelKit's floating launcher. */
	:global(.wchs-fk-cart-shell) {
		position: fixed;
		right: 0;
		bottom: 0;
		width: 0;
		height: 0;
		border: 0;
		z-index: 9998;
		pointer-events: none;
		background: transparent;
		opacity: 0;
		visibility: hidden;
		overflow: hidden;
	}

	:global(.wchs-fk-cart-shell--active) {
		inset: 0;
		width: 100%;
		height: 100%;
		opacity: 1;
		visibility: visible;
		pointer-events: auto;
	}
</style>
