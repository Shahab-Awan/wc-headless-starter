<script lang="ts">
	import { onMount } from 'svelte';
	import { browser } from '$app/environment';
	import { page } from '$app/state';
	import { cart } from '$lib/wc/cart.svelte';
	import { auth } from '$lib/wc/auth.svelte';
	import { primeSession } from '$lib/wc/store-api';
	import { theme } from '$lib/theme.svelte';
	import { pretext } from '$lib/pretext/engine';
	import { config } from '$lib/config.svelte';
	import { afterNavigate } from '$app/navigation';
	import SlideCart from '$lib/components/SlideCart.svelte';
	import Footer from '$lib/components/Footer.svelte';
	import BackToTop from '$lib/components/BackToTop.svelte';
	import AdminBar from '$lib/components/AdminBar.svelte';
	import ThemeToggle from '$lib/components/ThemeToggle.svelte';
	import MaintenanceScreen from '$lib/components/MaintenanceScreen.svelte';
	import SiteGate from '$lib/components/SiteGate.svelte';
	import { gate } from '$lib/gate.svelte';
	import { icons } from '$lib/icons';
	import {
		initGTM, trackPageView,
		initOmnisend, trackOmnisendPageViewed,
		initKlaviyo, initMetaPixel, initTikTokPixel, initPinterestTag,
		initClarity, initHotjar, initGoogleAds,
	} from '$lib/analytics';
	import { loadFont, HERO_FONTS } from '$lib/hero-fonts';
	import type { HeroFontKey } from '$lib/config.svelte';
	import { applyProductCardTokens } from '$lib/product-card-tokens';
	import AnnouncementBar from '$lib/components/AnnouncementBar.svelte';
	import HeaderSearch from '$lib/components/HeaderSearch.svelte';
	import '$lib/styles/header.css';

	let { children } = $props();
	let fontsReady = $state(false);
	let cartBumping = $state(false);
	let drawerOpen = $state(false);

	// ─── Mobile header item split ──────────────────────────────
	// On mobile, pinned items render inline (up to 3). Unpinned items
	// go into the hamburger drawer. When mobile_hamburger_side='off',
	// all items render inline as before (no drawer, current behavior).
	const MAX_PINNED = 3;

	const themeToggleVisible = $derived(
		Boolean(config.data.features?.dark_mode && config.data.header_show_toggle)
	);

	const logoOnlyHeader = $derived(
		page.url.pathname.replace(/\/$/, '') === '/why-alyve'
	);

	type DrawerEntry =
		| { kind: 'link'; link: import('$lib/config.svelte').HeaderLink }
		| { kind: 'toggle' }
		| { kind: 'cart' };

	const pinnedItems = $derived.by<DrawerEntry[]>(() => {
		if (config.data.mobile_hamburger_side === 'off') return [];
		const out: DrawerEntry[] = [];
		for (const link of config.data.header_links) {
			if (link.mobile_pin) out.push({ kind: 'link', link });
		}
		if (themeToggleVisible && config.data.header_toggle_mobile_pin) {
			out.push({ kind: 'toggle' });
		}
		if (config.data.header_cart_mobile_pin) out.push({ kind: 'cart' });
		return out.slice(0, MAX_PINNED);
	});

	const overflowPinned = $derived.by<DrawerEntry[]>(() => {
		if (config.data.mobile_hamburger_side === 'off') return [];
		const all: DrawerEntry[] = [];
		for (const link of config.data.header_links) {
			if (link.mobile_pin) all.push({ kind: 'link', link });
		}
		if (themeToggleVisible && config.data.header_toggle_mobile_pin) {
			all.push({ kind: 'toggle' });
		}
		if (config.data.header_cart_mobile_pin) all.push({ kind: 'cart' });
		return all.slice(MAX_PINNED);
	});

	const drawerItems = $derived.by<DrawerEntry[]>(() => {
		if (config.data.mobile_hamburger_side === 'off') return [];
		const out: DrawerEntry[] = [];
		for (const link of config.data.header_links) {
			if (!link.mobile_pin) out.push({ kind: 'link', link });
		}
		if (themeToggleVisible && !config.data.header_toggle_mobile_pin) {
			out.push({ kind: 'toggle' });
		}
		if (!config.data.header_cart_mobile_pin) out.push({ kind: 'cart' });
		return [...out, ...overflowPinned];
	});

	// Preview-mode detection — must run BEFORE first paint so the
	// min-height overrides in the style block apply to initial layout,
	// not just post-hydration. The browser guard + onMount timing would
	// be too late.
	if (browser && new URLSearchParams(window.location.search).has('preview')) {
		document.documentElement.setAttribute('data-preview', '');
	}

	// Content-height reporter for canvas admin iframe sizing. ResizeObserver
	// covers true element-box resizes, while the burst reporter covers the
	// common page-builder case where scrollHeight changes without a body/html
	// border-box resize event.
	onMount(() => {
		if (!browser) return;
		if (!new URLSearchParams(window.location.search).has('preview')) return;
		if (typeof ResizeObserver === 'undefined') return;

		let last = 0;
		const timers: ReturnType<typeof setTimeout>[] = [];
		const report = () => {
			const h = Math.max(
				document.body.scrollHeight,
				document.documentElement.scrollHeight,
				document.body.offsetHeight,
				document.documentElement.offsetHeight,
			);
			if (h === last) return;
			last = h;
			window.parent.postMessage(
				{ __wchs_preview_size: true, height: h, at: performance.now() },
				'*',
			);
		};
		const reportBurst = () => {
			requestAnimationFrame(() => {
				report();
				requestAnimationFrame(report);
			});
			[60, 180, 420, 900, 1500, 3000, 5000].forEach(delay => {
				timers.push(setTimeout(report, delay));
			});
		};
		const onPreviewMessage = (e: MessageEvent) => {
			if (!e.data?.__wchs_preview) return;
			reportBurst();
		};
		const ro = new ResizeObserver(() => reportBurst());
		ro.observe(document.documentElement);
		ro.observe(document.body);
		window.addEventListener('message', onPreviewMessage);
		document.fonts?.ready.then(reportBurst).catch(() => {});
		reportBurst();
		return () => {
			ro.disconnect();
			window.removeEventListener('message', onPreviewMessage);
			timers.forEach(timer => clearTimeout(timer));
		};
	});

	// Theme preview toggle from admin — admin posts {__wchs_preview_theme}
	// and we flip the root data-theme attribute without reload. Routes through
	// theme.setPreviewOverride() so config.initPreviewMode's theme_default
	// branch defers to the override on subsequent setting changes.
	onMount(() => {
		if (!browser) return;
		if (!new URLSearchParams(window.location.search).has('preview')) return;
		const onMsg = (e: MessageEvent) => {
			const d = e.data;
			if (!d || typeof d !== 'object') return;
			const t = d.__wchs_preview_theme;
			if (t === 'light' || t === 'dark') {
				theme.setPreviewOverride(t);
			}
		};
		window.addEventListener('message', onMsg);
		return () => window.removeEventListener('message', onMsg);
	});

	onMount(() => {
		// Safety sync — NOT the primary theme setter. The blocking <script>
		// in app.html already set data-theme before first paint. This call
		// syncs the reactive Svelte store with the DOM attribute so toggle
		// and cross-tab listeners work. See: theme flash prevention rules.
		theme.init('light');

		// Async init — fire and forget. Every step is resilient:
		// config.load() has its own try/catch (always sets ready=true).
		// auth.refresh() has its own try/catch (always resolves to guest or authenticated).
		// primeSession + cart.fetch are wrapped so failures don't block the app.
		(async () => {
			await config.load();
			config.initPreviewMode();

			const darkOn = Boolean(
				config.data.features?.dark_mode && config.data.header_show_toggle
			);
			theme.setDarkModeEnabled(darkOn);
			if (darkOn) {
				theme.applySiteDefault(config.data.theme_default ?? 'light');
			}

			// Non-blocking setup (doesn't need auth or cart) — each init
			// no-ops when its ID is empty. Order is intentional: GTM first
			// (may create window.dataLayer used by Google Ads), then the
			// rest in dashboard-tab order.
			if (config.data.gtm_id) initGTM(config.data.gtm_id);
			if (config.data.omnisend_brand_id) initOmnisend(config.data.omnisend_brand_id);
			if (config.data.klaviyo_public_key) initKlaviyo(config.data.klaviyo_public_key);
			if (config.data.meta_pixel_id) initMetaPixel(config.data.meta_pixel_id);
			if (config.data.tiktok_pixel_id) initTikTokPixel(config.data.tiktok_pixel_id);
			if (config.data.pinterest_tag_id) initPinterestTag(config.data.pinterest_tag_id);
			if (config.data.clarity_project_id) initClarity(config.data.clarity_project_id);
			if (config.data.hotjar_site_id) initHotjar(config.data.hotjar_site_id);
			if (config.data.google_ads_conversion_id) initGoogleAds(config.data.google_ads_conversion_id);

			// Hero font — lazy-load the Bunny <link> when admin picked something
			// other than Inter (Inter is already in app.html).
			loadFont(config.data.homepage?.hero?.headline_font);
			// Hero MODULE fonts — same deal for any hero placed as a module
			// on homepage/shop/pdp/pages. Idempotent loader.
			const collectHeroModuleFonts = (modules: unknown) => {
				if (!Array.isArray(modules)) return;
				modules.forEach(m => {
					if (m && typeof m === 'object' && (m as { type?: string }).type === 'hero') {
						const f = (m as { config?: { headline_font?: string } }).config?.headline_font;
						if (f) loadFont(f);
					}
				});
			};
			collectHeroModuleFonts(config.data.homepage?.modules);
			collectHeroModuleFonts(config.data.shop?.modules);
			collectHeroModuleFonts(config.data.pdp?.modules);
			(config.data.pages ?? []).forEach(p => collectHeroModuleFonts(p.modules));

			// Global typography — apply CSS custom properties + load fonts
			const typo = config.data.typography;
			if (typo) {
				const hSpec = HERO_FONTS[typo.heading_font as HeroFontKey];
				const bSpec = HERO_FONTS[typo.body_font as HeroFontKey];
				if (hSpec) {
					document.documentElement.style.setProperty('--font-heading', hSpec.family);
				}
				if (bSpec) {
					document.documentElement.style.setProperty('--font-body', bSpec.family);
					document.documentElement.style.setProperty('--font-sans', bSpec.family);
				}
				const wm: Record<string, string> = {
					light: '300', regular: '400', medium: '500', semibold: '600',
					bold: '700', extrabold: '800', black: '900',
				};
				document.documentElement.style.setProperty('--heading-weight', wm[typo.heading_weight] || '600');
				const sm: Record<string, string> = { s: '14px', m: '15px', l: '16px' };
				const bodySize = sm[typo.body_size] || '15px';
				document.documentElement.style.setProperty('--body-size', bodySize);
				document.documentElement.style.fontSize = bodySize;
				loadFont(typo.heading_font);
				loadFont(typo.body_font);
			}

			if (config.data.accent_color) {
				document.documentElement.style.setProperty('--accent', config.data.accent_color);
				if (config.data.accent_fg) {
					document.documentElement.style.setProperty('--accent-fg', config.data.accent_fg);
				}
			}

			// Design tokens — each is opt-in; null skips setting the var
			// so components fall back to their hardcoded defaults.
			const tokens = config.data.tokens;
			if (tokens) {
				const root = document.documentElement;
				const setOr = (name: string, v: number | null) => {
					if (typeof v === 'number' && Number.isFinite(v)) {
						root.style.setProperty(name, `${v}px`);
					}
				};
				setOr('--wchs-radius', tokens.radius);
				setOr('--wchs-spacing-v-compact', tokens.spacing_v_compact);
				setOr('--wchs-spacing-v-normal', tokens.spacing_v_normal);
				setOr('--wchs-spacing-v-spacious', tokens.spacing_v_spacious);
			}

			// Product-card tokens (Design tab → Product card section): sets
			// --card-* CSS vars and data-card-* attributes on <html>. Single
			// source of truth for card styling — preview streaming re-calls
			// this on every admin update via config.svelte.ts::applyAppearance.
			applyProductCardTokens(config.data.product_card);

			pretext.ready().then(() => { fontsReady = true; });

			// Curated third-party scripts — renders entries from
			// config.active_scripts whose surfaces include 'spa'. The
			// resolver (server-side in headless-rest-endpoints.php) already
			// filtered out disabled entries, missing-required-params, and
			// entries shadowed by a dedicated_setting_key. We only need to
			// inject, idempotent by data-wchs-id.
			for (const s of config.data.active_scripts ?? []) {
				if (!s.surfaces?.includes('spa')) continue;
				const target = s.placement === 'body_end' ? document.body : document.head;
				const bootId = `${s.id}__boot`;
				if (s.inline && !document.querySelector(`script[data-wchs-id="${bootId}"]`)) {
					const boot = document.createElement('script');
					boot.type = 'text/javascript';
					boot.dataset.wchsId = bootId;
					boot.textContent = s.inline;
					target.appendChild(boot);
				}
				if (!s.src || document.querySelector(`script[data-wchs-id="${s.id}"]`)) continue;
				const el = document.createElement('script');
				el.src = s.src;
				el.dataset.wchsId = s.id;
				if (s.async) el.async = true;
				if (s.defer) el.defer = true;
				target.appendChild(el);
			}

			// Auth FIRST — resolves the loading gate. Calls /wchs/v1/session
			// which is in the always_open list, so it works in all access modes.
			await auth.refresh();

			// Site gate check (needs both config + auth resolved)
			gate.check(config.data.gate_modal, auth.isAdmin);

			// Cart session — can fail in maintenance mode (503 from gated
			// endpoints) or on network error. Non-critical: SPA still renders,
			// cart shows empty, mutations fail with appropriate API errors.
			try {
				await primeSession();
				await cart.fetch();
			} catch {
				// Cart unavailable — swallow. The loading gate is already
				// resolved by this point so the app renders regardless.
			}

			consumeOpenCartIntent();

			// URL-based coupon auto-apply: landing on `/?coupon=CODE` or
			// any URL with a `?coupon=` param applies it to the cart once,
			// then strips the param so a refresh or share doesn't re-apply
			// or leak the code into analytics events. Guarded against:
			//  - double-apply if the cart already has the code
			//  - XSS via sanitize_text_field (coupon codes are alnum/dash/
			//    underscore; anything else is rejected server-side and
			//    stripped client-side for logging)
			//  - empty or invalid codes (silent skip)
			try {
				const rawUrl = new URL(window.location.href);
				const rawCode = (rawUrl.searchParams.get('coupon') || '').trim();
				// Client-side sanitize: keep only characters WC allows in coupon codes.
				// Matches Automattic\WooCommerce\StoreApi\Utilities\Validate handling.
				const code = rawCode.replace(/[^A-Za-z0-9 _-]/g, '').slice(0, 50);
				if (code) {
					const already = (cart.cart?.coupons ?? []).some((c: unknown) => {
						if (typeof c === 'string') return c.toLowerCase() === code.toLowerCase();
						if (c && typeof c === 'object' && 'code' in c) return String((c as { code: unknown }).code).toLowerCase() === code.toLowerCase();
						return false;
					});
					if (!already) {
						await cart.applyCoupon(code).catch(() => { /* invalid coupon: silent */ });
					}
					// Always strip the param so the URL doesn't retain state
					rawUrl.searchParams.delete('coupon');
					window.history.replaceState({}, '', rawUrl.pathname + (rawUrl.searchParams.toString() ? '?' + rawUrl.searchParams.toString() : '') + rawUrl.hash);
				}
			} catch {
				// URL parsing or applyCoupon side effect failure — don't
				// block app render over an auto-apply optimization.
			}
		})();

		document.body.addEventListener('added_to_cart', bumpCart);
		document.body.addEventListener('removed_from_cart', bumpCart);

		// Re-check config + auth when the tab becomes visible again.
		// The admin may have switched access modes while the user was away,
		// or the user may have logged in/out in another tab. Both config
		// and auth update silently in the background (no loading gate).
		document.addEventListener('visibilitychange', onVisibilityChange);
		document.addEventListener('keydown', onDrawerKey);

		return () => {
			document.body.removeEventListener('added_to_cart', bumpCart);
			document.body.removeEventListener('removed_from_cart', bumpCart);
			document.removeEventListener('visibilitychange', onVisibilityChange);
			document.removeEventListener('keydown', onDrawerKey);
		};
	});

	function onVisibilityChange() {
		if (document.visibilityState === 'visible') {
			// Refresh config first so access_mode is current, then auth.
			// The layout's reactive expression will pick up any changes.
			config.refresh().then(() => auth.refresh(true));
		}
	}

	function consumeOpenCartIntent(urlLike?: URL) {
		if (!browser) return;
		const url = urlLike ?? new URL(window.location.href);
		if (url.searchParams.get('open_cart') !== '1') return;
		cart.toggle(true);
		url.searchParams.delete('open_cart');
		window.history.replaceState(
			{},
			'',
			url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash
		);
	}

	// Track page views on SvelteKit client-side navigation.
	// SvelteKit doesn't trigger GA4's automatic pageview on route
	// changes — we push manually via afterNavigate.
	// Also closes the mobile drawer when the user navigates.
	afterNavigate(({ to }) => {
		if (to?.url) {
			trackPageView(to.url.pathname);
			trackOmnisendPageViewed();
			consumeOpenCartIntent(to.url);
		}
		drawerOpen = false;
	});

	function onDrawerKey(e: KeyboardEvent) {
		if (e.key === 'Escape') drawerOpen = false;
	}

	function syncDrawerTop() {
		const stack = document.querySelector('.site-header-stack');
		if (!stack) return;
		document.documentElement.style.setProperty(
			'--wchs-header-stack-height',
			`${stack.getBoundingClientRect().bottom}px`
		);
	}

	const hasAnnouncementBar = $derived(
		Boolean(config.data.announcement_bar_enabled && (config.data.announcement_bar_items?.length ?? 0) > 0)
	);

	$effect(() => {
		const isAdmin = auth.isAdmin;
		const hasModeBanner = isAdmin && config.data.access_mode !== 3;
		document.body.classList.toggle('has-admin-bar', isAdmin);
		document.body.classList.toggle('has-mode-banner', hasModeBanner);
		document.body.classList.toggle('has-announcement-bar', hasAnnouncementBar);
	});

	$effect(() => {
		if (!drawerOpen) return;
		syncDrawerTop();
		window.addEventListener('resize', syncDrawerTop);
		window.addEventListener('scroll', syncDrawerTop, { passive: true });
		return () => {
			window.removeEventListener('resize', syncDrawerTop);
			window.removeEventListener('scroll', syncDrawerTop);
		};
	});

	function bumpCart() {
		cartBumping = false;
		requestAnimationFrame(() => {
			cartBumping = true;
			setTimeout(() => (cartBumping = false), 400);
		});
	}
