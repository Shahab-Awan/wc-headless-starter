<script lang="ts">
	import type {
		ListicleItem,
		ListicleModuleConfig,
		ModuleResolved,
		SpacingPreset,
	} from '$lib/config.svelte';
	import { bridgeAwareHref } from '$lib/bridge-domain';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
	}: {
		config: ListicleModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
	} = $props();

	const accentStyle = $derived(
		resolved?.accent_color ? `--accent: ${resolved.accent_color};` : ''
	);

	const DEFAULT_LISTICLE_ITEMS: ListicleItem[] = [
		{
			headline: 'Unverified purity claims can invalidate your data.',
			body: '<p>Your outcomes depend on what is actually in the vial. Without independent testing on every batch, you are trusting a label—not a lab result. Third-party COAs and published batch records let you align compound identity and purity with your protocol before you spend time in the bench.</p>',
		},
		{
			headline: 'No COA before purchase means no audit trail.',
			body: '<p>Reputable suppliers publish Certificates of Analysis tied to batch numbers before you buy. Gray-market listings rarely offer the same transparency, which makes reproducibility and compliance documentation much harder when results need to be defended.</p>',
		},
		{
			headline: 'Inconsistent sourcing slows every experiment cycle.',
			body: '<p>Switching vendors mid-study introduces variables you cannot control. A single catalog with documented batches, clear SKUs, and predictable domestic fulfillment keeps your team focused on research—not re-qualifying material.</p>',
		},
		{
			headline: 'Research-use standards matter for your reputation.',
			body: '<p>Materials labeled and handled for research use, with clear disclaimers and batch traceability, reduce ambiguity for PI review, institutional policy, and downstream publication integrity.</p>',
		},
		{
			headline: 'Verified supply is faster to trust than faster to ship.',
			body: '<p>Tracked domestic shipping matters—but only after purity and documentation are settled. The best workflow pairs batch-tested inventory with fulfillment you can plan around.</p>',
		},
	];

	const items = $derived.by(() => {
		const saved = (config.items ?? []).filter((it) => (it.headline ?? '').trim());
		if (!saved.length) return DEFAULT_LISTICLE_ITEMS;
		const merged: ListicleItem[] = [];
		for (let i = 0; i < DEFAULT_LISTICLE_ITEMS.length; i++) {
			merged.push({ ...DEFAULT_LISTICLE_ITEMS[i], ...(saved[i] ?? {}) });
		}
		for (let j = DEFAULT_LISTICLE_ITEMS.length; j < saved.length; j++) {
			merged.push(saved[j]);
		}
		return merged.filter((it) => (it.headline ?? '').trim());
	});
	const showCta = $derived(Boolean(config.cta_label?.trim() && config.cta_href?.trim()));

	const introHtml = $derived(config.intro?.trim() ?? '');

	const DEFAULT_ITEMS_HEADLINE =
		'Here is why more research teams standardize on documented, batch-tested supply:';

	const itemsHeadline = $derived(
		config.items_headline?.trim() || DEFAULT_ITEMS_HEADLINE
	);

	const introBody = $derived.by(() => {
		if (!introHtml) return '';
		const stripped = introHtml.replace(
			/(<p[^>]*>)([\s\S]*?)(<\/p>)/gi,
			(match, open, content, close) => {
				const plain = content.replace(/<[^>]+>/g, '').trim();
				const explicitLead = config.items_headline?.trim();
				if (
					(explicitLead && plain === explicitLead) ||
					(!explicitLead && /^here is why more research teams standardize/i.test(plain))
				) {
					return '';
				}
				return match;
			}
		);
		const out = stripped.trim();
		return out || introHtml;
	});

	function pointNumber(item: { number?: string }, index: number): string {
		const raw = item.number?.trim();
		if (raw) return raw.padStart(2, '0');
		return String(index + 1).padStart(2, '0');
	}
</script>

