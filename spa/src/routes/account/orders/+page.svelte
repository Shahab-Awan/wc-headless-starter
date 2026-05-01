<script lang="ts">
	import { onMount } from 'svelte';
	import { listMyOrders, type MyOrdersResponse } from '$lib/wc/orders';

	let loading = $state(true);
	let response = $state<MyOrdersResponse | null>(null);
	let error = $state<string | null>(null);

	onMount(async () => {
		try {
			const data = await listMyOrders(1, 20);
			if (data === null) {
				error = 'not-logged-in';
				return;
			}
			response = data;
		} catch (e) {
			error = e instanceof Error ? e.message : String(e);
		} finally {
			loading = false;
		}
	});

	function formatDate(iso: string | null): string {
		if (!iso) return '—';
		try {
			return new Date(iso).toLocaleDateString(undefined, {
				year: 'numeric',
				month: 'short',
				day: 'numeric'
			});
		} catch {
			return iso;
		}
	}
</script>

<section class="orders">
	<nav class="orders__crumbs">
		<a href="/account">← Account</a>
	</nav>
	<h1>Orders</h1>

	{#if loading}
		<p class="orders__msg">Loading orders…</p>
	{:else if error === 'not-logged-in'}
		<p class="orders__msg">
			You must be <a href="/account">signed in</a> to view orders.
		</p>
	{:else if error}
		<p class="orders__msg orders__err">Failed to load orders: {error}</p>
	{:else if !response || response.orders.length === 0}
		<p class="orders__msg">You have no orders yet.</p>
	{:else}
		<table class="orders__table">
			<thead>
				<tr>
					<th>Order</th>
					<th>Date</th>
					<th>Status</th>
					<th>Total</th>
					<th class="orders__th-action">Action</th>
				</tr>
			</thead>
			<tbody>
				{#each response.orders as order (order.id)}
					<tr>
						<td><span class="orders__num">#{order.number}</span></td>
						<td>{formatDate(order.date_created)}</td>
						<td>
							<span class="orders__status orders__status--{order.status}">
								{order.status}
							</span>
						</td>
						<td class="orders__total">${order.total}</td>
						<td class="orders__action">
							<a
								class="orders__view"
								href={`/account/orders/${order.id}?key=${encodeURIComponent(order.order_key)}`}
							>View</a>
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	{/if}
</section>

<style>
	.orders {
		max-width: 960px;
		margin: 0 auto;
		padding: 48px 24px 120px;
	}
	.orders__crumbs {
		margin-bottom: 18px;
	}
	.orders__crumbs a {
		font-size: 12px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--fg-muted);
		text-decoration: none;
	}
	.orders h1 {
		font-family: var(--font-sans);
		font-size: 32px;
		font-weight: 500;
		margin: 0 0 32px;
	}
	.orders__table {
		width: 100%;
		border-collapse: collapse;
		background: var(--bg-elevated);
		border: 1px solid var(--border);
	}
	.orders__table th,
	.orders__table td {
		padding: 14px 16px;
		text-align: left;
		border-bottom: 1px solid var(--border);
	}
	.orders__table th {
		background: var(--bg-muted);
		font-family: var(--font-sans);
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--fg-muted);
	}
	.orders__num {
		font-family: var(--font-sans);
	}
	.orders__status {
		display: inline-block;
		padding: 4px 10px;
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		border: 1px solid var(--border);
	}
	.orders__status--completed,
	.orders__status--processing {
		color: var(--success);
		border-color: var(--success);
	}
	.orders__status--failed,
	.orders__status--cancelled {
		color: var(--danger);
		border-color: var(--danger);
	}
	.orders__total {
		font-family: var(--font-sans);
	}
	.orders__th-action,
	.orders__action {
		text-align: right;
	}
	.orders__view {
		color: var(--fg);
		font-size: 12px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		text-decoration: underline;
		text-underline-offset: 3px;
	}
	.orders__msg {
		padding: 40px 0;
		text-align: center;
		color: var(--fg-muted);
	}
	.orders__err {
		color: var(--danger);
	}
</style>
