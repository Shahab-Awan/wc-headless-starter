<script lang="ts">
	import type { CTAModuleConfig, ModuleResolved, SpacingPreset } from '$lib/config.svelte';

	let { config, resolved, spacing_v = 'normal', spacing_h = 'normal' }: {
		config: CTAModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
	} = $props();

	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color}` : '');
</script>

<section
	class="cta"
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	class:is-h-compact={spacing_h === 'compact'}
	class:is-h-spacious={spacing_h === 'spacious'}
	style={accentStyle}
>
	<div class="cta__inner" data-align={config.align}>
		<a
			class="cta__btn"
			data-style={config.style}
			data-size={config.size}
			href={config.href || '#'}
			target={config.open_new_tab ? '_blank' : undefined}
			rel={config.open_new_tab ? 'noopener noreferrer' : undefined}
		>
			<span class="cta__label">{config.label || 'Learn more'}</span>
			{#if config.style === 'text'}
				<svg class="cta__arrow" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M5 12h14M13 5l7 7-7 7" />
				</svg>
			{/if}
		</a>
	</div>
</section>

<style>
	.cta {
		--mod-pt: 24px;
		--mod-pb: 24px;
		--mod-px: 28px;
		--mod-max-w: 1200px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.cta.is-v-compact  { --mod-pt: var(--wchs-spacing-v-compact, 8px);  --mod-pb: var(--wchs-spacing-v-compact, 8px); }
	.cta.is-v-spacious { --mod-pt: var(--wchs-spacing-v-spacious, 48px); --mod-pb: var(--wchs-spacing-v-spacious, 56px); }
	.cta.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.cta.is-h-spacious { --mod-max-w: 960px; --mod-px: 40px; }

	.cta__inner {
		display: flex;
	}
	.cta__inner[data-align='left']   { justify-content: flex-start; }
	.cta__inner[data-align='center'] { justify-content: center; }
	.cta__inner[data-align='right']  { justify-content: flex-end; }

	.cta__btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		font-family: var(--font-sans);
		font-weight: 500;
		letter-spacing: 0.02em;
		text-decoration: none;
		border-radius: var(--wchs-radius, 0);
		cursor: pointer;
		transition: transform 0.15s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
	}
	.cta__btn:active { transform: translateY(1px); }

	.cta__btn[data-size='sm'] { padding: 8px 16px;  font-size: 13px; }
	.cta__btn[data-size='md'] { padding: 12px 24px; font-size: 14px; }
	.cta__btn[data-size='lg'] { padding: 16px 32px; font-size: 15px; }

	.cta__btn[data-style='primary'] {
		background: var(--accent);
		color: var(--accent-fg, #fff);
		border: 1px solid var(--accent);
	}
	.cta__btn[data-style='primary']:hover {
		filter: brightness(1.08);
	}

	.cta__btn[data-style='ghost'] {
		background: transparent;
		color: var(--accent);
		border: 1px solid var(--accent);
	}
	.cta__btn[data-style='ghost']:hover {
		background: var(--accent);
		color: var(--accent-fg, #fff);
	}

	.cta__btn[data-style='text'] {
		padding-left: 0;
		padding-right: 0;
		background: transparent;
		color: var(--accent);
		border: none;
		border-bottom: 1px solid transparent;
		text-underline-offset: 4px;
		border-radius: 0;
	}
	.cta__btn[data-style='text']:hover {
		border-bottom-color: currentColor;
	}
	.cta__btn[data-style='text'] .cta__arrow {
		transition: transform 0.2s ease;
	}
	.cta__btn[data-style='text']:hover .cta__arrow {
		transform: translateX(3px);
	}
</style>
