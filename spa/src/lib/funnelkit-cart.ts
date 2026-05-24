/**
 * FunnelKit Cart on the SPA — bootstrap drawer markup from WP, sync Store API, open drawer.
 */

import { browser } from '$app/environment';
import { config } from '$lib/config.svelte';
import { currentCartToken } from '$lib/wc/store-api';

type FkScript = { handle: string; src: string; deps?: string[] };
type FkStyle = { handle: string; src: string };
type FkInline = { handle: string; data: string };

type BootstrapPayload = {
	markup: string;
	scripts: FkScript[];
	styles: FkStyle[];
	inline: FkInline[];
};

let bootstrapped = false;
let bootstrapPromise: Promise<boolean> | null = null;
let syncInFlight: Promise<boolean> | null = null;
let floaterCssInjected = false;

const MOUNT_ID = 'wchs-fk-cart-mount';

export function funnelkitCartEnabled(): boolean {
	return Boolean(config.data.funnelkit_cart?.enabled);
}

function injectHideFloaterCss(): void {
	if (!browser || floaterCssInjected) return;
	floaterCssInjected = true;
	const el = document.createElement('style');
	el.id = 'wchs-fk-hide-floater';
	el.textContent =
		'#fkcart-mini-toggler,.fkcart-mini-toggler,.fkcart-floating-cart,.fkit-floating-cart,[data-fkcart-trigger="floating"]{display:none!important;visibility:hidden!important}';
	document.head.appendChild(el);
}

function hasFkDrawerDom(): boolean {
	return Boolean(
		document.querySelector(
			'#fkcart-slider, #fkcart-modal, .fkcart-modal, .fkcart-slider, [id^="fkcart-"]'
		)
	);
}

function sortScripts(scripts: FkScript[]): FkScript[] {
	const byHandle = new Map(scripts.map((s) => [s.handle, s]));
	const sorted: FkScript[] = [];
	const done = new Set<string>();

	const visit = (handle: string) => {
		if (done.has(handle)) return;
		const row = byHandle.get(handle);
		if (!row) {
			done.add(handle);
			return;
		}
		for (const dep of row.deps ?? []) {
			visit(dep);
		}
		done.add(handle);
		if (row.src) sorted.push(row);
	};

	for (const s of scripts) visit(s.handle);
	return sorted;
}

function loadScript(src: string, id: string): Promise<void> {
	return new Promise((resolve, reject) => {
		if (document.querySelector(`script[data-wchs-fk="${id}"]`)) {
			resolve();
			return;
		}
		const el = document.createElement('script');
		el.src = src;
		el.dataset.wchsFk = id;
		el.onload = () => resolve();
		el.onerror = () => reject(new Error(`Failed to load ${src}`));
		document.body.appendChild(el);
	});
}

function loadStyle(href: string, id: string): void {
	if (document.querySelector(`link[data-wchs-fk="${id}"]`)) return;
	// FK cart styles only — skip handles that are not fkcart-specific.
	if (!/fkcart|fk-cart|fkit-cart/i.test(id) && !/fkcart|fk-cart|fkit-cart/i.test(href)) {
		return;
	}
	const el = document.createElement('link');
	el.rel = 'stylesheet';
	el.href = href;
	el.dataset.wchsFk = id;
	document.head.appendChild(el);
}

function injectInlineScripts(inline: FkInline[]): void {
	for (const row of inline) {
		const id = `inline-${row.handle}`;
		if (document.getElementById(id)) continue;
		const el = document.createElement('script');
		el.id = id;
		el.textContent = row.data;
		document.body.appendChild(el);
	}
}

function sanitizeBootstrapMarkup(html: string): string {
	return html
		.replace(/<link\b[^>]*>/gi, '')
		.replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '')
		.trim();
}

function injectMarkup(markup: string): void {
	const clean = sanitizeBootstrapMarkup(markup);
	if (!clean) return;
	let mount = document.getElementById(MOUNT_ID);
	if (!mount) {
		mount = document.createElement('div');
		mount.id = MOUNT_ID;
		mount.setAttribute('aria-hidden', 'true');
		document.body.appendChild(mount);
	}
	mount.innerHTML = clean;
}

