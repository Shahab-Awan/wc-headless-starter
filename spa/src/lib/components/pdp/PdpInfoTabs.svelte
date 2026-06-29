<script lang="ts">
	import { config } from '$lib/config.svelte';
	import {
		coaDownloadFilename,
		downloadCoaFile,
		resolveCoaDownloadUrl,
	} from '$lib/wc/coa';
	import type { PdpFeatureItem } from '$lib/config.svelte';
	import type { StoreProduct, WchsCoaMetric, WchsCroProduct } from '$lib/wc/products';

	type TabId = 'overview' | 'coa' | 'specs' | 'research';

	const FEATURE_ICONS: Record<string, string> = {
		lab: '<path d="M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8M9.1 14.6h5.8"/>',
		zap: '<path d="M13 2L3 14h9l-1 8 10-12h-9z"/>',
		shield: '<path d="M12 3.6 18.4 6v5.5c0 4-2.5 7.5-6.4 8.9-3.9-1.4-6.4-4.9-6.4-8.9V6Z"/><path d="m9.3 12.2 1.9 1.9 3.6-3.9"/>',
		shipping:
			'<path d="M3.8 8.8h10.2v7.7H3.8z"/><path d="M14 11h3.1l3.1 3.2v2.3H14"/><circle cx="8" cy="17.6" r="1.7"/><circle cx="17.6" cy="17.6" r="1.7"/>',
		lock: '<rect x="4.5" y="11" width="15" height="9.5" rx="1.5"/><path d="M7.5 11V8a4.5 4.5 0 0 1 9 0v3"/>',
		check: '<path d="M5 12.5l4.2 4.2L19 7"/>',
	};

	let {
		product,
		cro,
		features = [],
	}: {
		product: StoreProduct;
		cro?: WchsCroProduct | null;
		features?: PdpFeatureItem[];
	} = $props();

	const section = $derived(config.data.pdp?.coa_section);
	const coaSectionEnabled = $derived(section?.enabled !== false);

	const coaUrl = $derived(
		resolveCoaDownloadUrl(product, cro, config.data.pdp?.coa_library_url)
	);

	const batch = $derived.by(() => {
		const fromProduct = cro?.coa_batch?.trim();
		if (fromProduct) return fromProduct;
		if (product.sku) return product.sku;
		return section?.default_batch || '';
	});

	const metrics = $derived.by((): WchsCoaMetric[] => {
		const rows = cro?.coa_metrics;
		if (rows?.length) return rows;
		return section?.default_metrics ?? [];
	});

	const tabs = $derived.by((): { id: TabId; label: string }[] => {
		const out: { id: TabId; label: string }[] = [
			{ id: 'overview', label: 'Overview' },
		];
		if (coaSectionEnabled) out.push({ id: 'coa', label: 'COA' });
		out.push({ id: 'specs', label: 'Specs' });
		if (product.description?.trim()) {
			out.push({ id: 'research', label: 'Research Context' });
		}
		return out;
	});

	let activeTab = $state<TabId>('overview');
	let downloading = $state(false);

	$effect(() => {
		product.id;
		activeTab = coaSectionEnabled ? 'coa' : 'overview';
	});

	$effect(() => {
		if (!tabs.some((t) => t.id === activeTab)) {
			activeTab = tabs[0]?.id ?? 'overview';
		}
	});

	const docMetrics = $derived(metrics.slice(0, 3));

	const coaSubtitle = $derived.by(() => {
		const lot = batch ? `Lot ${batch}` : '';
		if (product.name && lot) return `${product.name} • ${lot}`;
		return product.name || lot || '';
	});

	const hplcChartCaption = $derived.by(() => {
		const purity = metrics.find((m) => /hplc|purity/i.test(m.label));
		const row = purity ?? metrics[0];
		if (row) return `${row.label} • ${row.value}`;
		return 'HPLC Purity • ≥99.4%';
	});

	function metricShortLabel(label: string): string {
		return label.replace(/^(HPLC|LC-MS|RP-HPLC)\s+/i, '').trim() || label;
	}

	async function handleViewCoa() {
		if (!coaUrl || downloading) return;
		downloading = true;
		try {
			await downloadCoaFile(
				coaUrl,
				coaDownloadFilename(product.slug, batch, coaUrl)
			);
		} catch {
			window.open(coaUrl, '_blank', 'noopener,noreferrer');
		} finally {
			downloading = false;
		}
	}
