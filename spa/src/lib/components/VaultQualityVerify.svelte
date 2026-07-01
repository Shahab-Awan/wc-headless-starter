<script lang="ts">
	import { bridgeAwareHref } from '$lib/bridge-domain';
	import type {
		VaultQualityVerifyModuleConfig,
		SpacingPreset,
		ModuleResolved,
	} from '$lib/config.svelte';
	import { VAULT_QUALITY_VERIFY_DEFAULTS, mergeVaultQualityVerifyConfig } from '$lib/vault-page';
	import CircleCheck from '@lucide/svelte/icons/circle-check';
	import FileText from '@lucide/svelte/icons/file-text';
	import ChevronRight from '@lucide/svelte/icons/chevron-right';
	import Percent from '@lucide/svelte/icons/percent';
	import FlaskConical from '@lucide/svelte/icons/flask-conical';
	import ShieldCheck from '@lucide/svelte/icons/shield-check';
	import Box from '@lucide/svelte/icons/box';
	import TrendingUp from '@lucide/svelte/icons/trending-up';

	let {
		config,
		spacing_v = 'normal',
		spacing_h = 'normal',
		resolved,
	}: {
		config: VaultQualityVerifyModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		resolved?: ModuleResolved;
	} = $props();

	const accentStyle = $derived(
		resolved?.accent_color ? `--vault-qv-accent: ${resolved.accent_color};` : ''
	);

	const sectionTitle = $derived(
		config.section_title?.trim() || 'Quality You Can Verify, Not Just Trust'
	);

	function splitSectionTitle(title: string): { prefix: string; accent: string } {
		const verifyIdx = title.toLowerCase().indexOf('verify');
		if (verifyIdx <= 0) return { prefix: title, accent: '' };
		return {
			prefix: title.slice(0, verifyIdx).trim(),
			accent: title.slice(verifyIdx).trim(),
		};
	}

	const titleParts = $derived(splitSectionTitle(sectionTitle));
	const sectionSubtitle = $derived(
		config.section_subtitle?.trim() ||
			'Every batch is independently tested and documented. Review the data before you buy — not after.'
	);

	const defaultStats = [
		{ value: '99%+', label: 'Purity Guaranteed' },
		{ value: '5', label: 'Quality Checks' },
		{ value: '100%', label: 'U.S. Verified' },
	];

	const stats = $derived.by(() => {
		const rows = (config.stats ?? [])
			.map((row) => ({
				value: row.value?.trim() || '',
				label: row.label?.trim() || '',
			}))
			.filter((row) => row.value && row.label);
		return rows.length ? rows : defaultStats;
	});

	const defaultTabs =
		VAULT_QUALITY_VERIFY_DEFAULTS.tabs?.map((tab) => ({ ...tab })) ?? [];

	const mergedConfig = $derived(mergeVaultQualityVerifyConfig(config));

	const tabs = $derived(mergedConfig.tabs ?? defaultTabs);

	const tabIcons: Record<string, typeof Percent> = {
		Purity: Percent,
		Identity: FlaskConical,
		Endotoxin: ShieldCheck,
		Stability: Box,
		Consistency: TrendingUp,
	};
	const fallbackTabIcons = [Percent, FlaskConical, ShieldCheck, Box, TrendingUp];

	const purityBadgeTitle = $derived(
		mergedConfig.purity_badge_title?.trim() ||
			VAULT_QUALITY_VERIFY_DEFAULTS.purity_badge_title ||
			'99.4% Purity'
	);
	const purityBadgeSubtitle = $derived(
		mergedConfig.purity_badge_subtitle?.trim() ||
			VAULT_QUALITY_VERIFY_DEFAULTS.purity_badge_subtitle ||
			'Verified by HPLC'
	);
	const proofTitle = $derived(config.proof_link_title?.trim() || 'See the Proof');
	const proofSubtitle = $derived(
		config.proof_link_subtitle?.trim() || 'View our quality procedures'
	);
	const proofHref = $derived(bridgeAwareHref(config.proof_link_href?.trim() || '/coa-library'));
	const shopCtaText = $derived(config.shop_cta_text?.trim() || 'Shop Now →');
	const shopCtaHref = $derived(bridgeAwareHref(config.shop_cta_href?.trim() || '/shop'));
	const trustNote = $derived(
		config.trust_note?.trim() || 'Free COA included with every order'
	);

	const productImage = $derived(config.product_image?.trim() || '');
	const productAlt = $derived(config.product_image_alt?.trim() || 'Research peptide vial');
	const panelBg = $derived(config.panel_bg?.trim() || '#e8eef5');

	let activeIndex = $state(0);

	const activeTab = $derived(tabs[activeIndex] ?? tabs[0]);

	function selectTab(index: number) {
		activeIndex = index;
	}

	function verifiedHeading(title: string): string {
		return `Verified ${title}`;
	}