async function fetchBootstrap(): Promise<BootstrapPayload | null> {
	const fk = config.data.funnelkit_cart;
	const urls = [
		fk?.bootstrap_url,
		config.wpUrl('/cart/?wchs_fk_cart_bootstrap=1'),
		config.wpUrl('/wp-json/wchs/v1/funnelkit/bootstrap')
	].filter((u): u is string => Boolean(u));

	for (const url of urls) {
		try {
			const res = await fetch(url, { credentials: 'include' });
			if (!res.ok) continue;
			const data = (await res.json()) as BootstrapPayload;
			if (data && typeof data.markup === 'string') return data;
		} catch {
			// Try next URL.
		}
	}
	return null;
}

async function loadScriptsAndStyles(payload: BootstrapPayload): Promise<boolean> {
	const scripts = sortScripts(payload.scripts ?? []);
	const jquery =
		scripts.find((s) => s.handle === 'jquery')?.src ??
		config.wpUrl('/wp-includes/js/jquery/jquery.min.js');

	try {
		await loadScript(jquery, 'jquery');
	} catch {
		return false;
	}

	injectInlineScripts(payload.inline ?? []);

	for (const s of scripts) {
		if (s.handle === 'jquery' || !s.src) continue;
		try {
			await loadScript(s.src, s.handle);
		} catch {
			// Optional chunks — continue.
		}
	}

	for (const st of payload.styles ?? []) {
		if (st.src) loadStyle(st.src, st.handle);
	}

	return true;
}

/**
 * Fetch FK drawer HTML + load scripts (required before open works on SPA).
 */
export async function bootstrapFunnelKitCart(): Promise<boolean> {
	if (!browser || !funnelkitCartEnabled()) return false;
	if (bootstrapped && hasFkDrawerDom()) return true;
	if (bootstrapPromise) return bootstrapPromise;

	bootstrapPromise = (async () => {
		injectHideFloaterCss();

		const payload = await fetchBootstrap();
		if (!payload) return false;

		injectMarkup(payload.markup);
		const scriptsOk = await loadScriptsAndStyles(payload);
		if (!scriptsOk) return false;

		bootstrapped = hasFkDrawerDom();
		return bootstrapped;
	})().finally(() => {
		bootstrapPromise = null;
	});

	return bootstrapPromise;
}

export async function syncClassicCart(): Promise<boolean> {
	if (!funnelkitCartEnabled()) return false;

	const token = currentCartToken();
	if (!token) return false;

	const syncUrl = config.data.funnelkit_cart?.sync_url || config.wpUrl('/wp-json/wchs/v1/cart/sync-classic');

	if (syncInFlight) return syncInFlight;

	syncInFlight = (async () => {
		const res = await fetch(syncUrl, {
			method: 'POST',
			credentials: 'include',
			headers: {
				'Content-Type': 'application/json',
				'Cart-Token': token
			},
			body: JSON.stringify({ cart: token })
		});
		if (!res.ok) return false;
		const body = (await res.json()) as { ok?: boolean };
		return Boolean(body?.ok);
	})().finally(() => {
		syncInFlight = null;
	});

	return syncInFlight;
}

function triggerFkCartOpen(): void {
	const $ = (window as Window & { jQuery?: (sel?: unknown) => { trigger: (name: string, args?: unknown[]) => void } })
		.jQuery;
	if ($) {
		const body = $(document.body);
		try {
			body.trigger('fkcart_update_side_cart', [true]);
		} catch {
			/* FK version may omit this event. */
		}
		try {
			body.trigger('fkcart_open_slider');
		} catch {
			/* FK version may omit this event. */
		}
	}

	const selectors = [
		config.data.funnelkit_cart?.trigger_selector,
		'.site-header__fkcart-menu .fkcart-mini-open',
		'.site-header__fkcart-menu a',
		'.site-header__fkcart-menu [data-fkcart-trigger]',
		'.fkcart-mini-open'
	].filter(Boolean) as string[];

	for (const sel of selectors) {
		const el = document.querySelector(sel);
		if (el instanceof HTMLElement) {
			el.click();
			break;
		}
	}

	document.body.dispatchEvent(new CustomEvent('fkcart_cart_open', { bubbles: true }));
}

export async function openFunnelKitCart(itemCount = 0): Promise<void> {
	if (!funnelkitCartEnabled()) return;

	const ready = await bootstrapFunnelKitCart();
	if (!ready) return;

	if (itemCount > 0) {
		await syncClassicCart().catch(() => false);
	}

	// Allow FK init to bind after scripts + markup injection.
	await new Promise((r) => setTimeout(r, 120));
	triggerFkCartOpen();
}

export async function initFunnelKitCart(): Promise<void> {
	await bootstrapFunnelKitCart();
}
