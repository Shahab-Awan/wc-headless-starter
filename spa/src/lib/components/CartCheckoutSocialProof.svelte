<script lang="ts">
	import { browser } from '$app/environment';
	import { config } from '$lib/config.svelte';

	const sp = $derived(config.data.pdp?.slide_cart?.social_proof ?? {});
	const enabled = $derived(sp.enabled !== false);
	const suffix = $derived(sp.suffix?.trim() || 'researchers checking out now');
	const liveLabel = $derived(sp.live_label?.trim() || 'LIVE');
	const avatars = $derived(
		(sp.avatars ?? ['JM', 'AH', 'RT']).map((a) => a.trim()).filter(Boolean).slice(0, 4)
	);

	let count = $state(0);

	$effect(() => {
		if (!browser || !enabled) return;
		const min = Math.max(1, sp.count_min ?? 18);
		const max = Math.max(min, sp.count_max ?? 32);
		const day = new Date().toISOString().slice(0, 10);
		const storeKey = `wchs_cart_checkout_count_${day}`;
		const stored = sessionStorage.getItem(storeKey);
		if (stored) {
			const parsed = Number.parseInt(stored, 10);
			count = Number.isFinite(parsed) ? parsed : min;
			return;
		}
		const next = min + Math.floor(Math.random() * (max - min + 1));
		sessionStorage.setItem(storeKey, String(next));
		count = next;
	});
</script>

{#if enabled && count > 0}
	<div class="fkcart-social" role="status" aria-live="polite">
		<span class="fkcart-social__live">
			<span class="fkcart-social__live-dot" aria-hidden="true"></span>
			{liveLabel}
		</span>
		<p class="fkcart-social__copy">
			<strong class="fkcart-social__count tabular-nums">{count}</strong>
			<span>{suffix}</span>
		</p>
		{#if avatars.length}
			<div class="fkcart-social__avatars" aria-hidden="true">
				{#each avatars as initials, i (initials + i)}
					<span class="fkcart-social__avatar" style="--avatar-i: {i}">{initials}</span>
				{/each}
			</div>
		{/if}
	</div>
{/if}

<style>
	.fkcart-social {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 10px 12px;
		border: 1px solid var(--border);
		border-radius: var(--radius-md, 10px);
		background: var(--bg-elevated, var(--bg));
	}

	.fkcart-social__live {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		flex: 0 0 auto;
		padding: 4px 8px;
		border-radius: 999px;
		background: color-mix(in srgb, #22c55e 14%, var(--bg) 86%);
		color: #15803d;
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.08em;
		line-height: 1;
		text-transform: uppercase;
		white-space: nowrap;
	}

	:global([data-theme='dark']) .fkcart-social__live {
		color: #86efac;
		background: color-mix(in srgb, #22c55e 22%, var(--bg) 78%);
	}

	.fkcart-social__live-dot {
		width: 6px;
		height: 6px;
		border-radius: 50%;
		background: #22c55e;
		box-shadow: 0 0 0 2px color-mix(in srgb, #22c55e 28%, transparent);
		animation: fkcart-social-pulse 1.8s ease-in-out infinite;
	}

	@keyframes fkcart-social-pulse {
		0%,
		100% {
			opacity: 1;
			transform: scale(1);
		}
		50% {
			opacity: 0.65;
			transform: scale(0.88);
		}
	}

	.fkcart-social__copy {
		flex: 1 1 auto;
		min-width: 0;
		margin: 0;
		font-size: 12px;
		line-height: 1.35;
		color: var(--fg-muted);
	}

	.fkcart-social__count {
		margin-right: 4px;
		color: var(--fg);
		font-weight: 700;
	}

	.fkcart-social__avatars {
		display: flex;
		flex: 0 0 auto;
		align-items: center;
		padding-left: 4px;
	}

	.fkcart-social__avatar {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 26px;
		height: 26px;
		margin-left: calc(var(--avatar-i, 0) * -7px);
		border: 2px solid var(--bg-elevated, var(--bg));
		border-radius: 50%;
		background: color-mix(
			in srgb,
			var(--accent) calc(72% - var(--avatar-i, 0) * 8%),
			#6366f1 calc(28% + var(--avatar-i, 0) * 8%)
		);
		color: var(--accent-fg, #fff);
		font-size: 9px;
		font-weight: 700;
		letter-spacing: -0.02em;
		line-height: 1;
	}

	.fkcart-social__avatar:first-child {
		margin-left: 0;
	}

	@media (max-width: 380px) {
		.fkcart-social {
			flex-wrap: wrap;
			row-gap: 8px;
		}

		.fkcart-social__copy {
			flex: 1 1 calc(100% - 72px);
		}

		.fkcart-social__avatars {
			margin-left: auto;
		}
	}
</style>
