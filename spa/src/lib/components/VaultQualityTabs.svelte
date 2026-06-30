<script lang="ts">
	import { bridgeAwareHref } from '$lib/bridge-domain';
	import type { VaultQualityTabsModuleConfig, SpacingPreset, ModuleResolved } from '$lib/config.svelte';

	let {
		config,
		spacing_v = 'normal',
		spacing_h = 'normal',
		resolved,
	}: {
		config: VaultQualityTabsModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		resolved?: ModuleResolved;
	} = $props();

	const accentStyle = $derived(
		resolved?.accent_color
			? `--accent: ${resolved.accent_color};`
			: ''
	);

	const sectionTitle = $derived(config.section_title?.trim() || 'The Alyve Vault Guarantee');
	const sectionSubtitle = $derived(
		config.section_subtitle?.trim() ||
			'Every batch is independently verified — purity, identity, endotoxin, stability, and consistency.'
	);
	const productImage = $derived(config.product_image?.trim() || '');
	const productAlt = $derived(config.product_image_alt?.trim() || 'Research peptide vial');
	const imageBadge = $derived(
		config.image_badge?.trim() || '99.4% Purity — Verified by HPLC'
	);
	const panelBg = $derived(config.panel_bg?.trim() || '#ebe6f5');
	const detailCtaText = $derived(
		config.detail_cta_text?.trim() || 'See the Proof → View COA Library'
	);
	const detailCtaHref = $derived(config.detail_cta_href?.trim() || '/coa-library');

	const defaultTabs = [
		{
			title: 'Purity',
			summary: 'HPLC ≥99%',
			body: '<p>Every batch is verified by High-Performance Liquid Chromatography (HPLC) to confirm peptide purity meets or exceeds 99%.</p>',
			why_matters: 'Impurities can skew receptor binding and invalidate your study data.',
			chart_image: '',
		},
		{
			title: 'Identity',
			summary: 'Mass Spec confirmed',
			body: '<p>Mass spectrometry confirms the molecular weight and sequence identity of each peptide lot before release.</p>',
			why_matters: 'Ensures you receive the exact compound specified — not a mislabeled analog.',
			chart_image: '',
		},
		{
			title: 'Endotoxin',
			summary: 'LAL tested, pharma-grade low',
			body: '<p>Limulus Amebocyte Lysate (LAL) testing verifies endotoxin levels meet pharmaceutical-grade thresholds.</p>',
			why_matters: 'Elevated endotoxins can trigger immune responses that confound in vitro and in vivo results.',
			chart_image: '',
		},
		{
			title: 'Stability',
			summary: 'Lyophilized for shelf life',
			body: '<p>Peptides are lyophilized under controlled conditions to maximize stability during storage and transit.</p>',
			why_matters: 'Proper lyophilization preserves bioactivity from synthesis to your bench.',
			chart_image: '',
		},
		{
			title: 'Consistency',
			summary: 'Batch-to-batch variance data',
			body: '<p>We publish lot-to-lot analytical data so you can compare batches across your study timeline.</p>',
			why_matters: 'Reproducible research requires predictable material from order to order.',
			chart_image: '',
		},
	];

	const tabs = $derived.by(() => {
		const rows = (config.tabs ?? [])
			.map((tab) => ({
				title: tab.title?.trim() || '',
				summary: tab.summary?.trim() || '',
				body: tab.body?.trim() || '',
				why_matters: tab.why_matters?.trim() || '',
				chart_image: tab.chart_image?.trim() || '',
			}))
			.filter((tab) => tab.title);
		return rows.length ? rows : defaultTabs;
	});

	let activeIndex = $state(0);

	const activeTab = $derived(tabs[activeIndex] ?? tabs[0]);

	const tabIcons = [
		'<path d="M19 5L5 19"/><circle cx="6.5" cy="6.5" r="2.2"/><circle cx="17.5" cy="17.5" r="2.2"/>',
		'<path d="M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8"/><path d="M9.1 14.6h5.8"/>',
		'<path d="M12 3.6 18.4 6v5.5c0 4-2.5 7.5-6.4 8.9-3.9-1.4-6.4-4.9-6.4-8.9V6Z"/><path d="m9.3 12.2 1.9 1.9 3.6-3.9"/>',
		'<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>',
		'<path d="M3 3v18h18"/><path d="M7 16l4-6 4 3 5-7"/>',
	];

	function selectTab(index: number) {
		activeIndex = index;
	}
</script>

<section
	class="vault-qt"
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	style="{accentStyle} --vqt-panel-bg: {panelBg};"
	aria-labelledby="vault-qt-title"
