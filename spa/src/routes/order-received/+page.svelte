<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { getOrder, getOrderPayment, type StoreOrder, type OrderPaymentInfo } from '$lib/wc/orders';
	import { cart } from '$lib/wc/cart.svelte';
	import { clearShadow } from '$lib/wc/shadow-cart';
	import { clearCartToken } from '$lib/wc/store-api';
	import { readOrderReference, fireThankYouPurchaseEvents } from '$lib/thank-you/flow';
	import { formatPrice } from '$lib/utils/format';

	let order = $state<StoreOrder | null>(null);
	let payment = $state<OrderPaymentInfo | null>(null);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let lastLoadedRef = '';
	let activeLoad = 0;

	async function loadOrderReceived(): Promise<void> {
		const incoming = page.url.searchParams;
		if (incoming.get('id') && incoming.get('key')) {
			const qs = incoming.toString();
			await goto(qs ? `/thank-you?${qs}` : '/thank-you', { replaceState: true });
			return;
		}

		const { id, key, email, ref } = readOrderReference(page);

		if (!id || !key) {
			if (lastLoadedRef === '__missing__') return;
			lastLoadedRef = '__missing__';
			order = null;
			payment = null;
			error = 'Order reference missing. If you just completed a purchase, try refreshing from the confirmation link in your email.';
			loading = false;
			return;
		}
		if (ref === lastLoadedRef) {
			return;
		}

		lastLoadedRef = ref;
		const loadId = ++activeLoad;
		order = null;
		payment = null;
		error = null;
		loading = true;

		clearShadow();
		clearCartToken();

		try {
			const [orderData, paymentData] = await Promise.all([
				getOrder(id, key, email ?? undefined),
				getOrderPayment(id, key),
			]);
			if (loadId !== activeLoad) return;
			order = orderData;
			payment = paymentData;

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
		void loadOrderReceived();
	});

	function formatMoney(minor: string): string {
		if (!order) return '';
		return formatPrice(minor, order.totals);
	}

	function formatMoneyDecimal(decimal: string): string {
		if (!order) return '';
		const asMinor = Number(decimal) * Math.pow(10, order.totals.currency_minor_unit);
		return formatPrice(asMinor, order.totals);
	}
</script>

<svelte:head>
	<!-- Keep the order_key out of the Referer header on outbound links. -->
	<meta name="referrer" content="no-referrer" />
	<!-- Don't index order-received pages — they contain order keys. -->
	<meta name="robots" content="noindex" />
	<title>Order received</title>
</svelte:head>

