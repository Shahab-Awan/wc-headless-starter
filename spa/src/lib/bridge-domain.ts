import { browser } from '$app/environment';
import { config } from '$lib/config.svelte';
import { HOME_1_PATH, isHome1LandingPath } from '$lib/home-1-landing';

/** Legacy Why Alyve bridge — alyveresearch.com serves /why-alyve only. */
const WHY_ALYVE_BRIDGE_HOSTS = new Set(['alyveresearch.com']);

export const BRIDGE_PAGE_PATH = '/why-alyve';

const CL_UID_COOKIE = 'cl852373hycz6u_uid';
const CL_UTM_COOKIE = 'cl852373hycz6u_utmParams';

function readCookie(name: string): string | null {
	if (!browser) return null;
	const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
	if (!match?.[1]) return null;
	try {
		return decodeURIComponent(match[1]);
	} catch {
		return match[1];
	}
}

function parseUtmParamsCookie(raw: string | null): Record<string, string> | null {
	if (!raw) return null;

	const asRecord = (input: unknown): Record<string, string> | null => {
		if (!input || typeof input !== 'object' || Array.isArray(input)) return null;
		const out: Record<string, string> = {};
		for (const [key, val] of Object.entries(input as Record<string, unknown>)) {
			if (val == null || val === '') continue;
			if (typeof val === 'object' && val !== null && 'v' in val) {
				const v = (val as { v: unknown }).v;
				if (v == null || v === '') continue;
				out[key] = String(v);
			} else {
				out[key] = String(val);
			}
		}
		return Object.keys(out).length ? out : null;
	};

	try {
		const parsed: unknown = JSON.parse(raw);
		if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
			const root = parsed as Record<string, unknown>;
			const nested = asRecord(root.utm_params ?? root.utmParams);
			if (nested) return nested;
			const flat = asRecord(parsed);
			if (flat) return flat;
		}
	} catch {
		/* not JSON — try querystring */
	}

	try {
		const qs = raw.startsWith('?') ? raw.slice(1) : raw;
		const params = new URLSearchParams(qs);
		const out: Record<string, string> = {};
		for (const [key, val] of params) {
			if (val) out[key] = val;
		}
		return Object.keys(out).length ? out : null;
	} catch {
		return null;
	}
}

function isFacebookUtmSource(utms: Record<string, string>): boolean {
	const source = (utms.utm_source || utms.source || '').toLowerCase();
	return source.includes('facebook') || source === 'fb' || source.startsWith('fb_');
}

/**
 * Stamp CustomerLabs tracking onto a CTA URL:
 * 1) if cl852373hycz6u_uid exists → ?cluid=
 * 2) if cl852373hycz6u_utmParams has facebook utm_source → append all utm_* params
 */
export function withClTrackingParams(href: string): string {
	if (!browser || !href) return href;
	if (href === '#' || href.startsWith('mailto:') || href.startsWith('tel:')) return href;

	let url: URL;
	try {
		url = new URL(href, window.location.href);
	} catch {
		return href;
	}

	const cluid = readCookie(CL_UID_COOKIE) || window.CLabsgbVar?.generalProps?.uid || '';
	if (cluid) {
		url.searchParams.set('cluid', String(cluid));
	}

	const utms = parseUtmParamsCookie(readCookie(CL_UTM_COOKIE));
	if (utms && isFacebookUtmSource(utms)) {
		for (const [key, val] of Object.entries(utms)) {
			if (!val) continue;
			if (key.toLowerCase().startsWith('utm_')) {
				url.searchParams.set(key, val);
			}
		}
		url.searchParams.set('utm_medium', '.alyveresearch.com');
	}

	return url.href;
}

/** Bridge rewrite + CustomerLabs cluid / Facebook UTM stamping. */
export function bridgeAwareHrefWithClTracking(href: string): string {
	return withClTrackingParams(bridgeAwareHref(href));
}

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

/**
 * On bridge hosts (alyveresearch.com), load main-store media same-origin.
 * Absolute `https://alyvepeptides.com/wp-content/...` URLs often break there
 * (hotlink protection / captcha edge). Relative `/wp-content/...` hits the
 * same public_html and works.
 */
export function bridgeAwareAssetUrl(url: string | null | undefined): string {
	if (!url) return '';
	const raw = String(url).trim();
	if (!raw) return '';
	if (!browser || !isBridgeDomain()) return raw;
	if (raw.startsWith('/') && !raw.startsWith('//')) return raw;
	if (raw.startsWith('data:') || raw.startsWith('blob:')) return raw;

	try {
		const absolute = new URL(raw, window.location.origin);
		const path = absolute.pathname;
		if (
			!path.startsWith('/wp-content/') &&
			!path.startsWith('/wp-includes/') &&
			!path.startsWith('/_app/')
		) {
			return raw;
		}

		const main = mainStorefrontOrigin();
		const mainHost = main ? normalizeHost(new URL(main).hostname) : '';
		const linkHost = normalizeHost(absolute.hostname);
		const currentHost = normalizeHost(window.location.hostname);
		if (linkHost === currentHost || (mainHost && linkHost === mainHost)) {
			return `${path}${absolute.search}${absolute.hash}`;
		}
	} catch {
		// keep original
	}
	return raw;
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

	const currentHost = normalizeHost(window.location.hostname);
	const mainHost = normalizeHost(new URL(main).hostname);
	const linkHost = normalizeHost(url.hostname);

	if (linkHost !== currentHost && linkHost !== mainHost) {
		return href;
	}

	if (linkHost === currentHost && isLocalBridgeLandingPath(url.pathname)) {
		return href;
	}

	if (linkHost === currentHost) {
		return withClTrackingParams(`${main.replace(/\/$/, '')}${url.pathname}${url.search}${url.hash}`);
	}

	return withClTrackingParams(url.href);
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

	const currentHost = normalizeHost(window.location.hostname);
	const mainHost = normalizeHost(new URL(main).hostname);
	const linkHost = normalizeHost(url.hostname);

	if (linkHost !== currentHost && linkHost !== mainHost) {
		return null;
	}

	if (linkHost === currentHost && isLocalBridgeLandingPath(url.pathname)) {
		return null;
	}

	const dest =
		linkHost === currentHost
			? `${main.replace(/\/$/, '')}${url.pathname}${url.search}${url.hash}`
			: url.href;

	return withClTrackingParams(dest);
}

/** Rewrite outbound landing CTAs so href inspect + click both carry cluid / UTMs. */
export function stampBridgeOutboundAnchors(root: ParentNode = document): void {
	if (!browser || !isBridgeDomain()) return;
	const anchors = root.querySelectorAll('a[href]');
	for (const node of anchors) {
		const anchor = node as HTMLAnchorElement;
		const href = anchor.getAttribute('href');
		if (!href) continue;
		const dest = shouldHandOffBridgeNavigation(href);
		if (dest) anchor.setAttribute('href', dest);
	}
}
