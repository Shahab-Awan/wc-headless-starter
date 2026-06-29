<script lang="ts">
	import type { TrustBarModuleConfig, SpacingPreset, ModuleResolved } from '$lib/config.svelte';

	let { config, spacing_v = 'normal', spacing_h = 'normal', resolved }: { config: TrustBarModuleConfig; spacing_v?: SpacingPreset; spacing_h?: SpacingPreset; resolved?: ModuleResolved } = $props();

	// Per-module accent override. When the admin sets module.overrides.accent_color
	// on a trust_bar, the resolver produces resolved.accent_color. Scope --accent
	// to this section's subtree via inline style so icons use the override without
	// affecting siblings or the site default.
	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color}` : '');

	// Stroke-width 1.9, rounded caps/joins, hand-tuned geometry.
	const icons: Record<string, string> = {
		shipping: '<path d="M3.8 8.8h10.2v7.7H3.8z"/><path d="M14 11h3.1l3.1 3.2v2.3H14"/><circle cx="8" cy="17.6" r="1.7"/><circle cx="17.6" cy="17.6" r="1.7"/>',
		lab: '<path d="M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8"/><path d="M9.1 14.6h5.8"/>',
		shield: '<path d="M12 3.6 18.4 6v5.5c0 4-2.5 7.5-6.4 8.9-3.9-1.4-6.4-4.9-6.4-8.9V6Z"/><path d="m9.3 12.2 1.9 1.9 3.6-3.9"/>',
		star: '<path d="M12 3.6l2.3 4.9 5.4.8-3.9 3.8.9 5.3-4.7-2.5-4.8 2.5.9-5.3L4.2 9.3l5.4-.8L12 3.6z" fill="currentColor" stroke="none"/>',
		heart: '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
		lock: '<rect x="4.5" y="11" width="15" height="9.5" rx="1.5"/><path d="M7.5 11V8a4.5 4.5 0 0 1 9 0v3"/>',
		clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		refresh: '<path d="M21.5 5v5h-5"/><path d="M2.5 19v-5h5"/><path d="M4.2 9.5a8.5 8.5 0 0 1 14-3l3.3 3.5M2.5 14l3.3 3.5a8.5 8.5 0 0 0 14-3"/>',
		check: '<path d="M5 12.5l4.2 4.2L19 7"/>',
		leaf: '<path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.95-2.3c.48.17.98.3 1.34.3C19 20 22 3 22 3c-1 2-8 2.25-13 3.25S2 11.5 2 13.5s1.75 3.75 1.75 3.75"/>',
		gift: '<path d="M4 12v9a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-9"/><rect x="2.5" y="7.5" width="19" height="5" rx="1"/><path d="M12 22V7.5"/><path d="M12 7.5H8a2.5 2.5 0 0 1 0-5C11 2.5 12 7.5 12 7.5z"/><path d="M12 7.5h4a2.5 2.5 0 0 0 0-5C13 2.5 12 7.5 12 7.5z"/>',
		award: '<circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5L7 22l5-3 5 3-1.5-8.5"/>',
		globe: '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5a13 13 0 0 1 3.5 8.5 13 13 0 0 1-3.5 8.5 13 13 0 0 1-3.5-8.5A13 13 0 0 1 12 3.5z"/>',
		wallet: '<rect x="2.5" y="5.5" width="19" height="14.5" rx="1.5"/><path d="M2.5 10h19"/><circle cx="17" cy="14.5" r="1.2" fill="currentColor" stroke="none"/>',
		users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7.5" r="3.5"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		zap: '<path d="M13 2L3 14h9l-1 8 10-12h-9z"/>',
		percent: '<path d="M19 5L5 19"/><circle cx="6.5" cy="6.5" r="2.2"/><circle cx="17.5" cy="17.5" r="2.2"/>',
		phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.88.37 1.76.7 2.61a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.33 1.73.57 2.61.7A2 2 0 0 1 22 16.92z"/>',
		package: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>',
		thumbsup: '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>',
		database: '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
		cart: '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
		bag: '<circle cx="10" cy="20.5" r="1.5"/><circle cx="18" cy="20.5" r="1.5"/><path d="M2.5 2.5h3l2.7 12.4a1.5 1.5 0 0 0 1.5 1.1h7.7a1.5 1.5 0 0 0 1.4-1l2.7-7.2H7.1"/>',
		sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
		moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>',
	};
</script>

{#if config.items?.length}
	<section class="trust-bar" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'} style={accentStyle}>
		<div class="trust-bar__grid" style="--cols: {Math.min(config.items.length, 4)}">
			{#each config.items as item}
				<div class="trust-bar__item">
					{#if item.icon && icons[item.icon]}
						<svg class="trust-bar__icon" class:is-accent={config.icon_accent} viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
							{@html icons[item.icon]}
						</svg>
					{/if}
					<h3 class="trust-bar__headline">{item.headline}</h3>
					{#if item.description}
						<p class="trust-bar__desc">{item.description}</p>
					{/if}
				</div>
			{/each}
		</div>
	</section>
{/if}

<style>
	.trust-bar {
		--mod-pt: 32px;
		--mod-pb: 32px;
		--mod-px: 28px;
		--mod-max-w: 1200px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
		border-top: 1px solid var(--border);
		border-bottom: 1px solid var(--border);
	}
	.trust-bar.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.trust-bar.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.trust-bar.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.trust-bar.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }

	.trust-bar__grid {
		display: grid;
		grid-template-columns: repeat(var(--cols, 3), 1fr);
		gap: 0;
	}

	@media (max-width: 639px) {
		.trust-bar__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}

	.trust-bar__item {
		text-align: center;
		padding: 24px 20px;
	}
	.trust-bar__item + .trust-bar__item {
		border-left: 1px solid var(--border);
	}
	@media (max-width: 639px) {
		.trust-bar {
			--mod-px: 16px;
		}

		.trust-bar__item {
			padding: 16px 12px;
		}

		.trust-bar__item + .trust-bar__item {
			border-left: none;
			border-top: none;
		}

		.trust-bar__item:nth-child(2n) {
			border-left: 1px solid var(--border);
		}

		.trust-bar__item:nth-child(n + 3) {
			border-top: 1px solid var(--border);
		}

		.trust-bar__headline {
			font-size: 10px;
			letter-spacing: 0.06em;
		}

		.trust-bar__desc {
			font-size: 12px;
			max-width: none;
		}

		.trust-bar__icon {
			width: 24px;
			height: 24px;
			margin-bottom: 8px;
		}
	}

	.trust-bar__icon {
		color: var(--fg);
		margin-bottom: 12px;
		opacity: 0.7;
	}
	.trust-bar__icon.is-accent {
		color: var(--accent);
		opacity: 1;
	}

	.trust-bar__headline {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg);
		margin: 0 0 6px;
	}

	.trust-bar__desc {
		font-size: 13px;
		line-height: 1.5;
		color: var(--fg-muted);
		margin: 0;
		max-width: 280px;
		margin-left: auto;
		margin-right: auto;
	}
</style>
