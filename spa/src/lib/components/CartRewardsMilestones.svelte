<script lang="ts">
	import { config } from '$lib/config.svelte';
	import { formatPrice } from '$lib/utils/format';
	import { cart } from '$lib/wc/cart.svelte';

	const rewardsCfg = $derived(config.data.pdp?.slide_cart?.rewards ?? {});
	const enabled = $derived(rewardsCfg.enabled !== false);
	const urgencyLabel = $derived(rewardsCfg.urgency_label?.trim() || 'ENDS SUNDAY');

	const rewards = $derived(cart.cart?.extensions?.wchs_cro?.rewards ?? null);

	const currency = $derived({
		currency_minor_unit: cart.currencyMinorUnit || 2,
		currency_symbol: cart.currencySymbol,
		currency_code: cart.cart?.totals.currency_code ?? 'USD'
	});

	const subtotalMinor = $derived(rewards?.subtotal_minor ?? 0);
	const shipMinor = $derived(
		rewards?.shipping_threshold_minor ??
			Math.round((config.data.shipping_free_threshold || 0) * 10 ** currency.currency_minor_unit)
	);
	const bacMinor = $derived(
		rewards?.bac_water_threshold_minor ??
			Math.round((rewardsCfg.bac_water_threshold ?? 300) * 10 ** currency.currency_minor_unit)
	);
	const trackMaxMinor = $derived(rewards?.track_max_minor ?? Math.max(bacMinor, shipMinor, 1));

	const shippingUnlocked = $derived(
		rewards?.shipping_unlocked ?? (shipMinor > 0 && subtotalMinor >= shipMinor)
	);
	const bacUnlocked = $derived(
		rewards?.bac_water_unlocked ?? subtotalMinor >= bacMinor
	);

	const progressPct = $derived(
		Math.min(100, trackMaxMinor > 0 ? (subtotalMinor / trackMaxMinor) * 100 : 0)
	);
	const shipPosPct = $derived(
		trackMaxMinor > 0 ? Math.min(100, (shipMinor / trackMaxMinor) * 100) : 50
	);

	function money(minor: number): string {
		return formatPrice(minor, currency);
	}

	const headline = $derived.by((): { lead: string; amount: string; tail: string } => {
		if (!shipMinor && !bacMinor) {
			return { lead: '', amount: '', tail: '' };
		}
		if (!shippingUnlocked && shipMinor > 0) {
			const remaining = Math.max(0, shipMinor - subtotalMinor);
			return { lead: 'Add ', amount: money(remaining), tail: ' for free shipping' };
		}
		if (!bacUnlocked && bacMinor > 0) {
			const remaining = Math.max(0, bacMinor - subtotalMinor);
			return { lead: 'Add ', amount: money(remaining), tail: ' for free 10ml BAC water' };
		}
		return { lead: 'All cart rewards unlocked', amount: '', tail: '' };
	});

	const allUnlocked = $derived(shippingUnlocked && bacUnlocked);

	const showBar = $derived(
		enabled && trackMaxMinor > 0 && (shipMinor > 0 || bacMinor > 0) && subtotalMinor >= 0
	);
</script>

