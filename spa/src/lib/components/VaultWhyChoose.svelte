<script lang="ts">
	import type { VaultWhyChooseModuleConfig, SpacingPreset, ModuleResolved } from '$lib/config.svelte';
	import {
		normalizeVaultWhyChooseAccent,
	} from '$lib/vault-why-choose-icons';
	import VaultWhyChooseIcon from '$lib/components/VaultWhyChooseIcon.svelte';

	let {
		config,
		spacing_v = 'normal',
		spacing_h = 'normal',
		resolved,
	}: {
		config: VaultWhyChooseModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		resolved?: ModuleResolved;
	} = $props();

	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color};` : '');

	const sectionTitle = $derived(config.section_title?.trim() || 'Why Choose Alyve');

	const defaultItems = [
		{
			title: 'Always In Stock',
			description:
				'Core research compounds restocked on a reliable cadence — your protocol stays on schedule.',
			icon: 'stock',
			accent: 'violet',
		},
		{
			title: 'Volume Pricing',
			description:
				'Transparent tiered discounts from 3 vials up — scale your order and save on every batch.',
			icon: 'volume',
			accent: 'green',
		},
		{
			title: 'Safe & Protected Shipping',
			description:
				'Tracked domestic fulfillment with shipment protection on every order.',
			icon: 'shipping',
			accent: 'amber',
		},
		{
			title: 'Third-Party Verified',
			description:
				'Independent U.S. laboratory testing confirms identity, purity, and safety before release.',
			icon: 'verified',
			accent: 'rose',
		},
		{
			title: 'COA Every Batch',
			description:
				'Full Certificates of Analysis published for every lot — review documentation before you buy.',
			icon: 'coa',
			accent: 'blue',
		},
		{
			title: 'Same-Day Fulfillment',
			description:
				'Orders placed before 2PM EST ship same day via tracked carrier.',
			icon: 'fulfillment',
			accent: 'teal',
		},
	];

	const items = $derived.by(() => {
		const rows = (config.items ?? [])
			.map((row) => ({
				title: row.title?.trim() || '',
				description: row.description?.trim() || '',
				icon: row.icon?.trim() || 'stock',
				accent: normalizeVaultWhyChooseAccent(row.accent),
			}))
			.filter((row) => row.title);
		return rows.length ? rows : defaultItems;
	});
</script>

{#if items.length}
	<section
		class="vault-wc"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
		aria-labelledby="vault-wc-title"
	>
		<div class="vault-wc__inner">
			<h2 id="vault-wc-title" class="vault-wc__title wchs-section-heading">{sectionTitle}</h2>

			<ul class="vault-wc__grid">
				{#each items as item (item.title)}
					<li class="vault-wc__card vault-wc__card--{item.accent}">
						<div class="vault-wc__icon" aria-hidden="true">
							<VaultWhyChooseIcon icon={item.icon} size={28} strokeWidth={1.75} />
						</div>
						<h3 class="vault-wc__card-title">{item.title}</h3>
						{#if item.description}
							<p class="vault-wc__card-desc">{item.description}</p>
						{/if}
					</li>
				{/each}
			</ul>
		</div>
	</section>
{/if}

<style>
	.vault-wc {
		--mod-pt: clamp(48px, 6vw, 72px);
		--mod-pb: clamp(56px, 7vw, 88px);
		--mod-px: clamp(20px, 4vw, 32px);
		--mod-max-w: 1180px;
		width: 100%;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.vault-wc.is-v-compact {
		--mod-pt: 36px;
		--mod-pb: 44px;
	}
	.vault-wc.is-v-spacious {
		--mod-pt: 88px;
		--mod-pb: 96px;
	}
	.vault-wc.is-h-compact {
		--mod-max-w: 100%;
		--mod-px: 16px;
	}
	.vault-wc.is-h-spacious {
		--mod-max-w: 920px;
		--mod-px: 40px;
	}

	.vault-wc__inner {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: clamp(28px, 4vw, 40px);
	}

	.vault-wc__title {
		margin: 0;
		text-align: center;
		max-width: 24ch;
	}

	.vault-wc__grid {
		list-style: none;
		margin: 0;
		padding: 0;
		width: 100%;
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: clamp(14px, 2vw, 20px);
	}

	.vault-wc__card {
		padding: clamp(18px, 2.4vw, 24px) clamp(16px, 2vw, 22px);
		border-radius: 18px;
		background: var(--bg);
		border: 1px solid color-mix(in srgb, var(--border) 88%, transparent);
		box-shadow: 0 12px 32px color-mix(in srgb, var(--fg) 7%, transparent);
	}

	.vault-wc__card--violet {
		--vwc-icon-bg: color-mix(in srgb, #a78bfa 24%, var(--bg));
		--vwc-icon-fg: #6d28d9;
	}
	.vault-wc__card--green {
		--vwc-icon-bg: color-mix(in srgb, #86efac 22%, var(--bg));
		--vwc-icon-fg: #15803d;
	}
	.vault-wc__card--amber {
		--vwc-icon-bg: color-mix(in srgb, #fcd34d 26%, var(--bg));
		--vwc-icon-fg: #b45309;
	}
	.vault-wc__card--rose {
		--vwc-icon-bg: color-mix(in srgb, #fda4af 24%, var(--bg));
		--vwc-icon-fg: #be123c;
	}
	.vault-wc__card--blue {
		--vwc-icon-bg: color-mix(in srgb, #93c5fd 24%, var(--bg));
		--vwc-icon-fg: #1d4ed8;
	}
	.vault-wc__card--teal {
		--vwc-icon-bg: color-mix(in srgb, #5eead4 22%, var(--bg));
		--vwc-icon-fg: #0f766e;
	}

	.vault-wc__icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 52px;
		height: 52px;
		border-radius: 14px;
		margin-bottom: 16px;
		background: radial-gradient(
			circle at 35% 28%,
			color-mix(in srgb, white 70%, var(--vwc-icon-bg)) 0%,
			var(--vwc-icon-bg) 58%
		);
		color: var(--vwc-icon-fg);
		box-shadow: 0 8px 20px color-mix(in srgb, var(--vwc-icon-fg) 14%, transparent);
	}

	.vault-wc__icon :global(.lucide) {
		display: block;
	}

	.vault-wc__card-title {
		margin: 0 0 8px;
		font-size: clamp(15px, 1.6vw, 17px);
		font-weight: 800;
		line-height: 1.25;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.vault-wc__card-desc {
		margin: 0;
		font-size: clamp(13px, 1.4vw, 14px);
		line-height: 1.58;
		color: var(--fg-muted);
	}

	@media (max-width: 900px) {
		.vault-wc__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}

	@media (max-width: 560px) {
		.vault-wc__grid {
			grid-template-columns: 1fr;
		}
		.vault-wc__icon {
			width: 48px;
			height: 48px;
			border-radius: 12px;
			margin-bottom: 12px;
		}
	}
</style>
