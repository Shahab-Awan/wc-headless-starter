<script lang="ts">
	/**
	 * HeroWebGLVariant3 - voronoi wireframe backdrop.
	 *
	 * Morphing geometric cell outlines. Thin edges glow against the
	 * background while cell interiors stay dark. Linear/Runway aesthetic.
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

			vec2 hash2(vec2 p) {
				p = vec2(dot(p, vec2(127.1, 311.7)), dot(p, vec2(269.5, 183.3)));
				return fract(sin(p) * 43758.5453);
			}

			// Returns (distance to nearest edge, cell random value)
			vec2 voronoi(vec2 x, float t) {
				vec2 n = floor(x);
				vec2 f = fract(x);

				float md = 8.0;  // min distance
				float md2 = 8.0; // second min distance
				vec2 cellId;
				for (int j = -1; j <= 1; j++) {
					for (int i = -1; i <= 1; i++) {
						vec2 g = vec2(float(i), float(j));
						vec2 o = hash2(n + g);
						o = 0.5 + 0.5 * sin(t + 6.2831 * o);
						vec2 r = g + o - f;
						float d = dot(r, r);
						if (d < md) {
							md2 = md;
							md = d;
							cellId = o;
						} else if (d < md2) {
							md2 = d;
						}
					}
				}
				// Edge distance approximation: difference between nearest and second-nearest
				float edge = md2 - md;
				return vec2(edge, dot(cellId, vec2(1.0)));
			}

			void main() {
				vec2 uv = gl_FragCoord.xy / uResolution;
				uv.x *= uResolution.x / uResolution.y;
				float t = uTime * mix(0.3, 0.0, uReduced);

				vec2 v = voronoi(uv * 5.0, t);
				float edge = v.x;
				float cellRand = v.y;

				// Per-cell "height" - some cells are elevated, others recessed
				float cellHeight = sin(cellRand * 12.0 + t * 0.5) * 0.5 + 0.5;

				// Wireframe with varying brightness based on adjacent cell heights
				float wire = smoothstep(0.06, 0.0, edge) * 0.55;
				wire += smoothstep(0.18, 0.02, edge) * 0.10;

				// Cell fill: elevated cells glow brighter (faked top-down lighting)
				float fill = (1.0 - smoothstep(0.0, 0.6, edge)) * cellHeight * 0.18;

				// Faked directional light: bias brightness toward upper-left
				float lightDir = dot(uv - 0.5, normalize(vec2(-0.3, 0.4)));
				fill *= 0.7 + lightDir * 0.3;

				// Travelling highlight wave
				float wave = sin(uv.x * 4.0 + uv.y * 2.0 - t * 1.2) * 0.5 + 0.5;
				fill += wave * cellHeight * 0.06;

				float intensity = clamp(wire + fill, 0.0, 0.55);

				vec3 color = mix(uBg, uFg, intensity);
				float alpha = smoothstep(0.005, 0.04, intensity);
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
