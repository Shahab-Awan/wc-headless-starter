<script lang="ts">
	import type { ListicleFaqsModuleConfig, ModuleResolved, SpacingPreset } from '$lib/config.svelte';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
	}: {
		config: ListicleFaqsModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
	} = $props();

	const accentStyle = $derived(
		resolved?.accent_color ? `--accent: ${resolved.accent_color};` : ''
	);

	const items = $derived(
		(config.items ?? []).filter((it) => (it.q ?? '').trim() && (it.a ?? '').trim())
	);

	const headline = $derived.by(() => {
		const direct = config.headline?.trim();
		if (direct) return direct;
		const legacy = [config.headline_prefix?.trim(), config.headline_accent?.trim()]
			.filter(Boolean)
			.join('');
		return legacy;
	});

	let openIndex = $state<number | null>(null);

	function toggle(i: number) {
		openIndex = openIndex === i ? null : i;
	}
</script>

{#if items.length}
	<section
		class="listicle-faqs"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
	>
		<div class="listicle-faqs__inner">
			<header class="listicle-faqs__header">
				{#if config.eyebrow?.trim()}
					<p class="listicle-faqs__eyebrow">{config.eyebrow.trim()}</p>
				{/if}
				{#if headline}
					<h2 class="listicle-faqs__headline">{headline}</h2>
				{/if}
			</header>

			<div class="listicle-faqs__list">
				{#each items as item, i}
					<div class="listicle-faqs__item" class:is-open={openIndex === i}>
						<button
							type="button"
							class="listicle-faqs__trigger"
							class:is-open={openIndex === i}
							onclick={() => toggle(i)}
							aria-expanded={openIndex === i}
						>
							<span class="listicle-faqs__question">{item.q}</span>
							<span class="listicle-faqs__chevron" aria-hidden="true"></span>
						</button>
						<div class="listicle-faqs__panel" class:is-open={openIndex === i}>
							<div class="listicle-faqs__answer listicle-faqs__answer--html">
								{@html item.a}
							</div>
						</div>
					</div>
				{/each}
			</div>
		</div>
	</section>
{/if}

<style>
	.listicle-faqs {
		--mod-pt: var(--wchs-spacing-v-normal, 48px);
		--mod-pb: var(--wchs-spacing-v-normal, 56px);
		--mod-px: 28px;
		--lf-max: min(760px, 100%);
		background: var(--bg);
		color: var(--fg);
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.listicle-faqs.is-v-compact {
		--mod-pt: var(--wchs-spacing-v-compact, 24px);
		--mod-pb: var(--wchs-spacing-v-compact, 28px);
	}
	.listicle-faqs.is-v-spacious {
		--mod-pt: var(--wchs-spacing-v-spacious, 64px);
		--mod-pb: var(--wchs-spacing-v-spacious, 72px);
	}
	.listicle-faqs.is-h-compact {
		--mod-px: 16px;
	}
	.listicle-faqs.is-h-spacious {
		--mod-px: 40px;
	}

	.listicle-faqs__inner {
		max-width: var(--lf-max);
		margin: 0 auto;
	}

	.listicle-faqs__header {
		margin: 0 0 36px;
		text-align: center;
	}

	.listicle-faqs__eyebrow {
		margin: 0 0 10px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		color: var(--accent);
	}

	.listicle-faqs__headline {
		margin: 0;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(24px, 3.5vw, 34px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.15;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.listicle-faqs__list {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}

	.listicle-faqs__item {
		border: 1px solid var(--border);
		border-radius: 12px;
		background: var(--bg);
		overflow: hidden;
	}

	.listicle-faqs__trigger {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 16px;
		width: 100%;
		padding: 16px 18px;
		background: none;
		border: none;
		cursor: pointer;
		text-align: left;
		font-family: var(--font-sans);
		color: var(--fg);
	}

	.listicle-faqs__question {
		flex: 1;
		min-width: 0;
		font-size: 15px;
		font-weight: 600;
		line-height: 1.45;
		letter-spacing: -0.01em;
	}

	.listicle-faqs__chevron {
		flex-shrink: 0;
		width: 28px;
		height: 28px;
		border-radius: 50%;
		background: color-mix(in srgb, var(--accent) 14%, var(--bg) 86%);
		position: relative;
		transition: transform var(--dur-fast) var(--ease);
	}
	.listicle-faqs__chevron::after {
		content: '';
		position: absolute;
		left: 50%;
		top: 46%;
		transform: translate(-50%, -50%);
		border-left: 5px solid transparent;
		border-right: 5px solid transparent;
		border-top: 6px solid var(--accent);
	}
	.listicle-faqs__trigger.is-open .listicle-faqs__chevron {
		transform: rotate(180deg);
	}

	.listicle-faqs__panel {
		display: grid;
		grid-template-rows: minmax(0, 0fr);
		overflow: hidden;
		transition: grid-template-rows 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	}
	.listicle-faqs__panel.is-open {
		grid-template-rows: minmax(0, 1fr);
	}

	.listicle-faqs__answer {
		min-height: 0;
		overflow: hidden;
	}

	.listicle-faqs__answer--html {
		font-size: 14px;
		line-height: 1.65;
		color: var(--fg-muted);
		padding: 0 18px 16px;
	}
	.listicle-faqs__answer--html :global(p) {
		margin: 0 0 10px;
	}
	.listicle-faqs__answer--html :global(p:last-child) {
		margin-bottom: 0;
	}
	.listicle-faqs__answer--html :global(a) {
		color: var(--accent);
		text-decoration: underline;
		text-underline-offset: 2px;
	}
	.listicle-faqs__answer--html :global(strong) {
		font-weight: 700;
	}
	.listicle-faqs__answer--html :global(ul),
	.listicle-faqs__answer--html :global(ol) {
		padding-left: 22px;
		margin: 0 0 10px;
	}
</style>