</script>

<section
	class="vault-qv"
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	class:is-h-compact={spacing_h === 'compact'}
	class:is-h-spacious={spacing_h === 'spacious'}
	style="{accentStyle} --vqv-panel-bg: {panelBg};"
	aria-labelledby="vault-qv-title"
>
	<div class="vault-qv__shell">
		<div class="vault-qv__grid">
			<div class="vault-qv__content">
				<h2 id="vault-qv-title" class="vault-qv__title">
					{#if titleParts.accent}
						<span class="vault-qv__title-pre">{titleParts.prefix}</span>
						<span class="vault-qv__title-accent">{titleParts.accent}</span>
					{:else}
						{sectionTitle}
					{/if}
				</h2>
				{#if sectionSubtitle}
					<p class="vault-qv__subtitle">{sectionSubtitle}</p>
				{/if}

				{#if stats.length}
					<ul class="vault-qv__stats" aria-label="Quality highlights">
						{#each stats as stat, i (stat.label)}
							<li class="vault-qv__stat">
								<strong class="vault-qv__stat-value">{stat.value}</strong>
								<span class="vault-qv__stat-label">{stat.label}</span>
							</li>
							{#if i < stats.length - 1}
								<li class="vault-qv__stat-divider" aria-hidden="true"></li>
							{/if}
						{/each}
					</ul>
				{/if}

				<div class="vault-qv__pills" role="tablist" aria-label="Quality verification topics">
					{#each tabs as tab, i (tab.title)}
						{@const TabIcon = tabIcons[tab.title ?? ''] ?? fallbackTabIcons[i % fallbackTabIcons.length]}
						<button
							type="button"
							class="vault-qv__pill"
							class:is-active={activeIndex === i}
							role="tab"
							id="vault-qv-tab-{i}"
							aria-selected={activeIndex === i}
							aria-controls="vault-qv-panel"
							onclick={() => selectTab(i)}
						>
							<TabIcon size={16} strokeWidth={1.85} aria-hidden="true" />
							<span>{tab.title}</span>
						</button>
					{/each}
				</div>

				{#if activeTab}
					<div
						class="vault-qv__detail-card"
						role="tabpanel"
						id="vault-qv-panel"
						aria-labelledby="vault-qv-tab-{activeIndex}"
					>
						<div class="vault-qv__detail-head">
							<h3 class="vault-qv__detail-title">{verifiedHeading(activeTab.title ?? '')}</h3>
							{#if activeTab.summary}
								<span class="vault-qv__detail-badge">
									<CircleCheck size={14} strokeWidth={2.2} aria-hidden="true" />
									{activeTab.summary}
								</span>
							{/if}
						</div>
						{#if activeTab.body}
							<div class="vault-qv__body">{@html activeTab.body}</div>
						{/if}
						{#if activeTab.chart_image}
							<img
								class="vault-qv__chart"
								src={activeTab.chart_image}
								alt="{activeTab.title} analysis chart"
								loading="lazy"
							/>
						{/if}
						{#if activeTab.why_matters}
							<div class="vault-qv__why">
								<span class="vault-qv__why-label">Why it matters</span>
								<p class="vault-qv__why-text">{activeTab.why_matters}</p>
							</div>
						{/if}
					</div>
				{/if}

				<div class="vault-qv__actions">
					<a class="vault-qv__shop-btn" href={shopCtaHref}>{shopCtaText}</a>
					{#if trustNote}
						<p class="vault-qv__trust-note">
							<CircleCheck size={16} strokeWidth={2.2} aria-hidden="true" />
							{trustNote}
						</p>
					{/if}
				</div>
			</div>

			<div class="vault-qv__visual">
				<div class="vault-qv__visual-panel">
					{#if productImage}
						<img class="vault-qv__cover" src={productImage} alt={productAlt} loading="lazy" />
					{/if}

					<div class="vault-qv__purity-badge">
						<span class="vault-qv__purity-icon" aria-hidden="true">
							<CircleCheck size={18} strokeWidth={2.4} />
						</span>
						<span class="vault-qv__purity-copy">
							<strong>{purityBadgeTitle}</strong>
							<span>{purityBadgeSubtitle}</span>
						</span>
					</div>

					<a class="vault-qv__proof" href={proofHref}>
						<span class="vault-qv__proof-icon" aria-hidden="true">
							<FileText size={20} strokeWidth={1.85} />
						</span>
						<span class="vault-qv__proof-copy">
							<strong>{proofTitle}</strong>
							<span>{proofSubtitle}</span>
						</span>
						<span class="vault-qv__proof-arrow" aria-hidden="true">
							<ChevronRight size={18} strokeWidth={2} />
						</span>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>

<style>
	.vault-qv {
		--mod-pt: clamp(40px, 5vw, 64px);
		--mod-pb: clamp(48px, 6vw, 72px);
		--mod-px: clamp(20px, 4vw, 32px);
		width: 100%;
		padding: var(--mod-pt) 0 var(--mod-pb);
		background: var(--bg);
	}
	.vault-qv.is-v-compact {
		--mod-pt: 32px;
		--mod-pb: 40px;
	}
	.vault-qv.is-v-spacious {
		--mod-pt: 80px;
		--mod-pb: 88px;
	}

	.vault-qv__shell {
		max-width: 1180px;
		margin: 0 auto;
		padding: 0 var(--mod-px, clamp(20px, 4vw, 32px));
	}

	.vault-qv__grid {
		display: grid;
		grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
		gap: clamp(20px, 3vw, 36px);
		align-items: center;
	}

	.vault-qv__content {
		min-width: 0;
		padding: clamp(8px, 1.5vw, 16px) 0;
	}

	.vault-qv__title {
		margin: 0 0 12px;
		max-width: 22ch;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(22px, 3.2vw, 34px);
		font-weight: 700;
		line-height: 1.1;
		letter-spacing: -0.025em;
		color: var(--fg);
	}

	.vault-qv__title-pre {
		font-weight: 700;
	}

	.vault-qv__title-accent {
		font-weight: 700;
		color: var(--vault-qv-accent, #0d9488);
	}

	.vault-qv__subtitle {
		margin: 0 0 clamp(22px, 3vw, 28px);
		font-size: clamp(15px, 1.7vw, 17px);
		line-height: 1.55;
		color: var(--fg-muted);
		max-width: 48ch;
	}

	.vault-qv__stats {
		list-style: none;
		margin: 0 0 clamp(22px, 3vw, 28px);
		padding: 0;
		display: flex;
		align-items: center;
		flex-wrap: wrap;
	}

	.vault-qv__stat {
		display: flex;
		flex-direction: column;
		gap: 2px;
		padding: 0 clamp(14px, 2vw, 22px);
	}
	.vault-qv__stat:first-child {
		padding-left: 0;
	}

	.vault-qv__stat-value {
		font-size: clamp(22px, 2.6vw, 28px);
		font-weight: 800;
		line-height: 1;
		letter-spacing: -0.03em;
		color: var(--fg);
	}

	.vault-qv__stat-label {
		font-size: 12px;
		line-height: 1.35;
		color: var(--fg-muted);
		max-width: 11ch;
	}

	.vault-qv__stat-divider {
		width: 1px;
		height: 36px;
		background: var(--border);
		flex-shrink: 0;
	}

	.vault-qv__pills {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		margin-bottom: 16px;
	}

	.vault-qv__pill {
		display: inline-flex;
		align-items: center;
		gap: 7px;
		padding: 9px 14px;
		border-radius: 999px;
		border: 1px solid var(--border);
		background: color-mix(in srgb, var(--fg) 4%, var(--bg));
		color: var(--fg-muted);
		font-size: 13px;
		font-weight: 600;
		cursor: pointer;
		transition:
			background var(--dur-fast) var(--ease),
			border-color var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease);
	}

	.vault-qv__pill:hover {
		border-color: color-mix(in srgb, var(--fg) 18%, var(--border));
		color: var(--fg);
	}

	.vault-qv__pill.is-active {
		background: var(--fg);
		border-color: var(--fg);
		color: var(--bg);
	}

	.vault-qv__pill.is-active :global(.lucide) {
		color: var(--bg);
	}

	.vault-qv__detail-card {
		padding: clamp(18px, 2.4vw, 22px);
		border-radius: 16px;
		background: color-mix(in srgb, var(--fg) 3.5%, var(--bg));
		border: 1px solid color-mix(in srgb, var(--border) 90%, transparent);
		margin-bottom: clamp(18px, 2.5vw, 24px);
	}

	.vault-qv__detail-head {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 10px;
		margin-bottom: 12px;
	}

	.vault-qv__detail-title {
		margin: 0;
		font-size: clamp(17px, 1.9vw, 19px);
		font-weight: 800;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.vault-qv__detail-badge {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		padding: 5px 10px;
		border-radius: 999px;
		background: color-mix(in srgb, #22c55e 14%, var(--bg));
		color: #15803d;
		font-size: 12px;
		font-weight: 700;
	}

	.vault-qv__body :global(p) {
		margin: 0 0 14px;
		font-size: 14px;
		line-height: 1.58;
		color: var(--fg-muted);
	}

	.vault-qv__body :global(p:last-child) {
		margin-bottom: 0;
	}

	.vault-qv__chart {
		display: block;
		width: 100%;
		max-width: 100%;
		margin: 14px 0 0;
		border-radius: 10px;
		border: 1px solid color-mix(in srgb, var(--border) 88%, transparent);
		background: var(--bg);
	}

	.vault-qv__why {
		margin-top: 14px;
		padding: 12px 14px;
		border-radius: 10px;
		border: 1px solid color-mix(in srgb, #22c55e 22%, var(--border));
		border-left: 4px solid #22c55e;
		background: var(--bg);
	}

	.vault-qv__why-label {
		display: block;
		margin-bottom: 4px;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.07em;
		color: #15803d;
	}

	.vault-qv__why-text {
		margin: 0;
		font-size: 14px;
		line-height: 1.55;
		color: var(--fg);
	}

	.vault-qv__actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 14px 18px;
	}

	.vault-qv__shop-btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 13px 22px;
		border-radius: 999px;
		background: var(--fg);
		color: var(--bg);
		font-size: 15px;
		font-weight: 700;
		text-decoration: none;
		transition: opacity var(--dur-fast) var(--ease);
	}

	.vault-qv__shop-btn:hover {
		opacity: 0.88;
	}

	.vault-qv__trust-note {
		display: inline-flex;
		align-items: center;
		gap: 7px;
		margin: 0;
		font-size: 13px;
		font-weight: 600;
		color: #15803d;
	}

	.vault-qv__visual {
		min-width: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		align-self: center;
	}

	.vault-qv__visual-panel {
		position: relative;
		width: 100%;
		height: clamp(360px, 42vw, 520px);
		border-radius: 22px;
		background: var(--vqv-panel-bg);
		overflow: hidden;
	}

	.vault-qv__cover {
		position: absolute;
		inset: 0;
		width: 100%;
		height: 100%;
		object-fit: cover;
		object-position: center;
	}

	.vault-qv__purity-badge {
		position: absolute;
		top: clamp(16px, 2.5vw, 22px);
		right: clamp(16px, 2.5vw, 22px);
		z-index: 2;
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 10px 14px;
		border-radius: 14px;
		background: var(--bg);
		border: 1px solid color-mix(in srgb, var(--border) 88%, transparent);
		box-shadow: 0 10px 28px color-mix(in srgb, var(--fg) 10%, transparent);
		max-width: calc(100% - 32px);
	}

	.vault-qv__purity-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 34px;
		height: 34px;
		border-radius: 999px;
		background: color-mix(in srgb, #22c55e 16%, var(--bg));
		color: #15803d;
		flex-shrink: 0;
	}

	.vault-qv__purity-copy {
		display: flex;
		flex-direction: column;
		gap: 1px;
		min-width: 0;
	}

	.vault-qv__purity-copy strong {
		font-size: 14px;
		font-weight: 800;
		line-height: 1.2;
		color: var(--fg);
	}

	.vault-qv__purity-copy span {
		font-size: 12px;
		line-height: 1.3;
		color: var(--fg-muted);
	}

	.vault-qv__proof {
		position: absolute;
		left: clamp(14px, 2vw, 18px);
		right: clamp(14px, 2vw, 18px);
		bottom: clamp(14px, 2vw, 18px);
		z-index: 2;
		display: grid;
		grid-template-columns: auto 1fr auto;
		align-items: center;
		gap: 12px;
		padding: 12px 14px;
		border-radius: 14px;
		background: var(--bg);
		border: 1px solid color-mix(in srgb, var(--border) 88%, transparent);
		box-shadow: 0 10px 28px color-mix(in srgb, var(--fg) 10%, transparent);
		text-decoration: none;
		color: inherit;
		transition:
			border-color var(--dur-fast) var(--ease),
			box-shadow var(--dur-fast) var(--ease);
	}

	.vault-qv__proof:hover {
		border-color: color-mix(in srgb, var(--accent) 35%, var(--border));
		box-shadow: 0 12px 32px color-mix(in srgb, var(--fg) 12%, transparent);
	}

	.vault-qv__proof-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 40px;
		height: 40px;
		border-radius: 10px;
		background: color-mix(in srgb, var(--fg) 5%, var(--bg));
		color: var(--fg-muted);
	}

	.vault-qv__proof-copy {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 0;
	}

	.vault-qv__proof-copy strong {
		font-size: 14px;
		font-weight: 800;
		color: var(--fg);
	}

	.vault-qv__proof-copy span {
		font-size: 12px;
		color: var(--fg-muted);
	}

	.vault-qv__proof-arrow {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 32px;
		height: 32px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--fg) 5%, var(--bg));
		color: var(--fg-muted);
	}

	@media (max-width: 900px) {
		.vault-qv__grid {
			grid-template-columns: 1fr;
		}

		.vault-qv__visual-panel {
			height: clamp(260px, 58vw, 360px);
		}

		.vault-qv__title {
			max-width: none;
		}

		.vault-qv__stat-divider {
			display: none;
		}

		.vault-qv__stat {
			padding: 0 12px 0 0;
		}
	}
</style>
