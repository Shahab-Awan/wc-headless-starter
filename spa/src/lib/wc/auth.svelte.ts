/**
 * Auth state store for the headless SPA.
 *
 * WP's REST API zeros out the current user when a request carries cookies
 * but no X-WP-Nonce (CSRF defense for cookie-authed writes). The SPA can't
 * mint that nonce cross-origin, so we route all auth checks through our
 * own `/wchs/v1/session` endpoint which validates the HMAC-signed
 * wordpress_logged_in_* cookie directly via wp_validate_auth_cookie.
 *
 * See wp/mu-plugins/headless-rest-endpoints.php for the server side.
 *
 * State is intentionally runtime-only. No localStorage — the browser's
 * cookie is the source of truth. Mirroring it would create a window
 * where the client believes it's logged in after the cookie has expired.
 */

import { StoreApiError } from './store-api';
import { config } from '$lib/config.svelte';

type SessionUser = {
	id: number;
	email: string;
	display_name: string;
	first_name: string;
	last_name: string;
	role: string;
};

type AuthState =
	| { status: 'unknown' }
	| { status: 'loading' }
	| { status: 'guest' }
	| { status: 'authenticated'; user: SessionUser; emailVerified: boolean; fetchedAt: number };

class AuthStore {
	state = $state<AuthState>({ status: 'unknown' });
	private inflight: Promise<void> | null = null;

	get isAuthenticated(): boolean {
		return this.state.status === 'authenticated';
	}

	get user(): SessionUser | null {
		return this.state.status === 'authenticated' ? this.state.user : null;
	}

	get isAdmin(): boolean {
		return this.state.status === 'authenticated' && this.state.user.role === 'administrator';
	}

	get isVerified(): boolean {
		if (this.state.status !== 'authenticated') return false;
		return this.state.emailVerified;
	}

	/**
	 * Fetch current session state. Returns the same inflight promise if
	 * called concurrently (coalescing) so rapid /account mounts don't
	 * hammer the endpoint. Call again with force=true to bypass the
	 * in-flight dedupe (used after login/logout events).
	 *
	 * IMPORTANT: Only the initial check (from 'unknown') transitions to
	 * 'loading'. Force refreshes keep the current state visible until the
	 * new response arrives. Setting 'loading' on a force refresh would
	 * retrigger the layout's loading gate, which unmounts the page that
	 * called refresh, which remounts it, which calls refresh again — an
	 * infinite mount/unmount loop.
	 */
	async refresh(force = false): Promise<void> {
		if (this.inflight && !force) return this.inflight;
		if (force) this.inflight = null;

		// Only show the loading gate for the initial boot check.
		// Force refreshes (tab focus, /account mount) update silently.
		if (this.state.status === 'unknown') {
			this.state = { status: 'loading' };
		}
		const run = async () => {
			try {
				await config.load();
				const url = config.wpUrl('/wp-json/wchs/v1/session');
				const ac = new AbortController();
				const timer = setTimeout(() => ac.abort(), 10000);
				const res = await fetch(url, {
					method: 'GET',
					credentials: 'include',
					headers: { Accept: 'application/json' },
					signal: ac.signal,
				});
				clearTimeout(timer);
				if (!res.ok) {
					// 429, 5xx — resolve as guest. The gate must not hang.
					// If this was a transient error for an authenticated user,
					// they'll re-auth on the next navigation or refresh.
					this.state = { status: 'guest' };
					return;
				}
				const body = (await res.json()) as
					| { authenticated: false }
					| { authenticated: true; email_verified: boolean; user: SessionUser; server_time: number };
				if (body.authenticated) {
					this.state = {
						status: 'authenticated',
						user: body.user,
						emailVerified: body.email_verified !== false,
						fetchedAt: Date.now()
					};
				} else {
					this.state = { status: 'guest' };
				}
			} catch (err) {
				// Network error — resolve as guest so the gate unblocks.
				// If network recovers, user can refresh to re-authenticate.
				this.state = { status: 'guest' };
			} finally {
				this.inflight = null;
			}
		};
		this.inflight = run();
		return this.inflight;
	}

	/**
	 * Log out. Calls DELETE /wchs/v1/session which clears WP auth cookies
	 * server-side. We then flip local state to guest regardless of the
	 * response so the UI updates even if the network call 500'd (the
	 * cookie either cleared or will expire naturally).
	 */
	async logout(): Promise<void> {
		try {
			await config.load();
			const url = config.wpUrl('/wp-json/wchs/v1/session');
			await fetch(url, {
				method: 'DELETE',
				credentials: 'include',
				headers: { Accept: 'application/json' }
			});
		} catch {
			// ignore — we clear local state regardless
		}
		this.state = { status: 'guest' };
	}
}

export const auth = new AuthStore();