>
	<div class="vault-qt__grid">
		<div class="vault-qt__visual">
			<div class="vault-qt__visual-inner">
				{#if productImage}
					<img class="vault-qt__product" src={productImage} alt={productAlt} loading="lazy" />
				{:else}
					<div class="vault-qt__product-ph" aria-hidden="true"></div>
				{/if}
				{#if imageBadge}
					<div class="vault-qt__badge">{imageBadge}</div>
				{/if}
			</div>
		</div>

		<div class="vault-qt__content">
			<div class="vault-qt__content-inner">
				<h2 id="vault-qt-title" class="vault-qt__title">{sectionTitle}</h2>
				{#if sectionSubtitle}
					<p class="vault-qt__subtitle">{sectionSubtitle}</p>
				{/if}

				<div class="vault-qt__tabs" role="tablist" aria-label="Quality verification topics">
					{#each tabs as tab, i (tab.title)}
						<button
							type="button"
							class="vault-qt__tab"
							class:is-active={activeIndex === i}
							role="tab"
							id="vault-qt-tab-{i}"
							aria-selected={activeIndex === i}
							aria-controls="vault-qt-panel"
							onclick={() => selectTab(i)}
						>
							<span class="vault-qt__tab-icon" aria-hidden="true">
								<svg
									viewBox="0 0 24 24"
									width="22"
									height="22"
									fill="none"
									stroke="currentColor"
									stroke-width="1.9"
									stroke-linecap="round"
									stroke-linejoin="round"
								>
									{@html tabIcons[i % tabIcons.length]}
								</svg>
							</span>
							<span class="vault-qt__tab-text">
								<span class="vault-qt__tab-title">{tab.title}</span>
								{#if tab.summary}
									<span class="vault-qt__tab-summary">{tab.summary}</span>
								{/if}
							</span>
							<svg
								class="vault-qt__tab-chevron"
								viewBox="0 0 24 24"
								width="18"
								height="18"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								aria-hidden="true"
							>
								<path d="m9 6 6 6-6 6" />
							</svg>
						</button>
					{/each}
				</div>
			</div>
		</div>
	</div>

	{#if activeTab}
		<div class="vault-qt__detail">
			<div
				class="vault-qt__panel"
				role="tabpanel"
				id="vault-qt-panel"
				aria-labelledby="vault-qt-tab-{activeIndex}"
			>
				{#if activeTab.why_matters}
					<div class="vault-qt__why">
						<span class="vault-qt__why-label">Why it matters</span>
						<p class="vault-qt__why-text">{activeTab.why_matters}</p>
					</div>
				{/if}
				{#if activeTab.body}
					<div class="vault-qt__body">{@html activeTab.body}</div>
				{/if}
				{#if activeTab.chart_image}
					<img
						class="vault-qt__chart"
						src={activeTab.chart_image}
						alt="{activeTab.title} chart"
						loading="lazy"
					/>
				{/if}
				{#if detailCtaText}
					<a class="vault-qt__detail-cta" href={bridgeAwareHref(detailCtaHref)}>
						{detailCtaText}
					</a>
				{/if}
			</div>
		</div>
	{/if}
</section>

<style>
	.vault-qt {
		--mod-pt: 0;
		--mod-pb: 0;
		width: 100vw;
		max-width: 100vw;
		margin-left: calc(50% - 50vw);
		margin-right: calc(50% - 50vw);
		padding: var(--mod-pt) 0 var(--mod-pb);
	}

	.vault-qt__grid {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		align-items: stretch;
	}

	.vault-qt__visual {
		background: var(--vqt-panel-bg);
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 100%;
		padding: clamp(24px, 4vw, 48px) clamp(20px, 3vw, 36px);
	}

	.vault-qt__visual-inner {
		position: relative;
		width: min(92%, 400px);
		height: 100%;
		min-height: clamp(280px, 42vw, 480px);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.vault-qt__product {
		width: auto;
		height: auto;
		max-width: 100%;
		max-height: 100%;
		object-fit: contain;
		filter: drop-shadow(0 16px 28px color-mix(in srgb, var(--fg) 12%, transparent));
	}

	.vault-qt__product-ph {
		width: 68%;
		height: 72%;
		max-height: 100%;
		border-radius: 8px;
		background: color-mix(in srgb, var(--fg) 6%, transparent);
	}

	.vault-qt__badge {
		position: absolute;
		left: 50%;
		bottom: 8%;
		transform: translateX(-50%);
		padding: 8px 14px;
		border-radius: 999px;
		background: var(--bg);
		border: 1px solid var(--border);
		font-size: 12px;
		font-weight: 600;
		color: var(--fg);
		white-space: nowrap;
		box-shadow: 0 8px 24px color-mix(in srgb, var(--fg) 10%, transparent);
	}

	.vault-qt__content {
		background: var(--bg);
		display: flex;
		align-items: stretch;
		padding: clamp(24px, 4vw, 40px) clamp(24px, 4vw, 48px);
	}

	.vault-qt__content-inner {
		width: 100%;
		max-width: 480px;
	}

	.vault-qt__title {
		margin: 0 0 10px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(1.65rem, 3.2vw, 2.25rem);
		font-weight: var(--heading-weight, 600);
		line-height: 1.12;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.vault-qt__subtitle {
		margin: 0 0 clamp(24px, 3vw, 32px);
		font-size: clamp(14px, 1.8vw, 16px);
		line-height: 1.55;
		color: var(--fg-muted);
		max-width: 46ch;
	}

	.vault-qt__tabs {
		display: flex;
		flex-direction: column;
		gap: 8px;
		margin-bottom: 0;
	}

	.vault-qt__tab {
		display: grid;
		grid-template-columns: auto 1fr auto;
		align-items: center;
		gap: 14px;
		width: 100%;
		padding: 14px 16px;
		border: 1px solid var(--border);
		border-radius: 12px;
		background: var(--bg);
		color: var(--fg);
		text-align: left;
		cursor: pointer;
		transition:
			border-color var(--dur-fast) var(--ease),
			box-shadow var(--dur-fast) var(--ease),
			background var(--dur-fast) var(--ease);
	}

	.vault-qt__tab:hover {
		border-color: color-mix(in srgb, var(--accent) 40%, var(--border));
	}

	.vault-qt__tab.is-active {
		border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
		background: color-mix(in srgb, var(--accent) 6%, var(--bg));
		box-shadow: inset 3px 0 0 var(--accent);
	}

	.vault-qt__tab-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 40px;
		height: 40px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--accent) 12%, var(--bg));
		color: var(--accent);
		flex-shrink: 0;
	}

	.vault-qt__tab-text {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 0;
	}

	.vault-qt__tab-title {
		font-size: 15px;
		font-weight: 600;
		color: var(--fg);
	}

	.vault-qt__tab-summary {
		font-size: 13px;
		color: var(--fg-muted);
	}

	.vault-qt__tab-chevron {
		color: var(--fg-muted);
		flex-shrink: 0;
		opacity: 0.6;
	}

	.vault-qt__tab.is-active .vault-qt__tab-chevron {
		color: var(--accent);
		opacity: 1;
	}

	.vault-qt__detail {
		background: var(--bg);
		display: flex;
		justify-content: center;
		padding: clamp(20px, 3vw, 32px) clamp(20px, 4vw, 40px) clamp(28px, 4vw, 40px);
		border-top: 1px solid var(--border);
	}

	.vault-qt__panel {
		width: 100%;
		max-width: min(92vw, 760px);
		text-align: center;
	}

	.vault-qt__why {
		margin: 0 auto 18px;
		padding: 12px 16px;
		border-radius: 10px;
		border: 1px solid color-mix(in srgb, var(--accent) 25%, var(--border));
		background: color-mix(in srgb, var(--accent) 7%, var(--bg));
		text-align: center;
		max-width: 520px;
	}

	.vault-qt__body :global(p) {
		margin: 0 0 16px;
		font-size: clamp(14px, 1.8vw, 16px);
		line-height: 1.55;
		color: var(--fg-muted);
		max-width: none;
	}

	.vault-qt__body :global(p:last-child) {
		margin-bottom: 0;
	}

	.vault-qt__chart {
		display: block;
		width: 100%;
		max-width: 420px;
		margin: 16px auto;
		border-radius: 8px;
		border: 1px solid var(--border);
	}

	.vault-qt__detail-cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		margin-top: 20px;
		font-size: 15px;
		font-weight: 600;
		color: var(--accent);
		text-decoration: none;
		transition: opacity var(--dur-fast) var(--ease);
	}

	.vault-qt__detail-cta:hover {
		opacity: 0.85;
		text-decoration: underline;
		text-underline-offset: 3px;
	}

	.vault-qt__why-label {
		display: block;
		margin-bottom: 6px;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--accent);
	}

	.vault-qt__why-text {
		margin: 0;
		font-size: 14px;
		line-height: 1.55;
		color: var(--fg);
	}

	@media (max-width: 900px) {
		.vault-qt__grid {
			grid-template-columns: 1fr;
		}

		.vault-qt__visual {
			padding: clamp(20px, 4vw, 32px) clamp(16px, 4vw, 24px);
		}

		.vault-qt__visual-inner {
			width: min(78%, 280px);
			min-height: clamp(220px, 52vw, 320px);
		}

		.vault-qt__detail {
			padding: clamp(18px, 4vw, 24px) clamp(16px, 4vw, 24px) clamp(24px, 5vw, 32px);
		}

		.vault-qt__panel {
			max-width: 100%;
		}

		.vault-qt__content {
			padding: clamp(24px, 5vw, 32px) clamp(20px, 4vw, 28px);
		}

		.vault-qt__content-inner {
			max-width: none;
		}

		.vault-qt__badge {
			font-size: 11px;
			padding: 7px 12px;
			white-space: normal;
			text-align: center;
			max-width: 90%;
		}
	}
</style>
