<script lang="ts">
	import { auth } from '$lib/wc/auth.svelte';
	import { config } from '$lib/config.svelte';
</script>

{#if auth.isAdmin}
	{@const wp = config.data.wp_origin || 'http://localhost:8099'}
	{@const mode = config.data.access_mode}
	<div class="admin-bar">
		<div class="admin-bar__inner">
			<span class="admin-bar__label">Admin</span>
			<nav class="admin-bar__links">
				<!-- /wp-admin/ (bare directory) gets 403'd by Siteground's NGINX.
				     Link to index.php explicitly so the request passes through
				     to PHP regardless of host. -->
				<a href="{wp}/wp-admin/index.php">Dashboard</a>
				<a href="{wp}/wp-admin/edit.php?post_type=shop_order&post_status=wc-on-hold">Orders</a>
				<a href="{wp}/wp-admin/edit.php?post_type=product">Products</a>
				<a href="{wp}/wp-admin/admin.php?page=wchs-settings">WCHS</a>
				<a href="{wp}/wp-admin/admin.php?page=wc-settings">WC Settings</a>
			</nav>
			<span class="admin-bar__user">{auth.user?.display_name}</span>
		</div>
	</div>
	{#if mode !== 3}
		<div class="admin-mode-banner" class:admin-mode-banner--red={mode === 0} class:admin-mode-banner--amber={mode === 1} class:admin-mode-banner--blue={mode === 2}>
			<span>
				{#if mode === 0}
					Site is in maintenance mode - only admins can access
				{:else if mode === 1}
					Site is locked - only registered members can access
				{:else if mode === 2}
					Site is in browse-only mode - guests cannot checkout
				{/if}
			</span>
			<a href="{wp}/wp-admin/admin.php?page=wchs-settings&tab=security" class="admin-mode-banner__link">Change</a>
		</div>
	{/if}
{/if}

<style>
	.admin-bar {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 9999;
		height: 32px;
		background: #1d2327;
		color: #c3c4c7;
		font-family: var(--font-sans);
		font-size: 12px;
		line-height: 32px;
	}

	.admin-bar__inner {
		display: flex;
		align-items: center;
		gap: 0;
		max-width: 100%;
		padding: 0 12px;
		height: 32px;
	}

	.admin-bar__label {
		font-weight: 600;
		color: #fff;
		letter-spacing: 0.02em;
		padding-right: 12px;
		margin-right: 4px;
		border-right: 1px solid #3c434a;
	}

	.admin-bar__links {
		display: flex;
		gap: 0;
		flex: 1;
		min-width: 0;
	}

	.admin-bar__links a {
		color: #c3c4c7;
		text-decoration: none;
		padding: 0 10px;
		transition: color 0.15s, background 0.15s;
		white-space: nowrap;
	}

	.admin-bar__links a:hover {
		color: #72aee6;
		background: rgba(255, 255, 255, 0.04);
	}

	.admin-bar__user {
		margin-left: auto;
		color: #c3c4c7;
		font-size: 11px;
		white-space: nowrap;
	}

	@media (max-width: 640px) {
		.admin-bar__links a {
			padding: 0 6px;
			font-size: 11px;
		}
		.admin-bar__user {
			display: none;
		}
	}

	/* Access mode banner */
	.admin-mode-banner {
		position: fixed;
		top: 32px;
		left: 0;
		right: 0;
		z-index: 9998;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 12px;
		height: 28px;
		font-family: var(--font-sans);
		font-size: 11px;
		font-weight: 500;
		letter-spacing: 0.02em;
		color: #fff;
	}
	.admin-mode-banner--red {
		background: #b91c1c;
	}
	.admin-mode-banner--amber {
		background: #b45309;
	}
	.admin-mode-banner--blue {
		background: #1d4ed8;
	}
	.admin-mode-banner__link {
		color: rgba(255, 255, 255, 0.8);
		text-decoration: underline;
		text-underline-offset: 2px;
	}
	.admin-mode-banner__link:hover {
		color: #fff;
	}
</style>