</script>

{#if !browser || !config.ready || auth.state.status === 'loading'}
	<div class="loading-gate">
		{#if config.ready}
			<span class="loading-gate__brand">{config.data.brand_name}</span>
		{/if}
	</div>
{:else if config.data.access_mode === 0 && !auth.isAdmin}
	<MaintenanceScreen />
{:else}
	<AdminBar />

	<div class="site-header-stack" class:has-admin-bar={auth.isAdmin}>
		<AnnouncementBar />
		<header
			class="site-header"
			data-hamburger-side={config.data.mobile_hamburger_side}
			data-logo-size={config.data.logo_url ? (config.data.logo_size ?? 'standard') : 'none'}
			data-brand-position={logoOnlyHeader ? 'left' : (config.data.brand_position ?? 'left')}
			class:site-header--inverted={config.data.header_inverted}
			class:site-header--borderless={config.data.header_borderless}
			class:site-header--logo-only={logoOnlyHeader}
		>
		<a class="site-header__brand" href="/">
			{#if config.data.logo_url}
				<img
					class="site-header__logo site-header__logo--size-{config.data.logo_size ?? 'standard'}"
					class:site-header__logo--light-variant={config.data.logo_dark_url}
					class:site-header__logo--auto-invert={config.data.logo_invert_on_dark && !config.data.logo_dark_url}
					src={config.data.logo_url}
					alt={config.data.brand_name}
				/>
				{#if config.data.logo_dark_url}
					<img
						class="site-header__logo site-header__logo--dark-variant site-header__logo--size-{config.data.logo_size ?? 'standard'}"
						src={config.data.logo_dark_url}
						alt=""
						aria-hidden="true"
					/>
				{/if}
			{:else}
				{config.data.brand_name}
			{/if}
		</a>

		{#if !logoOnlyHeader}
		<nav class="site-header__nav">
			<!-- Full inline nav — renders on desktop always, and on mobile
			     when mobile_hamburger_side='off'. Hidden by CSS when the
			     hamburger is active. -->
			<div class="site-header__nav-inline">
				<div class="site-header__nav-menu">
					{#each config.data.header_links as link}
						{#if link.display === 'text'}
							<a href={link.url} class="site-header__nav-link" class:is-accent={link.accent}>{link.label}</a>
						{/if}
					{/each}
				</div>
				<div class="site-header__nav-actions">
					<HeaderSearch />
					{#each config.data.header_links as link}
						{#if link.display === 'icon' || link.display === 'both'}
							<a href={link.url} class="site-header__icon-link" class:is-accent={link.accent} aria-label={link.label}>
								{#if link.icon && icons[link.icon]}
									<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{@html icons[link.icon]}</svg>
								{/if}
								{#if link.display === 'both'}
									<span>{link.label}</span>
								{/if}
							</a>
						{/if}
					{/each}
					{#if themeToggleVisible}
						<span class:is-accent-toggle={config.data.header_toggle_accent}>
							<ThemeToggle />
						</span>
					{/if}
					<button
						type="button"
						class="site-header__cart"
						class:is-accent={config.data.header_cart_accent}
						class:is-bumping={cartBumping}
						onclick={() => cart.toggle()}
						aria-label="Open cart"
					>
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
						<span class="site-header__cart-count tabular-nums">{cart.itemCount}</span>
					</button>
				</div>
			</div>

			<!-- Mobile pinned cluster — shows only on mobile when
			     hamburger is active. Up to 3 items. -->
			{#if config.data.mobile_hamburger_side !== 'off'}
				<div class="site-header__nav-group--pinned">
					<HeaderSearch />
					{#each pinnedItems as entry}
						{#if entry.kind === 'link'}
							{#if entry.link.display === 'icon' || entry.link.display === 'both'}
								<a href={entry.link.url} class="site-header__icon-link" class:is-accent={entry.link.accent} aria-label={entry.link.label}>
									{#if entry.link.icon && icons[entry.link.icon]}
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{@html icons[entry.link.icon]}</svg>
									{/if}
									{#if entry.link.display === 'both'}
										<span>{entry.link.label}</span>
									{/if}
								</a>
							{:else}
								<a href={entry.link.url} class="site-header__nav-link" class:is-accent={entry.link.accent}>{entry.link.label}</a>
							{/if}
						{:else if entry.kind === 'toggle'}
							<span class:is-accent-toggle={config.data.header_toggle_accent}>
								<ThemeToggle />
							</span>
						{:else if entry.kind === 'cart'}
							<button
								type="button"
								class="site-header__cart"
								class:is-accent={config.data.header_cart_accent}
								class:is-bumping={cartBumping}
								onclick={() => cart.toggle()}
								aria-label="Open cart"
							>
								<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
								<span class="site-header__cart-count tabular-nums">{cart.itemCount}</span>
							</button>
						{/if}
					{/each}
				</div>
				<button
					type="button"
					class="site-header__burger"
					aria-label="Open menu"
					aria-expanded={drawerOpen}
					aria-controls="site-drawer"
					onclick={() => {
						if (!drawerOpen) syncDrawerTop();
						drawerOpen = !drawerOpen;
					}}
				>
					<svg class="site-header__burger-open" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
						<path d="M3 6h18M3 12h18M3 18h18"/>
					</svg>
					<svg class="site-header__burger-close" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
						<path d="M6 6l12 12M18 6L6 18"/>
					</svg>
				</button>
			{/if}
		</nav>
		{/if}
	</header>
	</div>

	<!-- Mobile drawer — rendered as a sibling of the header since it's
	     position:fixed. `hidden` attribute gates visibility. -->
	{#if config.data.mobile_hamburger_side !== 'off' && !logoOnlyHeader}
		<div
			class="site-header-drawer"
			id="site-drawer"
			role="dialog"
			aria-label="Navigation menu"
			hidden={!drawerOpen}
		>
			{#each drawerItems as entry}
				{#if entry.kind === 'link'}
					<a
						class="site-header-drawer__item"
						class:is-accent={entry.link.accent}
						href={entry.link.url}
						onclick={() => (drawerOpen = false)}
					>
						{#if entry.link.icon && icons[entry.link.icon]}
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{@html icons[entry.link.icon]}</svg>
						{/if}
						<span>{entry.link.label}</span>
					</a>
				{:else if entry.kind === 'toggle'}
					<div class="site-header-drawer__item" class:is-accent={config.data.header_toggle_accent}>
						<ThemeToggle />
						<span>Theme</span>
					</div>
				{:else if entry.kind === 'cart'}
					<button
						type="button"
						class="site-header-drawer__item"
						class:is-accent={config.data.header_cart_accent}
						onclick={() => { drawerOpen = false; cart.toggle(); }}
					>
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
						<span>Cart ({cart.itemCount})</span>
					</button>
				{/if}
			{/each}
		</div>
	{/if}

	<a class="skip-to-content" href="#main-content">Skip to content</a>

	<main id="main-content">
		{@render children()}
	</main>

	<Footer />
	<SlideCart />
	<SiteGate />
	<BackToTop />
{/if}

<style>
	@import '$lib/styles/tokens.css';

	.skip-to-content {
		position: absolute;
		top: -40px;
		left: 8px;
		padding: 8px 14px;
		background: var(--fg);
		color: var(--bg);
		text-decoration: none;
		font-size: 13px;
		font-weight: 500;
		border-radius: var(--radius-sm);
		z-index: 9999;
		transition: top var(--dur-fast) var(--ease);
	}
	.skip-to-content:focus {
		top: 8px;
		outline: 2px solid var(--accent, var(--fg));
		outline-offset: 2px;
	}

	:global(html, body) {
		margin: 0;
		padding: 0;
		background: var(--bg);
		color: var(--fg);
		font-family: var(--font-sans);
		font-size: 14px;
		line-height: 1.5;
		letter-spacing: -0.16px;
		transition:
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease);
	}
	:global(*, *::before, *::after) {
		box-sizing: border-box;
	}
	:global(a) {
		color: inherit;
	}
	:global(::selection) {
		background: var(--fg);
		color: var(--bg);
	}
	:global(main) {
		min-height: calc(100vh - 73px);
	}
	/* Preview mode (?preview=1) — strip artificial min-heights so the
	   canvas artboard measures the true content size. Hero mobile floor
	   (1010px + 530px padding) and main's calc(100vh) are the two
	   biggest offenders; remove both only in preview, never in live.
	   Homepage uses .hero-rx (research-motion), not .hero — include both. */
	:global(html[data-preview] main) {
		min-height: 0;
	}
	:global(html[data-preview] .hero),
	:global(html[data-preview] .hero-rx) {
		min-height: auto;
	}
	@media (max-width: 639px) {
		:global(html[data-preview] .hero),
		:global(html[data-preview] .hero-rx) {
			padding-bottom: 40px;
		}
	}

	/* Header styles are in the shared header.css file (symlinked from
	   wp/mu-plugins/wchs-design-system/assets/header.css). Both the
	   SPA and WP use the same CSS. See: header consistency rules. */

	.loading-gate {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 100vh;
		background: var(--bg);
	}
	.loading-gate__brand {
		font-family: var(--font-sans);
		font-size: 13px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.12em;
		color: var(--fg-muted);
		opacity: 0;
		animation: loading-fade-in 0.3s ease 0.1s forwards;
	}
	@keyframes loading-fade-in {
		to { opacity: 1; }
	}

	:global(body.has-admin-bar) {
		padding-top: 32px;
	}
	:global(body.has-mode-banner) {
		padding-top: 60px;
	}
	:global(body.has-mode-banner) .site-header-stack {
		top: 60px;
	}
</style>
