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
	 * Hidden preload — zero footprint until FunnelKit confirms the drawer opened.
	 * Full viewport only while interactive so the FK drawer + backdrop are usable.
	 */
	:global(.wchs-fk-cart-shell) {
		position: fixed;
		top: 0;
		left: 0;
		width: 0;
		height: 0;
		border: 0;
		opacity: 0;
		z-index: -1;
		pointer-events: none;
		background: transparent;
	}

	:global(.wchs-fk-cart-shell--interactive) {
		inset: 0;
		width: 100%;
		height: 100%;
		opacity: 1;
		z-index: 9998;
		pointer-events: auto;
	}
</style>
