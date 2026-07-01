<script lang="ts">
	import { bridgeAwareHref } from '$lib/bridge-domain';
	import { config as siteConfig, type VaultHeroModuleConfig, type SpacingPreset, type ModuleResolved } from '$lib/config.svelte';
	import { HERO_FONTS } from '$lib/hero-fonts';

	const WEIGHT_MAP: Record<string, string> = {
		light: '300',
		regular: '400',
		medium: '500',
		semibold: '600',
		bold: '700',
		extrabold: '800',
		black: '900',
	};

	let {
		config,
		spacing_v = 'normal',
		spacing_h = 'normal',
		resolved,
	}: {
		config: VaultHeroModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		resolved?: ModuleResolved;
	} = $props();

	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color};` : '');

	const headline = $derived(
		config.headline?.trim() || 'Quality You Can Verify, Not Just Trust'
	);

	const stats = $derived.by(() => {
		const rows = (config.stats ?? [])
			.map((row) => row.label?.trim())
			.filter(Boolean);
		if (rows.length) return rows;
		return ['99%+ Purity Guaranteed', '5 Quality Checks', '100% US Verified'];
	});

	const ctaText = $derived(config.cta_text?.trim() || 'Browse the Vault →');
	const ctaHref = $derived(config.cta_href?.trim() || '/shop');

	const bgImage = $derived(config.bg_image?.trim() || '');
	const vialPrimary = $derived(config.vial_primary?.trim() || '');
	const vialSecondary = $derived(config.vial_secondary?.trim() || '');
	const vialTertiary = $derived(config.vial_tertiary?.trim() || '');
	const hasVials = $derived(Boolean(vialPrimary || vialSecondary || vialTertiary));

	const titleStyle = $derived.by(() => {
		const hero = siteConfig.data.homepage?.hero;
		const fontKey = (hero?.headline_font ??
			siteConfig.data.typography?.heading_font ??
			'inter') as keyof typeof HERO_FONTS;
		const family = HERO_FONTS[fontKey]?.family ?? HERO_FONTS.inter.family;
		const weightKey =
			hero?.headline_weight ?? siteConfig.data.typography?.heading_weight ?? 'semibold';
		const weight = WEIGHT_MAP[weightKey] ?? '600';
		return `font-family: ${family}; font-weight: ${weight};`;
	});
</script>

<section
	class="vault-hero"
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	class:is-h-compact={spacing_h === 'compact'}
	class:is-h-spacious={spacing_h === 'spacious'}
	style={accentStyle}
	aria-labelledby="vault-hero-title"
>
	<div class="vault-hero__grid">
		<div class="vault-hero__copy">
			<div class="vault-hero__copy-inner">
				<h1 id="vault-hero-title" class="vault-hero__title" style={titleStyle}>{headline}</h1>

				{#if stats.length}
					<ul class="vault-hero__stats" aria-label="Quality highlights">
						{#each stats as stat, i (stat + i)}
							<li class="vault-hero__stat">{stat}</li>
							{#if i < stats.length - 1}
								<li class="vault-hero__stat-sep" aria-hidden="true">·</li>
							{/if}
						{/each}
					</ul>
				{/if}

				{#if ctaText}
					<a class="vault-hero__cta" href={bridgeAwareHref(ctaHref)}>
						{ctaText}
					</a>
				{/if}
			</div>
		</div>

		<div class="vault-hero__visual" class:has-bg={!!bgImage} class:has-vials={hasVials}>
			{#if bgImage}
				<div
					class="vault-hero__visual-bg"
					style:background-image="url('{bgImage.replace(/'/g, '%27')}')"
					role="presentation"
				></div>
			{:else}
				<div class="vault-hero__visual-fallback" role="presentation"></div>
			{/if}

			{#if hasVials}
				<div class="vault-hero__vials" aria-hidden="true">
					{#if vialPrimary}
						<img
							class="vault-hero__vial vault-hero__vial--primary"
							src={vialPrimary}
							alt={config.vial_primary_alt?.trim() || ''}
							loading="eager"
						/>
					{/if}
					{#if vialSecondary}
						<img
							class="vault-hero__vial vault-hero__vial--secondary"
							src={vialSecondary}
							alt={config.vial_secondary_alt?.trim() || ''}
							loading="eager"
						/>
					{/if}
					{#if vialTertiary}
						<img
							class="vault-hero__vial vault-hero__vial--tertiary"
							src={vialTertiary}
							alt={config.vial_tertiary_alt?.trim() || ''}
							loading="eager"
						/>
					{/if}
				</div>
			{/if}
		</div>
	</div>
</section>

<style>
	.vault-hero {
		--mod-pt: 0;
		--mod-pb: 0;
		width: 100vw;
		max-width: 100vw;
		margin-left: calc(50% - 50vw);
		margin-right: calc(50% - 50vw);
		padding: var(--mod-pt) 0 var(--mod-pb);
	}
	.vault-hero.is-v-compact {
		--mod-pb: 0;
	}
	.vault-hero.is-v-spacious {
		--mod-pb: 0;
	}

	.vault-hero__grid {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		min-height: clamp(440px, 58vh, 620px);
		align-items: stretch;
	}

	.vault-hero__copy {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		padding: clamp(32px, 5vw, 72px) clamp(20px, 4vw, 48px);
		background: var(--bg);
	}

	.vault-hero__copy-inner {
		width: 100%;
		max-width: 520px;
	}

	.vault-hero__title {
		margin: 0 0 clamp(24px, 3.5vw, 32px);
		font-size: clamp(2.15rem, 4.8vw, 3.5rem);
		line-height: 1.05;
		letter-spacing: -0.03em;
		color: var(--fg);
		text-wrap: balance;
	}

	.vault-hero__stats {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px 10px;
		list-style: none;
		margin: 0 0 clamp(24px, 4vw, 32px);
		padding: 0;
	}

	.vault-hero__stat {
		font-size: clamp(12px, 1.6vw, 14px);
		font-weight: 600;
		letter-spacing: 0.02em;
		color: var(--accent);
	}

	.vault-hero__stat-sep {
		font-size: 14px;
		line-height: 1;
		color: color-mix(in srgb, var(--accent) 55%, var(--fg-muted));
		user-select: none;
	}

	.vault-hero__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 48px;
		padding: 12px 28px;
		border-radius: 999px;
		background: var(--fg);
		color: var(--bg);
		font-size: 14px;
		font-weight: 600;
		text-decoration: none;
		transition:
			opacity var(--dur-fast) var(--ease),
			transform var(--dur-fast) var(--ease);
	}

	.vault-hero__cta:hover {
		opacity: 0.9;
		transform: translateY(-1px);
	}

	.vault-hero__visual {
		position: relative;
		min-height: 100%;
		overflow: hidden;
		border: none;
		border-radius: 0;
	}

	.vault-hero__visual-bg,
	.vault-hero__visual-fallback {
		position: absolute;
		inset: 0;
		background-size: cover;
		background-position: center;
		background-repeat: no-repeat;
	}

	.vault-hero__visual-fallback {
		background: linear-gradient(
			160deg,
			color-mix(in srgb, var(--accent) 14%, var(--bg)) 0%,
			color-mix(in srgb, var(--accent) 6%, var(--bg)) 55%,
			var(--bg) 100%
		);
	}

	.vault-hero__vials {
		position: absolute;
		inset: 0;
		z-index: 2;
		pointer-events: none;
	}

	.vault-hero__vial {
		position: absolute;
		height: auto;
		object-fit: contain;
		will-change: transform;
	}

	.vault-hero__vial--primary {
		--vial-rotate: -14deg;
		left: 6%;
		top: 18%;
		width: min(50%, 230px);
		z-index: 3;
		transform: rotate(var(--vial-rotate));
		filter: drop-shadow(0 20px 32px color-mix(in srgb, var(--fg) 16%, transparent));
		animation: vault-vial-float 5.2s ease-in-out infinite;
	}

	.vault-hero__vial--secondary {
		--vial-rotate: 12deg;
		right: 2%;
		top: 6%;
		width: min(38%, 168px);
		z-index: 2;
		transform: rotate(var(--vial-rotate));
		filter: drop-shadow(0 14px 24px color-mix(in srgb, var(--fg) 12%, transparent));
		animation: vault-vial-float 4.4s ease-in-out infinite 0.35s;
	}

	.vault-hero__vial--tertiary {
		--vial-rotate: -8deg;
		right: 8%;
		bottom: 8%;
		width: min(36%, 158px);
		z-index: 2;
		transform: rotate(var(--vial-rotate));
		filter: drop-shadow(0 16px 26px color-mix(in srgb, var(--fg) 12%, transparent));
		animation: vault-vial-float 4.9s ease-in-out infinite 0.7s;
	}

	@keyframes vault-vial-float {
		0%,
		100% {
			transform: translateY(0) rotate(var(--vial-rotate));
		}
		50% {
			transform: translateY(-12px) rotate(calc(var(--vial-rotate) + 2deg));
		}
	}

	@media (max-width: 900px) {
		.vault-hero__grid {
			grid-template-columns: 1fr;
			min-height: auto;
		}

		.vault-hero__copy {
			justify-content: flex-start;
			padding: clamp(28px, 5vw, 40px) clamp(20px, 4vw, 28px);
			order: 1;
		}

		.vault-hero__copy-inner {
			max-width: none;
		}

		.vault-hero__visual {
			order: 0;
			min-height: clamp(280px, 62vw, 400px);
		}

		.vault-hero__stats {
			flex-direction: column;
			align-items: flex-start;
			gap: 6px;
		}

		.vault-hero__stat-sep {
			display: none;
		}
	}

	@media (max-width: 520px) {
		.vault-hero__cta {
			width: 100%;
		}
	}

	@media (prefers-reduced-motion: reduce) {
		.vault-hero__vial {
			animation: none;
		}
	}
</style>
