import { browser } from '$app/environment';
import { config } from '$lib/config.svelte';

/** Hostnames that only serve the Why Alyve bridge page; all other nav exits to spa_origin. */
const BRIDGE_HOSTS = new Set(['alyveresearch.com']);

export const BRIDGE_PAGE_PATH = '/why-alyve';

export function normalizeHost(hostname: string): string {
	return hostname.toLowerCase().replace(/^www\./, '');
}

export function isBridgeDomain(hostname?: string): boolean {
	if (!hostname) {
		if (!browser) return false;
		hostname = window.location.hostname;
	}
	return BRIDGE_HOSTS.has(normalizeHost(hostname));
}

export function mainStorefrontOrigin(): string {
	const origin = config.data.spa_origin?.replace(/\/$/, '');
	if (origin) return origin;
	if (browser) return window.location.origin;
	return '';
}

/** Rewrite internal hrefs to the main storefront when viewed on a bridge domain. */
export function bridgeAwareHref(href: string): string {
	if (!browser || !isBridgeDomain() || !href) return href;
	if (href === '#' || href.startsWith('mailto:') || href.startsWith('tel:')) return href;

	let url: URL;
	try {
		url = new URL(href, window.location.href);
	} catch {
		return href;
	}

	const main = mainStorefrontOrigin();
	if (!main) return href;

	const mainHost = normalizeHost(new URL(main).hostname);
	const linkHost = normalizeHost(url.hostname);

	if (linkHost !== normalizeHost(window.location.hostname)) {
		return href;
	}

	if (url.pathname.replace(/\/$/, '') === BRIDGE_PAGE_PATH) {
		return href;
	}

	return `${main.replace(/\/$/, '')}${url.pathname}${url.search}${url.hash}`;
}

export function isBridgePagePath(path: string): boolean {
	return path.replace(/\/$/, '') === BRIDGE_PAGE_PATH;
}

/** Marketing / consent overlays are disabled on the Why Alyve landing page. */
export function shouldSuppressLandingPopups(path: string): boolean {
	return isBridgePagePath(path);
}

export function shouldHandOffBridgeNavigation(href: string): string | null {
	if (!browser || !isBridgeDomain()) return null;
	if (!href || href === '#' || href.startsWith('mailto:') || href.startsWith('tel:')) return null;

	let url: URL;
	try {
		url = new URL(href, window.location.href);
	} catch {
		return null;
	}

	const main = mainStorefrontOrigin();
	if (!main) return null;

	if (normalizeHost(url.hostname) !== normalizeHost(window.location.hostname)) {
		return null;
	}

	if (url.pathname.replace(/\/$/, '') === BRIDGE_PAGE_PATH) {
		return null;
	}

	return `${main.replace(/\/$/, '')}${url.pathname}${url.search}${url.hash}`;
}
