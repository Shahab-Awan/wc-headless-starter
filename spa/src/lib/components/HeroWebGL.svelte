<script lang="ts">
	/**
	 * HeroWebGL — decorative animated backdrop for the home hero.
	 *
	 * Uses OGL (45KB, already in package.json) — a lightweight WebGL
	 * library. Imported dynamically so the initial SPA bundle stays
	 * fast; this component only pulls OGL when the hero renders.
	 *
	 * Renders a fullscreen fragment shader: slow-flowing noise field
	 * colored with the page's current --fg and --bg token values so
	 * it stays in sync with the theme. A MutationObserver on <html>
	 * data-theme catches theme swaps and re-uploads the color uniforms.
	 *
	 * Respects prefers-reduced-motion: renders a single frame and halts
	 * the animation loop.
	 *
	 * Non-interactive — pointer-events: none so hero text stays
	 * clickable above it.
	 */

	import { onMount, onDestroy } from 'svelte';
	import { browser } from '$app/environment';
	import { parseCssColor } from '$lib/utils/color';

	let canvas: HTMLCanvasElement | undefined = $state();
	let destroyFn: (() => void) | null = null;
	let webglState = $state<'ok' | 'failed' | 'lost'>('ok');

	onMount(async () => {
		if (!browser || !canvas) return;

		// Everything WebGL-related is wrapped — if the renderer fails to
		// construct (context exhaustion under rapid admin-preview variant
		// switches, driver issues, etc.) we want to fail silently rather
		// than crash the hosting page. canvas stays blank + the hero's own
		// background tokens still show through since this layer is decorative.
		let renderer: any;
		let Renderer: any, Program: any, Mesh: any, Triangle: any;
		try {
			({ Renderer, Program, Mesh, Triangle } = await import('ogl'));
			renderer = new Renderer({
				canvas,
				dpr: Math.min(2, window.devicePixelRatio || 1),
				alpha: false
			});
		} catch (err) {
			webglState = 'failed';
			console.warn('[HeroWebGL] init failed, using static fallback', err);
			return;
		}
		const gl = renderer.gl;

		// Fullscreen triangle covers the viewport — simpler than a quad
		const geometry = new Triangle(gl);

		const fragment = /* glsl */ `
			precision highp float;

			uniform float uTime;
			uniform vec2 uResolution;
			uniform vec3 uFg;
			uniform vec3 uBg;
			uniform float uReduced;

			float hash(vec2 p) {
				return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
			}

			float noise(vec2 p) {
				vec2 i = floor(p);
				vec2 f = fract(p);
				vec2 u = f * f * (3.0 - 2.0 * f);
				return mix(
					mix(hash(i + vec2(0.0, 0.0)), hash(i + vec2(1.0, 0.0)), u.x),
					mix(hash(i + vec2(0.0, 1.0)), hash(i + vec2(1.0, 1.0)), u.x),
					u.y
				);
			}

			float fbm(vec2 p) {
				float v = 0.0;
				float a = 0.5;
				for (int i = 0; i < 5; i++) {
					v += a * noise(p);
					p *= 2.0;
					a *= 0.5;
				}
				return v;
			}

			void main() {
				vec2 uv = gl_FragCoord.xy / uResolution;
				vec2 p = uv;
				p.x *= uResolution.x / uResolution.y;

				float t = uTime * mix(0.08, 0.0, uReduced);

				// Domain-warp the noise to get organic flowing shapes
				vec2 q = vec2(
					fbm(p * 1.2 + vec2(t, -t * 0.4)),
					fbm(p * 1.2 + vec2(-t * 0.3, t * 0.3))
				);
				vec2 r = vec2(
					fbm(p * 1.6 + q * 2.0 + vec2(t * 0.15, 0.0)),
					fbm(p * 1.6 + q * 2.0 + vec2(0.0, t * 0.18))
				);
				float field = fbm(p * 1.8 + r * 2.4);

				// Two broad light pools that drift slowly
				vec2 p1 = vec2(0.25 + sin(t * 0.6) * 0.15, 0.30 + cos(t * 0.4) * 0.1);
				vec2 p2 = vec2(1.10 + cos(t * 0.5) * 0.12, 0.85 + sin(t * 0.7) * 0.08);
				float pool1 = smoothstep(1.0, 0.1, length(p - p1));
				float pool2 = smoothstep(1.2, 0.2, length(p - p2));
				float lightMap = max(pool1, pool2 * 0.85);

				// High-visibility intensity — up to 45% of fg mixed into bg
				float intensity = smoothstep(0.25, 0.85, field) * lightMap * 0.45;

				// Additional subtle base wash so the canvas never reads
				// as pure bg — gives the hero a subtle texture even in
				// the dead zones
				intensity += field * 0.08;

				vec3 color = mix(uBg, uFg, intensity);

				gl_FragColor = vec4(color, 1.0);
			}
		`;

		const vertex = /* glsl */ `
			attribute vec2 position;
			void main() {
				gl_Position = vec4(position, 0.0, 1.0);
			}
		`;

		const program = new Program(gl, {
			vertex,
			fragment,
			uniforms: {
				uTime: { value: 0 },
				uResolution: { value: [canvas.clientWidth, canvas.clientHeight] },
				uFg: { value: [1, 1, 1] },
				uBg: { value: [0, 0, 0] },
				uReduced: { value: 0 }
			},
			transparent: true
		});

		const mesh = new Mesh(gl, { geometry, program });

		function readTokens() {
			const styles = getComputedStyle(document.documentElement);
			program.uniforms.uFg.value = parseCssColor(styles.getPropertyValue('--fg'), [1, 1, 1]);
			program.uniforms.uBg.value = parseCssColor(styles.getPropertyValue('--bg'), [0, 0, 0]);
		}
		readTokens();

		// Reduced-motion check
		const reducedMq = window.matchMedia('(prefers-reduced-motion: reduce)');
		program.uniforms.uReduced.value = reducedMq.matches ? 1 : 0;
		const onReducedChange = () => {
			program.uniforms.uReduced.value = reducedMq.matches ? 1 : 0;
		};
		reducedMq.addEventListener('change', onReducedChange);

		// Theme-sync observers — re-read tokens on data-theme change AND on
		// system color-scheme change (some OS-level theme swaps don't touch
		// the data-theme attribute).
		const themeMq = window.matchMedia('(prefers-color-scheme: dark)');
		const onThemeChange = () => readTokens();
		themeMq.addEventListener('change', onThemeChange);
		const observer = new MutationObserver(() => readTokens());
		observer.observe(document.documentElement, {
			attributes: true,
			attributeFilter: ['data-theme', 'style']
		});

		// Resize — read the parent's rendered size, not the canvas clientWidth
		// (which would be 0 before layout settles).
		function resize() {
			if (!canvas) return;
			const parent = canvas.parentElement;
			const w = parent ? parent.clientWidth : window.innerWidth;
			const h = parent ? parent.clientHeight : window.innerHeight;
			if (w === 0 || h === 0) return;
			renderer.setSize(w, h);
			program.uniforms.uResolution.value = [w, h];
		}
		resize();
		// Observe the parent for size changes (hero is min-height-based)
		const resizeObserver = new ResizeObserver(() => resize());
		if (canvas.parentElement) resizeObserver.observe(canvas.parentElement);
		window.addEventListener('resize', resize);

		// Render loop — per-frame errors (context loss mid-render, etc.) are
		// caught so the page doesn't crash from an uncaught throw inside rAF.
		let raf = 0;
		const start = performance.now();
		function frame() {
			try {
				program.uniforms.uTime.value = (performance.now() - start) / 1000;
				renderer.render({ scene: mesh });
			} catch (err) {
				webglState = 'failed';
				cancelAnimationFrame(raf);
				console.warn('[HeroWebGL] render loop aborted', err);
				return;
			}
			raf = requestAnimationFrame(frame);
		}
		frame();

		// WebGL context loss — stop the loop and mark state. On restore we
		// don't auto-re-init (admin switches to a fresh component anyway);
		// the static bg shows through instead.
		const onContextLost = (e: Event) => {
			e.preventDefault();
			webglState = 'lost';
			cancelAnimationFrame(raf);
		};
		canvas.addEventListener('webglcontextlost', onContextLost, false);

		destroyFn = () => {
			cancelAnimationFrame(raf);
			window.removeEventListener('resize', resize);
			resizeObserver.disconnect();
			reducedMq.removeEventListener('change', onReducedChange);
			themeMq.removeEventListener('change', onThemeChange);
			observer.disconnect();
			canvas?.removeEventListener('webglcontextlost', onContextLost);
		};
	});

	onDestroy(() => {
		if (destroyFn) destroyFn();
	});
</script>

<canvas bind:this={canvas} class="hero-webgl" data-webgl-state={webglState} aria-hidden="true"></canvas>

<style>
	.hero-webgl {
		position: absolute;
		inset: 0;
		width: 100%;
		height: 100%;
		pointer-events: none;
		z-index: 1;
		mix-blend-mode: multiply;
		opacity: 0.7;
	}
	:global([data-theme='dark']) .hero-webgl {
		mix-blend-mode: screen;
		opacity: 0.5;
	}
</style>
