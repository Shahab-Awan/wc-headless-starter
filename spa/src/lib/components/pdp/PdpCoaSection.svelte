<script lang="ts">
	import { config } from '$lib/config.svelte';
	import { resolveCoaDownloadUrl } from '$lib/wc/coa';
	import type { StoreProduct, WchsCoaMetric, WchsCroProduct } from '$lib/wc/products';

	let {
		product,
		cro,
		embedded = false,
	}: {
		product: StoreProduct;
		cro?: WchsCroProduct | null;
		/** In the PDP buy column — left-aligned, before product description. */
		embedded?: boolean;
	} = $props();

	const section = $derived(config.data.pdp?.coa_section);
	const enabled = $derived(section?.enabled !== false);

	const coaUrl = $derived(
		resolveCoaDownloadUrl(product, cro, config.data.pdp?.coa_library_url)
	);

	const batch = $derived.by(() => {
		const fromProduct = cro?.coa_batch?.trim();
		if (fromProduct) return fromProduct;
		if (product.sku) return product.sku;
		return section?.default_batch || '';
	});

	const lab = $derived(cro?.coa_lab?.trim() || section?.default_lab || '');

	const metrics = $derived.by((): WchsCoaMetric[] => {
		const rows = cro?.coa_metrics;
		if (rows?.length) return rows;
		return section?.default_metrics ?? [];
	});
</script>

