<script lang="ts">
	import type { AccordionModuleConfig, SpacingPreset } from '$lib/config.svelte';

	let { config, spacing_v = 'normal', spacing_h = 'normal', center_header = false }: { config: AccordionModuleConfig; spacing_v?: SpacingPreset; spacing_h?: SpacingPreset; center_header?: boolean } = $props();
	let openIndex = $state<number | null>(null);

	function toggle(i: number) {
		openIndex = openIndex === i ? null : i;
	}
</script>

{#if config.items?.length}
	<section class="accordion" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'} id={config.title?.toLowerCase().replace(/\s+/g, '-') || 'accordion'}>
		<h2 class="accordion__title" class:is-centered={center_header}>{config.title}</h2>
		<div class="accordion__list">
			{#each config.items as item, i}
				<div class="accordion__item">
					<button
						class="accordion__trigger"
						class:is-open={openIndex === i}
						onclick={() => toggle(i)}
						aria-expanded={openIndex === i}
					>
						<span class="accordion__question">{item.q}</span>
						<svg class="accordion__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M6 9l6 6 6-6" />
						</svg>
					</button>
					<div class="accordion__panel" class:is-open={openIndex === i}>
						<div class="accordion__answer accordion__answer--html">
							{@html item.a}
						</div>
					</div>
				</div>
			{/each}
		</div>
	</section>
{/if}

<style>
	.accordion {
		--mod-pt: 40px;
		--mod-pb: 40px;
		--mod-px: 28px;
		--mod-max-w: 960px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.accordion.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.accordion.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.accordion.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.accordion.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }

	.accordion__title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 13px;
		font-weight: var(--heading-weight, 600);
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg);
		margin: 0 0 24px;
	}
	.accordion__title.is-centered {
		text-align: center;
	}

	.accordion__list {
		border-top: 1px solid var(--border);
	}

	.accordion__item {
		border-bottom: 1px solid var(--border);
	}

	.accordion__trigger {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		padding: 18px 0;
		background: none;
		border: none;
		cursor: pointer;
		text-align: left;
		font-family: var(--font-sans);
		color: var(--fg);
	}

	.accordion__question {
		font-size: 14px;
		font-weight: 500;
		letter-spacing: -0.01em;
		line-height: 1.4;
		padding-right: 16px;
	}

	.accordion__chevron {
		flex-shrink: 0;
		color: var(--fg-muted);
		transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
	}

	.accordion__trigger.is-open .accordion__chevron {
		transform: rotate(180deg);
	}

	/* Grid-row height animation. Panel clips the item box (which has
	   padding-bottom that would otherwise overflow the 0-height track),
	   while the item itself clips its own text content. */
	.accordion__panel {
		display: grid;
		grid-template-rows: minmax(0, 0fr);
		overflow: hidden;
		transition: grid-template-rows 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	}

	.accordion__panel.is-open {
		grid-template-rows: minmax(0, 1fr);
	}

	.accordion__answer {
		min-height: 0;
		overflow: hidden;
	}

	/* Rich HTML content from WYSIWYG editor */
	.accordion__answer--html {
		font-size: 14px;
		line-height: 1.65;
		color: var(--fg-muted);
		max-width: 640px;
		padding: 0 0 18px;
	}
	.accordion__answer--html :global(p) { margin: 0 0 10px; }
	.accordion__answer--html :global(p:last-child) { margin-bottom: 0; }
	.accordion__answer--html :global(a) { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
	.accordion__answer--html :global(strong) { font-weight: 700; }
	.accordion__answer--html :global(em) { font-style: italic; }
	.accordion__answer--html :global(ul),
	.accordion__answer--html :global(ol) { padding-left: 24px; margin: 0 0 10px; }
	.accordion__answer--html :global(li) { margin-bottom: 4px; }
</style>
