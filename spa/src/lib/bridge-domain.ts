import { browser } from '$app/environment';
import { config } from '$lib/config.svelte';
import { HOME_1_PATH, isHome1LandingPath } from '$lib/home-1-landing';

/** Legacy Why Alyve bridge — alyveresearch.com serves /why-alyve only. */
const WHY_ALYVE_BRIDGE_HOSTS = new Set(['alyveresearch.com']);

export const BRIDGE_PAGE_PATH = '/why-alyve';

export function normalizeHost(hostname: string): string {
	return hostname.toLowerCase().replace(/^www\./, '');
}

function home1BridgeHosts(): Set<string> {
	const hosts = config.data.home_1?.bridge_hosts ?? [];
	return new Set(hosts.map(normalizeHost).filter(Boolean));
}

/** Landing path served at `/` on this hostname (null = not a bridge host). */
export function bridgeLandingPathForHost(hostname?: string): string | null {
	if (!hostname) {
		if (!browser) return null;
		hostname = window.location.hostname;
	}
	const host = normalizeHost(hostname);
	if (WHY_ALYVE_BRIDGE_HOSTS.has(host)) return BRIDGE_PAGE_PATH;
	if (home1BridgeHosts().has(host)) return HOME_1_PATH;
	return null;
}

export function isBridgeDomain(hostname?: string): boolean {
	return bridgeLandingPathForHost(hostname) !== null;
}

export function getActiveBridgeLandingPath(): string | null {
	if (!browser) return null;
	return bridgeLandingPathForHost(window.location.hostname);
}

export function mainStorefrontOrigin(): string {
	const origin = config.data.spa_origin?.replace(/\/$/, '');
	if (origin) return origin;
	if (browser) return window.location.origin;
	return '';
}

function isLocalBridgeLandingPath(pathname: string): boolean {
	const path = pathname.replace(/\/$/, '') || '/';
	if (path === BRIDGE_PAGE_PATH) return true;
	if (isHome1LandingPath(path)) return true;
	return false;
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

	const linkHost = normalizeHost(url.hostname);

	if (linkHost !== normalizeHost(window.location.hostname)) {
		return href;
	}

	if (isLocalBridgeLandingPath(url.pathname)) {
		return href;
	}

	return `${main.replace(/\/$/, '')}${url.pathname}${url.search}${url.hash}`;
}

export function isBridgePagePath(path: string): boolean {
	return isLocalBridgeLandingPath(path);
}

/** Marketing / consent overlays are disabled on bridge landing pages. */
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

	if (isLocalBridgeLandingPath(url.pathname)) {
		return null;
	}

	return `${main.replace(/\/$/, '')}${url.pathname}${url.search}${url.hash}`;
}
