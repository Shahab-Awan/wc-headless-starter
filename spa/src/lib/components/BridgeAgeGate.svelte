<script lang="ts">
	/**
	 * Full-page age gate for Alyve Research / why-alyve.
	 * Layout mirrors Certified Pep-style entry: logo, RUO title, CTA,
	 * then a generic standards note (no third-party review buttons).
	 * Optional bg image; otherwise theme-colored animated particle canvas.
	 */
	import { page } from '$app/state';
	import { config } from '$lib/config.svelte';
	import { bridgeAgeGate } from '$lib/bridge-age-gate.svelte';
	import { BRIDGE_PAGE_PATH, bridgeAwareAssetUrl } from '$lib/bridge-domain';

	let panelEl = $state<HTMLDivElement | undefined>(undefined);
	let canvasEl = $state<HTMLCanvasElement | undefined>(undefined);

	const gateConfig = $derived(config.data.homepage.bridge_age_gate);
	const onWhyAlyve = $derived(page.url.pathname.replace(/\/$/, '') === BRIDGE_PAGE_PATH);
	const show = $derived(
		onWhyAlyve && Boolean(gateConfig?.enabled) && bridgeAgeGate.open && bridgeAgeGate.checked
	);
	const bgImage = $derived(bridgeAwareAssetUrl(gateConfig?.bg_image ?? ''));
	const useParticles = $derived(!bgImage);
	const brandName = $derived(config.data.brand_name || 'Alyve');
	const logoUrl = $derived(
		bridgeAwareAssetUrl(config.data.logo_url || config.data.logo_dark_url || '')
	);

	const showNote = $derived(Boolean((gateConfig?.note_title ?? '').trim() || (gateConfig?.note_content ?? '').trim()));
	const leftLabel = $derived((gateConfig?.note_left_label ?? '').trim());
	const leftText = $derived((gateConfig?.note_left_text ?? '').trim());
	const rightLabel = $derived((gateConfig?.note_right_label ?? '').trim());
	const rightText = $derived((gateConfig?.note_right_text ?? '').trim());
	const showCallouts = $derived(Boolean(leftLabel || leftText || rightLabel || rightText));

	type Dot = {
		x: number;
		y: number;
		r: number;
		vx: number;
		vy: number;
		alpha: number;
		pulse: number;
		pulseSpeed: number;
	};

	type Node = {
		x: number;
		y: number;
		size: number;
		vx: number;
		vy: number;
		rot: number;
		vr: number;
		alpha: number;
	};

	function confirm() {
		bridgeAgeGate.accept(gateConfig?.version ?? 1);
	}

	function decline() {
		const url = gateConfig?.decline_url || 'https://google.com';
		window.location.href = url;
	}

	$effect(() => {
		if (typeof document === 'undefined') return;
		document.body.classList.toggle('wchs-bridge-age-lock', show);
	});

	$effect(() => {
		if (!show || !panelEl) return;
		const focusable = panelEl.querySelectorAll<HTMLElement>(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);
		const first = focusable[0];
		requestAnimationFrame(() => first?.focus());
	});

	$effect(() => {
		if (typeof window === 'undefined' || !show || !useParticles || !canvasEl) return;

		const canvas = canvasEl;
		const maybeCtx = canvas.getContext('2d');
		if (!maybeCtx) return;
		const ctx: CanvasRenderingContext2D = maybeCtx;

		const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		let raf = 0;
		let running = true;
		let w = 0;
		let h = 0;
		let dpr = 1;

		const dots: Dot[] = [];
		const nodes: Node[] = [];

		function resize() {
			dpr = Math.min(window.devicePixelRatio || 1, 2);
			w = window.innerWidth;
			h = window.innerHeight;
			canvas.width = Math.floor(w * dpr);
			canvas.height = Math.floor(h * dpr);
			canvas.style.width = `${w}px`;
			canvas.style.height = `${h}px`;
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		}

		function seed() {
			dots.length = 0;
			nodes.length = 0;
			const dotCount = Math.min(64, Math.max(32, Math.floor((w * h) / 24000)));
			for (let i = 0; i < dotCount; i++) {
				const r = 1.2 + Math.random() * 4.2;
				dots.push({
					x: Math.random() * w,
					y: Math.random() * h,
					r,
					vx: (Math.random() - 0.5) * 0.32,
					vy: -0.1 - Math.random() * 0.32,
					alpha: 0.2 + Math.random() * 0.45,
					pulse: Math.random() * Math.PI * 2,
					pulseSpeed: 0.01 + Math.random() * 0.018
				});
			}
			const nodeCount = Math.min(10, Math.max(5, Math.floor(w / 200)));
			for (let i = 0; i < nodeCount; i++) {
				nodes.push({
					x: Math.random() * w,
					y: Math.random() * h,
					size: 26 + Math.random() * 34,
					vx: (Math.random() - 0.5) * 0.16,
					vy: (Math.random() - 0.5) * 0.16,
					rot: Math.random() * Math.PI * 2,
					vr: (Math.random() - 0.5) * 0.004,
					alpha: 0.1 + Math.random() * 0.14
				});
			}
		}

		function wrap(p: { x: number; y: number }, pad: number) {
			if (p.x < -pad) p.x = w + pad;
			if (p.x > w + pad) p.x = -pad;
			if (p.y < -pad) p.y = h + pad;
			if (p.y > h + pad) p.y = -pad;
		}

		function cssColor(name: string, fallback: string): string {
			const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
			return v || fallback;
		}

		function parseRgb(input: string): { r: number; g: number; b: number } | null {
			const hex = input.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
			if (hex) {
				let h = hex[1];
				if (h.length === 3) h = h.split('').map((c) => c + c).join('');
				return {
					r: parseInt(h.slice(0, 2), 16),
					g: parseInt(h.slice(2, 4), 16),
					b: parseInt(h.slice(4, 6), 16)
				};
			}
			const rgb = input.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i);
			if (rgb) {
				return { r: Number(rgb[1]), g: Number(rgb[2]), b: Number(rgb[3]) };
			}
			return null;
		}

		let accentRgb = parseRgb(cssColor('--accent', '#0ea5a4')) ?? { r: 14, g: 165, b: 164 };
		let fgRgb = parseRgb(cssColor('--fg', '#0f172a')) ?? { r: 15, g: 23, b: 42 };

		function drawNode(n: Node) {
			const arm = n.size * 0.38;
			const tip = n.size * 0.1;
			ctx.save();
			ctx.translate(n.x, n.y);
			ctx.rotate(n.rot);
			ctx.globalAlpha = n.alpha;
			ctx.strokeStyle = `rgba(${fgRgb.r}, ${fgRgb.g}, ${fgRgb.b}, 0.55)`;
			ctx.fillStyle = `rgba(${fgRgb.r}, ${fgRgb.g}, ${fgRgb.b}, 0.45)`;
			ctx.lineWidth = 1.4;
			ctx.beginPath();
			ctx.moveTo(0, -arm);
			ctx.lineTo(0, arm);
			ctx.moveTo(-arm, 0);
			ctx.lineTo(arm, 0);
			ctx.stroke();
			for (const [cx, cy] of [
				[0, -arm],
				[0, arm],
				[-arm, 0],
				[arm, 0]
			] as const) {
				ctx.beginPath();
				ctx.arc(cx, cy, tip, 0, Math.PI * 2);
				ctx.fill();
			}
			ctx.restore();
		}

		function frame() {
			if (!running) return;
			ctx.clearRect(0, 0, w, h);

			if (!reduceMotion) {
				for (const d of dots) {
					d.x += d.vx;
					d.y += d.vy;
					d.pulse += d.pulseSpeed;
					wrap(d, 20);
				}
				for (const n of nodes) {
					n.x += n.vx;
					n.y += n.vy;
					n.rot += n.vr;
					wrap(n, 40);
				}
			}

			for (const n of nodes) drawNode(n);

			for (const d of dots) {
				const pulse = 0.75 + Math.sin(d.pulse) * 0.25;
				const r = d.r * pulse;
				const { r: ar, g: ag, b: ab } = accentRgb;
				const g = ctx.createRadialGradient(d.x, d.y, 0, d.x, d.y, r * 3.4);
				g.addColorStop(0, `rgba(${ar}, ${ag}, ${ab}, ${d.alpha})`);
				g.addColorStop(0.45, `rgba(${ar}, ${ag}, ${ab}, ${d.alpha * 0.4})`);
				g.addColorStop(1, `rgba(${ar}, ${ag}, ${ab}, 0)`);
				ctx.beginPath();
				ctx.fillStyle = g;
				ctx.arc(d.x, d.y, r * 3.4, 0, Math.PI * 2);
				ctx.fill();
				ctx.beginPath();
				ctx.fillStyle = `rgba(${ar}, ${ag}, ${ab}, ${Math.min(1, d.alpha + 0.2)})`;
				ctx.arc(d.x, d.y, Math.max(1.1, r * 0.5), 0, Math.PI * 2);
				ctx.fill();
			}

			raf = requestAnimationFrame(frame);
		}

		const onResize = () => {
			accentRgb = parseRgb(cssColor('--accent', '#0ea5a4')) ?? accentRgb;
			fgRgb = parseRgb(cssColor('--fg', '#0f172a')) ?? fgRgb;
			resize();
			seed();
		};

		resize();
		seed();
		raf = requestAnimationFrame(frame);
		window.addEventListener('resize', onResize);

		return () => {
			running = false;
			cancelAnimationFrame(raf);
			window.removeEventListener('resize', onResize);
		};
	});
