<script lang="ts">
	import { page } from '$app/state';
	import { formatPrice } from '$lib/utils/format';
	import { readOrderReference, fireThankYouPurchaseEvents } from '$lib/thank-you/flow';
	import { getOrder } from '$lib/wc/orders';
	import { cart } from '$lib/wc/cart.svelte';
	import { clearShadow } from '$lib/wc/shadow-cart';
	import { clearCartToken } from '$lib/wc/store-api';

	let order = $state<import('$lib/wc/orders').StoreOrder | null>(null);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let lastLoadedRef = '';
	let activeLoad = 0;

	async function loadThankYou(): Promise<void> {
		const { id, key, email, ref } = readOrderReference(page);

		if (!id || !key) {
			if (lastLoadedRef === '__missing__') return;
			lastLoadedRef = '__missing__';
			order = null;
			error =
				'Order reference missing. If you just completed a purchase, try refreshing from the confirmation link in your email.';
			loading = false;
			return;
		}
		if (ref === lastLoadedRef) return;

		lastLoadedRef = ref;
		const loadId = ++activeLoad;
		order = null;
		error = null;
		loading = true;

		clearShadow();
		clearCartToken();

		try {
			const orderData = await getOrder(id, key, email ?? undefined);
			if (loadId !== activeLoad) return;
			order = orderData;
			fireThankYouPurchaseEvents(orderData);
			await cart.fetch();
		} catch (e) {
			if (loadId !== activeLoad) return;
			error = e instanceof Error ? e.message : String(e);
		} finally {
			if (loadId !== activeLoad) return;
			loading = false;
		}
	}

	$effect(() => {
		page.url.pathname;
		page.url.search;
		if (typeof window === 'undefined') return;
		void loadThankYou();
	});

	function formatTotal(): string {
		if (!order) return '';
		return formatPrice(order.totals.total_price, order.totals);
	}
</script>

<svelte:head>
	<meta name="referrer" content="no-referrer" />
	<meta name="robots" content="noindex" />
	<title>Thank you for your order</title>
</svelte:head>

<section class="thank-you">
	{#if loading}
		<p class="thank-you__msg">Confirming your order…</p>
	{:else if error}
		<div class="thank-you__card">
			<h1>Something went wrong</h1>
			<p class="thank-you__msg">{error}</p>
			<a href="/" class="thank-you__link">Back to home</a>
		</div>
	{:else if order}
		<div class="thank-you__card">
			<p class="thank-you__eyebrow">Order confirmed</p>
			<h1>Thank you for your order.</h1>
			<p class="thank-you__line">
				Your order <strong class="thank-you__order-id">#{order.id}</strong> has been received.
			</p>
			<p class="thank-you__line">
				We are preparing your order and will send updates to
				<strong>{order.billing_address.email}</strong>.
			</p>
			<p class="thank-you__line thank-you__line--muted">
				We appreciate your business and will keep you updated as your order moves forward.
			</p>
			<div class="thank-you__actions">
				<a href="/" class="thank-you__cta">Continue shopping</a>
				<a href="/order-received" class="thank-you__link">View full order details</a>
			</div>
		</div>
	{/if}
</section>

<style>
	.thank-you {
		max-width: 560px;
		margin: 0 auto;
		padding: 48px 24px 120px;
	}
	.thank-you__card {
		background: var(--bg-elevated);
		border: 1px solid var(--border);
		padding: 40px 32px;
		text-align: center;
	}
	.thank-you__eyebrow {
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--accent);
		margin: 0 0 12px;
		font-weight: 600;
	}
	.thank-you h1 {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(28px, 6vw, 36px);
		font-weight: 700;
		line-height: 1.2;
		margin: 0 0 20px;
		color: var(--fg);
	}
	.thank-you__line {
		margin: 0 0 14px;
		line-height: 1.65;
		color: var(--fg);
		font-size: 16px;
	}
	.thank-you__line--muted {
		color: var(--fg-muted);
		font-size: 14px;
	}
	.thank-you__order-id {
		font-family: var(--font-sans);
		color: var(--accent);
	}
	.thank-you__actions {
		margin-top: 32px;
		display: flex;
		flex-direction: column;
		gap: 14px;
		align-items: center;
	}
	.thank-you__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 14px 28px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		text-decoration: none;
		font-size: 13px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
	}
	.thank-you__cta:hover {
		opacity: 0.9;
	}
	.thank-you__link {
		color: var(--fg-muted);
		font-size: 12px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		text-decoration: underline;
		text-underline-offset: 3px;
	}
	.thank-you__msg {
		padding: 40px 0;
		text-align: center;
		color: var(--fg-muted);
	}
</style>
