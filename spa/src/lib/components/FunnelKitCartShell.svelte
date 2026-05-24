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
	:global(.site-header__fk-slot) {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 44px;
		height: 44px;
		flex-shrink: 0;
		position: relative;
		vertical-align: middle;
	}

	:global(.wchs-fk-cart-shell) {
		position: fixed;
		width: 44px;
		height: 44px;
		border: 0;
		z-index: 101;
		pointer-events: auto;
		background: transparent;
		overflow: visible;
	}

	:global(.wchs-fk-cart-shell--active) {
		inset: 0 !important;
		width: 100% !important;
		height: 100% !important;
		z-index: 10000;
	}
</style>
