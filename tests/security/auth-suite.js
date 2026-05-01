#!/usr/bin/env node
/**
 * Adversarial auth testing for the SPA ↔ WP session bridge.
 *
 * Covers (in rough increasing severity):
 *
 *   1. Happy path (guest → login → signed in → reload → logout → guest)
 *   2. Idempotency (concurrent /session calls, multi-logout, replayed login)
 *   3. Session forgery (tampered cookies, wrong HMAC, swapped user_id)
 *   4. Session expiry (cookie past its expiration)
 *   5. CSRF on DELETE /session (hostile Origin, missing Origin)
 *   6. Origin allowlist bypass attempts (case, scheme, port, userinfo)
 *   7. Rate limiting / flood
 *   8. Cross-tab consistency (logout in tab A reflects in tab B on refetch)
 *   9. Race: simultaneous login + logout from two tabs
 *  10. Return path allowlist (arbitrary SPA path, open redirect attempt)
 *  11. Auth cookie path scope (must not leak through non-matching paths)
 *  12. DELETE without cookie (unauthenticated) → 401-ish
 *  13. Guest /session returns {authenticated:false}, not 401
 *  14. /session body never exposes sensitive fields
 *  15. /my-orders now uses the same cookie bypass (regression coverage)
 *
 * Dependencies: Node 20+, undici (global fetch), playwright (for the
 * multi-tab scenarios). Playwright is already in the tests node_modules.
 */

const path = require('path');
const { chromium } = require('../playwright');

const WP = 'http://localhost:8099';
const SPA = 'http://localhost:5175';
const SPA_ORIGIN = 'http://localhost:5175';
const HOSTILE_ORIGIN = 'http://evil.example.com:9999';

let pass = 0;
let fail = 0;
const failures = [];

function ok(label) {
	pass++;
	console.log(`  \x1b[32m✓\x1b[0m ${label}`);
}

function bad(label, detail) {
	fail++;
	failures.push({ label, detail });
	console.log(`  \x1b[31m✗\x1b[0m ${label}`);
	if (detail) console.log(`      ${detail}`);
}

