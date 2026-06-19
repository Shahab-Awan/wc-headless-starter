<script lang="ts">
	import { bridgeAwareHref } from '$lib/bridge-domain';

	let {
		label,
		href,
	}: {
		label: string;
		href: string;
	} = $props();
</script>

<div class="why-alyve-sticky" role="region" aria-label="Call to action">
	<a class="why-alyve-sticky__btn" href={bridgeAwareHref(href)}>{label}</a>
</div>

<style>
	.why-alyve-sticky {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 200;
		padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
		background: color-mix(in srgb, var(--bg) 94%, transparent);
		border-top: 1px solid color-mix(in srgb, var(--border) 88%, transparent);
		box-shadow: 0 -10px 36px color-mix(in srgb, var(--fg) 10%, transparent);
		backdrop-filter: blur(14px);
		-webkit-backdrop-filter: blur(14px);
	}

	@media (min-width: 640px) {
		.why-alyve-sticky {
			display: none;
		}
	}

	.why-alyve-sticky__btn {
		display: flex;
		align-items: center;
		justify-content: center;
		width: min(100%, 560px);
		min-height: 52px;
		margin: 0 auto;
		padding: 14px 20px;
		border: 0;
		border-radius: 14px;
		background: linear-gradient(
			135deg,
			color-mix(in srgb, var(--accent) 88%, #1a1630 12%),
			color-mix(in srgb, var(--accent) 72%, #0f172a 28%)
		);
		color: var(--accent-fg);
		text-decoration: none;
		font: inherit;
		font-size: 13px;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		box-shadow: 0 8px 22px color-mix(in srgb, var(--accent) 32%, transparent);
		transition:
			opacity var(--dur-fast) var(--ease),
			transform var(--dur-fast) var(--ease),
			box-shadow var(--dur-fast) var(--ease);
	}

	.why-alyve-sticky__btn:hover {
		opacity: 0.95;
		box-shadow: 0 10px 28px color-mix(in srgb, var(--accent) 40%, transparent);
	}

	.why-alyve-sticky__btn:active {
		transform: scale(0.985);
	}
</style>
