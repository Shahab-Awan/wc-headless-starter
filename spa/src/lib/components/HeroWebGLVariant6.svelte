<script lang="ts">
	/**
	 * HeroWebGLVariant6 — bokeh depth field.
	 *
	 * Scattered soft orbs at multiple depths slowly parallax across the
	 * canvas. Orbs never merge (unlike metaballs) — they stay as discrete
	 * glowing discs with soft falloff. Reads as cinematic / photographic
	 * defocused lights, and they're deliberately distributed across the
	 * full canvas so nothing clusters behind the headline.
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

			// 14 orbs, each: vec4(baseX, baseY, radius, depth). Depths are
			// pre-sorted near→far and used both for drift amplitude (nearer
			// orbs drift more like true parallax) and for opacity (nearer
			// is brighter). Positions chosen to spread edge-to-edge, not
			// cluster in the middle.
			const int N = 14;
			vec4 ORBS[N];

			void initOrbs() {
				ORBS[ 0] = vec4(-1.30, -0.45, 0.26, 0.95);
				ORBS[ 1] = vec4(-0.85,  0.55, 0.32, 0.80);
				ORBS[ 2] = vec4(-0.55, -0.65, 0.18, 0.70);
				ORBS[ 3] = vec4(-0.10,  0.10, 0.42, 0.55);
				ORBS[ 4] = vec4( 0.25, -0.20, 0.22, 0.90);
				ORBS[ 5] = vec4( 0.60,  0.45, 0.30, 0.65);
				ORBS[ 6] = vec4( 0.95, -0.55, 0.24, 0.75);
				ORBS[ 7] = vec4( 1.35,  0.30, 0.38, 0.50);
				ORBS[ 8] = vec4(-1.45,  0.10, 0.20, 0.40);
				ORBS[ 9] = vec4( 1.50, -0.15, 0.16, 0.35);
				ORBS[10] = vec4(-0.30,  0.70, 0.14, 0.60);
				ORBS[11] = vec4( 0.00, -0.70, 0.28, 0.45);
				ORBS[12] = vec4( 0.80,  0.75, 0.20, 0.30);
				ORBS[13] = vec4(-0.70, -0.20, 0.12, 0.85);
			}

			void main() {
				initOrbs();
				vec2 uv = (gl_FragCoord.xy / uResolution) * 2.0 - 1.0;
				uv.x *= uResolution.x / uResolution.y;

				float t = uTime * mix(0.16, 0.0, uReduced);

				float accumA = 0.0;    // accent channel
				float accumF = 0.0;    // fg channel (for cores)

				for (int i = 0; i < N; i++) {
					vec4 o = ORBS[i];
					vec2 base = o.xy;
					float r = o.z;
					float depth = o.w;
					float fi = float(i);

					// Drift: nearer orbs move more (parallax). Phase-offset
					// per index so motion doesn't look synchronized.
					vec2 drift = vec2(
						sin(t * (0.55 + fi * 0.017) + fi * 1.7),
						cos(t * (0.64 + fi * 0.019) + fi * 2.3)
					) * (0.28 * depth);

					// Gentle breathing — each orb pulses its effective radius
					// on a different phase, so the whole field feels alive
					// instead of static-with-drift.
					float pulse = 0.82 + 0.22 * sin(t * (0.8 + fi * 0.05) + fi * 1.3);
					float rr = r * pulse;

					vec2 c = base + drift;
					float d = length(uv - c);
					// Soft disk with exponential falloff.
					float glow = exp(-(d * d) / (rr * rr));
					// Weight by depth — nearer orbs contribute more.
					accumA += glow * depth * 0.55;
					// Bright pin-sharp core at a tighter falloff for the
					// "in-focus hotspot" of a real defocused light.
					accumF += exp(-(d * d) / (rr * rr * 0.12)) * depth * 0.35;
				}

				vec3 color = mix(uBg, uAccent, clamp(accumA, 0.0, 1.0));
				color = mix(color, uFg, clamp(accumF, 0.0, 0.7));

				float alpha = clamp(accumA * 0.7 + accumF * 0.5, 0.0, 0.85);
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
