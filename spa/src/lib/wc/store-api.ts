/**
 * WooCommerce Store API client.
 *
 * All calls go through /wp/wp-json/wc/store/v1/* which is proxied by Vite
 * to http://localhost:8099. In prod, point WCHS_WP_ORIGIN at your WP host.
 *
 * Responsibilities:
 *   - capture the Cart-Token header on every response and persist it
 *   - capture the Nonce header and send it on writes
 *   - send credentials: 'include' so the logged-in cookie flows (same-origin via proxy)
 *   - throw structured errors the UI can render
 *
 * This client is intentionally thin. Feature code (cart, products, auth)
 * wraps it and exposes typed helpers.
 */

import { isCaptchaChallenge, handleCaptchaChallenge } from '$lib/siteground-captcha';

const BASE = '/wp-json/wc/store/v1';

const CART_TOKEN_KEY = 'wchs_cart_token';
const NONCE_KEY = 'wchs_store_nonce';

// In-memory cache of tokens. SessionStorage is the persistence layer, but
// during a single page life we prefer the in-memory value (avoids the cost
// of reading storage on every request).
let cartTokenCache: string | null = null;
let nonceCache: string | null = null;

function loadCartToken(): string | null {
	if (cartTokenCache) return cartTokenCache;
	try {
		if (typeof sessionStorage !== 'undefined') {
			cartTokenCache = sessionStorage.getItem(CART_TOKEN_KEY);
		}
	} catch {
		// sessionStorage disabled (private browsing, security policy, etc.)
	}
	return cartTokenCache;
}

function saveCartToken(token: string) {
	cartTokenCache = token;
	try {
		if (typeof sessionStorage !== 'undefined') {
			sessionStorage.setItem(CART_TOKEN_KEY, token);
		}
	} catch {
		// sessionStorage disabled
	}
}

function loadNonce(): string | null {
	if (nonceCache) return nonceCache;
	try {
		if (typeof sessionStorage !== 'undefined') {
			nonceCache = sessionStorage.getItem(NONCE_KEY);
		}
	} catch {
		// sessionStorage disabled
	}
	return nonceCache;
}

function saveNonce(nonce: string) {
	nonceCache = nonce;
	try {
		if (typeof sessionStorage !== 'undefined') {
			sessionStorage.setItem(NONCE_KEY, nonce);
		}
	} catch {
		// sessionStorage disabled
	}
}

export function currentCartToken(): string | null {
	return loadCartToken();
}

export function clearCartToken() {
	cartTokenCache = null;
	try {
		if (typeof sessionStorage !== 'undefined') {
			sessionStorage.removeItem(CART_TOKEN_KEY);
		}
	} catch {
		// sessionStorage disabled
	}
}

export class StoreApiError extends Error {
	status: number;
	code?: string;
	data?: unknown;
	constructor(message: string, status: number, code?: string, data?: unknown) {
		super(message);
		this.status = status;
		this.code = code;
		this.data = data;
	}
}

type RequestOptions = {
	method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
	body?: unknown;
	query?: Record<string, string | number | boolean | undefined>;
	/**
	 * Skip sending the Cart-Token / Nonce headers. Used by endpoints that
	 * have their own auth story (e.g. /order/{id}?key=&billing_email= where
	 * the key+email IS the auth, and sending a cart token confuses WC).
	 */
	skipCartToken?: boolean;
};

export async function request<T>(path: string, opts: RequestOptions = {}): Promise<T> {
	const method = opts.method ?? 'GET';

	let url = `${BASE}${path}`;
	const qs = new URLSearchParams();
	if (opts.query) {
		for (const [k, v] of Object.entries(opts.query)) {
			if (v !== undefined) qs.set(k, String(v));
		}
	}
	if (method === 'GET') {
		// SiteGround's dynamic cache can hold onto pre-cutover Store API payloads
		// even after home/siteurl are corrected. A per-request query bust keeps the
		// storefront from rendering stale product/media origins.
		qs.set('__wchs_bust', Date.now().toString(36));
	}
	const s = qs.toString();
	if (s) {
		url += `?${s}`;
	}

	const headers: Record<string, string> = {
		Accept: 'application/json'
	};
	if (opts.body !== undefined) {
		headers['Content-Type'] = 'application/json';
	}

	if (!opts.skipCartToken) {
		const cartToken = loadCartToken();
		if (cartToken) headers['Cart-Token'] = cartToken;

		const nonce = loadNonce();
		// WC Store API accepts the nonce via either header name depending on version.
		if (nonce && method !== 'GET') {
			headers['Nonce'] = nonce;
			headers['X-WC-Store-API-Nonce'] = nonce;
		}
	}

	const res = await fetch(url, {
		method,
		headers,
		body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
		credentials: 'include'
	});

	if (isCaptchaChallenge(res)) {
		if (handleCaptchaChallenge()) {
			await new Promise(() => {});
		}
		throw new StoreApiError('Security challenge — please refresh the page.', 0, 'sg_captcha', null);
	}

	// Capture any new tokens regardless of status — WC sends them on errors too.
	const newCartToken = res.headers.get('Cart-Token');
	if (newCartToken) saveCartToken(newCartToken);

	const newNonce = res.headers.get('Nonce') ?? res.headers.get('X-WC-Store-API-Nonce');
	if (newNonce) saveNonce(newNonce);

	if (!res.ok) {
		let body: unknown = undefined;
		try {
			body = await res.json();
		} catch {
			// non-JSON error body; ignore
		}
		const message =
			(body && typeof body === 'object' && 'message' in body && typeof (body as any).message === 'string'
				? (body as any).message
				: res.statusText) ?? `HTTP ${res.status}`;
		const code =
			body && typeof body === 'object' && 'code' in body ? String((body as any).code) : undefined;

		// If the server tells us access mode changed (503 = maintenance,
		// 403 with access_mode in body = locked/browse-only), refresh
		// config so the SPA's in-memory mode catches up. The layout's
		// reactive expression will then show the correct screen without
		// a hard refresh. Fire-and-forget — don't block the error throw.
		if (res.status === 503 || (res.status === 403 && code?.startsWith('wchs_'))) {
			import('$lib/config.svelte').then(({ config }) => config.refresh()).catch(() => {});
		}

		throw new StoreApiError(message, res.status, code, body);
	}

	if (res.status === 204) return undefined as T;
	return (await res.json()) as T;
}

/**
 * Call this once on SPA boot. A GET /cart primes the cart-token and nonce
 * caches for subsequent writes, and detects the logged-in customer.
 */
export async function primeSession(): Promise<unknown> {
	return request('/cart', { method: 'GET' });
}
