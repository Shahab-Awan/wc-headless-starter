<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/state';
	import { getOrder, type StoreOrder } from '$lib/wc/orders';
	import { formatPrice } from '$lib/utils/format';

	let order = $state<StoreOrder | null>(null);
	let loading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		const id = parseInt(page.params.id ?? '', 10);
		const key = page.url.searchParams.get('key') ?? '';
		if (!id || !key) {
			error = 'Missing order id or key';
			loading = false;
			return;
		}
		try {
			order = await getOrder(id, key);
		} catch (e) {
			error = e instanceof Error ? e.message : String(e);
		} finally {
			loading = false;
		}
	});

	function formatMoney(minor: string): string {
		if (!order) return '';
		return formatPrice(minor, order.totals);
	}
</script>

<svelte:head>
	<meta name="referrer" content="no-referrer" />
</svelte:head>

<section class="order">
	<nav class="order__crumbs">
		<a href="/account/orders">← Orders</a>
	</nav>

	{#if loading}
		<p class="order__msg">Loading…</p>
	{:else if error}
		<p class="order__msg order__err">{error}</p>
	{:else if order}
		<h1>Order #{order.id}</h1>
		<p class="order__status">Status: <strong>{order.status}</strong></p>

		<section class="order__section">
			<h2>Items</h2>
			<ul class="order__items">
				{#each order.items as item}
					<li>
						<span class="order__item-name">{item.name}</span>
						<span class="order__item-qty">× {item.quantity}</span>
						<span class="order__item-total">{formatMoney(item.totals.line_total)}</span>
					</li>
				{/each}
			</ul>
		</section>

		<section class="order__section">
			<h2>Total</h2>
			<p class="order__total">{formatMoney(order.totals.total_price)}</p>
		</section>
	{/if}
</section>

<style>
	.order {
		max-width: 720px;
		margin: 0 auto;
		padding: 48px 24px 120px;
	}
	.order__crumbs {
		margin-bottom: 18px;
	}
	.order__crumbs a {
		font-size: 12px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--fg-muted);
		text-decoration: none;
	}
	.order h1 {
		font-family: var(--font-sans);
		font-size: 32px;
		font-weight: 500;
		margin: 0 0 8px;
	}
	.order__status {
		color: var(--fg-muted);
		margin: 0 0 32px;
	}
	.order__section {
		border-top: 1px solid var(--border);
		padding-top: 24px;
		margin-top: 24px;
	}
	.order__section h2 {
		font-size: 13px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--fg-muted);
		margin: 0 0 16px;
		font-family: var(--font-sans);
		font-weight: 500;
	}
	.order__items {
		list-style: none;
		padding: 0;
		margin: 0;
	}
	.order__items li {
		display: grid;
		grid-template-columns: 1fr auto auto;
		gap: 16px;
		padding: 12px 0;
		border-bottom: 1px solid var(--border);
	}
	.order__items li:last-child {
		border-bottom: 0;
	}
	.order__item-name {
		color: var(--fg);
	}
	.order__item-qty {
		color: var(--fg-muted);
		font-family: var(--font-sans);
	}
	.order__item-total {
		font-family: var(--font-sans);
		color: var(--fg);
		text-align: right;
	}
	.order__total {
		font-size: 24px;
		font-family: var(--font-sans);
		margin: 0;
	}
	.order__msg {
		padding: 40px 0;
		text-align: center;
		color: var(--fg-muted);
	}
	.order__err {
		color: var(--danger);
	}
</style>
