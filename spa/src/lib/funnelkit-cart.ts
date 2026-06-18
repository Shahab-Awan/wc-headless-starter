/**
 * FunnelKit Cart on the SPA — sync Store API → classic session, open drawer in shell iframe.
 */

import { browser } from '$app/environment';
import { config } from '$lib/config.svelte';
import { currentCartToken } from '$lib/wc/store-api';

let shellFrame: HTMLIFrameElement | null = null;
let shellReady = false;
let shellReadyWaiters: Array<() => void> = [];
let cartOpenedWaiters: Array<(opened: boolean) => void> = [];
let syncInFlight: Promise<boolean> | null = null;

export function funnelkitCartEnabled(): boolean {
	return Boolean(config.data.funnelkit_cart?.enabled);
}

export function funnelkitCartRequested(): boolean {
	return Boolean(config.data.funnelkit_cart?.requested ?? config.data.funnelkit_cart?.enabled);
}

function wpOrigin(): string {
	return config.data.wp_origin.replace(/\/$/, '');
}

function allowedMessageOrigin(origin: string): boolean {
	const wp = wpOrigin();
	const spa = (config.data.spa_origin || '').replace(/\/$/, '');
	if (origin === wp) return true;
	if (spa && origin === spa) return true;
	if (typeof window !== 'undefined' && origin === window.location.origin) return true;
	return false;
}

function onShellMessage(event: MessageEvent) {
	if (!allowedMessageOrigin(event.origin)) return;
	const type = event.data?.type;
	if (type === 'wchs-fk-cart-ready') {
		shellReady = true;
		const waiters = shellReadyWaiters.splice(0);
		for (const fn of waiters) fn();
		return;
	}
	if (type === 'wchs-fk-cart-opened') {
		shellFrame?.classList.add('wchs-fk-cart-shell--interactive');
		const waiters = cartOpenedWaiters.splice(0);
		for (const fn of waiters) fn(true);
		return;
	}
	if (type === 'wchs-fk-cart-closed') {
		closeFunnelKitCartShell();
		const waiters = cartOpenedWaiters.splice(0);
		for (const fn of waiters) fn(false);
	}
}

function waitForShellReady(timeoutMs = 10000): Promise<void> {
	if (shellReady) return Promise.resolve();
	return new Promise((resolve, reject) => {
		const t = setTimeout(() => reject(new Error('FunnelKit cart shell timed out')), timeoutMs);
		shellReadyWaiters.push(() => {
			clearTimeout(t);
			resolve();
		});
	});
}

function waitForCartOpened(timeoutMs = 5000): Promise<boolean> {
	return new Promise((resolve) => {
		const t = setTimeout(() => {
			cartOpenedWaiters = cartOpenedWaiters.filter((fn) => fn !== done);
			resolve(false);
		}, timeoutMs);
		const done = (opened: boolean) => {
			clearTimeout(t);
			resolve(opened);
		};
		cartOpenedWaiters.push(done);
	});
}

export function ensureFunnelKitCartShell(): HTMLIFrameElement | null {
	if (!browser || !funnelkitCartEnabled()) return null;
	const shellUrl = config.data.funnelkit_cart?.shell_url;
	if (!shellUrl) return null;

	if (shellFrame?.isConnected) return shellFrame;

	window.addEventListener('message', onShellMessage);

	shellReady = false;
	shellFrame = document.createElement('iframe');
	shellFrame.src = shellUrl;
	shellFrame.title = 'Cart';
	shellFrame.setAttribute('aria-hidden', 'true');
	shellFrame.dataset.wchsFkCartShell = '1';
	shellFrame.className = 'wchs-fk-cart-shell';
	document.body.appendChild(shellFrame);
	return shellFrame;
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

/** Opens FunnelKit drawer in the shell iframe (Store API cart synced first when non-empty). */
export async function openFunnelKitCart(itemCount = 0): Promise<boolean> {
	if (!funnelkitCartEnabled()) return false;

	const frame = ensureFunnelKitCartShell();
	if (!frame?.contentWindow) return false;

	closeFunnelKitCartShell();

	try {
		await waitForShellReady();
	} catch {
		return false;
	}

	if (itemCount > 0) {
		let synced = await syncClassicCart().catch(() => false);
		if (!synced) {
			const { primeSession } = await import('$lib/wc/store-api');
			await primeSession().catch(() => {});
			synced = await syncClassicCart().catch(() => false);
		}
		if (!synced) return false;
		frame.contentWindow.postMessage({ type: 'wchs-fk-cart-synced' }, wpOrigin());
	}

	const openedPromise = waitForCartOpened();

	frame.contentWindow.postMessage({ type: 'wchs-fk-cart-open' }, wpOrigin());

	const opened = await openedPromise;
	if (!opened) {
		closeFunnelKitCartShell();
		return false;
	}

	return true;
}

export function closeFunnelKitCartShell(): void {
	shellFrame?.classList.remove('wchs-fk-cart-shell--interactive');
}
