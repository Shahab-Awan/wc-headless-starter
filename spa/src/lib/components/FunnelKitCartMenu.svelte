<script lang="ts">
	import { config } from '$lib/config.svelte';
	import { cart } from '$lib/wc/cart.svelte';
	import { initFunnelKitCart } from '$lib/funnelkit-cart';

	type Props = {
		accent?: boolean;
		bumping?: boolean;
		class?: string;
		/** Drawer row uses text label instead of shortcode icon. */
		variant?: 'header' | 'drawer';
		ondrawerclick?: () => void;
	};

	let {
		accent = false,
		bumping = false,
		class: className = '',
		variant = 'header',
		ondrawerclick
	}: Props = $props();

	let hostEl = $state<HTMLElement | null>(null);

	$effect(() => {
		if (!config.data.funnelkit_cart?.enabled) return;
		void initFunnelKitCart();
	});

	$effect(() => {
		const host = hostEl;
		if (!host || variant !== 'header') return;

		const onClick = (e: MouseEvent) => {
			const target = e.target as HTMLElement | null;
			if (!target || !host.contains(target)) return;
			e.preventDefault();
			e.stopPropagation();
			void cart.toggle(true);
		};

		host.addEventListener('click', onClick);
		return () => host.removeEventListener('click', onClick);
	});
</script>

{#if variant === 'drawer'}
	<button
		type="button"
		class="site-header-drawer__item site-header__fkcart-menu {className}"
		class:is-accent={accent}
		onclick={() => {
			ondrawerclick?.();
			void cart.toggle();
		}}
	>
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
		<span>Cart ({cart.itemCount})</span>
	</button>
{:else if config.data.funnelkit_cart?.menu_html}
	<div
		bind:this={hostEl}
		class="site-header__fkcart-menu site-header__cart {className}"
		class:is-accent={accent}
		class:is-bumping={bumping}
		role="button"
		tabindex="0"
		aria-label="Open cart"
		onkeydown={(e) => {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				void cart.toggle();
			}
		}}
	>
		{@html config.data.funnelkit_cart.menu_html}
	</div>
{:else}
	<button
		bind:this={hostEl}
		type="button"
		class="site-header__cart site-header__fkcart-menu fkcart-mini-open {className}"
		class:is-accent={accent}
		class:is-bumping={bumping}
		aria-label="Open cart"
		onclick={() => cart.toggle()}
	>
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
		<span class="site-header__cart-count tabular-nums">{cart.itemCount}</span>
	</button>
{/if}

<style>
	.site-header__fkcart-menu {
		display: inline-flex;
		align-items: center;
		cursor: pointer;
		border: 0;
		background: transparent;
		padding: 0;
		color: inherit;
		font: inherit;
	}

	.site-header__fkcart-menu :global(a),
	.site-header__fkcart-menu :global(button) {
		cursor: pointer;
	}
</style>
