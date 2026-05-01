<script lang="ts">
	/**
	 * HeroWebGLVariant2 — liquid plasma.
	 *
	 * Domain-warped fbm noise renders a continuous morphing gradient
	 * across the entire canvas. No discrete blobs to cluster in the
	 * middle like the old metaballs. Reads as iridescent oil-on-water
	 * / slow plasma sheet and flexes nicely with the accent color.
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
			uniform vec3 uAccent;
			uniform float uReduced;

			// Cheap value noise — no texture lookups. Good enough for a
			// gradient backdrop and GPU-friendly on mobile.
			float hash(vec2 p) { return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453); }
			float noise(vec2 p) {
				vec2 i = floor(p); vec2 f = fract(p);
				vec2 u = f * f * (3.0 - 2.0 * f);
				return mix(mix(hash(i), hash(i + vec2(1, 0)), u.x),
				           mix(hash(i + vec2(0, 1)), hash(i + vec2(1, 1)), u.x), u.y);
			}
			float fbm(vec2 p) {
				float v = 0.0, a = 0.5;
				for (int i = 0; i < 5; i++) {
					v += a * noise(p);
					p *= 2.0; a *= 0.5;
				}
				return v;
			}

			void main() {
				vec2 uv = (gl_FragCoord.xy / uResolution) * 2.0 - 1.0;
				uv.x *= uResolution.x / uResolution.y;

				float t = uTime * mix(0.08, 0.0, uReduced);

				// Domain warping — pass noise back into itself for that
				// liquid / iridescent flow that makes gradient meshes feel
				// organic rather than procedural.
				vec2 q = vec2(
					fbm(uv * 1.4 + vec2(0.0, t)),
					fbm(uv * 1.4 + vec2(5.2, t + 1.3))
				);
				vec2 r = vec2(
					fbm(uv * 1.4 + q * 2.0 + vec2(1.7, t * 1.2)),
					fbm(uv * 1.4 + q * 2.0 + vec2(8.3, t * 0.9))
				);
				float f = fbm(uv * 1.2 + r * 1.8);

				// Three-stop gradient: bg → accent → fg. Smoothstep bands
				// shape the transitions so you get ribbon-like color fields
				// rather than a flat gradient.
				float a1 = smoothstep(0.35, 0.55, f);
				float a2 = smoothstep(0.55, 0.78, f);
				vec3 color = mix(uBg, uAccent, a1);
				color = mix(color, uFg, a2 * 0.55);

				// Alpha modulates on field intensity so extreme-low values
				// drop to transparent (lets the hero bg breathe through).
				float alpha = smoothstep(0.25, 0.45, f) * 0.55;
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
				uFg: { value: [1, 1, 1] }, uBg: { value: [0, 0, 0] }, uAccent: { value: [1, 0.86, 0.14] }, uReduced: { value: 0 }
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
			const accent = s.getPropertyValue('--accent').trim();
			if (accent) program.uniforms.uAccent.value = parseColor(accent);
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
