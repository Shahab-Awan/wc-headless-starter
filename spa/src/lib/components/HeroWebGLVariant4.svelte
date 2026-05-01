<script lang="ts">
	/**
	 * HeroWebGLVariant4 - hex grid backdrop.
	 *
	 * Honeycomb pattern with soft glowing cell edges. Cells pulse
	 * subtly at offset phases. Clean, technical, geometric.
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

			float hash(vec2 p) {
				return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
			}

			// Proper hexagonal SDF - returns distance to hex edge
			float hexDist(vec2 p) {
				p = abs(p);
				// Hex is the intersection of 3 slabs
				return max(p.x, dot(p, vec2(0.5, 0.866025)));
			}

			// Returns (offset from hex center, hex cell ID)
			vec4 hexInfo(vec2 uv) {
				const vec2 s = vec2(1.0, 1.7320508);
				vec4 hC = floor(vec4(uv, uv - vec2(0.5, 1.0)) / s.xyxy + 0.5);
				vec4 h = vec4(uv - hC.xy * s, uv - (hC.zw + 0.5) * s);
				return (dot(h.xy, h.xy) < dot(h.zw, h.zw))
					? vec4(h.xy, hC.xy)
					: vec4(h.zw, hC.zw + 0.5);
			}

			void main() {
				vec2 uv = gl_FragCoord.xy / uResolution;
				uv.x *= uResolution.x / uResolution.y;
				float t = uTime * mix(0.5, 0.0, uReduced);

				// Slow diagonal drift so the grid feels alive
				vec2 drift = uv * 10.0 + vec2(t * 0.15, t * 0.08);

				vec4 hex = hexInfo(drift);
				vec2 off = hex.xy;
				vec2 id = hex.zw;

				float hd = hexDist(off);
				float edgeDist = 0.5 - hd;

				// Per-cell properties
				float phase = hash(id) * 6.283;
				float cellRand = hash(id + 100.0);

				// Per-cell "elevation" that oscillates over time
				float elevation = sin(t * 0.6 + phase) * 0.5 + 0.5;

				// Crisp wireframe
				float wire = smoothstep(0.03, 0.0, edgeDist) * 0.45;
				wire += smoothstep(0.08, 0.01, edgeDist) * 0.10;

				// Wire brightness varies with adjacent cell elevation difference
				// (higher cells have brighter edges on the "lit" side)
				wire *= 0.7 + elevation * 0.3;

				// Faked top-down lighting on cell interior
				// Offset the center toward upper-left to fake a light direction
				vec2 lightOff = off + vec2(-0.06, 0.04);
				float lightDist = hexDist(lightOff);
				float cellLit = smoothstep(0.50, 0.10, lightDist) * elevation * 0.22;

				// Travelling highlight wave - two sources
				float wave1 = sin(length(id) * 0.6 - t * 1.8) * 0.5 + 0.5;
				float wave2 = sin(length(id - vec2(8.0, 5.0)) * 0.5 - t * 1.2 + 2.0) * 0.5 + 0.5;
				float wave = max(wave1, wave2 * 0.8);
				cellLit += wave * elevation * 0.08;

				// Cells that are "down" get a faint shadow
				float shadow = (1.0 - elevation) * 0.04 * (1.0 - smoothstep(0.0, 0.45, hd));

				float intensity = clamp(wire + cellLit - shadow, 0.0, 0.55);

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
