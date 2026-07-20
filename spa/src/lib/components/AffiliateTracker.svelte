<script lang="ts">
	import {
		AffiliateCouponError,
		fetchAffiliateCouponStats,
		formatExpiresAt,
		formatMoney,
		type AffiliateCouponStats,
	} from '$lib/wc/affiliate-coupon';

	let code = $state('');
	let loading = $state(false);
	let error = $state('');
	let stats = $state<AffiliateCouponStats | null>(null);

	async function track(e: Event) {
		e.preventDefault();
		const trimmed = code.trim();
		if (!trimmed) {
			error = 'Enter your coupon code.';
			stats = null;
			return;
		}

		loading = true;
		error = '';
		stats = null;

		try {
			stats = await fetchAffiliateCouponStats(trimmed);
			code = stats.code;
		} catch (err) {
			if (err instanceof AffiliateCouponError) {
				error = err.message;
			} else {
				error = 'Could not load stats. Please try again.';
			}
		} finally {
			loading = false;
		}
	}

	const statusLabel = $derived.by(() => {
		if (!stats) return '';
		if (stats.status === 'expired') return 'Expired';
		if (stats.status === 'exhausted') return 'Usage limit reached';
		return 'Active';
	});
</script>

<section class="aff" aria-labelledby="aff-title">
	<header class="aff__hero">
		<div class="aff__hero-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.75">
				<path d="M3 3v18h18"/>
				<path d="M7 14l4-4 3 3 5-6"/>
			</svg>
		</div>
		<h1 id="aff-title" class="aff__title">Affiliate Tracker</h1>
		<p class="aff__lead">
			Enter your coupon code to see paid-order usage and totals. Stats come from completed store orders that used this code — not estimates.
		</p>
	</header>

	<form class="aff__form" onsubmit={track}>
		<label class="aff__field" for="aff-code">
			<span class="aff__label">Coupon code</span>
			<input
				id="aff-code"
				class="aff__input"
				type="text"
				name="code"
				placeholder="YOURCODE"
				autocomplete="off"
				autocapitalize="characters"
				spellcheck="false"
				bind:value={code}
				disabled={loading}
				required
			/>
		</label>
		<button class="aff__submit" type="submit" disabled={loading}>
			{loading ? 'Looking up…' : 'Track Now'}
		</button>
	</form>

	{#if error}
		<p class="aff__status aff__status--error" role="alert">{error}</p>
	{/if}

	{#if stats}
		<div class="aff__results" aria-live="polite">
			<div class="aff__meta">
				<div class="aff__code-row">
					<span class="aff__code">{stats.code}</span>
					<span class="aff__badge" data-status={stats.status}>{statusLabel}</span>
				</div>
				<p class="aff__discount">{stats.amount_label}</p>
				{#if stats.expires_at || stats.usage_limit !== null || stats.minimum_amount}
					<ul class="aff__meta-list">
						<li>Expires: {formatExpiresAt(stats.expires_at)}</li>
						{#if stats.usage_limit !== null}
							<li>
								Usage limit: {stats.coupon_recorded_uses} / {stats.usage_limit}
								{#if stats.usage_remaining !== null}
									({stats.usage_remaining} left)
								{/if}
							</li>
						{:else}
							<li>Coupon-recorded uses: {stats.coupon_recorded_uses}</li>
						{/if}
						{#if stats.minimum_amount}
							<li>
								Minimum order: {formatMoney(
									stats.minimum_amount,
									stats.currency_symbol,
									stats.currency
								)}
							</li>
						{/if}
					</ul>
				{/if}
			</div>

			<div class="aff__stats" role="list">
				<div class="aff__stat" role="listitem">
					<span class="aff__stat-value">{stats.orders_count}</span>
					<span class="aff__stat-label">Paid orders</span>
				</div>
				<div class="aff__stat" role="listitem">
					<span class="aff__stat-value">
						{formatMoney(stats.orders_revenue, stats.currency_symbol, stats.currency)}
					</span>
					<span class="aff__stat-label">Order revenue</span>
				</div>
				<div class="aff__stat" role="listitem">
					<span class="aff__stat-value">
						{formatMoney(stats.orders_discount_total, stats.currency_symbol, stats.currency)}
					</span>
					<span class="aff__stat-label">Discount given</span>
				</div>
			</div>

			{#if stats.coupon_recorded_uses !== stats.orders_count}
				<p class="aff__note">
					WooCommerce recorded {stats.coupon_recorded_uses} use{stats.coupon_recorded_uses === 1 ? '' : 's'} on the coupon itself; paid orders with this code total {stats.orders_count}. Paid orders are the source of truth (pending, failed, cancelled, and fully refunded orders are excluded).
				</p>
			{:else}
				<p class="aff__note">
					Totals include processing and completed orders only. Pending, failed, cancelled, and fully refunded orders are excluded.
				</p>
			{/if}
		</div>
	{/if}
</section>

<style>
	.aff {
		width: 100%;
		max-width: 720px;
		margin: 0 auto;
		padding: 32px 28px 80px;
		box-sizing: border-box;
	}
	.aff__hero {
		text-align: center;
		margin-bottom: 28px;
	}
	.aff__hero-icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 56px;
		height: 56px;
		border-radius: 14px;
		background: color-mix(in srgb, var(--accent) 14%, transparent);
		color: var(--accent);
		margin-bottom: 16px;
	}
	.aff__title {
		font-size: clamp(28px, 4vw, 40px);
		font-weight: 700;
		color: var(--fg);
		margin: 0 0 12px;
		letter-spacing: -0.02em;
	}
	.aff__lead {
		margin: 0 auto;
		max-width: 520px;
		font-size: 15px;
		line-height: 1.55;
		color: color-mix(in srgb, var(--fg) 65%, transparent);
	}
	.aff__form {
		display: flex;
		flex-direction: column;
		gap: 14px;
		margin-bottom: 24px;
	}
	.aff__field {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
	.aff__label {
		font-size: 13px;
		font-weight: 600;
		color: color-mix(in srgb, var(--fg) 75%, transparent);
	}
	.aff__input {
		height: 52px;
		padding: 0 18px;
		border: 1px solid var(--border);
		border-radius: 12px;
		background: var(--bg);
		color: var(--fg);
		font-size: 16px;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		outline: none;
	}
	.aff__input:focus {
		border-color: var(--accent);
		box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
	}
	.aff__input:disabled {
		opacity: 0.7;
	}
	.aff__submit {
		height: 52px;
		border: none;
		border-radius: 12px;
		background: var(--accent);
		color: var(--accent-fg, #fff);
		font-size: 15px;
		font-weight: 650;
		cursor: pointer;
	}
	.aff__submit:hover:not(:disabled) {
		filter: brightness(1.05);
	}
	.aff__submit:disabled {
		opacity: 0.7;
		cursor: wait;
	}
	.aff__status {
		margin: 0 0 16px;
		font-size: 14px;
		color: color-mix(in srgb, var(--fg) 70%, transparent);
	}
	.aff__status--error {
		color: #c0392b;
	}
	:global([data-theme='dark']) .aff__status--error {
		color: #ff8a80;
	}
	.aff__results {
		display: flex;
		flex-direction: column;
		gap: 20px;
		padding-top: 8px;
		border-top: 1px solid var(--border);
	}
	.aff__meta {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
	.aff__code-row {
		display: flex;
		align-items: center;
		gap: 10px;
		flex-wrap: wrap;
	}
	.aff__code {
		font-size: 22px;
		font-weight: 700;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		color: var(--fg);
	}
	.aff__badge {
		display: inline-flex;
		align-items: center;
		padding: 4px 10px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 650;
		background: color-mix(in srgb, var(--accent) 16%, transparent);
		color: var(--accent);
	}
	.aff__badge[data-status='expired'],
	.aff__badge[data-status='exhausted'] {
		background: color-mix(in srgb, #c0392b 16%, transparent);
		color: #c0392b;
	}
	:global([data-theme='dark']) .aff__badge[data-status='expired'],
	:global([data-theme='dark']) .aff__badge[data-status='exhausted'] {
		color: #ff8a80;
	}
	.aff__discount {
		margin: 0;
		font-size: 15px;
		color: color-mix(in srgb, var(--fg) 72%, transparent);
	}
	.aff__meta-list {
		margin: 4px 0 0;
		padding: 0;
		list-style: none;
		display: flex;
		flex-direction: column;
		gap: 4px;
		font-size: 13px;
		color: color-mix(in srgb, var(--fg) 60%, transparent);
	}
	.aff__stats {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 12px;
	}
	.aff__stat {
		display: flex;
		flex-direction: column;
		gap: 6px;
		padding: 16px 14px;
		border: 1px solid var(--border);
		border-radius: 12px;
		background: color-mix(in srgb, var(--fg) 3%, var(--bg));
	}
	.aff__stat-value {
		font-size: clamp(18px, 3.2vw, 24px);
		font-weight: 700;
		color: var(--fg);
		letter-spacing: -0.02em;
		line-height: 1.15;
		word-break: break-word;
	}
	.aff__stat-label {
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: color-mix(in srgb, var(--fg) 55%, transparent);
	}
	.aff__note {
		margin: 0;
		font-size: 12.5px;
		line-height: 1.5;
		color: color-mix(in srgb, var(--fg) 55%, transparent);
	}
	@media (max-width: 560px) {
		.aff {
			padding: 24px 18px 64px;
		}
		.aff__stats {
			grid-template-columns: 1fr;
		}
	}
</style>
