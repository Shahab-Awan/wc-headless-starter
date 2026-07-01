<script lang="ts">
	import type {
		VaultGuaranteeCard,
		VaultQualityTabsModuleConfig,
		SpacingPreset,
		ModuleResolved,
	} from '$lib/config.svelte';
	import { vaultGuaranteeIconMarkup } from '$lib/vault-guarantee-icons';

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
		resolved?.accent_color ? `--accent: ${resolved.accent_color};` : ''
	);

	const sectionTitle = $derived(config.section_title?.trim() || 'The Alyve Vault Guarantee');
	const sectionSubtitle = $derived(
		config.section_subtitle?.trim() ||
			'Documented quality for research and laboratory use. Every batch meets our internal purity standards.'
	);
	const productImage = $derived(config.product_image?.trim() || '');
	const productAlt = $derived(config.product_image_alt?.trim() || 'Research peptide vial');
	const imageBadge = $derived(
		config.image_badge?.trim() || '99.4% Purity — Verified by HPLC'
	);
	const panelBg = $derived(config.panel_bg?.trim() || '#ebe6f5');

	const defaultGuaranteeCards: VaultGuaranteeCard[] = [
		{
			title: '99% Purity Guaranteed',
			description: 'Every batch verified',
			tooltip: '',
			accent: 'green',
			icon: 'purity',
		},
		{
			title: 'Shipment Protection',
			description: 'Every order fully covered',
			tooltip:
				'Full replacement or refund if your shipment is lost or damaged in transit.',
			accent: 'blue',
			icon: 'shipping',
		},
		{
			title: 'COA with Every Batch',
			description: 'Third Party tested in America',
			tooltip:
				'Independent U.S. lab Certificates of Analysis ship with every order and are published before purchase.',
			accent: 'yellow',
			icon: 'coa',
		},
	];

	const guaranteeCards = $derived.by(() => {
		const rows = (config.guarantee_cards ?? [])
			.map((card) => ({
				title: card.title?.trim() || '',
				description: card.description?.trim() || '',
				tooltip: card.tooltip?.trim() || '',
				accent: card.accent?.trim() || 'green',
				icon: card.icon?.trim() || 'purity',
			}))
			.filter((card) => card.title);
		return rows.length ? rows : defaultGuaranteeCards;
	});

	function cardAccentClass(accent: string): string {
		return accent === 'blue' || accent === 'yellow' || accent === 'green' ? accent : 'green';
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
				<h2 id="vault-qt-title" class="vault-qt__title wchs-section-heading">{sectionTitle}</h2>
				{#if sectionSubtitle}
					<p class="vault-qt__subtitle">{sectionSubtitle}</p>
				{/if}

				<ul class="vault-qt__guarantees" aria-label="Vault guarantees">
					{#each guaranteeCards as card (card.title)}
						<li class="vault-qt__card vault-qt__card--{cardAccentClass(card.accent || 'green')}">
							<div class="vault-qt__card-icon" aria-hidden="true">
								<svg
									class="vault-qt__card-glyph"
									viewBox="0 0 24 24"
									width="28"
									height="28"
									fill="none"
									focusable="false"
								>
									{@html vaultGuaranteeIconMarkup(card.icon)}
								</svg>
							</div>
							<div class="vault-qt__card-copy">
								<h3 class="vault-qt__card-title">
									{card.title}
									{#if card.tooltip}
										<button
											type="button"
											class="vault-qt__card-tip"
											title={card.tooltip}
											aria-label="More about {card.title}"
										>?</button>
									{/if}
								</h3>
								{#if card.description}
									<p class="vault-qt__card-desc">{card.description}</p>
								{/if}
							</div>
						</li>
					{/each}
				</ul>
			</div>
		</div>
	</div>
</section>

<style>
	.vault-qt {
		--mod-pt: 0;
		--mod-pb: 0;
		width: 100%;
		max-width: 100%;
		margin-left: 0;
		margin-right: 0;
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
	}

	.vault-qt__subtitle {
		margin: 0 0 clamp(22px, 3vw, 28px);
		font-size: clamp(16px, 1.8vw, 18px);
		line-height: 1.4;
		letter-spacing: -0.02em;
		color: var(--fg-muted);
		max-width: 46ch;
	}

	.vault-qt__guarantees {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	.vault-qt__card {
		display: grid;
		grid-template-columns: auto 1fr;
		align-items: center;
		gap: 16px;
		padding: 16px 18px 16px 0;
		border-radius: 14px;
		background: var(--bg);
		border: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
		box-shadow: 0 10px 28px color-mix(in srgb, var(--fg) 6%, transparent);
		position: relative;
		overflow: hidden;
	}

	.vault-qt__card::before {
		content: '';
		position: absolute;
		left: 0;
		top: 0;
		bottom: 0;
		width: 4px;
		background: var(--vqt-card-bar, var(--accent));
	}

	.vault-qt__card--green {
		--vqt-card-bar: #86efac;
		--vqt-card-icon-bg: color-mix(in srgb, #86efac 22%, var(--bg));
		--vqt-card-icon-fg: #15803d;
	}
	.vault-qt__card--blue {
		--vqt-card-bar: #93c5fd;
		--vqt-card-icon-bg: color-mix(in srgb, #93c5fd 24%, var(--bg));
		--vqt-card-icon-fg: #1d4ed8;
	}
	.vault-qt__card--yellow {
		--vqt-card-bar: #fde047;
		--vqt-card-icon-bg: color-mix(in srgb, #fde047 28%, var(--bg));
		--vqt-card-icon-fg: #a16207;
	}

	.vault-qt__card-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 58px;
		height: 58px;
		margin-left: 18px;
		border-radius: 999px;
		background: radial-gradient(
			circle at 35% 28%,
			color-mix(in srgb, white 72%, var(--vqt-card-icon-bg)) 0%,
			var(--vqt-card-icon-bg) 58%
		);
		color: var(--vqt-card-icon-fg);
		flex-shrink: 0;
	}

	.vault-qt__card-glyph {
		display: block;
	}

	.vault-qt__card-copy {
		min-width: 0;
		padding-right: 8px;
	}

	.vault-qt__card-title {
		display: flex;
		align-items: center;
		gap: 8px;
		margin: 0 0 4px;
		font-size: 15px;
		font-weight: 700;
		color: var(--fg);
	}

	.vault-qt__card-tip {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 18px;
		height: 18px;
		padding: 0;
		border: 1px solid var(--border);
		border-radius: 999px;
		background: color-mix(in srgb, var(--fg) 4%, var(--bg));
		color: var(--fg-muted);
		font-size: 11px;
		font-weight: 700;
		cursor: help;
	}

	.vault-qt__card-desc {
		margin: 0;
		font-size: 14px;
		line-height: 1.4;
		color: var(--fg-muted);
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
