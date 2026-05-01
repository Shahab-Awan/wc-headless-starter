<script lang="ts">
	/**
	 * HeroWebGLVariant5 - dot matrix backdrop.
	 *
	 * Regular grid of soft dots that pulse in waves radiating from
	 * the center. Minimal, elegant, fabric-like.
	 */

	import { onMount, onDestroy } from 'svelte';
	import { browser } from '$app/environment';

	let canvas: HTMLCanvasElement | undefined = $state();
	let destroyFn: (() => void) | null = null;
	let webglState = $state<'ok' | 'failed' | 'lost'>('ok');

	onMount(async () => {
		if (!browser || !canvas) return;
		let Renderer: any, Program: any, Mesh: any, Triangle: any, renderer: any;
		try {
			({ Renderer, Program, Mesh, Triangle } = await import('ogl'));
			renderer = new Renderer({ canvas, dpr: Math.min(2, window.devicePixelRatio || 1), alpha: true, premultipliedAlpha: true });
		} catch (err) {
			webglState = 'failed';
			console.warn('[HeroWebGL] init failed', err);
			return;
		}
		const gl = renderer.gl;
		gl.clearColor(0, 0, 0, 0);
		const geometry = new Triangle(gl);

		const fragment = /* glsl */ `
			precision highp float;
			uniform float uTime;
			uniform vec2 uResolution;
			uniform vec3 uFg;
			uniform vec3 uBg;
			uniform float uReduced;

			void main() {
				vec2 uv = gl_FragCoord.xy / uResolution;
				float aspect = uResolution.x / uResolution.y;
				uv.x *= aspect;
				float t = uTime * mix(1.0, 0.0, uReduced);

				// Grid
				float gridSize = 22.0;
				vec2 cell = fract(uv * gridSize);
				vec2 cellId = floor(uv * gridSize);
				vec2 center = vec2(aspect * 0.5, 0.5);

				// Distance of this cell from screen center (in grid units)
				vec2 cellCenter = (cellId + 0.5) / gridSize;
				float distFromCenter = length(cellCenter - center);

				// Two radial waves with different speeds and scales
				float wave1 = sin(distFromCenter * 18.0 - t * 3.0) * 0.5 + 0.5;
				float wave2 = sin(distFromCenter * 10.0 + t * 1.8 + 1.5) * 0.5 + 0.5;
				float waveMod = wave1 * 0.6 + wave2 * 0.4;

				// Dot radius driven by wave - big range so motion is obvious
				float radius = 0.06 + waveMod * 0.18;

				// Distance from dot center
				float dist = length(cell - 0.5);

				// Shadow: offset dot drawn darker, slightly below-right
				vec2 shadowOff = vec2(0.04, -0.04) * (0.5 + waveMod * 0.5);
				float shadowDist = length(cell - 0.5 + shadowOff);
				float shadowRadius = radius * 1.1;
				float shadow = smoothstep(shadowRadius + 0.02, shadowRadius - 0.01, shadowDist) * 0.08;

				// Main dot with slight soft edge
				float dot = smoothstep(radius + 0.01, radius - 0.01, dist);

				// Brightness modulated by wave (bigger = closer = brighter)
				float brightness = 0.18 + waveMod * 0.32;

				// Specular highlight offset toward upper-left for 3D sphere look
				vec2 specOff = vec2(-0.02, 0.02);
				float spec = smoothstep(radius * 0.55, 0.0, length(cell - 0.5 + specOff)) * waveMod * 0.18;

				// Rim: faint bright ring at dot edge for backlight feel
				float rimDist = abs(dist - radius);
				float rim = smoothstep(0.025, 0.0, rimDist) * waveMod * 0.08;

				// Vignette
				float vy = smoothstep(0.0, 0.25, uv.y) * smoothstep(0.0, 0.15, 1.0 - uv.y);
				float vx = smoothstep(0.0, 0.12, uv.x) * smoothstep(0.0, 0.12, aspect - uv.x);
				float vignette = vx * vy;

				float intensity = (dot * brightness + spec + rim - shadow) * vignette;

				vec3 color = mix(uBg, uFg, intensity);
				float alpha = smoothstep(0.005, 0.03, intensity);
				gl_FragColor = vec4(color * alpha, alpha);
			}
		`;

		const vertex = /* glsl */ `
			attribute vec2 position;
			void main() { gl_Position = vec4(position, 0.0, 1.0); }
		`;

		const program = new Program(gl, {
			vertex, fragment,
			uniforms: {
				uTime: { value: 0 }, uResolution: { value: [canvas.clientWidth, canvas.clientHeight] },
				uFg: { value: [1, 1, 1] }, uBg: { value: [0, 0, 0] }, uReduced: { value: 0 }
			},
			transparent: true
		});
		const mesh = new Mesh(gl, { geometry, program });

		function parseColor(css: string): [number, number, number] {
			const el = document.createElement('div'); el.style.color = css;
			document.body.appendChild(el);
			const c = getComputedStyle(el).color; document.body.removeChild(el);
			const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(c);
			return m ? [+m[1] / 255, +m[2] / 255, +m[3] / 255] : [0, 0, 0];
		}
		function readTokens() {
			const s = getComputedStyle(document.documentElement);
			program.uniforms.uFg.value = parseColor(s.getPropertyValue('--fg').trim() || '#fff');
			program.uniforms.uBg.value = parseColor(s.getPropertyValue('--bg').trim() || '#000');
		}
		readTokens();

		const rmq = window.matchMedia('(prefers-reduced-motion: reduce)');
		program.uniforms.uReduced.value = rmq.matches ? 1 : 0;
		const onRmq = () => { program.uniforms.uReduced.value = rmq.matches ? 1 : 0; };
		rmq.addEventListener('change', onRmq);

		const obs = new MutationObserver(() => readTokens());
		obs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

		function resize() {
			if (!canvas) return;
			const p = canvas.parentElement;
			const w = p ? p.clientWidth : innerWidth, h = p ? p.clientHeight : innerHeight;
			if (!w || !h) return;
			renderer.setSize(w, h); program.uniforms.uResolution.value = [w, h];
		}
		resize();
		const ro = new ResizeObserver(() => resize());
		if (canvas.parentElement) ro.observe(canvas.parentElement);
		addEventListener('resize', resize);

		let raf = 0; const t0 = performance.now();
		function frame() {
			try { program.uniforms.uTime.value = (performance.now() - t0) / 1000; renderer.render({ scene: mesh }); }
			catch (err) { webglState = 'failed'; cancelAnimationFrame(raf); console.warn('[HeroWebGL] render aborted', err); return; }
			raf = requestAnimationFrame(frame);
		}
		frame();
		const onContextLost = (e: Event) => { e.preventDefault(); webglState = 'lost'; cancelAnimationFrame(raf); };
		canvas.addEventListener('webglcontextlost', onContextLost, false);

		destroyFn = () => { cancelAnimationFrame(raf); removeEventListener('resize', resize); ro.disconnect(); rmq.removeEventListener('change', onRmq); obs.disconnect(); canvas?.removeEventListener('webglcontextlost', onContextLost); };
	});
	onDestroy(() => destroyFn?.());
</script>

<canvas bind:this={canvas} class="hero-webgl" data-webgl-state={webglState} aria-hidden="true"></canvas>

<style>
	.hero-webgl { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; mix-blend-mode: screen; }
	:global([data-theme='light']) .hero-webgl { mix-blend-mode: multiply; }
</style>
