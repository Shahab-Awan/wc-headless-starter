<script lang="ts">
	import { bridgeAwareHref } from '$lib/bridge-domain';
	import type { VaultCtaModuleConfig, SpacingPreset, ModuleResolved } from '$lib/config.svelte';

	let {
		config,
		spacing_v = 'normal',
		spacing_h = 'normal',
		resolved,
	}: {
		config: VaultCtaModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		resolved?: ModuleResolved;
	} = $props();

	const accentStyle = $derived(
		resolved?.accent_color ? `--vault-cta-teal: ${resolved.accent_color};` : ''
	);

	const headlinePrefix = $derived(
		config.headline_prefix?.trim() || 'Ready to Verify? Browse the'
	);
	const headlineAccent = $derived(config.headline_accent?.trim() || 'Research Vault.');
	const primaryText = $derived(config.primary_cta_text?.trim() || 'Browse Catalog →');
	const primaryHref = $derived(bridgeAwareHref(config.primary_cta_href?.trim() || '/shop'));
	const secondaryText = $derived(config.secondary_cta_text?.trim() || 'View COA Library');
	const secondaryHref = $derived(
		bridgeAwareHref(config.secondary_cta_href?.trim() || '/coa-library')
	);
</script>

<section
	class="vault-cta"
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	class:is-h-compact={spacing_h === 'compact'}
	class:is-h-spacious={spacing_h === 'spacious'}
	style={accentStyle}
	aria-labelledby="vault-cta-title"
>
	<div class="vault-cta__shell">
		<h2 id="vault-cta-title" class="vault-cta__title">
			<span class="vault-cta__prefix">{headlinePrefix}</span>
			<span class="vault-cta__accent">{headlineAccent}</span>
		</h2>
		<div class="vault-cta__actions">
			<a class="vault-cta__btn vault-cta__btn--primary" href={primaryHref}>{primaryText}</a>
			<a class="vault-cta__btn vault-cta__btn--secondary" href={secondaryHref}>{secondaryText}</a>
		</div>
	</div>
</section>

<style>
	.vault-cta {
		--vault-cta-teal: var(--accent, #0d9488);
		width: 100%;
		padding: clamp(56px, 7vw, 88px) clamp(20px, 4vw, 32px);
		background: linear-gradient(
			135deg,
			color-mix(in srgb, var(--vault-cta-teal) 8%, var(--bg) 92%) 0%,
			color-mix(in srgb, var(--vault-cta-teal) 14%, var(--bg) 86%) 52%,
			color-mix(in srgb, var(--vault-cta-teal) 6%, var(--bg) 94%) 100%
		);
	}

	.vault-cta.is-v-compact {
		padding-top: 40px;
		padding-bottom: 44px;
	}

	.vault-cta.is-v-spacious {
		padding-top: 96px;
		padding-bottom: 104px;
	}

	.vault-cta__shell {
		max-width: 920px;
		margin: 0 auto;
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
		gap: clamp(22px, 3vw, 28px);
	}

	.vault-cta.is-h-compact .vault-cta__shell {
		max-width: 100%;
	}

	.vault-cta.is-h-spacious .vault-cta__shell {
		max-width: 760px;
	}

	.vault-cta__title {
		margin: 0;
		max-width: 18ch;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(28px, 4.2vw, 44px);
		font-weight: 800;
		line-height: 1.08;
		letter-spacing: -0.03em;
		color: var(--fg);
	}

	.vault-cta__prefix {
		display: inline;
	}

	.vault-cta__accent {
		position: relative;
		display: inline;
		white-space: nowrap;
	}

	.vault-cta__accent::after {
		content: '';
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0.06em;
		height: 3px;
		border-radius: 999px;
		background: linear-gradient(
			90deg,
			color-mix(in srgb, var(--vault-cta-teal) 45%, transparent),
			var(--vault-cta-teal)
		);
	}

	.vault-cta__actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: center;
		gap: 12px;
	}

	.vault-cta__btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 48px;
		padding: 12px 24px;
		border-radius: 999px;
		font-size: 14px;
		font-weight: 600;
		text-decoration: none;
		transition:
			opacity var(--dur-fast) var(--ease),
			transform var(--dur-fast) var(--ease),
			background var(--dur-fast) var(--ease),
			border-color var(--dur-fast) var(--ease);
	}

	.vault-cta__btn:hover {
		transform: translateY(-1px);
	}

	.vault-cta__btn--primary {
		background: var(--fg);
		color: var(--bg);
		border: 1px solid var(--fg);
	}

	.vault-cta__btn--primary:hover {
		opacity: 0.92;
	}

	.vault-cta__btn--secondary {
		background: var(--bg);
		color: var(--fg);
		border: 1px solid color-mix(in srgb, var(--fg) 18%, var(--border));
		box-shadow: 0 8px 24px color-mix(in srgb, var(--fg) 6%, transparent);
	}

	.vault-cta__btn--secondary:hover {
		border-color: color-mix(in srgb, var(--fg) 28%, var(--border));
	}

	@media (max-width: 540px) {
		.vault-cta__title {
			max-width: none;
		}

		.vault-cta__btn {
			width: 100%;
			max-width: 320px;
		}
	}
</style>