</script>

{#if tabs.length > 0}
	<div class="pdp-tabs">
		<div class="pdp-tabs__bar" role="tablist" aria-label="Product information">
			{#each tabs as tab (tab.id)}
				<button
					type="button"
					class="pdp-tabs__tab"
					class:is-active={activeTab === tab.id}
					role="tab"
					aria-selected={activeTab === tab.id}
					aria-controls="pdp-tab-panel-{tab.id}"
					id="pdp-tab-{tab.id}"
					onclick={() => (activeTab = tab.id)}
				>
					{tab.label}
				</button>
			{/each}
		</div>

		<div
			class="pdp-tabs__panel"
			role="tabpanel"
			id="pdp-tab-panel-{activeTab}"
			aria-labelledby="pdp-tab-{activeTab}"
		>
			{#if activeTab === 'overview'}
				{#if product.short_description}
					<!-- eslint-disable-next-line svelte/no-at-html-tags -->
					<div class="pdp-tabs__prose">{@html product.short_description}</div>
				{/if}
				{#if features.length}
					<ul class="pdp-tabs__features">
						{#each features as feat}
							<li>
								{#if feat.icon && FEATURE_ICONS[feat.icon]}
									<svg
										viewBox="0 0 24 24"
										width="16"
										height="16"
										fill="none"
										stroke="currentColor"
										stroke-width="1.8"
										stroke-linecap="round"
										stroke-linejoin="round"
										aria-hidden="true"
									>
										{@html FEATURE_ICONS[feat.icon]}
									</svg>
								{/if}
								<span>{feat.label}</span>
							</li>
						{/each}
					</ul>
				{/if}
				{#if !product.short_description && !features.length}
					<p class="pdp-tabs__empty">Product overview coming soon.</p>
				{/if}
			{:else if activeTab === 'coa'}
				<article class="pdp-coa-card">
					<header class="pdp-coa-card__head">
						<div class="pdp-coa-card__title-row">
							<span class="pdp-coa-card__icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
									<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
									<path d="M14 3v5h5"/>
									<path d="m9 14 2 2 4-4"/>
								</svg>
							</span>
							<div class="pdp-coa-card__titles">
								<h3 class="pdp-coa-card__title">
									{section?.title ?? 'Certificate of Analysis'}
								</h3>
								{#if coaSubtitle}
									<p class="pdp-coa-card__subtitle">{coaSubtitle}</p>
								{/if}
							</div>
						</div>
						<span class="pdp-coa-card__verified">
							<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
								<path d="M5 12.5l4.2 4.2L19 7"/>
							</svg>
							Verified
						</span>
					</header>

					<div class="pdp-coa-card__chart">
						<div class="pdp-coa-card__chart-meta">
							<span>{hplcChartCaption}</span>
							{#if batch}
								<span>Lot {batch}</span>
							{/if}
						</div>
						<svg class="pdp-coa-card__chromatogram" viewBox="0 0 320 88" preserveAspectRatio="none" aria-hidden="true">
							<line x1="16" y1="72" x2="304" y2="72" stroke="currentColor" stroke-width="1" opacity="0.25"/>
							<path
								d="M16 72 L48 72 L72 68 L96 28 L112 18 L128 24 L144 58 L168 72 L304 72 Z"
								fill="url(#pdp-coa-peak)"
								opacity="0.85"
							/>
							<path
								d="M48 72 L72 68 L96 28 L112 18 L128 24 L144 58 L168 72"
								fill="none"
								stroke="currentColor"
								stroke-width="1.5"
								opacity="0.45"
							/>
							<defs>
								<linearGradient id="pdp-coa-peak" x1="0" y1="0" x2="0" y2="1">
									<stop offset="0%" stop-color="var(--coa-peak-top)" />
									<stop offset="100%" stop-color="var(--coa-peak-bottom)" />
								</linearGradient>
							</defs>
						</svg>
					</div>

					{#if docMetrics.length}
						<ul class="pdp-coa-card__metrics">
							{#each docMetrics as row}
								<li>
									<strong>{row.value}</strong>
									<span>{metricShortLabel(row.label)}</span>
								</li>
							{/each}
						</ul>
					{/if}

					<div class="pdp-coa-card__footer">
						<div class="pdp-coa-card__thumb" aria-hidden="true">
							<span class="pdp-coa-card__thumb-doc">
								<span class="pdp-coa-card__thumb-title">Certificate of Analysis</span>
								{#if batch}
									<span class="pdp-coa-card__thumb-batch">Batch #{batch}</span>
								{/if}
								{#if docMetrics.length}
									<ul class="pdp-coa-card__thumb-lines">
										{#each docMetrics as row}
											<li>
												<span>{metricShortLabel(row.label)}</span>
												<strong>{row.value}</strong>
											</li>
										{/each}
									</ul>
								{/if}
								<span class="pdp-coa-card__thumb-pass">PASS</span>
							</span>
						</div>
						<div class="pdp-coa-card__action">
							{#if coaUrl}
								<button
									type="button"
									class="pdp-coa-card__view"
									onclick={handleViewCoa}
									disabled={downloading}
									aria-busy={downloading}
								>
									{downloading ? 'Opening…' : 'View COA'}
								</button>
							{:else}
								<button type="button" class="pdp-coa-card__view" disabled>
									View COA
								</button>
							{/if}
							{#if section?.disclaimer}
								<p class="pdp-coa-card__note">{section.disclaimer}</p>
							{/if}
						</div>
					</div>
				</article>
			{:else if activeTab === 'specs'}
				<dl class="pdp-tabs__specs">
					{#if product.sku}
						<div>
							<dt>SKU</dt>
							<dd>{product.sku}</dd>
						</div>
					{/if}
					{#each product.attributes as attr}
						<div>
							<dt>{attr.name}</dt>
							<dd>{attr.terms.map((t) => t.name).join(', ')}</dd>
						</div>
					{/each}
					{#each metrics as row}
						<div>
							<dt>{row.label}</dt>
							<dd>{row.value}</dd>
						</div>
					{/each}
				</dl>
				{#if !product.sku && !product.attributes.length && !metrics.length}
					<p class="pdp-tabs__empty">No specifications listed.</p>
				{/if}
			{:else if activeTab === 'research'}
				<!-- eslint-disable-next-line svelte/no-at-html-tags -->
				<div class="pdp-tabs__prose pdp-tabs__prose--research">{@html product.description}</div>
			{/if}
		</div>
	</div>
{/if}

<style>
	.pdp-tabs {
		margin-top: 4px;
		border-top: 1px solid var(--border);
		padding-top: 16px;
	}

	.pdp-tabs__bar {
		display: flex;
		gap: 4px;
		overflow-x: auto;
		scrollbar-width: none;
		margin-bottom: 14px;
	}

	.pdp-tabs__bar::-webkit-scrollbar {
		display: none;
	}

	.pdp-tabs__tab {
		flex: 0 0 auto;
		padding: 8px 12px;
		border: 0;
		border-radius: 999px;
		background: transparent;
		color: var(--fg-muted);
		font: inherit;
		font-size: 12px;
		font-weight: 600;
		letter-spacing: 0.02em;
		cursor: pointer;
		white-space: nowrap;
		transition:
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease);
	}

	.pdp-tabs__tab:hover {
		color: var(--fg);
		background: color-mix(in srgb, var(--fg) 5%, transparent);
	}

	.pdp-tabs__tab.is-active {
		background: color-mix(in srgb, var(--accent) 12%, var(--bg));
		color: var(--accent);
	}

	.pdp-tabs__panel {
		min-height: 120px;
	}

	.pdp-tabs__prose {
		font-size: 14px;
		line-height: 1.6;
		color: var(--fg-muted);
	}

	.pdp-tabs__prose :global(p) {
		margin: 0 0 10px;
	}

	.pdp-tabs__prose :global(p:last-child) {
		margin-bottom: 0;
	}

	.pdp-tabs__prose--research {
		color: var(--fg);
	}

	.pdp-tabs__features {
		list-style: none;
		margin: 12px 0 0;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 8px 12px;
	}

	.pdp-tabs__features li {
		display: flex;
		align-items: flex-start;
		gap: 8px;
		min-width: 0;
		font-size: 12px;
		font-weight: 600;
		line-height: 1.35;
		color: var(--fg);
	}

	@media (max-width: 520px) {
		.pdp-tabs__features {
			gap: 8px 10px;
		}

		.pdp-tabs__features li {
			font-size: 11px;
		}
	}

	.pdp-tabs__features svg {
		flex-shrink: 0;
		margin-top: 1px;
		color: var(--accent);
	}

	.pdp-coa-card {
		--coa-surface: color-mix(in srgb, var(--accent) 6%, var(--bg));
		--coa-surface-strong: color-mix(in srgb, var(--accent) 10%, var(--bg));
		--coa-border: color-mix(in srgb, var(--accent) 18%, var(--border));
		--coa-ink: color-mix(in srgb, var(--accent) 55%, var(--fg));
		--coa-peak-top: color-mix(in srgb, var(--accent) 35%, transparent);
		--coa-peak-bottom: color-mix(in srgb, var(--accent) 8%, transparent);
		border: 1px solid var(--coa-border);
		border-radius: 14px;
		background: var(--bg);
		padding: 14px;
		display: flex;
		flex-direction: column;
		gap: 12px;
		box-shadow: 0 4px 18px color-mix(in srgb, var(--fg) 5%, transparent);
	}

	.pdp-coa-card__head {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 10px;
	}

	.pdp-coa-card__title-row {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		min-width: 0;
	}

	.pdp-coa-card__icon {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 36px;
		height: 36px;
		border-radius: 10px;
		background: var(--coa-surface-strong);
		color: var(--coa-ink);
	}

	.pdp-coa-card__titles {
		min-width: 0;
	}

	.pdp-coa-card__title {
		margin: 0;
		font-size: 15px;
		font-weight: 700;
		line-height: 1.25;
		letter-spacing: -0.01em;
		color: var(--fg);
	}

	.pdp-coa-card__subtitle {
		margin: 3px 0 0;
		font-size: 12px;
		line-height: 1.35;
		color: var(--fg-muted);
	}

	.pdp-coa-card__verified {
		flex-shrink: 0;
		display: inline-flex;
		align-items: center;
		gap: 4px;
		padding: 4px 9px;
		border-radius: 999px;
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.02em;
		color: var(--coa-ink);
		background: var(--coa-surface-strong);
	}

	.pdp-coa-card__chart {
		padding: 10px 12px 8px;
		border-radius: 12px;
		border: 1px solid var(--coa-border);
		background: var(--coa-surface);
	}

	.pdp-coa-card__chart-meta {
		display: flex;
		justify-content: space-between;
		gap: 8px;
		margin-bottom: 6px;
		font-size: 10px;
		font-weight: 600;
		color: var(--fg-muted);
	}

	.pdp-coa-card__chromatogram {
		display: block;
		width: 100%;
		height: 72px;
		color: var(--coa-ink);
	}

	.pdp-coa-card__metrics {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 8px;
	}

	@media (max-width: 420px) {
		.pdp-coa-card__metrics {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}

	.pdp-coa-card__metrics li {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 2px;
		min-height: 58px;
		padding: 10px 8px;
		border: 1px solid var(--border);
		border-radius: 10px;
		background: var(--bg);
		text-align: center;
	}

	.pdp-coa-card__metrics strong {
		font-size: 14px;
		font-weight: 800;
		line-height: 1.2;
		color: var(--fg);
		font-variant-numeric: tabular-nums;
	}

	.pdp-coa-card__metrics span {
		font-size: 11px;
		font-weight: 500;
		color: var(--fg-muted);
	}

	.pdp-coa-card__footer {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px;
		border-radius: 12px;
		background: var(--coa-surface);
		border: 1px solid var(--coa-border);
	}

	@media (max-width: 420px) {
		.pdp-coa-card__footer {
			flex-direction: column;
			align-items: stretch;
		}
	}

	.pdp-coa-card__thumb {
		flex: 0 0 108px;
		width: 108px;
		border-radius: 8px;
		overflow: hidden;
		border: 1px solid var(--coa-border);
		background: var(--bg);
		box-shadow: 0 4px 12px color-mix(in srgb, var(--fg) 6%, transparent);
	}

	@media (max-width: 420px) {
		.pdp-coa-card__thumb {
			width: 100%;
			flex-basis: auto;
			max-width: 140px;
			margin: 0 auto;
		}
	}

	.pdp-coa-card__thumb-doc {
		position: relative;
		display: block;
		padding: 10px 10px 12px;
		min-height: 108px;
		background: linear-gradient(
			165deg,
			color-mix(in srgb, var(--bg) 92%, white 8%) 0%,
			color-mix(in srgb, var(--bg-muted, var(--bg)) 70%, var(--bg) 30%) 100%
		);
	}

	.pdp-coa-card__thumb-title {
		display: block;
		margin: 0 0 3px;
		padding-right: 2.25rem;
		font-size: 7px;
		font-weight: 800;
		line-height: 1.35;
		letter-spacing: 0.05em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--fg) 72%, transparent);
	}

	.pdp-coa-card__thumb-batch {
		display: block;
		margin: 0 0 6px;
		font-size: 8px;
		font-weight: 600;
		color: var(--fg-muted);
		font-variant-numeric: tabular-nums;
	}

	.pdp-coa-card__thumb-lines {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		gap: 3px;
	}

	.pdp-coa-card__thumb-lines li {
		display: flex;
		justify-content: space-between;
		gap: 4px;
		font-size: 7px;
		line-height: 1.3;
		color: var(--fg-muted);
		border-bottom: 1px dashed color-mix(in srgb, var(--border) 80%, transparent);
		padding-bottom: 2px;
	}

	.pdp-coa-card__thumb-lines strong {
		font-weight: 700;
		color: var(--fg);
	}

	.pdp-coa-card__thumb-pass {
		position: absolute;
		top: 8px;
		right: 8px;
		padding: 2px 6px;
		border-radius: 999px;
		font-size: 7px;
		font-weight: 800;
		letter-spacing: 0.05em;
		color: hsl(152 55% 28%);
		background: hsl(152 48% 92%);
	}

	:global([data-theme='dark']) .pdp-coa-card__thumb-pass {
		color: hsl(152 55% 72%);
		background: hsl(152 35% 18%);
	}

	.pdp-coa-card__action {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 8px;
		min-width: 0;
	}

	.pdp-coa-card__view {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		padding: 12px 18px;
		border: 0;
		border-radius: 10px;
		background: var(--accent);
		color: var(--accent-fg);
		font: inherit;
		font-size: 14px;
		font-weight: 700;
		cursor: pointer;
		transition: opacity var(--dur-fast) var(--ease);
	}

	.pdp-coa-card__view:hover:not(:disabled) {
		opacity: 0.92;
	}

	.pdp-coa-card__view:disabled {
		opacity: 0.45;
		cursor: not-allowed;
	}

	.pdp-coa-card__note {
		margin: 0;
		font-size: 10px;
		line-height: 1.45;
		color: var(--fg-faint, var(--fg-muted));
	}

	.pdp-tabs__specs {
		margin: 0;
		display: grid;
		gap: 10px;
	}

	.pdp-tabs__specs div {
		display: grid;
		grid-template-columns: minmax(0, 38%) minmax(0, 1fr);
		gap: 10px;
		padding-bottom: 10px;
		border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, transparent);
	}

	.pdp-tabs__specs div:last-child {
		border-bottom: 0;
		padding-bottom: 0;
	}

	.pdp-tabs__specs dt {
		margin: 0;
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: var(--fg-muted);
	}

	.pdp-tabs__specs dd {
		margin: 0;
		font-size: 13px;
		font-weight: 600;
		color: var(--fg);
	}

	.pdp-tabs__empty {
		margin: 0;
		font-size: 13px;
		color: var(--fg-muted);
	}
</style>
