<script lang="ts">
	import { onMount } from 'svelte';
	import { auth } from '$lib/wc/auth.svelte';
	import {
		AffiliateCouponError,
		affiliateForgotCoupon,
		affiliateForgotPassword,
		affiliateLogin,
		affiliateRegister,
		affiliateResetPassword,
		fetchAffiliateCouponStats,
		fetchAffiliateMe,
		formatExpiresAt,
		formatMoney,
		type AffiliateCouponStats,
		type AffiliateDashboard,
	} from '$lib/wc/affiliate';

	type Gate = 'choose' | 'login' | 'register';
	type Tab = 'track' | 'details' | 'additional';

	let bootLoading = $state(true);
	let dashboard = $state<AffiliateDashboard | null>(null);
	let gate = $state<Gate>('choose');
	let tab = $state<Tab>('track');

	let formError = $state('');
	let formOk = $state('');
	let submitting = $state(false);

	let loginId = $state('');
	let loginPassword = $state('');
	let regName = $state('');
	let regEmail = $state('');
	let regPassword = $state('');

	let trackCode = $state('');
	let trackLoading = $state(false);
	let trackError = $state('');
	let trackStats = $state<AffiliateCouponStats | null>(null);

	let forgotOpen = $state(false);
	let forgotEmail = $state('');
	let forgotLoading = $state(false);
	let forgotError = $state('');
	let forgotOk = $state('');

	let resetOpen = $state(false);
	let resetLogin = $state('');
	let resetLoading = $state(false);
	let resetError = $state('');
	let resetOk = $state('');

	let additionalMsg = $state('');
	let additionalError = $state('');
	let additionalLoading = $state(false);

	onMount(() => {
		let cancelled = false;
		(async () => {
			bootLoading = true;
			try {
				await auth.refresh();
				if (cancelled) return;
				if (!auth.isAuthenticated) return;
				const me = await fetchAffiliateMe();
				if (cancelled || !me) return;
				dashboard = me;
				if (me.coupon) {
					trackStats = me.coupon;
					trackCode = me.coupon.code;
				} else if (me.user.coupon_code) {
					trackCode = me.user.coupon_code;
				}
			} catch {
				/* guest / network — show choose gate */
			} finally {
				bootLoading = false;
			}
		})();
		return () => {
			cancelled = true;
		};
	});

	function clearMessages() {
		formError = '';
		formOk = '';
	}

	async function onRegister(e: Event) {
		e.preventDefault();
		clearMessages();
		submitting = true;
		try {
			dashboard = await affiliateRegister({
				name: regName.trim(),
				email: regEmail.trim(),
				password: regPassword,
			});
			await auth.refresh(true);
			tab = 'track';
			if (dashboard.coupon) {
				trackStats = dashboard.coupon;
				trackCode = dashboard.coupon.code;
			}
		} catch (err) {
			formError = err instanceof AffiliateCouponError ? err.message : 'Could not create account.';
		} finally {
			submitting = false;
		}
	}

	async function onLogin(e: Event) {
		e.preventDefault();
		clearMessages();
		submitting = true;
		try {
			dashboard = await affiliateLogin({
				login: loginId.trim(),
				password: loginPassword,
			});
			await auth.refresh(true);
			tab = 'track';
			if (dashboard.coupon) {
				trackStats = dashboard.coupon;
				trackCode = dashboard.coupon.code;
			} else if (dashboard.user.coupon_code) {
				trackCode = dashboard.user.coupon_code;
			}
		} catch (err) {
			formError = err instanceof AffiliateCouponError ? err.message : 'Could not log in.';
		} finally {
			submitting = false;
		}
	}

	async function onLogout() {
		await auth.logout();
		dashboard = null;
		trackStats = null;
		trackCode = '';
		gate = 'choose';
		tab = 'track';
		clearMessages();
	}

	async function onTrack(e: Event) {
		e.preventDefault();
		const trimmed = trackCode.trim();
		if (!trimmed) {
			trackError = 'Enter your coupon code.';
			trackStats = null;
			return;
		}
		trackLoading = true;
		trackError = '';
		try {
			trackStats = await fetchAffiliateCouponStats(trimmed);
			trackCode = trackStats.code;
		} catch (err) {
			trackStats = null;
			trackError = err instanceof AffiliateCouponError ? err.message : 'Could not load stats.';
		} finally {
			trackLoading = false;
		}
	}

	async function onForgotCoupon(e: Event) {
		e.preventDefault();
		forgotLoading = true;
		forgotError = '';
		forgotOk = '';
		try {
			const res = await affiliateForgotCoupon(forgotEmail.trim());
			trackCode = res.code;
			forgotOk = `${res.message} Your code: ${res.codes.join(', ')}`;
			forgotOpen = false;
		} catch (err) {
			forgotError = err instanceof AffiliateCouponError ? err.message : 'Lookup failed.';
		} finally {
			forgotLoading = false;
		}
	}

	async function onForgotPassword(e: Event) {
		e.preventDefault();
		resetLoading = true;
		resetError = '';
		resetOk = '';
		try {
			const res = await affiliateForgotPassword(resetLogin.trim() || loginId.trim());
			resetOk = res.message;
		} catch (err) {
			resetError = err instanceof AffiliateCouponError ? err.message : 'Could not email a new password.';
		} finally {
			resetLoading = false;
		}
	}

	async function onResetPasswordFromDash() {
		additionalLoading = true;
		additionalError = '';
		additionalMsg = '';
		try {
			const res = await affiliateResetPassword();
			await auth.logout();
			dashboard = null;
			trackStats = null;
			trackCode = '';
			gate = 'login';
			tab = 'track';
			resetOpen = true;
			resetLogin = '';
			resetOk = res.message;
			formError = '';
		} catch (err) {
			additionalError = err instanceof AffiliateCouponError ? err.message : 'Could not email a new password.';
		} finally {
			additionalLoading = false;
		}
	}

	const statusLabel = $derived.by(() => {
		if (!trackStats) return '';
		if (trackStats.status === 'expired') return 'Expired';
		if (trackStats.status === 'exhausted') return 'Usage limit reached';
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
		<h1 id="aff-title" class="aff__title">Affiliate Portal</h1>
		<p class="aff__lead">
			{#if dashboard}
				Welcome back. Track paid-order sales for your coupon, or review your affiliate details.
			{:else}
				Already an affiliate? Log in. New here? Create an account — we’ll generate your 15% coupon automatically.
			{/if}
		</p>
	</header>

	{#if bootLoading}
		<p class="aff__status" role="status">Loading…</p>
	{:else if !dashboard}
		{#if gate === 'choose'}
			<div class="aff__choose">
				<button type="button" class="aff__choose-btn" onclick={() => { gate = 'login'; clearMessages(); }}>
					Already an affiliate
				</button>
				<button type="button" class="aff__choose-btn aff__choose-btn--primary" onclick={() => { gate = 'register'; clearMessages(); }}>
					Become an affiliate
				</button>
			</div>
		{:else if gate === 'login'}
			<div class="aff__form">
				<button type="button" class="aff__back" onclick={() => { gate = 'choose'; clearMessages(); }}>← Back</button>
				<form class="aff__form-inner" onsubmit={onLogin}>
					<label class="aff__field" for="aff-login">
						<span class="aff__label">Email or username</span>
						<input id="aff-login" class="aff__input aff__input--normal" type="text" autocomplete="username" bind:value={loginId} disabled={submitting} required />
					</label>
					<label class="aff__field" for="aff-login-pass">
						<span class="aff__label">Password</span>
						<input id="aff-login-pass" class="aff__input aff__input--normal" type="password" autocomplete="current-password" bind:value={loginPassword} disabled={submitting} required />
					</label>
					<button class="aff__submit" type="submit" disabled={submitting}>{submitting ? 'Signing in…' : 'Log in'}</button>
				</form>
				<div class="aff__row-links">
					<button type="button" class="aff__text-link" onclick={() => { resetOpen = !resetOpen; resetLogin = loginId; }}>Forgot password?</button>
				</div>
				{#if resetOpen}
					<div class="aff__subpanel">
						<form onsubmit={onForgotPassword}>
							<label class="aff__field" for="aff-reset">
								<span class="aff__label">Email or username</span>
								<input id="aff-reset" class="aff__input aff__input--normal" type="text" bind:value={resetLogin} disabled={resetLoading} required />
							</label>
							<button class="aff__submit aff__submit--secondary" type="submit" disabled={resetLoading}>
								{resetLoading ? 'Sending…' : 'Email me a new password'}
							</button>
						</form>
						{#if resetError}<p class="aff__status aff__status--error" role="alert">{resetError}</p>{/if}
						{#if resetOk}<p class="aff__status aff__status--ok" role="status">{resetOk}</p>{/if}
					</div>
				{/if}
				{#if formError}<p class="aff__status aff__status--error" role="alert">{formError}</p>{/if}
				{#if resetOk && !resetOpen}<p class="aff__status aff__status--ok" role="status">{resetOk}</p>{/if}
			</div>
		{:else}
			<form class="aff__form" onsubmit={onRegister}>
				<button type="button" class="aff__back" onclick={() => { gate = 'choose'; clearMessages(); }}>← Back</button>
				<label class="aff__field" for="aff-name">
					<span class="aff__label">Full name</span>
					<input id="aff-name" class="aff__input aff__input--normal" type="text" autocomplete="name" bind:value={regName} disabled={submitting} required />
				</label>
				<label class="aff__field" for="aff-email">
					<span class="aff__label">Email</span>
					<input id="aff-email" class="aff__input aff__input--normal" type="email" autocomplete="email" bind:value={regEmail} disabled={submitting} required />
				</label>
				<label class="aff__field" for="aff-reg-pass">
					<span class="aff__label">Password</span>
					<input id="aff-reg-pass" class="aff__input aff__input--normal" type="password" autocomplete="new-password" bind:value={regPassword} disabled={submitting} minlength="8" required />
				</label>
				<p class="aff__hint">We’ll create your account and a 15% off coupon (unlimited uses), named like <code>yourname-15</code>. Your email is saved on the coupon Description so it can be recovered later.</p>
				<button class="aff__submit" type="submit" disabled={submitting}>{submitting ? 'Creating…' : 'Create affiliate account'}</button>
				{#if formError}<p class="aff__status aff__status--error" role="alert">{formError}</p>{/if}
			</form>
		{/if}
	{:else}
		<div class="aff__dash-top">
			<p class="aff__signed">Signed in as <strong>{dashboard.user.name}</strong></p>
			<button type="button" class="aff__text-link" onclick={onLogout}>Log out</button>
		</div>

		<div class="aff__tabs" role="tablist" aria-label="Affiliate sections">
			<button type="button" role="tab" class="aff__tab" aria-selected={tab === 'track'} class:is-active={tab === 'track'} onclick={() => (tab = 'track')}>Track your sales</button>
			<button type="button" role="tab" class="aff__tab" aria-selected={tab === 'details'} class:is-active={tab === 'details'} onclick={() => (tab = 'details')}>Details</button>
			<button type="button" role="tab" class="aff__tab" aria-selected={tab === 'additional'} class:is-active={tab === 'additional'} onclick={() => (tab = 'additional')}>Additional</button>
		</div>

		{#if tab === 'track'}
			<div class="aff__form">
				<form class="aff__form-inner" onsubmit={onTrack}>
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
							bind:value={trackCode}
							disabled={trackLoading}
							required
						/>
					</label>
					<button class="aff__submit" type="submit" disabled={trackLoading}>
						{trackLoading ? 'Looking up…' : 'Track Now'}
					</button>
				</form>
				<div class="aff__row-links">
					<button type="button" class="aff__text-link" onclick={() => { forgotOpen = !forgotOpen; forgotEmail = dashboard?.user.email ?? ''; }}>Forgot coupon code?</button>
				</div>
			</div>

			{#if forgotOpen}
				<div class="aff__subpanel">
					<form onsubmit={onForgotCoupon}>
						<label class="aff__field" for="aff-forgot-email">
							<span class="aff__label">Email on your coupon</span>
							<input id="aff-forgot-email" class="aff__input aff__input--normal" type="email" bind:value={forgotEmail} disabled={forgotLoading} required />
						</label>
						<p class="aff__hint">We match the email saved in the WooCommerce coupon Description (manual coupons work the same way).</p>
						<button class="aff__submit aff__submit--secondary" type="submit" disabled={forgotLoading}>
							{forgotLoading ? 'Looking up…' : 'Find my code'}
						</button>
					</form>
					{#if forgotError}<p class="aff__status aff__status--error" role="alert">{forgotError}</p>{/if}
					{#if forgotOk}<p class="aff__status aff__status--ok" role="status">{forgotOk}</p>{/if}
				</div>
			{/if}

			{#if trackError}
				<p class="aff__status aff__status--error" role="alert">{trackError}</p>
			{/if}

			{#if trackStats}
				<div class="aff__results" aria-live="polite">
					<div class="aff__meta">
						<div class="aff__code-row">
							<span class="aff__code">{trackStats.code}</span>
							<span class="aff__badge" data-status={trackStats.status}>{statusLabel}</span>
						</div>
						<p class="aff__discount">{trackStats.amount_label}</p>
						{#if trackStats.expires_at || trackStats.usage_limit !== null || trackStats.minimum_amount}
							<ul class="aff__meta-list">
								<li>Expires: {formatExpiresAt(trackStats.expires_at)}</li>
								{#if trackStats.usage_limit !== null}
									<li>
										Usage limit: {trackStats.coupon_recorded_uses} / {trackStats.usage_limit}
										{#if trackStats.usage_remaining !== null}
											({trackStats.usage_remaining} left)
										{/if}
									</li>
								{:else}
									<li>Coupon-recorded uses: {trackStats.coupon_recorded_uses}</li>
								{/if}
								{#if trackStats.minimum_amount}
									<li>
										Minimum order: {formatMoney(
											trackStats.minimum_amount,
											trackStats.currency_symbol,
											trackStats.currency
										)}
									</li>
								{/if}
							</ul>
						{/if}
					</div>

					<div class="aff__stats" role="list">
						<div class="aff__stat" role="listitem">
							<span class="aff__stat-value">{trackStats.orders_count}</span>
							<span class="aff__stat-label">Paid orders</span>
						</div>
						<div class="aff__stat" role="listitem">
							<span class="aff__stat-value">
								{formatMoney(trackStats.orders_revenue, trackStats.currency_symbol, trackStats.currency)}
							</span>
							<span class="aff__stat-label">Order revenue</span>
						</div>
						<div class="aff__stat" role="listitem">
							<span class="aff__stat-value">
								{formatMoney(trackStats.orders_discount_total, trackStats.currency_symbol, trackStats.currency)}
							</span>
							<span class="aff__stat-label">Discount given</span>
						</div>
					</div>

					{#if trackStats.coupon_recorded_uses !== trackStats.orders_count}
						<p class="aff__note">
							WooCommerce recorded {trackStats.coupon_recorded_uses} use{trackStats.coupon_recorded_uses === 1 ? '' : 's'} on the coupon itself; paid orders with this code total {trackStats.orders_count}. Paid orders are the source of truth (pending, failed, cancelled, and fully refunded orders are excluded).
						</p>
					{:else}
						<p class="aff__note">
							Totals include processing and completed orders only. Pending, failed, cancelled, and fully refunded orders are excluded.
						</p>
					{/if}
				</div>
			{/if}
		{:else if tab === 'details'}
			<div class="aff__details">
				<div class="aff__detail-row"><span>Name</span><strong>{dashboard.user.name}</strong></div>
				<div class="aff__detail-row"><span>Email</span><strong>{dashboard.user.email}</strong></div>
				<div class="aff__detail-row"><span>Username</span><strong>{dashboard.user.username}</strong></div>
				<div class="aff__detail-row"><span>Coupon code</span><strong>{dashboard.user.coupon_code ?? 'Not linked yet'}</strong></div>
				{#if dashboard.coupon}
					<div class="aff__detail-row"><span>Discount</span><strong>{dashboard.coupon.amount_label}</strong></div>
					<div class="aff__detail-row"><span>Status</span><strong>{dashboard.coupon.status}</strong></div>
				{/if}
			</div>
		{:else}
			<div class="aff__additional">
				<p class="aff__hint">Account actions for your affiliate login.</p>
				<div class="aff__actions">
					<button
						type="button"
						class="aff__submit aff__submit--secondary"
						disabled={additionalLoading}
						onclick={onResetPasswordFromDash}
					>
						{additionalLoading ? 'Sending…' : 'Email me a new password'}
					</button>
					<button type="button" class="aff__submit aff__submit--danger" onclick={onLogout}>
						Log out
					</button>
				</div>
				{#if additionalError}<p class="aff__status aff__status--error" role="alert">{additionalError}</p>{/if}
				{#if additionalMsg}<p class="aff__status aff__status--ok" role="status">{additionalMsg}</p>{/if}
			</div>
		{/if}
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
	.aff__choose {
		display: grid;
		gap: 12px;
	}
	.aff__choose-btn {
		height: 52px;
		border: 1px solid var(--border);
		border-radius: 12px;
		background: var(--bg);
		color: var(--fg);
		font-size: 15px;
		font-weight: 650;
		cursor: pointer;
	}
	.aff__choose-btn--primary {
		border-color: transparent;
		background: var(--accent);
		color: var(--accent-fg, #fff);
	}
	.aff__form {
		display: flex;
		flex-direction: column;
		gap: 14px;
		margin-bottom: 24px;
	}
	.aff__form-inner {
		display: flex;
		flex-direction: column;
		gap: 14px;
	}
	.aff__back {
		align-self: flex-start;
		border: none;
		background: transparent;
		color: color-mix(in srgb, var(--fg) 65%, transparent);
		font-size: 13px;
		cursor: pointer;
		padding: 0;
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
	.aff__input--normal {
		letter-spacing: normal;
		text-transform: none;
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
	.aff__submit--secondary {
		background: color-mix(in srgb, var(--fg) 8%, var(--bg));
		color: var(--fg);
		border: 1px solid var(--border);
	}
	.aff__submit--danger {
		background: color-mix(in srgb, var(--fg) 8%, var(--bg));
		color: var(--fg);
		border: 1px solid var(--border);
	}
	.aff__submit:hover:not(:disabled) {
		filter: brightness(1.05);
	}
	.aff__submit:disabled {
		opacity: 0.7;
		cursor: wait;
	}
	.aff__hint {
		margin: 0;
		font-size: 12.5px;
		line-height: 1.5;
		color: color-mix(in srgb, var(--fg) 55%, transparent);
	}
	.aff__hint code {
		font-size: 12px;
	}
	.aff__row-links {
		display: flex;
		justify-content: flex-start;
	}
	.aff__text-link {
		border: none;
		background: transparent;
		color: var(--accent);
		font-size: 13px;
		font-weight: 600;
		cursor: pointer;
		padding: 0;
	}
	.aff__subpanel {
		padding: 14px;
		border: 1px solid var(--border);
		border-radius: 12px;
		background: color-mix(in srgb, var(--fg) 3%, var(--bg));
		display: flex;
		flex-direction: column;
		gap: 12px;
	}
	.aff__status {
		margin: 0 0 16px;
		font-size: 14px;
		color: color-mix(in srgb, var(--fg) 70%, transparent);
	}
	.aff__status--error {
		color: var(--accent);
	}
	.aff__status--ok {
		color: color-mix(in srgb, var(--fg) 75%, transparent);
	}
	.aff__dash-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 16px;
	}
	.aff__signed {
		margin: 0;
		font-size: 14px;
		color: color-mix(in srgb, var(--fg) 70%, transparent);
	}
	.aff__tabs {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 8px;
		margin-bottom: 20px;
	}
	.aff__tab {
		height: 42px;
		border: 1px solid var(--border);
		border-radius: 999px;
		background: var(--bg);
		color: color-mix(in srgb, var(--fg) 70%, transparent);
		font-size: 13px;
		font-weight: 650;
		cursor: pointer;
	}
	.aff__tab.is-active {
		border-color: transparent;
		background: var(--accent);
		color: var(--accent-fg, #fff);
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
	.aff__details {
		display: flex;
		flex-direction: column;
		gap: 10px;
		border-top: 1px solid var(--border);
		padding-top: 16px;
	}
	.aff__detail-row {
		display: flex;
		justify-content: space-between;
		gap: 16px;
		padding: 12px 0;
		border-bottom: 1px solid var(--border);
		font-size: 14px;
	}
	.aff__detail-row span {
		color: color-mix(in srgb, var(--fg) 55%, transparent);
	}
	.aff__detail-row strong {
		color: var(--fg);
		font-weight: 650;
		text-align: right;
		word-break: break-word;
	}
	.aff__placeholder {
		padding: 28px 16px;
		border: 1px dashed var(--border);
		border-radius: 12px;
		text-align: center;
		color: color-mix(in srgb, var(--fg) 55%, transparent);
		font-size: 14px;
	}
	.aff__additional {
		display: flex;
		flex-direction: column;
		gap: 14px;
		border-top: 1px solid var(--border);
		padding-top: 16px;
	}
	.aff__actions {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
	@media (max-width: 560px) {
		.aff {
			padding: 24px 18px 64px;
		}
		.aff__stats {
			grid-template-columns: 1fr;
		}
		.aff__tabs {
			grid-template-columns: 1fr;
		}
	}
</style>
