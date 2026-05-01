<script lang="ts">
	/**
	 * Video / embed module. Detects YouTube, Vimeo, or direct .mp4 from
	 * `source_url` and renders the appropriate tag. MP4 uses native <video>
	 * with optional poster; YouTube/Vimeo use their official iframe embeds
	 * with query params mirroring the module's boolean toggles.
	 *
	 * Autoplay implies muted (browser policy), so `autoplay=true` forces
	 * `muted=true` in the resolved attributes regardless of the config.
	 */
	import type { SpacingPreset } from '$lib/config.svelte';

	type VideoConfig = {
		title?: string;
		source_url?: string;
		poster_url?: string;
		aspect_ratio?: '16/9' | '4/3' | '1/1' | '9/16';
		autoplay?: boolean;
		muted?: boolean;
		loop?: boolean;
		controls?: boolean;
	};

	let { config, spacing_v = 'normal', spacing_h = 'normal', center_header = false }: {
		config: VideoConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
	} = $props();

	// Resolve booleans — autoplay forces muted.
	const autoplay = $derived(!!config.autoplay);
	const muted = $derived(autoplay || config.muted !== false);
	const loop = $derived(!!config.loop);
	const controls = $derived(config.controls !== false);
	const aspectRatio = $derived(config.aspect_ratio || '16/9');

	type Parsed =
		| { kind: 'youtube'; id: string }
		| { kind: 'vimeo'; id: string }
		| { kind: 'mp4'; url: string }
		| { kind: 'none' };

	function parseSource(url: string | undefined): Parsed {
		if (!url) return { kind: 'none' };
		const s = url.trim();
		// YouTube: youtu.be/<id> or youtube.com/watch?v=<id> or /embed/<id> or /shorts/<id>
		const yt = s.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
		if (yt) return { kind: 'youtube', id: yt[1] };
		// Vimeo: vimeo.com/<id>
		const vm = s.match(/vimeo\.com\/(\d+)/);
		if (vm) return { kind: 'vimeo', id: vm[1] };
		// MP4 or anything direct
		if (/\.(mp4|webm|mov|m4v)(\?|$)/i.test(s) || s.startsWith('http')) {
			return { kind: 'mp4', url: s };
		}
		return { kind: 'none' };
	}

	const parsed = $derived(parseSource(config.source_url));

	const youtubeSrc = $derived(
		parsed.kind === 'youtube'
			? `https://www.youtube-nocookie.com/embed/${parsed.id}?rel=0`
				+ (autoplay ? '&autoplay=1' : '')
				+ (muted ? '&mute=1' : '')
				+ (loop ? `&loop=1&playlist=${parsed.id}` : '')
				+ (!controls ? '&controls=0' : '')
			: ''
	);
	const vimeoSrc = $derived(
		parsed.kind === 'vimeo'
			? `https://player.vimeo.com/video/${parsed.id}`
				+ '?'
				+ (autoplay ? 'autoplay=1' : 'autoplay=0')
				+ (muted ? '&muted=1' : '')
				+ (loop ? '&loop=1' : '')
				+ (!controls ? '&controls=0' : '')
			: ''
	);
</script>

{#if parsed.kind !== 'none'}
	<section
		class="video"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
	>
		{#if config.title}
			<h3 class="video__title" class:is-centered={center_header}>{config.title}</h3>
		{/if}
		<div class="video__frame" style="aspect-ratio: {aspectRatio}">
			{#if parsed.kind === 'youtube'}
				<iframe
					src={youtubeSrc}
					title={config.title || 'Video player'}
					loading="lazy"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					allowfullscreen
				></iframe>
			{:else if parsed.kind === 'vimeo'}
				<iframe
					src={vimeoSrc}
					title={config.title || 'Video player'}
					loading="lazy"
					allow="autoplay; fullscreen; picture-in-picture"
					allowfullscreen
				></iframe>
			{:else if parsed.kind === 'mp4'}
				<video
					src={parsed.url}
					poster={config.poster_url || ''}
					{autoplay}
					{muted}
					{loop}
					{controls}
					playsinline
					preload="metadata"
				></video>
			{/if}
		</div>
	</section>
{/if}

<style>
	.video {
		--mod-pt: 40px;
		--mod-pb: 40px;
		--mod-px: 28px;
		--mod-max-w: 960px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.video.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.video.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.video.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.video.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }

	.video__title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 13px;
		font-weight: var(--heading-weight, 600);
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg-muted);
		margin: 0 0 20px;
	}
	.video__title.is-centered {
		text-align: center;
	}

	.video__frame {
		position: relative;
		overflow: hidden;
		background: #000;
	}
	.video__frame iframe,
	.video__frame video {
		position: absolute;
		inset: 0;
		width: 100%;
		height: 100%;
		border: 0;
		display: block;
	}
</style>
