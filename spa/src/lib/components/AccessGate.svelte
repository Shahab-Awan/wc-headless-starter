<script lang="ts">
	/**
	 * AccessGate — UX layer for access control. Wraps page content and
	 * shows a "membership required" message instead of the content when
	 * the access mode blocks the current user.
	 *
	 * IMPORTANT: This is NOT the security boundary. The API layer
	 * (headless-access-control.php) is the real gate. This component
	 * prevents the user from seeing a broken page with a raw 403 error.
	 *
	 * Usage:
	 *   <AccessGate requires="products">
	 *     <ShopContent />
	 *   </AccessGate>
	 *
	 * `requires` levels:
	 *   - "products": blocked in Mode 1, allowed in 2+3
	 *   - "cart": blocked in Mode 1+2, allowed in 3
	 */
	import { config } from '$lib/config.svelte';
	import { auth } from '$lib/wc/auth.svelte';

	let { requires = 'products', children }: {
		requires?: 'products' | 'cart';
		children?: any;
	} = $props();

	const blocked = $derived.by(() => {
		// Authenticated AND verified users always pass
		if (auth.isAuthenticated && auth.isVerified) return false;
		// Admins always pass
		if (auth.isAdmin) return false;
		const mode = config.data.access_mode;
		if (requires === 'products' && (mode === 0 || mode === 1)) return true;
		if (requires === 'cart' && (mode === 0 || mode === 1 || mode === 2)) return true;
		return false;
	});

	/** Authenticated but email not verified — needs verification, not login. */
	const needsVerification = $derived(auth.isAuthenticated && !auth.isVerified);

	const loginUrl = $derived(config.myAccountUrl());
</script>

{#if blocked}
	<div class="access-gate">
		<div class="access-gate__inner">
			{#if needsVerification}
				<p class="access-gate__eyebrow">Verification required</p>
				<h2 class="access-gate__title">Check your email</h2>
				<p class="access-gate__lede">
					We sent a verification code to <strong>{auth.user?.email}</strong>. Verify your email address to access the store.
				</p>
				<div class="access-gate__actions">
					<a href={loginUrl} class="access-gate__cta" data-sveltekit-reload>Verify email</a>
				</div>
			{:else}
				<p class="access-gate__eyebrow">Members only</p>
				<h2 class="access-gate__title">
					{#if config.data.access_mode === 1}
						Sign in to access the store
					{:else}
						Sign in to continue
					{/if}
				</h2>
				<p class="access-gate__lede">
					{#if config.data.access_mode === 1}
						This store is available to registered members. Create an account or sign in to browse products and place orders.
					{:else}
						You need an account to add items to your cart and checkout.
					{/if}
				</p>
				<div class="access-gate__actions">
					<a href={loginUrl} class="access-gate__cta" data-sveltekit-reload>Sign in or register</a>
				</div>
			{/if}
		</div>
	</div>
{:else}
	{@render children?.()}
{/if}

<style>
	.access-gate {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 50vh;
		padding: 48px 24px;
	}
	.access-gate__inner {
		max-width: 480px;
		text-align: center;
	}
	.access-gate__eyebrow {
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.14em;
		color: var(--fg-muted);
		margin: 0 0 16px;
	}
	.access-gate__title {
		font-size: 28px;
		font-weight: 500;
		letter-spacing: -0.03em;
		line-height: 1.2;
		margin: 0 0 12px;
		color: var(--fg);
	}
	.access-gate__lede {
		font-size: 14px;
		line-height: 1.6;
		color: var(--fg-muted);
		margin: 0 0 28px;
	}
	.access-gate__cta {
		display: inline-flex;
		padding: 14px 28px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		text-decoration: none;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		transition: opacity var(--dur-fast) var(--ease);
	}
	.access-gate__cta:hover {
		opacity: 0.85;
	}
</style>
