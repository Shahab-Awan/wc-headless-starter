// Adversarial test for the server-side cart lock.
//
// Fires N parallel raw fetches at /wc/store/v1/cart/add-item bypassing
// the SPA mutex entirely. Without the lock, the WC session handler's
// read-modify-write race loses items. With the lock (GET_LOCK), all N
// items land in the cart.
//
// Run: node tests/security/cart-lock-race.js
// Exits non-zero on failure.

const path = require('path');
const { chromium } = require('../playwright');

const WP = 'http://localhost:8099';
const N = parseInt(process.env.WCHS_RACE_N || '10', 10);

function assert(label, cond, detail = '') {
	const ok = !!cond;
	const prefix = ok ? '✓' : '✗';
	console.log(`  ${prefix} ${label}${detail ? ' — ' + detail : ''}`);
	return ok;
}

async function run() {
	// Use Playwright only because it has fetch built into a browser-ish
	// environment. We could also use raw node-fetch, but Playwright gives
	// us cookie handling.
	const browser = await chromium.launch({ headless: true });
	const ctx = await browser.newContext();
	const page = await ctx.newPage();
	// Navigate somewhere so fetch() has a same-origin cookie jar.
	await page.goto(`${WP}/wp-login.php`, { waitUntil: 'domcontentloaded' });

	// 1. Prime a Store API session + grab token
	const { token, nonce } = await page.evaluate(async (wp) => {
		const res = await fetch(`${wp}/wp-json/wc/store/v1/cart`, { credentials: 'include' });
		return {
			token: res.headers.get('Cart-Token'),
			nonce: res.headers.get('Nonce') || res.headers.get('X-WC-Store-API-Nonce')
		};
	}, WP);
	console.log(`primed session, token: ${token.slice(0, 30)}...`);

	// 2. Pick N distinct product IDs to add. We cycle through the seed
	//    products so the test doesn't depend on having exactly N.
	const productIds = await page.evaluate(async (wp) => {
		const r = await fetch(`${wp}/wp-json/wc/store/v1/products?per_page=40`);
		const data = await r.json();
		return data.filter((p) => p.is_purchasable && p.is_in_stock && !p.has_options).map((p) => p.id);
	}, WP);

	if (productIds.length < 2) {
		console.log(`need at least 2 simple products, have ${productIds.length}`);
		await browser.close();
		process.exit(2);
	}

	// Use N distinct product ids — cycle if we don't have enough. Using
	// distinct ids means each add-item creates a new line (not increments
	// an existing one), which makes the race more visible.
	const toAdd = [];
	for (let i = 0; i < N; i++) {
		toAdd.push(productIds[i % productIds.length]);
	}
	console.log(`will add ${N} items cycling through ${productIds.length} products: ${productIds.slice(0, 5).join(',')}…`);

	// 3. Fire N POSTs in parallel — no awaits between dispatch
	const results = await page.evaluate(
		async ({ wp, token, nonce, toAdd }) => {
			const calls = toAdd.map((id) =>
				fetch(`${wp}/wp-json/wc/store/v1/cart/add-item`, {
					method: 'POST',
					credentials: 'include',
					headers: {
						'Content-Type': 'application/json',
						'Cart-Token': token,
						'Nonce': nonce,
						'X-WC-Store-API-Nonce': nonce
					},
					body: JSON.stringify({ id, quantity: 1 })
				}).then(async (r) => ({
					status: r.status,
					items_count: r.ok ? (await r.json()).items_count : null
				}))
			);
			const responses = await Promise.all(calls);
			// Final authoritative state — GET /cart with the same token
			const finalRes = await fetch(`${wp}/wp-json/wc/store/v1/cart`, {
				credentials: 'include',
				headers: { 'Cart-Token': token }
			});
			const final = await finalRes.json();
			return { responses, final_count: final.items_count, final_items: final.items.length };
		},
		{ wp: WP, token, nonce, toAdd }
	);

	console.log(`responses: ${results.responses.map((r) => `${r.status}/${r.items_count}`).join(', ')}`);
	console.log(`final items_count=${results.final_count} items.length=${results.final_items}`);

	// Count how many of N succeeded (201) vs were locked out (409)
	const ok = results.responses.filter((r) => r.status === 201 || r.status === 200).length;
	const locked = results.responses.filter((r) => r.status === 409).length;
	const other = results.responses.filter((r) => r.status !== 201 && r.status !== 200 && r.status !== 409).length;

	console.log(`${ok} succeeded, ${locked} returned 409 (lock timeout), ${other} other statuses`);

	// The critical assertion: the server state (final GET) shows exactly
	// as many items as successful adds. With the lock, they serialize
	// correctly. Without the lock, the races clobber each other and the
	// count is usually much less than `ok`.
	//
	// In an expected-correct run: ok === N, locked === 0, final_count === N
	const a1 = assert('no unexpected error responses', other === 0, `${other} unexpected`);
	const a2 = assert('final cart count matches successful adds', results.final_count === ok, `final=${results.final_count} ok=${ok}`);
	// Stronger assertion: at least 8 of 10 should succeed given the 5s
	// timeout. If more than 2 return 409, either the timeout is too
	// short or the lock is held too long (a performance bug).
	const a3 = assert('at least 80% of parallel adds succeeded', ok >= Math.floor(N * 0.8), `${ok}/${N}`);

	await browser.close();

	const passed = [a1, a2, a3].filter(Boolean).length;
	console.log(`\n${passed}/3 assertions passed`);
	process.exit(passed === 3 ? 0 : 1);
}

run().catch((e) => {
	console.error(e);
	process.exit(1);
});
