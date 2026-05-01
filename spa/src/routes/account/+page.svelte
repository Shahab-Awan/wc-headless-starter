<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { goto } from '$app/navigation';
	import { config } from '$lib/config.svelte';
	import { auth } from '$lib/wc/auth.svelte';

	let loggingOut = $state(false);

	onMount(async () => {
		await config.load();
		// Always re-check on mount — the session could have changed on
		// another tab, or the user just returned from WP login.
		await auth.refresh(true);

		// Re-check on tab focus (user may have logged in/out in another tab).
		window.addEventListener('focus', onFocus);
	});

	onDestroy(() => {
		if (typeof window !== 'undefined') {
			window.removeEventListener('focus', onFocus);
		}
	});

	function onFocus() {
		auth.refresh(true);
	}

	async function handleLogout(e: Event) {
		e.preventDefault();
		if (loggingOut) return;
		loggingOut = true;
		try {
			await auth.logout();
			await goto('/', { invalidateAll: true });
		} finally {
			loggingOut = false;
		}
	}

	const loading = $derived(
		auth.state.status === 'unknown' || auth.state.status === 'loading'
	);
	const user = $derived(auth.user);
	const customerName = $derived(
		user
			? [user.first_name, user.last_name].filter(Boolean).join(' ') ||
					user.display_name ||
					user.email
			: ''
	);
	const customerEmail = $derived(user?.email ?? '');

	const loginUrl = $derived(config.myAccountUrl());
	const editAddressUrl = $derived(config.myAccountPage('edit-address/'));
	const editAccountUrl = $derived(config.myAccountPage('edit-account/'));
	const paymentMethodsUrl = $derived(config.myAccountPage('payment-methods/'));
</script>

<svelte:head><title>Account | {config.data.brand_name}</title></svelte:head>

<section class="account">
	<h1>Account</h1>

	{#if loading}
		<p class="account__msg">Loading…</p>
	{:else if !user}
		<div class="account__card">
			<p>You are not signed in.</p>
			<a class="account__cta" href={loginUrl} data-sveltekit-reload>Sign in</a>
			<p class="account__register-hint">Don't have an account? <a href={loginUrl} data-sveltekit-reload>Register here</a></p>
		</div>
	{:else}
		{#if !auth.isVerified}
			<div class="account__verify-banner">
				<p class="account__verify-text">Your email address has not been verified. Check your inbox for a verification code.</p>
				<a class="account__verify-link" href={loginUrl} data-sveltekit-reload>Verify now</a>
			</div>
		{/if}
		<div class="account__card">
			<p class="account__eyebrow">Signed in as</p>
			<p class="account__name">{customerName}</p>
			{#if customerEmail}
				<p class="account__email">{customerEmail}</p>
			{/if}
		</div>

		<nav class="account__nav">
			<a href="/account/orders">Orders</a>
			<a href={editAddressUrl} data-sveltekit-reload>Addresses</a>
			<a href={editAccountUrl} data-sveltekit-reload>Account details</a>
			<a href={paymentMethodsUrl} data-sveltekit-reload>Payment methods</a>
			<button
				type="button"
				class="account__logout"
				onclick={handleLogout}
				disabled={loggingOut}
			>
				{loggingOut ? 'Logging out…' : 'Log out'}
			</button>
		</nav>
	{/if}
</section>

<style>
	.account {
		max-width: 720px;
		margin: 0 auto;
		padding: 56px 28px 120px;
	}
	.account h1 {
		font-family: var(--font-sans);
		font-size: clamp(38px, 5vw, 52px);
		font-weight: 500;
		line-height: 1;
		letter-spacing: -0.025em;
		margin: 0 0 40px;
		color: var(--fg);
	}
	.account__card {
		background: var(--bg-elevated);
		border: 1px solid var(--border);
		border-radius: var(--radius-md);
		padding: 32px;
		margin-bottom: 28px;
	}
	.account__eyebrow {
		font-size: 11px;
		font-weight: 450;
		text-transform: uppercase;
		letter-spacing: 0.12em;
		color: var(--fg-muted);
		margin: 0 0 10px;
	}
	.account__name {
		margin: 0;
		font-family: var(--font-sans);
		font-size: 22px;
		font-weight: 500;
		letter-spacing: -0.4px;
		color: var(--fg);
	}
	.account__email {
		margin: 8px 0 0;
		color: var(--fg-muted);
		font-size: 13px;
		letter-spacing: -0.16px;
	}
	.account__cta {
		display: inline-flex;
		padding: 14px 28px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		border-radius: var(--radius-sm);
		text-decoration: none;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		margin-top: 16px;
		transition:
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease);
	}
	.account__cta:hover {
		background: transparent;
		color: var(--accent);
	}
	.account__register-hint {
		font-size: 13px;
		color: var(--fg-muted);
		margin: 12px 0 0;
	}
	.account__register-hint a {
		color: var(--fg);
		text-decoration: underline;
		text-underline-offset: 2px;
	}
	.account__nav {
		display: flex;
		flex-direction: column;
		border: 1px solid var(--border);
		border-radius: var(--radius-md);
		background: var(--bg-elevated);
		overflow: hidden;
	}
	.account__nav a {
		display: block;
		padding: 18px 22px;
		color: var(--fg);
		text-decoration: none;
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		border-bottom: 1px solid var(--border);
		transition: background var(--dur-fast) var(--ease);
	}
	.account__nav a:last-child,
	.account__nav button:last-child {
		border-bottom: 0;
	}
	.account__nav a:hover,
	.account__nav button:hover {
		background: var(--bg-muted);
	}
	.account__logout {
		display: block;
		width: 100%;
		padding: 18px 22px;
		color: var(--danger);
		background: transparent;
		border: 0;
		border-bottom: 1px solid var(--border);
		font-family: inherit;
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		text-align: left;
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease);
	}
	.account__logout:disabled {
		opacity: 0.5;
		cursor: default;
	}
	.account__verify-banner {
		background: color-mix(in oklch, var(--warning, #f59e0b) 12%, var(--bg));
		border: 1px solid color-mix(in oklch, var(--warning, #f59e0b) 30%, var(--border));
		border-radius: var(--radius-md);
		padding: 20px 24px;
		margin-bottom: 20px;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 16px;
	}
	.account__verify-text {
		margin: 0;
		font-size: 13px;
		color: var(--fg);
		line-height: 1.5;
	}
	.account__verify-link {
		flex-shrink: 0;
		padding: 10px 20px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		border-radius: var(--radius-sm);
		text-decoration: none;
		font-size: 11px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		transition: opacity var(--dur-fast) var(--ease);
	}
	.account__verify-link:hover {
		opacity: 0.85;
	}
	.account__msg {
		padding: 40px 0;
		text-align: center;
		color: var(--fg-muted);
	}
</style>