{#if showBar}
	<div
		class="fkcart-rewards"
		class:is-complete={allUnlocked}
		aria-label="Cart rewards progress"
	>
		<p class="fkcart-rewards__headline">
			{#if headline.amount}
				{headline.lead}<strong class="fkcart-rewards__amount">{headline.amount}</strong>{headline.tail}
			{:else}
				<strong>{headline.lead}</strong>
			{/if}
		</p>

		<div class="fkcart-rewards__track-wrap">
			<div class="fkcart-rewards__track-inner">
				<div class="fkcart-rewards__track" aria-hidden="true">
					<div class="fkcart-rewards__fill" style="width: {progressPct}%"></div>
				</div>

				{#if shipMinor > 0}
					<div
						class="fkcart-rewards__node"
						class:is-unlocked={shippingUnlocked}
						style="left: {shipPosPct}%"
					>
						<div class="fkcart-rewards__icon-wrap">
							{#if shippingUnlocked}
								<span class="fkcart-rewards__check" aria-hidden="true">
									<svg viewBox="0 0 16 16" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.2">
										<path d="M3.5 8.2 6.4 11 12.5 5" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
								</span>
							{/if}
							<span class="fkcart-rewards__icon">
								<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
									<path d="M3 7h13l3 5v5H3V7z" stroke-linejoin="round" />
									<circle cx="7.5" cy="17.5" r="1.5" />
									<circle cx="16.5" cy="17.5" r="1.5" />
									<path d="M16 7V4H3" stroke-linecap="round" />
								</svg>
							</span>
						</div>
						<span class="fkcart-rewards__label">Free shipping</span>
						{#if urgencyLabel && !shippingUnlocked}
							<span class="fkcart-rewards__urgency">{urgencyLabel}</span>
						{/if}
					</div>
				{/if}

				{#if bacMinor > 0}
					<div
						class="fkcart-rewards__node fkcart-rewards__node--bac"
						class:is-unlocked={bacUnlocked}
					>
						<div class="fkcart-rewards__icon-wrap">
							{#if bacUnlocked}
								<span class="fkcart-rewards__check" aria-hidden="true">
									<svg viewBox="0 0 16 16" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.2">
										<path d="M3.5 8.2 6.4 11 12.5 5" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
								</span>
							{/if}
							<span class="fkcart-rewards__icon">
								<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
									<path d="M9 3h6v3H9z" stroke-linejoin="round" />
									<rect x="8" y="6" width="8" height="14" rx="2" />
									<path d="M10 10h4M10 14h4" stroke-linecap="round" />
								</svg>
							</span>
						</div>
						<span class="fkcart-rewards__label">Free 10ml BAC</span>
						{#if urgencyLabel && !bacUnlocked}
							<span class="fkcart-rewards__urgency">{urgencyLabel}</span>
						{/if}
					</div>
				{/if}
			</div>
		</div>
	</div>
{/if}

<style>
	.fkcart-rewards {
		width: 100%;
		max-width: 100%;
		min-width: 0;
		box-sizing: border-box;
		margin-top: 10px;
		padding: 12px 14px 8px;
		border-radius: 12px;
		border: 1px solid color-mix(in srgb, var(--accent) 28%, var(--border));
		background: linear-gradient(
			180deg,
			color-mix(in srgb, var(--accent) 10%, var(--bg)) 0%,
			color-mix(in srgb, var(--accent) 4%, var(--bg)) 100%
		);
		box-shadow:
			0 1px 0 color-mix(in srgb, white 35%, transparent) inset,
			0 6px 20px color-mix(in srgb, var(--accent) 12%, transparent);
	}

	.fkcart-rewards.is-complete {
		border-color: color-mix(in srgb, #22c55e 32%, var(--border));
		background: linear-gradient(
			180deg,
			color-mix(in srgb, #22c55e 10%, var(--bg)) 0%,
			color-mix(in srgb, #22c55e 4%, var(--bg)) 100%
		);
		box-shadow:
			0 1px 0 color-mix(in srgb, white 35%, transparent) inset,
			0 6px 20px color-mix(in srgb, #22c55e 10%, transparent);
	}

	.fkcart-rewards__headline {
		margin: 0 0 12px;
		padding: 0;
		font-size: 13px;
		font-weight: 600;
		line-height: 1.45;
		text-align: center;
		color: var(--fg);
		text-wrap: balance;
		overflow: visible;
	}

	.fkcart-rewards__headline strong {
		font-weight: 800;
	}

	.fkcart-rewards.is-complete .fkcart-rewards__headline {
		color: color-mix(in srgb, #15803d 82%, var(--fg));
	}

	.fkcart-rewards__amount {
		color: var(--accent);
		font-weight: 800;
	}

	.fkcart-rewards__track-wrap {
		width: 100%;
		min-width: 0;
	}

	.fkcart-rewards__track-inner {
		position: relative;
		padding: 0 0 52px;
	}

	.fkcart-rewards__track {
		position: relative;
		height: 5px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--fg) 12%, var(--bg));
		overflow: hidden;
	}

	.fkcart-rewards__fill {
		height: 100%;
		border-radius: inherit;
		background: linear-gradient(
			90deg,
			color-mix(in srgb, var(--accent) 82%, #fff 18%),
			var(--accent)
		);
		box-shadow: 0 0 14px color-mix(in srgb, var(--accent) 55%, transparent);
		transition: width 420ms var(--ease-out);
	}

	.fkcart-rewards__node {
		position: absolute;
		top: -11px;
		transform: translateX(-50%);
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 5px;
		width: max-content;
		max-width: min(108px, 34vw);
	}

	.fkcart-rewards__node--bac {
		left: auto;
		right: 0;
		transform: none;
	}

	.fkcart-rewards__icon-wrap {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.fkcart-rewards__icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 28px;
		height: 28px;
		border-radius: 50%;
		border: 2px solid color-mix(in srgb, var(--fg) 16%, var(--border));
		background: var(--bg);
		color: var(--fg-muted);
		transition:
			border-color 240ms var(--ease-out),
			background 240ms var(--ease-out),
			color 240ms var(--ease-out),
			box-shadow 240ms var(--ease-out);
	}

	.fkcart-rewards__node.is-unlocked .fkcart-rewards__icon {
		border-color: var(--accent);
		background: color-mix(in srgb, var(--accent) 12%, var(--bg));
		color: var(--accent);
		box-shadow:
			0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent),
			0 0 20px color-mix(in srgb, var(--accent) 42%, transparent);
	}

	.fkcart-rewards__check {
		position: absolute;
		top: -3px;
		right: -3px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 14px;
		height: 14px;
		border-radius: 50%;
		background: #22c55e;
		color: #fff;
		box-shadow: 0 0 0 2px var(--bg);
	}

	.fkcart-rewards__label {
		font-size: 8px;
		font-weight: 700;
		line-height: 1.2;
		text-align: center;
		letter-spacing: 0.03em;
		text-transform: uppercase;
		color: var(--fg-muted);
		white-space: normal;
	}

	.fkcart-rewards__node.is-unlocked .fkcart-rewards__label {
		color: var(--fg);
	}

	.fkcart-rewards__urgency {
		display: inline-block;
		margin-top: 1px;
		padding: 3px 7px;
		border-radius: 999px;
		background: #fef08a;
		color: #713f12;
		font-size: 8px;
		font-weight: 800;
		line-height: 1;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		white-space: nowrap;
	}

	:global([data-theme='dark']) .fkcart-rewards__urgency {
		background: color-mix(in srgb, #fef08a 88%, #422006 12%);
		color: #fef08a;
	}
</style>