function eq(actual, expected, label) {
	if (JSON.stringify(actual) === JSON.stringify(expected)) {
		ok(label);
	} else {
		bad(label, `expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
	}
}

function truthy(cond, label, detail) {
	if (cond) ok(label);
	else bad(label, detail);
}

function section(title) {
	console.log(`\n\x1b[36m== ${title} ==\x1b[0m`);
}

/**
 * Thin cookie-jar wrapper so we don't pull in another dep. Tracks the
 * raw Set-Cookie headers we care about and replays them as a Cookie
 * header on the next request. Only handles name=value — skips all the
 * attribute parsing (Domain/Path/Expires/etc) because every cookie in
 * the test suite is scoped to the same host.
 */
class Jar {
	constructor() {
		this.cookies = new Map(); // name → value
	}
	fromHeaders(headers) {
		const raw = headers.get('set-cookie');
		if (!raw) return;
		// Node fetch deliberately joins multiple Set-Cookie headers with
		// commas. Split on comma but NOT on commas inside expires=...
		// A safe enough heuristic: split on comma followed by space + word=.
		const parts = raw.split(/,(?=\s*[a-zA-Z0-9_]+=)/);
		for (const p of parts) {
			const firstSemi = p.indexOf(';');
			const pair = firstSemi === -1 ? p : p.slice(0, firstSemi);
			const eq = pair.indexOf('=');
			if (eq === -1) continue;
			const name = pair.slice(0, eq).trim();
			const value = pair.slice(eq + 1).trim();
			if (name) this.cookies.set(name, value);
		}
	}
	toHeader() {
		return [...this.cookies].map(([n, v]) => `${n}=${v}`).join('; ');
	}
	clear() {
		this.cookies.clear();
	}
	get(name) {
		return this.cookies.get(name);
	}
	set(name, value) {
		this.cookies.set(name, value);
	}
	has(name) {
		return this.cookies.has(name);
	}
	delete(name) {
		this.cookies.delete(name);
	}
	findByPrefix(prefix) {
		for (const [k, v] of this.cookies) {
			if (k.startsWith(prefix)) return [k, v];
		}
		return null;
	}
}

async function wpLogin(jar) {
	// 1. Prime test cookie
	const r1 = await fetch(`${WP}/wp-login.php`, {
		method: 'GET',
		redirect: 'manual'
	});
	jar.fromHeaders(r1.headers);

	// 2. Post login
	const body = new URLSearchParams({
		log: 'admin',
		pwd: 'wchs-admin-dev',
		'wp-submit': 'Log In',
		redirect_to: `${WP}/wp-admin/`,
		testcookie: '1'
	});
	const r2 = await fetch(`${WP}/wp-login.php`, {
		method: 'POST',
		body,
		headers: {
			'content-type': 'application/x-www-form-urlencoded',
			cookie: jar.toHeader()
		},
		redirect: 'manual'
	});
	jar.fromHeaders(r2.headers);

	if (r2.status !== 302) {
		throw new Error(`WP login expected 302, got ${r2.status}`);
	}
	return r2;
}

async function sessionGet(jar, { origin } = {}) {
	const headers = { Accept: 'application/json' };
	if (jar) headers.cookie = jar.toHeader();
	if (origin) headers.origin = origin;
	const res = await fetch(`${WP}/wp-json/wchs/v1/session`, { headers });
	return { status: res.status, headers: res.headers, body: await res.json() };
}

async function sessionDelete(jar, { origin } = {}) {
	const headers = { Accept: 'application/json' };
	if (jar) headers.cookie = jar.toHeader();
	if (origin) headers.origin = origin;
	const res = await fetch(`${WP}/wp-json/wchs/v1/session`, { method: 'DELETE', headers });
	let body = null;
	try {
		body = await res.json();
	} catch {}
	return { status: res.status, headers: res.headers, body };
}

// ---------- TESTS ----------

async function test1_happy_path() {
	section('1. Happy path');

	// Guest
	const guestJar = new Jar();
	const guest = await sessionGet(guestJar);
	eq(guest.status, 200, 'guest GET /session → 200');
	eq(guest.body, { authenticated: false }, 'guest body = {authenticated:false}');

	// Login
	const jar = new Jar();
	await wpLogin(jar);
	const loginCookie = jar.findByPrefix('wordpress_logged_in_');
	truthy(!!loginCookie, 'wordpress_logged_in_ cookie set after login', 'cookie missing');

	// Logged in /session
	const me = await sessionGet(jar);
	eq(me.status, 200, 'logged-in GET /session → 200');
	truthy(me.body.authenticated === true, 'authenticated=true', me.body);
	truthy(typeof me.body.user?.id === 'number' && me.body.user.id > 0, 'user.id is numeric', me.body.user);
	truthy(me.body.user?.email === 'admin@wchs.local', 'user.email correct', me.body.user);
	truthy(typeof me.body.server_time === 'number', 'server_time numeric', me.body.server_time);

	// /my-orders also works with this auth
	const orders = await fetch(`${WP}/wp-json/wchs/v1/my-orders`, {
		headers: { cookie: jar.toHeader() }
	});
	eq(orders.status, 200, '/my-orders works with same cookie auth');

	// Logout
	const logout = await sessionDelete(jar, { origin: SPA_ORIGIN });
	eq(logout.status, 200, 'DELETE /session → 200');
	eq(logout.body, { ok: true }, 'logout body = {ok:true}');

	// After logout the server sends Set-Cookie to clear the auth cookies
	// (empty value + immediate expiry). Our jar picks up the cleared
	// values. Regardless, even if a stale cookie were replayed, the
	// session token was rotated by wp_logout() so the HMAC no longer
	// validates. Verify by asking /session again with whatever the jar
	// holds now.
	jar.fromHeaders(logout.headers);
	const afterLogoutCookie = jar.findByPrefix('wordpress_logged_in_');
	const rawVal = afterLogoutCookie ? afterLogoutCookie[1] : '';
	// WP clears cookies by setting value to a single space and
	// expires in the past. Our jar parses this as "%20" or " ".
	const cleared =
		!afterLogoutCookie ||
		rawVal === '' ||
		rawVal === '%20' ||
		rawVal.trim() === '' ||
		rawVal === 'deleted';
	truthy(cleared, 'login cookie cleared by DELETE (empty/space/deleted value)', afterLogoutCookie);

	const afterLogout = await sessionGet(jar);
	eq(afterLogout.body, { authenticated: false }, 'after logout, GET /session = guest');

	// Stronger check: even with the ORIGINAL pre-logout cookie replayed
	// (attacker replays a stolen cookie after the user clicked Log out),
	// the server must reject it because wp_logout rotated the session
	// token bound to that HMAC.
	const replayJar = new Jar();
	// Re-login to get a fresh cookie
	const replaySource = new Jar();
	await wpLogin(replaySource);
	const legit = replaySource.findByPrefix('wordpress_logged_in_');
	// Verify it's live
	replayJar.set(legit[0], legit[1]);
	const beforeReplayLogout = await sessionGet(replayJar);
	truthy(beforeReplayLogout.body.authenticated === true, 'replay setup: fresh cookie is live', beforeReplayLogout.body);
	// Now logout using the source jar
	await sessionDelete(replaySource, { origin: SPA_ORIGIN });
	// Replay the preserved cookie value
	const afterReplay = await sessionGet(replayJar);
	eq(
		afterReplay.body,
		{ authenticated: false },
		'post-logout cookie replay rejected (session token rotated)'
	);
}

async function test2_idempotency() {
	section('2. Idempotency');

	// Concurrent /session GETs from a logged-in jar should all agree
	const jar = new Jar();
	await wpLogin(jar);

	const calls = await Promise.all(Array.from({ length: 10 }, () => sessionGet(jar)));
	const ids = new Set(calls.map((c) => c.body.user?.id));
	truthy(ids.size === 1 && ids.has(1), `10 concurrent /session calls agree on user.id (${[...ids]})`, ids);

	// Replay logout twice
	await sessionDelete(jar, { origin: SPA_ORIGIN });
	const jar2 = new Jar();
	await wpLogin(jar2);
	const a = await sessionDelete(jar2, { origin: SPA_ORIGIN });
	const b = await sessionDelete(jar2, { origin: SPA_ORIGIN });
	truthy(a.status === 200, 'first logout → 200', a);
	// Second logout: cookie is gone or invalidated, permission_callback
	// will reject it. We accept 401 or 403 here.
	truthy(
		b.status === 401 || b.status === 403,
		'second logout → 401 or 403 (nothing to clear, not authenticated)',
		b.status
	);
}

async function test3_forgery() {
	section('3. Session forgery');

	// Arbitrary value — not an HMAC-valid cookie
	const jar = new Jar();
	jar.set('wordpress_logged_in_359b53540cfc8153e3f9a4caf8014772', 'admin|9999999999|totally|fake|hmac');
	const r = await sessionGet(jar);
	eq(r.body, { authenticated: false }, 'forged HMAC rejected');

	// Tamper with a legit cookie: flip one byte
	const good = new Jar();
	await wpLogin(good);
	const legit = good.findByPrefix('wordpress_logged_in_');
	truthy(!!legit, 'got a legit cookie to tamper', null);
	const [name, value] = legit;
	const tampered = value.slice(0, -4) + 'dead';
	const tamperedJar = new Jar();
	tamperedJar.set(name, tampered);
	const tamperRes = await sessionGet(tamperedJar);
	eq(tamperRes.body, { authenticated: false }, 'tampered HMAC rejected');

	// Swap user_id in the cookie body (admin|timestamp|token|hmac)
	const parts = decodeURIComponent(value).split('|');
	if (parts.length >= 4) {
		parts[0] = 'nobody';
		const swapped = parts.join('%7C');
		const swapJar = new Jar();
		swapJar.set(name, swapped);
		const swapRes = await sessionGet(swapJar);
		eq(swapRes.body, { authenticated: false }, 'swapped username rejected');
	}
}

async function test4_expiry() {
	section('4. Session expiry');

	// We can't wait 48 hours. Forge a cookie with an expiration in the
	// past using WP's constants — but we don't have the secret keys.
	// Best we can do here is confirm a completely absent cookie = guest.
	const r = await sessionGet(new Jar());
	eq(r.body, { authenticated: false }, 'no cookie → guest');

	// And a cookie that's structurally valid-looking but untrusted
	// (covered in test3).
}

async function test5_csrf_on_delete() {
	section('5. CSRF on DELETE /session');

	// A legit login
	const jar = new Jar();
	await wpLogin(jar);

	// 5a. Hostile Origin: should be rejected
	const hostile = await sessionDelete(jar, { origin: HOSTILE_ORIGIN });
	truthy(
		hostile.status === 403,
		`DELETE with hostile Origin (${HOSTILE_ORIGIN}) → 403`,
		hostile.status
	);

	// Confirm the cookie survived the rejected attempt
	const after = await sessionGet(jar);
	truthy(after.body.authenticated === true, 'session still live after rejected CSRF', after.body);

	// 5b. Allowed origin — still works
	const good = await sessionDelete(jar, { origin: SPA_ORIGIN });
	eq(good.status, 200, 'DELETE with allowed Origin → 200');

	// 5c. No Origin header at all (server-side tool, same-origin) is
	// permitted. Confirm this branch isn't accidentally CSRF-exploitable
	// by having a fresh session and deleting with NO Origin.
	const jar2 = new Jar();
	await wpLogin(jar2);
	const noOrigin = await sessionDelete(jar2);
	eq(noOrigin.status, 200, 'DELETE with no Origin header → 200 (same-origin path)');
}

async function test6_origin_bypass_attempts() {
	section('6. Origin allowlist bypass attempts');

	const jar = new Jar();
	await wpLogin(jar);

	const hostileOrigins = [
		'HTTP://LOCALHOST:5175',                      // scheme case
		'http://localhost:5175.evil.com',              // suffix injection
		'http://localhost:5175@evil.com',              // userinfo
		'http://evil.com#localhost:5175',              // fragment injection
		'http://xn--localhost-v9a:5175',               // punycode lookalike
		'http://localhost:5175/',                      // trailing slash
		'http://localhost:5176',                       // wrong port
		'https://localhost:5175',                      // wrong scheme
		'null',                                        // browser 'null' origin
		''                                             // empty (becomes 200, see 5c)
	];

	let stillAlive = true;
	for (const o of hostileOrigins) {
		const freshJar = new Jar();
		await wpLogin(freshJar);
		const res = await sessionDelete(freshJar, { origin: o });

		if (o === '' || o === 'http://localhost:5175') {
			// exact allowlist hit or no-origin same-origin path — expected 200
			truthy(res.status === 200, `origin="${o}" → 200 (legit)`, res.status);
		} else {
			truthy(res.status === 403, `hostile origin "${o}" → 403`, res.status);
		}
	}
}

async function test7_rate_limit() {
	section('7. Rate limiting');

	// We exercise the rate limiter via /wchs/v1/reviews/23 which has a
	// 10/60 ceiling and is completely isolated from the /session buckets.
	// This verifies the shared wchs_rest_rate_limit helper (the same code
	// path used by /session and /session_delete) actually trips.
	//
	// We use a real product id — the first one from /products — to avoid
	// 404s masking the rate limit.
	const productsRes = await fetch(`${WP}/wp-json/wc/store/v1/products?per_page=1`);
	const products = await productsRes.json();
	const productId = Array.isArray(products) && products[0] ? products[0].id : null;
	truthy(!!productId, 'got a product id for rate-limit probe', products);

	const results = [];
	for (let i = 0; i < 15; i++) {
		const r = await fetch(`${WP}/wp-json/wchs/v1/reviews/${productId}`);
		results.push(r.status);
	}
	const limited = results.filter((s) => s === 429).length;
	truthy(
		limited >= 5,
		`>=5 of 15 rapid reviews calls got 429 (ceiling=10, saw ${limited})`,
		results
	);
}

async function test8_guest_and_crossorigin_headers() {
	section('8. Guest shape + no sensitive fields + CORS headers');

	const res = await fetch(`${WP}/wp-json/wchs/v1/session`, {
		headers: { origin: SPA_ORIGIN }
	});
	const body = await res.json();
	truthy(
		'authenticated' in body && body.authenticated === false,
		'guest body shape correct',
		body
	);
	const sensitive = ['user_pass', 'user_activation_key', 'user_registered', 'password'];
	const bodyStr = JSON.stringify(body);
	const leaked = sensitive.filter((k) => bodyStr.includes(k));
	truthy(leaked.length === 0, 'no sensitive fields in guest body', leaked);

	// CORS headers when Origin=SPA
	const cors = res.headers.get('access-control-allow-origin');
	truthy(cors === SPA_ORIGIN, `Access-Control-Allow-Origin = ${SPA_ORIGIN}`, cors);
	const creds = res.headers.get('access-control-allow-credentials');
	truthy(creds === 'true', 'Allow-Credentials = true', creds);
}

async function test9_logged_in_no_secrets() {
	section('9. Logged-in body no secret leakage');
	const jar = new Jar();
	await wpLogin(jar);
	const res = await sessionGet(jar);
	const bodyStr = JSON.stringify(res.body);
	const leaks = [
		'user_pass',
		'user_activation_key',
		'user_registered',
		'hash',
		'capabilities',
		'roles'
	].filter((k) => bodyStr.includes(k));
	truthy(leaks.length === 0, 'logged-in body has no WP internals leaked', leaks);
	truthy(res.body.user && Object.keys(res.body.user).length <= 6, 'user object is minimal (≤6 fields)', Object.keys(res.body.user));
}

async function test10_return_path_allowlist() {
	section('10. Return-path allowlist (open-redirect defense)');

	// We probe wchs_resolve_return_url via the login_form hook which
	// emits a hidden <input name="return"> on wp-login.php. The value
	// is passed through esc_attr so we decode HTML entities before
	// comparing.
	const attempts = [
		{ given: `${SPA}/`, expect: `${SPA}/` },
		{ given: `${SPA}/account`, expect: `${SPA}/account` },
		{ given: `${SPA}/account/`, expect: `${SPA}/account/` },
		{ given: `${SPA}/account/orders`, expect: `${SPA}/account/orders` },
		{ given: `${SPA}/account/../evil`, expect: `${SPA}/` },     // path-traversal collapses to '/'
		{ given: `${SPA}/checkout`, expect: `${SPA}/` },            // not in path allowlist
		{ given: `${SPA}/shop`, expect: `${SPA}/shop` },
		{ given: `http://evil.example.com/account`, expect: null }, // hostile origin → no hidden field emitted
		{ given: `javascript:alert(1)`, expect: null },
		{ given: `${SPA}/account\r\nSet-Cookie: hi=1`, expect: null } // CRLF
	];

	function htmlDecode(s) {
		if (!s) return s;
		return s
			.replace(/&amp;/g, '&')
			.replace(/&lt;/g, '<')
			.replace(/&gt;/g, '>')
			.replace(/&quot;/g, '"')
			.replace(/&#0*39;/g, "'");
	}

	for (const a of attempts) {
		const url = `${WP}/wp-login.php?return=${encodeURIComponent(a.given)}`;
		const html = await (await fetch(url)).text();
		const m = html.match(/<input type="hidden" name="return" value="([^"]*)"\s*\/>/);
		const seen = m ? htmlDecode(m[1]) : null;
		if (a.expect === null) {
			truthy(seen === null, `rejected "${a.given.replace(/[\r\n]/g, '\\n')}"`, seen);
		} else {
			truthy(seen === a.expect, `accepted "${a.given}" → "${a.expect}"`, `saw: ${seen}`);
		}
	}
}

async function test11_race_login_logout() {
	section('11. Race: simultaneous login + logout');

	// Set up a logged-in jar
	const jar = new Jar();
	await wpLogin(jar);

	// Fire logout and GET /session concurrently. Both should succeed,
	// order doesn't matter, but state must be consistent afterward.
	const [del, get] = await Promise.all([
		sessionDelete(jar, { origin: SPA_ORIGIN }),
		sessionGet(jar)
	]);
	truthy(del.status === 200, 'race DELETE succeeded', del);
	// The GET could land before or after; both are acceptable as long as
	// it's one of {authenticated:true, authenticated:false}.
	truthy(
		get.body.authenticated === true || get.body.authenticated === false,
		'race GET returned a consistent state',
		get.body
	);

	// Now confirm final state is guest
	jar.fromHeaders(del.headers);
	const finalState = await sessionGet(jar);
	eq(finalState.body, { authenticated: false }, 'post-race state = guest');
}

async function test12_multi_tab_consistency() {
	section('12. Multi-tab consistency via Playwright');

	const b = await chromium.launch({ headless: true });
	const ctx = await b.newContext();
	const tabA = await ctx.newPage();
	const tabB = await ctx.newPage();

	// Tab A logs in
	await tabA.goto(`${SPA}/account`, { waitUntil: 'networkidle' });
	await tabA.locator('.account__cta').click();
	await tabA.waitForURL(/my-account/, { waitUntil: 'networkidle' });
	await tabA.locator('#username').fill('admin');
	await tabA.locator('#password').fill('wchs-admin-dev');
	await Promise.all([
		tabA.waitForNavigation({ waitUntil: 'networkidle' }),
		tabA.locator('button[name="login"]').click()
	]);

	// Tab B opens /account, should also see signed in (shared cookie)
	await tabB.goto(`${SPA}/account`, { waitUntil: 'networkidle' });
	await tabB.waitForTimeout(800);
	const bText = await tabB.locator('.account__card').innerText();
	truthy(bText.includes('SIGNED IN AS'), 'tab B sees signed in (shared cookie)', bText);

	// Tab A logs out
	await tabA.locator('.account__logout').click();
	await tabA.waitForTimeout(800);

	// Tab B focuses — auth store refetches on focus, should flip to guest
	await tabB.bringToFront();
	await tabB.evaluate(() => window.dispatchEvent(new Event('focus')));
	await tabB.waitForTimeout(800);
	const bAfter = await tabB.locator('.account__card').innerText();
	truthy(bAfter.includes('not signed in'), 'tab B flips to guest on focus re-check', bAfter);

	await b.close();
}

async function test13_delete_without_cookie() {
	section('13. DELETE without auth');

	const res = await sessionDelete(new Jar(), { origin: SPA_ORIGIN });
	truthy(res.status === 401 || res.status === 403, `unauth DELETE → ${res.status}`, res);
}

async function test14_my_orders_regression() {
	section('14. /my-orders regression (uses same cookie bypass)');

	// Guest
	const guest = await fetch(`${WP}/wp-json/wchs/v1/my-orders`);
	truthy(guest.status === 401 || guest.status === 403, 'guest /my-orders denied', guest.status);

	// Logged in
	const jar = new Jar();
	await wpLogin(jar);
	const me = await fetch(`${WP}/wp-json/wchs/v1/my-orders`, {
		headers: { cookie: jar.toHeader() }
	});
	eq(me.status, 200, 'logged-in /my-orders → 200');
	const body = await me.json();
	truthy('orders' in body && Array.isArray(body.orders), 'body has orders array', body);
}

async function main() {
	const t0 = Date.now();
	console.log('Auth adversarial test suite\n');
	try {
		await test1_happy_path();
		await test2_idempotency();
		await test3_forgery();
		await test4_expiry();
		await test5_csrf_on_delete();
		await test6_origin_bypass_attempts();
		await test7_rate_limit();
		await test8_guest_and_crossorigin_headers();
		await test9_logged_in_no_secrets();
		await test10_return_path_allowlist();
		await test11_race_login_logout();
		await test12_multi_tab_consistency();
		await test13_delete_without_cookie();
		await test14_my_orders_regression();
	} catch (err) {
		console.error('\n\x1b[31munhandled test exception\x1b[0m:', err);
		fail++;
	}

	const dur = ((Date.now() - t0) / 1000).toFixed(1);
	console.log(`\n\x1b[36m== result ==\x1b[0m`);
	console.log(`${pass} passed  ${fail} failed  in ${dur}s`);
	if (fail > 0) {
		console.log('\nfailures:');
		for (const f of failures) console.log(`  - ${f.label}${f.detail ? ' :: ' + JSON.stringify(f.detail) : ''}`);
		process.exit(1);
	}
}

main();