<section class="thanks">
	{#if loading}
		<p class="thanks__msg">Loading your order…</p>
	{:else if error}
		<div class="thanks__card">
			<h1>Something went wrong</h1>
			<p class="thanks__msg">{error}</p>
			<a href="/" class="thanks__link">Back to home</a>
		</div>
	{:else if order}
		<div class="thanks__card">
			{#if payment?.instructions}
				<div class="thanks__payment-banner">
					<p class="thanks__payment-banner-title">{payment.method_title}</p>

					{#if payment.instructions.type === 'cod'}
						<p class="thanks__payment-banner-msg">{payment.instructions.message}</p>

					{:else if payment.instructions.type === 'bacs'}
						{#if payment.instructions.message}
							<p class="thanks__payment-banner-msg">{payment.instructions.message}</p>
						{/if}
						{#if payment.instructions.accounts?.length}
							{#each payment.instructions.accounts as acct}
								<div class="thanks__bank thanks__bank--banner">
									{#if acct.bank_name}<div><span class="thanks__bank-label">Bank:</span> {acct.bank_name}</div>{/if}
									{#if acct.account_name}<div><span class="thanks__bank-label">Name:</span> {acct.account_name}</div>{/if}
									{#if acct.account_number}<div><span class="thanks__bank-label">Account:</span> {acct.account_number}</div>{/if}
									{#if acct.sort_code}<div><span class="thanks__bank-label">Sort code:</span> {acct.sort_code}</div>{/if}
									{#if acct.iban}<div><span class="thanks__bank-label">IBAN:</span> {acct.iban}</div>{/if}
									{#if acct.bic}<div><span class="thanks__bank-label">BIC:</span> {acct.bic}</div>{/if}
								</div>
							{/each}
						{/if}

					{:else if payment.instructions.type === 'offline'}
						{#if payment.instructions.message}
							<p class="thanks__payment-banner-msg">{payment.instructions.message}</p>
						{/if}
						{#if payment.instructions.handle}
							<p class="thanks__payment-banner-msg">
								Send <strong>{formatMoney(String(Number(order.totals.total_price)))}</strong> to
								<span class="thanks__payment-handle">{payment.instructions.handle}</span>
							</p>
						{/if}
						<div class="thanks__payment-banner-actions">
							{#if payment.instructions.link}
								<a href={payment.instructions.link} target="_blank" rel="noopener" class="thanks__payment-link">
									Open {payment.method_title}
								</a>
							{/if}
							{#if payment.instructions.show_qr && payment.instructions.link}
								<img
									class="thanks__payment-qr"
									src="https://quickchart.io/qr?text={encodeURIComponent(payment.instructions.link)}&size=160&margin=1"
									alt="Payment QR code"
									width="160"
									height="160"
								/>
							{/if}
						</div>
					{/if}
				</div>
			{/if}

			<p class="thanks__eyebrow">Order received</p>
			<h1>Thank you.</h1>
			<p class="thanks__subtitle">
				Your order
				<span class="thanks__num">#{order.id}</span>
				has been placed. A confirmation has been sent to
				<strong>{order.billing_address.email}</strong>.
			</p>

			<section class="thanks__section">
				<h2>Items</h2>
				<ul class="thanks__items">
					{#each order.items as item}
						<li class="thanks__item">
							{#if item.images[0]}
								<div class="thanks__item-img">
									<img src={item.images[0].thumbnail} alt={item.images[0].alt || item.name} />
								</div>
							{/if}
							<div class="thanks__item-body">
								<div class="thanks__item-name">{item.name}</div>
								<div class="thanks__item-qty">Qty: {item.quantity}</div>
							</div>
							<div class="thanks__item-total">{formatMoney(item.totals.line_total)}</div>
						</li>
					{/each}
				</ul>
			</section>

			<section class="thanks__section">
				<h2>Totals</h2>
				<dl class="thanks__totals">
					<dt>Subtotal</dt>
					<dd>{formatMoney(order.totals.total_items)}</dd>
					{#if Number(order.totals.total_shipping) > 0}
						<dt>Shipping</dt>
						<dd>{formatMoney(order.totals.total_shipping)}</dd>
					{/if}
					{#if Number(order.totals.total_discount) > 0}
						<dt>Discount</dt>
						<dd class="thanks__discount">-{formatMoney(order.totals.total_discount)}</dd>
					{/if}
					{#if payment?.fees?.length}
						{#each payment.fees as fee}
							<dt>{fee.name}</dt>
							<dd>{formatMoneyDecimal(fee.total)}</dd>
						{/each}
					{/if}
					{#if Number(order.totals.total_tax) > 0}
						<dt>Tax</dt>
						<dd>{formatMoney(order.totals.total_tax)}</dd>
					{/if}
					<dt class="thanks__total-row">Total</dt>
					<dd class="thanks__total-row">{formatMoney(order.totals.total_price)}</dd>
				</dl>
			</section>

				<section class="thanks__section">
				<h2>Shipping to</h2>
				<address class="thanks__addr">
					{order.shipping_address.first_name} {order.shipping_address.last_name}<br />
					{order.shipping_address.address_1}{order.shipping_address.address_2 ? ', ' + order.shipping_address.address_2 : ''}<br />
					{order.shipping_address.city}, {order.shipping_address.state} {order.shipping_address.postcode}<br />
					{order.shipping_address.country}
				</address>
			</section>

			<div class="thanks__actions">
				<a href="/" class="thanks__cta">Continue shopping</a>
				<a href="/account/orders" class="thanks__link">View all orders</a>
			</div>
		</div>
	{/if}
</section>

<style>
	.thanks {
		max-width: 640px;
		margin: 0 auto;
		padding: 48px 24px 120px;
	}
	.thanks__card {
		background: var(--bg-elevated);
		border: 1px solid var(--border);
		padding: 40px;
	}
	.thanks__eyebrow {
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg-muted);
		margin: 0 0 8px;
	}
	.thanks h1 {
		font-family: var(--font-sans);
		font-size: 40px;
		font-weight: 500;
		letter-spacing: -0.02em;
		margin: 0 0 12px;
		color: var(--fg);
	}
	.thanks__num {
		font-family: var(--font-sans);
		color: var(--fg);
	}
	.thanks__subtitle {
		color: var(--fg-muted);
		line-height: 1.6;
		margin: 0 0 32px;
	}
	.thanks__section {
		margin: 32px 0;
		padding-top: 24px;
		border-top: 1px solid var(--border);
	}
	.thanks__section h2 {
		font-family: var(--font-sans);
		font-size: 14px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--fg-muted);
		margin: 0 0 16px;
	}
	.thanks__items {
		list-style: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 14px;
	}
	.thanks__item {
		display: flex;
		gap: 14px;
		align-items: center;
	}
	.thanks__item-img {
		width: 56px;
		height: 56px;
		flex-shrink: 0;
		background: var(--bg-muted);
	}
	.thanks__item-img img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.thanks__item-body {
		flex: 1;
	}
	.thanks__item-name {
		color: var(--fg);
		font-size: 14px;
	}
	.thanks__item-qty {
		color: var(--fg-muted);
		font-size: 12px;
		margin-top: 2px;
	}
	.thanks__item-total {
		font-family: var(--font-sans);
		color: var(--fg);
	}
	.thanks__totals {
		display: grid;
		grid-template-columns: 1fr auto;
		gap: 6px 20px;
		margin: 0;
	}
	.thanks__totals dt {
		color: var(--fg-muted);
		font-size: 13px;
	}
	.thanks__totals dd {
		margin: 0;
		text-align: right;
		font-family: var(--font-sans);
		color: var(--fg);
		font-size: 13px;
	}
	.thanks__discount {
		color: var(--success);
	}
	.thanks__total-row {
		padding-top: 8px;
		border-top: 1px solid var(--border);
		font-weight: 600;
		font-size: 15px !important;
	}
	.thanks__payment-banner {
		background: var(--bg-muted);
		border: 1px solid var(--border);
		padding: 28px 28px 24px;
		margin: -40px -40px 32px;
		border-bottom: 2px solid var(--accent);
	}
	.thanks__payment-banner-title {
		font-size: 16px;
		font-weight: 600;
		color: var(--fg);
		margin: 0 0 8px;
		text-transform: uppercase;
		letter-spacing: 0.04em;
	}
	.thanks__payment-banner-msg {
		color: var(--fg);
		font-size: 15px;
		font-weight: 500;
		line-height: 1.6;
		margin: 0 0 16px;
	}
	.thanks__payment-banner-actions {
		display: flex;
		align-items: center;
		gap: 20px;
	}
	.thanks__payment-handle {
		font-weight: 700;
		color: var(--accent);
	}
	.thanks__payment-link {
		display: inline-flex;
		align-items: center;
		padding: 12px 24px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		text-decoration: none;
		font-size: 13px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.06em;
	}
	.thanks__payment-link:hover {
		opacity: 0.85;
	}
	.thanks__payment-qr {
		display: block;
	}
	.thanks__bank {
		font-size: 13px;
		line-height: 1.8;
		color: var(--fg);
		padding: 12px 0;
		border-bottom: 1px solid var(--border);
	}
	.thanks__bank--banner {
		border-color: var(--border-strong);
	}
	.thanks__bank:last-child {
		border-bottom: none;
	}
	.thanks__bank-label {
		color: var(--fg-muted);
		min-width: 80px;
		display: inline-block;
	}
	.thanks__addr {
		font-style: normal;
		color: var(--fg);
		line-height: 1.6;
	}
	.thanks__actions {
		margin-top: 36px;
		display: flex;
		gap: 16px;
		align-items: center;
	}
	.thanks__cta {
		display: inline-flex;
		align-items: center;
		padding: 14px 26px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		text-decoration: none;
		font-size: 13px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
	}
	.thanks__cta:hover {
		background: transparent;
		color: var(--accent);
	}
	.thanks__link {
		color: var(--fg-muted);
		font-size: 12px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		text-decoration: underline;
		text-underline-offset: 3px;
	}
	.thanks__msg {
		padding: 40px 0;
		text-align: center;
		color: var(--fg-muted);
	}
</style>
