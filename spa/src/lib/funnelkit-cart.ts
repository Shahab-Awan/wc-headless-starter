/**
 * FunnelKit Cart on the SPA — load WP assets, render [fk_cart_menu], sync + open drawer.
 */

import { browser } from '$app/environment';
import { config } from '$lib/config.svelte';
import { currentCartToken } from '$lib/wc/store-api';

let assetsLoaded = false;
let assetsLoading: Promise<boolean> | null = null;
let syncInFlight: Promise<boolean> | null = null;
let floaterCssInjected = false;

export function funnelkitCartEnabled(): boolean {
	return Boolean(config.data.funnelkit_cart?.enabled);
}

function wpOrigin(): string {
	return config.data.wp_origin.replace(/\/$/, '');
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
	const el = document.createElement('link');
	el.rel = 'stylesheet';
	el.href = href;
	el.dataset.wchsFk = id;
	document.head.appendChild(el);
}

/**
 * Load jQuery + FunnelKit cart scripts/styles from wp_origin (absolute URLs in config).
 */
export async function loadFunnelKitAssets(): Promise<boolean> {
	if (!browser || !funnelkitCartEnabled()) return false;
	if (assetsLoaded) return true;
	if (assetsLoading) return assetsLoading;

	const fk = config.data.funnelkit_cart;
	if (!fk) return false;

	assetsLoading = (async () => {
		injectHideFloaterCss();

		const scripts = fk.scripts ?? [];
		const styles = fk.styles ?? [];

		const jquery =
			scripts.find((s) => s.handle === 'jquery')?.src ??
			config.wpUrl('/wp-includes/js/jquery/jquery.min.js');

		try {
			await loadScript(jquery, 'jquery');
		} catch {
			return false;
		}

		for (const s of scripts) {
			if (s.handle === 'jquery' || !s.src) continue;
			try {
				await loadScript(s.src, s.handle);
			} catch {
				// Continue — some builds split optional chunks.
			}
		}

		for (const st of styles) {
			if (st.src) loadStyle(st.src, st.handle);
		}

		assetsLoaded = true;
		return true;
	})().finally(() => {
		assetsLoading = null;
	});

	return assetsLoading;
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
	const w = window as Window & { jQuery?: { (sel: unknown): { trigger: (ev: string) => void } } };
	if (w.jQuery) {
		try {
			w.jQuery(document.body).trigger('fkcart_open_slider');
		} catch {
			// FK version-specific event names.
		}
	}

	const selector =
		config.data.funnelkit_cart?.trigger_selector ??
		'.site-header__fkcart-menu .fkcart-mini-open, .site-header__fkcart-menu a, .site-header__fkcart-menu button';
	const el = document.querySelector(selector);
	if (el instanceof HTMLElement) {
		el.click();
	}

	document.body.dispatchEvent(new CustomEvent('fkcart_cart_open', { bubbles: true }));
}

export async function openFunnelKitCart(itemCount = 0): Promise<void> {
	if (!funnelkitCartEnabled()) return;

	const ready = await loadFunnelKitAssets();
	if (!ready) return;

	if (itemCount > 0) {
		await syncClassicCart().catch(() => false);
	}

	triggerFkCartOpen();
}

export async function initFunnelKitCart(): Promise<void> {
	if (!funnelkitCartEnabled()) return;
	await loadFunnelKitAssets();
}