{#if enabled}
	<section class="pdp-coa" class:pdp-coa--embedded={embedded} aria-labelledby="pdp-coa-title">
		<div class="pdp-coa__inner">
			{#if !embedded}
				<p class="pdp-coa__eyebrow">{section?.eyebrow ?? 'TRANSPARENCY'}</p>
			{/if}
			<h2 id="pdp-coa-title" class="pdp-coa__title">{section?.title ?? 'Certificate of Analysis'}</h2>
			<p class="pdp-coa__subtitle">
				{section?.subtitle ??
					'Every batch independently verified by third-party laboratories.'}
			</p>

			<div class="pdp-coa__card">
				<div class="pdp-coa__card-head">
					<div class="pdp-coa__card-meta">
						{#if batch}
							<p class="pdp-coa__batch"><span>Batch</span> #{batch}</p>
						{/if}
						{#if lab}
							<p class="pdp-coa__lab">{lab}</p>
						{/if}
					</div>
					<div class="pdp-coa__card-status">
						<span class="pdp-coa__pass">PASS</span>
						<span class="pdp-coa__live">
							<span class="pdp-coa__live-dot" aria-hidden="true"></span>
							Live
						</span>
					</div>
				</div>

				{#if metrics.length}
					<ul class="pdp-coa__grid">
						{#each metrics as row}
							<li>
								<span class="pdp-coa__metric-label">{row.label}</span>
								<span class="pdp-coa__metric-value">{row.value}</span>
							</li>
						{/each}
					</ul>
				{/if}

				<div class="pdp-coa__actions">
					{#if coaUrl}
						<a
							class="pdp-coa__download"
							href={coaUrl}
							target="_blank"
							rel="noopener noreferrer"
						>
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
								<path d="M15 3h6v6M10 14L21 3"/>
							</svg>
							View Report
						</a>
					{:else}
						<button type="button" class="pdp-coa__download pdp-coa__download--pending" disabled>
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
								<path d="M15 3h6v6M10 14L21 3"/>
							</svg>
							View Report
						</button>
					{/if}
				</div>
			</div>

			{#if section?.disclaimer}
				<p class="pdp-coa__disclaimer">{section.disclaimer}</p>
			{/if}
		</div>
	</section>
{/if}

<style>
	.pdp-coa {
		--pdp-radius: 16px;
		padding: 0 28px 56px;
		max-width: 1320px;
		margin: 0 auto;
	}
	.pdp-coa--embedded {
		padding: 0;
		max-width: none;
		margin: 28px 0 0;
	}
	@media (max-width: 860px) {
		.pdp-coa:not(.pdp-coa--embedded) {
			padding: 0 20px 40px;
		}
	}
	.pdp-coa__inner {
		max-width: 720px;
		margin: 0 auto;
		text-align: center;
	}
	.pdp-coa--embedded .pdp-coa__inner {
		max-width: none;
		margin: 0;
		text-align: left;
		border: 1px solid var(--border);
		border-radius: var(--pdp-radius);
		background: var(--bg);
		padding: 20px;
		box-shadow: 0 4px 24px color-mix(in srgb, var(--fg) 6%, transparent);
	}
	.pdp-coa--embedded .pdp-coa__eyebrow {
		margin-bottom: 8px;
	}
	.pdp-coa--embedded .pdp-coa__title {
		font-size: clamp(22px, 2.4vw, 28px);
		margin-bottom: 8px;
	}
	.pdp-coa--embedded .pdp-coa__subtitle {
		margin-bottom: 20px;
		font-size: 14px;
	}
	.pdp-coa--embedded .pdp-coa__card {
		border: 0;
		border-radius: 0;
		background: transparent;
		padding: 0;
	}
	.pdp-coa--embedded .pdp-coa__actions {
		justify-content: flex-start;
	}
	.pdp-coa--embedded .pdp-coa__download {
		min-width: 0;
	}
	.pdp-coa--embedded .pdp-coa__disclaimer {
		text-align: left;
	}
	.pdp-coa__eyebrow {
		margin: 0 0 12px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.14em;
		color: var(--accent);
	}
	.pdp-coa__title {
		margin: 0 0 10px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(28px, 3.2vw, 36px);
		font-weight: var(--heading-weight, 600);
		letter-spacing: -0.03em;
		color: var(--fg);
	}
	.pdp-coa__subtitle {
		margin: 0 0 28px;
		font-size: 15px;
		line-height: 1.5;
		color: var(--fg-muted);
	}
	.pdp-coa__card {
		text-align: left;
		border: 0;
		border-radius: var(--pdp-radius);
		background: var(--bg-elevated, var(--bg));
		padding: 20px 22px 22px;
	}
	.pdp-coa__card-head {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 16px;
		margin-bottom: 20px;
		padding-bottom: 16px;
		border-bottom: 1px solid var(--border);
	}
	.pdp-coa__batch {
		margin: 0 0 4px;
		font-size: 15px;
		font-weight: 600;
		color: var(--fg);
	}
	.pdp-coa__batch span {
		font-weight: 500;
		color: var(--fg-muted);
	}
	.pdp-coa__lab {
		margin: 0;
		font-size: 13px;
		color: var(--fg-muted);
	}
	.pdp-coa__card-status {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 8px;
		flex-shrink: 0;
	}
	.pdp-coa__pass {
		display: inline-flex;
		padding: 4px 10px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--success, #059669) 14%, transparent);
		color: var(--success, #059669);
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.08em;
	}
	.pdp-coa__live {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 12px;
		font-weight: 500;
		color: var(--fg-muted);
	}
	.pdp-coa__live-dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background: var(--success, #059669);
		box-shadow: 0 0 0 0 color-mix(in srgb, var(--success, #059669) 40%, transparent);
		animation: pdp-coa-pulse 2s ease-out infinite;
	}
	@keyframes pdp-coa-pulse {
		0% {
			box-shadow: 0 0 0 0 color-mix(in srgb, var(--success, #059669) 45%, transparent);
		}
		70% {
			box-shadow: 0 0 0 8px color-mix(in srgb, var(--success, #059669) 0%, transparent);
		}
		100% {
			box-shadow: 0 0 0 0 transparent;
		}
	}
	.pdp-coa__grid {
		list-style: none;
		margin: 0 0 22px;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 16px 20px;
	}
	@media (max-width: 640px) {
		.pdp-coa__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}
	.pdp-coa__metric-label {
		display: block;
		margin-bottom: 4px;
		font-size: 11px;
		font-weight: 500;
		color: var(--fg-muted);
	}
	.pdp-coa__metric-value {
		display: block;
		font-size: 14px;
		font-weight: 600;
		color: var(--fg);
	}
	.pdp-coa__actions {
		display: flex;
		justify-content: center;
	}
	.pdp-coa__download {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		min-width: min(100%, 280px);
		padding: 14px 28px;
		border: 0;
		border-radius: var(--pdp-radius);
		background: var(--accent);
		color: var(--accent-fg);
		font: inherit;
		font-size: 14px;
		font-weight: 600;
		text-decoration: none;
		cursor: pointer;
		transition: opacity var(--dur-fast) var(--ease);
	}
	.pdp-coa__download:hover:not(:disabled) {
		opacity: 0.92;
	}
	.pdp-coa__download--pending {
		opacity: 0.45;
		cursor: not-allowed;
	}
	.pdp-coa__disclaimer {
		margin: 20px 0 0;
		font-size: 11px;
		line-height: 1.55;
		color: var(--fg-faint, var(--fg-muted));
	}
</style>
