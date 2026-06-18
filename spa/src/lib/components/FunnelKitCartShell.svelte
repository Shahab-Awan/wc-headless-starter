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
	/*
	 * Right-aligned strip only — never block the full viewport.
	 * pointer-events stay off until FunnelKit confirms the drawer opened.
	 */
	:global(.wchs-fk-cart-shell) {
		position: fixed;
		top: 0;
		right: 0;
		width: min(100vw, 480px);
		height: 100%;
		border: 0;
		z-index: 9998;
		pointer-events: none;
		background: transparent;
	}

	:global(.wchs-fk-cart-shell--interactive) {
		pointer-events: auto;
	}
</style>