{#if config.headline?.trim() || config.intro?.trim() || items.length}
	<section
		class="listicle"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
	>
		<div class="listicle__inner">
			{#if config.section_eyebrow?.trim() || config.headline?.trim() || introBody || showCta || config.hero_image?.trim()}
				<header class="listicle__hero" class:has-items-headline={Boolean(itemsHeadline && items.length)}>
					{#if config.section_eyebrow?.trim()}
						<p class="listicle__eyebrow listicle__hero-eyebrow">{config.section_eyebrow.trim()}</p>
					{/if}
					<div class="listicle__hero-grid">
						<div class="listicle__hero-media">
							{#if config.hero_image?.trim()}
								<img
									src={config.hero_image.trim()}
									alt={config.hero_image_alt?.trim() || ''}
									loading="eager"
								/>
							{:else}
								<div class="listicle__hero-placeholder" aria-hidden="true"></div>
							{/if}
						</div>
						<div class="listicle__hero-aside">
							{#if config.headline?.trim()}
								<h2 class="listicle__headline listicle__hero-headline">{config.headline.trim()}</h2>
							{/if}
							{#if introBody || showCta}
								<div class="listicle__hero-body">
									{#if introBody}
										<div class="listicle__intro listicle__prose">{@html introBody}</div>
									{/if}
									{#if showCta}
										<p class="listicle__cta-wrap">
											<a href={bridgeAwareHref(config.cta_href!.trim())} class="listicle__cta">{config.cta_label!.trim()}</a>
										</p>
									{/if}
								</div>
							{/if}
						</div>
					</div>
				</header>
			{/if}

			{#if itemsHeadline && items.length}
				<h3 class="listicle__items-headline">{itemsHeadline}</h3>
			{/if}

			{#if items.length}
				<div class="listicle__rows">
					{#each items as item, i}
						<article
							class="listicle__row"
							class:listicle__row--media-first={i % 2 === 1}
						>
							<div class="listicle__copy">
								<div class="listicle__meta">
									<span class="listicle__index" aria-hidden="true">{pointNumber(item, i)}</span>
									{#if item.label?.trim()}
										<span class="listicle__label">{item.label.trim()}</span>
									{/if}
								</div>
								<h3 class="listicle__point-title">{item.headline}</h3>
								{#if item.body?.trim()}
									<div class="listicle__point-body listicle__prose">{@html item.body}</div>
								{/if}
								{#if item.callout?.trim()}
									<aside class="listicle__callout listicle__prose">{@html item.callout}</aside>
								{/if}
							</div>
							<div class="listicle__media">
								{#if item.image?.trim()}
									<img
										src={item.image.trim()}
										alt={item.image_alt?.trim() || ''}
										loading="lazy"
									/>
								{:else}
									<div class="listicle__media-placeholder" aria-hidden="true"></div>
								{/if}
							</div>
						</article>
					{/each}
				</div>
			{/if}

			{#if config.closing?.trim()}
				<div class="listicle__closing listicle__prose">{@html config.closing}</div>
			{/if}
		</div>
	</section>
{/if}

<style>
	.listicle {
		--mod-pt: var(--wchs-spacing-v-normal, 56px);
		--mod-pb: var(--wchs-spacing-v-normal, 64px);
		--mod-px: 28px;
		--listicle-max: min(1120px, 100%);
		--listicle-hero-max: min(1280px, 100%);
		background: var(--bg);
		color: var(--fg);
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.listicle.is-v-compact {
		--mod-pt: var(--wchs-spacing-v-compact, 28px);
		--mod-pb: var(--wchs-spacing-v-compact, 32px);
	}
	.listicle.is-v-spacious {
		--mod-pt: var(--wchs-spacing-v-spacious, 80px);
		--mod-pb: var(--wchs-spacing-v-spacious, 88px);
	}
	.listicle.is-h-compact {
		--mod-px: 16px;
	}
	.listicle.is-h-spacious {
		--mod-px: 40px;
	}

	.listicle__inner {
		max-width: var(--listicle-max);
		margin: 0 auto;
	}

	.listicle__hero {
		display: flex;
		flex-direction: column;
		gap: 16px;
		margin: 0 auto 56px;
		max-width: var(--listicle-hero-max);
		width: 100%;
	}
	.listicle__hero.has-items-headline {
		margin-bottom: 40px;
	}

	.listicle__hero-eyebrow {
		margin: 0;
	}

	.listicle__hero-grid {
		display: grid;
		grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
		grid-template-areas: 'media aside';
		gap: clamp(28px, 4vw, 56px);
		align-items: center;
	}

	.listicle__hero-aside {
		grid-area: aside;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: flex-start;
		gap: 16px;
		min-width: 0;
	}

	.listicle__hero-headline {
		margin: 0;
	}

	.listicle__hero-body {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 16px;
		min-width: 0;
		width: 100%;
	}

	.listicle__hero-media {
		grid-area: media;
		min-width: 0;
		border-radius: 14px;
		overflow: hidden;
		background: var(--bg-muted);
		min-height: clamp(320px, 44vw, 480px);
	}
	.listicle__hero-media img {
		display: block;
		width: 100%;
		height: 100%;
		min-height: clamp(320px, 44vw, 480px);
		object-fit: cover;
	}
	.listicle__hero-placeholder {
		width: 100%;
		min-height: clamp(320px, 44vw, 480px);
		aspect-ratio: 5 / 4;
		background: color-mix(in srgb, var(--accent) 8%, var(--bg-muted) 92%);
	}

	.listicle__items-headline {
		margin: 0 auto clamp(40px, 6vw, 56px);
		max-width: min(42rem, 100%);
		padding: 0 4px;
		text-align: center;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(20px, 2.6vw, 26px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.3;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.listicle__eyebrow {
		margin: 0 0 12px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		color: var(--accent);
	}

	.listicle__headline {
		margin: 0;
		max-width: 22ch;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(24px, 3.5vw, 34px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.15;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.listicle__intro {
		margin: 0;
		max-width: 40ch;
		width: 100%;
	}
	.listicle__intro :global(p) {
		font-family: var(--font-sans);
		font-size: 16px;
		font-weight: 400;
		line-height: 1.75;
		color: var(--fg);
	}

	.listicle__rows {
		display: flex;
		flex-direction: column;
		gap: clamp(48px, 8vw, 80px);
	}

	.listicle__row {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		gap: clamp(24px, 4vw, 48px);
		align-items: center;
	}

	.listicle__row--media-first .listicle__copy {
		order: 2;
	}
	.listicle__row--media-first .listicle__media {
		order: 1;
	}

	.listicle__copy {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		text-align: left;
		gap: 16px;
		min-width: 0;
	}

	.listicle__meta {
		display: flex;
		align-items: baseline;
		gap: 12px;
		flex-wrap: wrap;
	}

	.listicle__index {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(40px, 6vw, 56px);
		font-weight: 700;
		line-height: 1;
		letter-spacing: -0.04em;
		color: color-mix(in srgb, var(--fg) 12%, transparent);
	}

	.listicle__label {
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.12em;
		text-transform: uppercase;
		color: var(--accent);
	}

	.listicle__point-title {
		margin: 0;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(22px, 2.8vw, 30px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.2;
		letter-spacing: -0.025em;
		color: var(--fg);
		max-width: 22ch;
	}

	.listicle__callout {
		width: 100%;
		max-width: 36rem;
		margin: 4px 0 0;
		padding: 14px 16px 14px 18px;
		border-left: 3px solid var(--accent);
		background: color-mix(in srgb, var(--fg) 4%, var(--bg-muted) 96%);
		border-radius: 0 8px 8px 0;
	}

	.listicle__media {
		min-width: 0;
		border-radius: 12px;
		overflow: hidden;
		background: var(--bg-muted);
	}
	.listicle__media img {
		display: block;
		width: 100%;
		height: auto;
		object-fit: cover;
	}
	.listicle__media-placeholder {
		width: 100%;
		aspect-ratio: 4 / 3;
		background: color-mix(in srgb, var(--accent) 8%, var(--bg-muted) 92%);
	}

	.listicle__cta-wrap {
		margin: 8px 0 0;
		text-align: left;
		width: 100%;
	}

	.listicle__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 14px 28px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		border-radius: 14px;
		text-decoration: none;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		transition: opacity var(--dur-fast) var(--ease);
	}
	.listicle__cta:hover {
		opacity: 0.88;
	}

	.listicle__prose :global(p) {
		font-size: 16px;
		line-height: 1.75;
		color: var(--fg);
		margin: 0 0 14px;
	}
	.listicle__prose :global(p:last-child) {
		margin-bottom: 0;
	}
	.listicle__closing {
		margin-top: 48px;
		text-align: center;
		max-width: 52ch;
		margin-left: auto;
		margin-right: auto;
	}

	.listicle__point-body {
		max-width: 40rem;
	}

	@media (max-width: 800px) {
		.listicle__hero-eyebrow {
			text-align: center;
		}

		.listicle__hero-grid {
			display: flex;
			flex-direction: column;
			gap: 20px;
			align-items: center;
			text-align: center;
		}

		.listicle__hero-aside {
			display: contents;
		}

		.listicle__hero-headline {
			order: 1;
			max-width: none;
			width: 100%;
		}

		.listicle__hero-media {
			order: 2;
			width: 100%;
			min-height: clamp(280px, 68vw, 420px);
		}
		.listicle__hero-media img,
		.listicle__hero-placeholder {
			min-height: clamp(280px, 68vw, 420px);
		}

		.listicle__hero-body {
			order: 3;
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 20px;
			width: 100%;
			text-align: center;
		}

		.listicle__hero-body .listicle__cta-wrap {
			order: 1;
			margin: 0;
		}

		.listicle__hero-body .listicle__intro {
			order: 2;
		}

		.listicle__headline {
			max-width: none;
			width: 100%;
		}

		.listicle__intro {
			max-width: 36rem;
			margin-left: auto;
			margin-right: auto;
		}

		.listicle__cta-wrap {
			text-align: center;
			width: 100%;
		}

		.listicle__row,
		.listicle__row--media-first {
			display: flex;
			flex-direction: column;
			gap: 20px;
		}

		.listicle__row .listicle__media,
		.listicle__row--media-first .listicle__media {
			order: 0;
			width: 100%;
		}

		.listicle__row .listicle__copy,
		.listicle__row--media-first .listicle__copy {
			order: 1;
			width: 100%;
		}

		.listicle__point-title {
			max-width: none;
		}
	}
</style>