</script>

<div
	class="bag"
	class:bag--show={show}
	class:bag--image={Boolean(bgImage)}
	class:bag--particles={useParticles}
	role="dialog"
	aria-modal="true"
	aria-hidden={!show}
	aria-label={gateConfig?.title || 'Age verification'}
>
	{#if bgImage}
		<div class="bag__bg-image" style="background-image: url('{bgImage}')" aria-hidden="true"></div>
		<div class="bag__bg-shade" aria-hidden="true"></div>
	{:else}
		<canvas class="bag__canvas" bind:this={canvasEl} aria-hidden="true"></canvas>
	{/if}

	<div class="bag__shell" bind:this={panelEl}>
		<div class="bag__panel">
			{#if logoUrl}
				<img class="bag__logo" src={logoUrl} alt={brandName} />
			{:else}
				<div class="bag__brand">{brandName}</div>
			{/if}

			{#if gateConfig?.title}
				<h1 class="bag__title">{gateConfig.title}</h1>
			{/if}

			{#if gateConfig?.content}
				<div class="bag__content">{@html gateConfig.content}</div>
			{/if}

			<div class="bag__actions">
				<button type="button" class="bag__confirm" onclick={confirm}>
					{gateConfig?.confirm_text || 'Enter Alyve Research'}
				</button>
				{#if gateConfig?.redirect_note}
					<p class="bag__note">{gateConfig.redirect_note}</p>
				{/if}
				{#if gateConfig?.decline_text}
					<button type="button" class="bag__decline" onclick={decline}>
						{gateConfig.decline_text}
					</button>
				{/if}
			</div>
		</div>

		{#if showNote}
			<section class="bag__standards" aria-label="Standards note">
				{#if gateConfig?.note_title}
					<h2 class="bag__standards-title">{gateConfig.note_title}</h2>
				{/if}
				{#if gateConfig?.note_content}
					<p class="bag__standards-body">{gateConfig.note_content}</p>
				{/if}
				{#if showCallouts}
					<div class="bag__callouts">
						{#if leftLabel || leftText}
							<div class="bag__callout">
								{#if leftLabel}<p class="bag__callout-label">{leftLabel}</p>{/if}
								{#if leftText}<p class="bag__callout-text">{leftText}</p>{/if}
							</div>
						{/if}
						{#if rightLabel || rightText}
							<div class="bag__callout">
								{#if rightLabel}<p class="bag__callout-label">{rightLabel}</p>{/if}
								{#if rightText}<p class="bag__callout-text">{rightText}</p>{/if}
							</div>
						{/if}
					</div>
				{/if}
			</section>
		{/if}
	</div>
</div>

<style>
	.bag {
		position: fixed;
		inset: 0;
		z-index: 10050;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: clamp(20px, 4vh, 40px) 16px;
		opacity: 0;
		pointer-events: none;
		visibility: hidden;
		transition:
			opacity var(--dur-med, 0.28s) var(--ease-out, ease-out),
			visibility 0s linear 0.28s;
	}
	.bag--show {
		opacity: 1;
		pointer-events: auto;
		visibility: visible;
		transition:
			opacity var(--dur-med, 0.28s) var(--ease-out, ease-out),
			visibility 0s;
	}

	.bag--particles {
		background:
			radial-gradient(
				ellipse 85% 65% at 50% 38%,
				color-mix(in srgb, var(--accent) 10%, var(--bg)) 0%,
				transparent 68%
			),
			var(--bg);
	}
	.bag--image {
		background: var(--bg);
	}

	.bag__canvas {
		position: absolute;
		inset: 0;
		width: 100%;
		height: 100%;
		pointer-events: none;
	}

	.bag__bg-image {
		position: absolute;
		inset: 0;
		background-size: cover;
		background-position: center;
		background-repeat: no-repeat;
	}
	.bag__bg-shade {
		position: absolute;
		inset: 0;
		background: linear-gradient(
			105deg,
			color-mix(in srgb, var(--fg) 78%, transparent) 0%,
			color-mix(in srgb, var(--fg) 55%, transparent) 50%,
			color-mix(in srgb, var(--fg) 35%, transparent) 100%
		);
	}

	.bag__shell {
		position: relative;
		z-index: 1;
		width: min(560px, 100%);
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: clamp(36px, 6vh, 56px);
		max-height: 100%;
		overflow: auto;
		scrollbar-width: thin;
	}

	.bag__panel {
		width: min(440px, 100%);
		text-align: center;
		color: var(--fg);
	}
	.bag--image .bag__panel {
		color: var(--bg);
	}

	.bag__logo {
		display: block;
		height: 48px;
		width: auto;
		max-width: 220px;
		margin: 0 auto 28px;
		object-fit: contain;
	}
	.bag--image .bag__logo {
		filter: brightness(0) invert(1);
	}
	.bag__brand {
		font-family: var(--font-heading, var(--font-sans));
		font-size: 1.35rem;
		font-weight: 600;
		letter-spacing: -0.02em;
		margin: 0 0 28px;
		color: var(--fg);
	}
	.bag--image .bag__brand {
		color: var(--bg);
	}

	.bag__title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(1.7rem, 4.4vw, 2.25rem);
		font-weight: var(--heading-weight, 600);
		letter-spacing: -0.03em;
		line-height: 1.2;
		margin: 0 0 16px;
		color: var(--fg);
	}
	.bag--image .bag__title {
		color: var(--bg);
	}

	.bag__content {
		font-size: 0.95rem;
		line-height: 1.65;
		color: var(--fg-muted);
		margin: 0 auto 28px;
		max-width: 36rem;
	}
	.bag--image .bag__content {
		color: color-mix(in srgb, var(--bg) 82%, transparent);
	}
	.bag__content :global(p) {
		margin: 0 0 12px;
	}
	.bag__content :global(p:last-child) {
		margin-bottom: 0;
	}
	.bag__content :global(a) {
		color: var(--accent);
		text-underline-offset: 2px;
	}
	.bag--image .bag__content :global(a) {
		color: var(--bg);
	}

	.bag__actions {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 12px;
	}
	.bag__confirm {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: min(280px, 100%);
		padding: 15px 28px;
		border: 1px solid var(--accent);
		border-radius: var(--radius-sm, 4px);
		background: var(--accent);
		color: var(--accent-fg, #fff);
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		cursor: pointer;
		transition:
			opacity var(--dur-fast, 0.15s) var(--ease, ease),
			transform var(--dur-fast, 0.15s) var(--ease, ease);
	}
	.bag__confirm:hover {
		opacity: 0.9;
		transform: translateY(-1px);
	}
	.bag__confirm:focus-visible {
		outline: 2px solid var(--accent);
		outline-offset: 3px;
	}
	.bag__note {
		margin: 0;
		font-size: 11px;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		color: var(--fg-muted);
	}
	.bag--image .bag__note {
		color: color-mix(in srgb, var(--bg) 55%, transparent);
	}
	.bag__decline {
		background: none;
		border: none;
		color: var(--fg-muted);
		font-size: 12px;
		letter-spacing: 0.04em;
		cursor: pointer;
		padding: 8px 12px;
	}
	.bag--image .bag__decline {
		color: color-mix(in srgb, var(--bg) 65%, transparent);
	}
	.bag__decline:hover {
		color: var(--fg);
	}
	.bag--image .bag__decline:hover {
		color: var(--bg);
	}

	.bag__standards {
		width: min(520px, 100%);
		text-align: center;
		padding-top: 8px;
		border-top: 1px solid var(--border);
	}
	.bag--image .bag__standards {
		border-top-color: color-mix(in srgb, var(--bg) 18%, transparent);
	}
	.bag__standards-title {
		margin: 0 0 12px;
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.12em;
		text-transform: uppercase;
		color: var(--fg);
	}
	.bag--image .bag__standards-title {
		color: var(--bg);
	}
	.bag__standards-body {
		margin: 0 auto 22px;
		max-width: 34rem;
		font-size: 0.9rem;
		line-height: 1.6;
		color: var(--fg-muted);
	}
	.bag--image .bag__standards-body {
		color: color-mix(in srgb, var(--bg) 72%, transparent);
	}
	.bag__callouts {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px 24px;
	}
	.bag__callout {
		text-align: center;
	}
	.bag__callout-label {
		margin: 0 0 6px;
		font-size: 13px;
		font-weight: 600;
		letter-spacing: 0.04em;
		color: var(--fg);
	}
	.bag--image .bag__callout-label {
		color: var(--bg);
	}
	.bag__callout-text {
		margin: 0;
		font-size: 12px;
		line-height: 1.45;
		color: var(--fg-muted);
	}
	.bag--image .bag__callout-text {
		color: color-mix(in srgb, var(--bg) 62%, transparent);
	}

	:global(html:has(body.wchs-bridge-age-lock)),
	:global(body.wchs-bridge-age-lock) {
		overflow: hidden;
	}

	@media (max-width: 520px) {
		.bag__callouts {
			grid-template-columns: 1fr;
			gap: 14px;
		}
	}
</style>
