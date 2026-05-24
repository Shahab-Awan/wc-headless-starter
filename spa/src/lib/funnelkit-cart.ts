/**
 * FunnelKit Cart on the SPA — sync Store API → classic session, open drawer in shell iframe.
 */

import { browser } from '$app/environment';
import { config } from '$lib/config.svelte';
import { currentCartToken } from '$lib/wc/store-api';

let shellFrame: HTMLIFrameElement | null = null;
let shellReady = false;
let shellReadyWaiters: Array<() => void> = [];
let syncInFlight: Promise<boolean> | null = null;

export function funnelkitCartEnabled(): boolean {
	return Boolean(config.data.funnelkit_cart?.enabled);
}

function wpOrigin(): string {
	return config.data.wp_origin.replace(/\/$/, '');
}

function onShellMessage(event: MessageEvent) {
	if (event.origin !== wpOrigin()) return;
	if (event.data?.type === 'wchs-fk-cart-ready') {
		shellReady = true;
		const waiters = shellReadyWaiters.splice(0);
		for (const fn of waiters) fn();
	}
}

function waitForShellReady(timeoutMs = 8000): Promise<void> {
	if (shellReady) return Promise.resolve();
	return new Promise((resolve, reject) => {
		const t = setTimeout(() => reject(new Error('FunnelKit cart shell timed out')), timeoutMs);
		shellReadyWaiters.push(() => {
			clearTimeout(t);
			resolve();
		});
	});
}

function positionShellInHeader(frame: HTMLIFrameElement): void {
	let slot: Element | null = null;
	for (const el of document.querySelectorAll('.site-header__fk-slot')) {
		const rect = el.getBoundingClientRect();
		if (rect.width > 0 && rect.height > 0) {
			slot = el;
			break;
		}
	}
	if (!slot) return;
	const rect = slot.getBoundingClientRect();
	if (rect.width < 1 || rect.height < 1) return;
	frame.style.top = `${rect.top}px`;
	frame.style.left = `${rect.left}px`;
	frame.style.width = `${rect.width}px`;
	frame.style.height = `${rect.height}px`;
}

export function ensureFunnelKitCartShell(): HTMLIFrameElement | null {
	if (!browser || !funnelkitCartEnabled()) return null;
	const shellUrl = config.data.funnelkit_cart?.shell_url;
	if (!shellUrl) return null;

	if (shellFrame?.isConnected) {
		if (!shellFrame.classList.contains('wchs-fk-cart-shell--active')) {
			positionShellInHeader(shellFrame);
		}
		return shellFrame;
	}

	window.addEventListener('message', onShellMessage);
	window.addEventListener('resize', () => {
		if (shellFrame && !shellFrame.classList.contains('wchs-fk-cart-shell--active')) {
			positionShellInHeader(shellFrame);
		}
	});

	shellFrame = document.createElement('iframe');
	shellFrame.src = shellUrl;
	shellFrame.title = 'Cart';
	shellFrame.setAttribute('aria-label', 'Open cart');
	shellFrame.dataset.wchsFkCartShell = '1';
	shellFrame.className = 'wchs-fk-cart-shell';
	document.body.appendChild(shellFrame);

	const place = () => positionShellInHeader(shellFrame!);
	place();
	shellFrame.addEventListener('load', place);
	requestAnimationFrame(place);

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

export async function openFunnelKitCart(itemCount = 0): Promise<void> {
	if (!funnelkitCartEnabled()) return;

	if (itemCount > 0) {
		await syncClassicCart().catch(() => false);
	}

	const frame = ensureFunnelKitCartShell();
	if (!frame?.contentWindow) return;

	frame.classList.add('wchs-fk-cart-shell--active');

	try {
		await waitForShellReady();
	} catch {
		return;
	}

	frame.contentWindow.postMessage({ type: 'wchs-fk-cart-open' }, wpOrigin());
}

export function closeFunnelKitCartShell(): void {
	if (!shellFrame) return;
	shellFrame.classList.remove('wchs-fk-cart-shell--active');
	positionShellInHeader(shellFrame);
}
