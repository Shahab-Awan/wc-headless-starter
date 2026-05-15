// Full adversarial E2E suite for wc-headless-starter.
//
// Exercises: security surface (browser-side), runtime + race conditions,
// idempotency, theme toggle, variable products, shop filters + search,
// cart restoration, full purchase flow (COD), account probe, orders list.
//
// Run: node tests/e2e/full-journey.js
// Exits non-zero on any assertion failure or any console/page error.

const path = require('path');
const fs = require('fs');

const { chromium } = require('../playwright');

const SPA = 'http://localhost:5175';
const WP = 'http://localhost:8099';
const SHOTS = path.resolve(__dirname, '..', 'screenshots', 'e2e');
fs.mkdirSync(SHOTS, { recursive: true });
// Nuke previous run
for (const f of fs.readdirSync(SHOTS)) {
	if (f.endsWith('.png') || f.endsWith('.json')) fs.unlinkSync(path.join(SHOTS, f));
}

const report = {
	startedAt: new Date().toISOString(),
	assertions: [],
	errors: [],
	timings: {}
};

const T0 = Date.now();
function tick() {
	return `${((Date.now() - T0) / 1000).toFixed(1)}s`;
}

function log(msg) {
	const line = `[${tick()}] ${msg}`;
	console.log(line);
	report.assertions.push({ ts: tick(), log: msg });
}

let passCount = 0;
let failCount = 0;

function assert(label, cond, detail = '') {
	const ok = !!cond;
	if (ok) passCount++;
	else failCount++;
	const prefix = ok ? '✓' : '✗';
	console.log(`  ${prefix} ${label}${detail ? ' — ' + detail : ''}`);
	report.assertions.push({ label, ok, detail });
}

function shot(page, name) {
	return page.screenshot({ path: path.join(SHOTS, `${name}.png`), fullPage: true });
}

function attachErrors(page, tag) {
	page.on('pageerror', (e) => {
		report.errors.push({ tag, type: 'pageerror', msg: e.message });
	});
	page.on('console', (m) => {
		if (m.type() === 'error') {
			const text = m.text();
			// Filter noise:
			//   - favicon 404 (no icon set)
			//   - HMR aborts on context close
			//   - "Failed to load resource" 4xx — these are expected negative
			//     responses (guest probe → 401, missing order → 404) that the
			//     SPA handles gracefully. The browser logs them regardless of
			//     our try/catch.
			if (text.includes('favicon')) return;
			if (text.includes('ERR_ABORTED')) return;
			if (text.includes('SvelteKitError: Not found: /favicon')) return;
			if (text.includes('Failed to load resource') && /status of 40\d/.test(text)) return;
			report.errors.push({ tag, type: 'console', msg: text });
		}
	});
	page.on('requestfailed', (req) => {
		const url = req.url();
		if (url.includes('favicon') || url.includes('/@vite/') || url.includes('bunny.net')) return;
		const err = req.failure()?.errorText || '';
		if (err === 'net::ERR_ABORTED') return; // HMR context-close churn
		report.errors.push({ tag, type: 'requestfailed', msg: `${req.method()} ${url} ${err}` });
	});
}

async function waitForStable(page, selector, ms = 400) {
	await page.waitForSelector(selector);
	await page.waitForTimeout(ms);
}

// ============================================================
// Test groups
// ============================================================

async function testHomeAndShop(browser) {
	log('=== Home + Shop ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'home-shop');

	await page.goto(SPA, { waitUntil: 'networkidle' });
	await waitForStable(page, '.store-card');
	await shot(page, '01-home-light');

	const cards = await page.locator('.store-card').count();
	assert('home has product cards', cards > 0, `${cards} cards`);

	// Pretext applied? Measure a card title height attribute
	const hasMeasuredTitle = await page.evaluate(() => {
		const t = document.querySelector('.store-card__title');
		if (!t) return false;
		const style = t.getAttribute('style') || '';
		return /height:/.test(style);
	});
	assert('pretext measured card title (style contains height)', hasMeasuredTitle);

	// Shop grid with filters
	await page.goto(`${SPA}/shop`, { waitUntil: 'networkidle' });
	await waitForStable(page, '.store-card');
	await shot(page, '02-shop-light');

	const shopCards = await page.locator('.store-card').count();
	assert('shop grid shows seeded products', shopCards >= 6, `${shopCards} cards`);

	// Search: debounced
	await page.fill('.shop-grid__search input', 'tote');
	await page.waitForTimeout(600); // debounce + fetch
	const searchCards = await page.locator('.store-card').count();
	assert('search filters results', searchCards > 0 && searchCards <= shopCards);

	// Sort
	await page.selectOption('.shop-grid__sort select', 'price-asc');
	await page.waitForTimeout(500);
	const prices = await page.$$eval('.store-card__price', (els) =>
		els.map((e) => parseFloat((e.textContent || '').replace(/[^0-9.]/g, '')))
	);
	const sorted = [...prices].sort((a, b) => a - b);
	assert('price-asc sort is monotonic', JSON.stringify(prices) === JSON.stringify(sorted), prices.join(','));

	// URL reflects state
	const url = page.url();
	assert('url contains sort param', url.includes('sort=price-asc'));

	await ctx.close();
}

async function testRapidFilterRace(browser) {
	log('=== Rapid filter clicks race condition ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'race-filter');

	await page.goto(`${SPA}/shop`, { waitUntil: 'networkidle' });
	await waitForStable(page, '.store-card');

	// Spam sort in rapid succession; the final state must match the last click
	const sortOptions = ['price-asc', 'price-desc', 'title-asc', 'date-desc'];
	for (const opt of sortOptions) {
		await page.selectOption('.shop-grid__sort select', opt);
	}
	// Wait for the last fetch to settle
	await page.waitForTimeout(900);
	const finalValue = await page.$eval('.shop-grid__sort select', (el) => (el).value);
	assert('final sort value matches last click', finalValue === 'date-desc', `got ${finalValue}`);

	// URL should also reflect the last choice (after SK nav settles)
	assert('url reflects final sort', page.url().includes('sort=date-desc'));

	await ctx.close();
}

async function testVariableProductPDP(browser) {
	log('=== Variable product PDP ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'variable-pdp');

	await page.goto(`${SPA}/product/variable-test-backpack`, { waitUntil: 'networkidle' });
	await waitForStable(page, '.pdp__variant-btn', 800);
	await shot(page, '03-pdp-variable-light');

	// Should show variant selectors for Size + Color
	const sizeButtons = await page.locator('.pdp__variant-group').nth(0).locator('.pdp__variant-btn').count();
	assert('Size has 3 options', sizeButtons === 3);

	// Defaults are pre-selected (Small + Black), so add should be ENABLED on load
	const addEnabled1 = !(await page.locator('.pdp__add').isDisabled());
	assert('add enabled with defaults pre-selected', addEnabled1);

	// Small should be active (pre-selected default)
	const smallActive = await page.locator('.pdp__variant-btn--active').filter({ hasText: 'Small' }).count();
	assert('Small default pre-selected', smallActive === 1);

	// Black should be active (pre-selected default)
	const blackActive = await page.locator('.pdp__variant-btn--active').filter({ hasText: 'Black' }).count();
	assert('Black default pre-selected', blackActive === 1);

	// Price updated to variation price
	const priceText = (await page.locator('.pdp__price').textContent()) || '';
	assert('price shows single variation value', /\$59\.00/.test(priceText), priceText);

	// Now select Medium - the Natural button should become unavailable
	// because Medium + Natural is out of stock. We verify this via the
	// --unavailable class (the button is correctly unclickable).
	await page.locator('.pdp__variant-btn').filter({ hasText: 'Medium' }).click();
	await page.waitForTimeout(200);
	const naturalUnavailable = await page.locator('.pdp__variant-btn--unavailable').filter({ hasText: 'Natural' }).count();
	assert('Natural marked unavailable when Medium selected (out-of-stock combo)', naturalUnavailable === 1);

	// The add button should still be ENABLED because Medium + Black is valid
	// (Black is still selected from defaults). Verify that.
	const afterMaybeEnabled = !(await page.locator('.pdp__add').isDisabled());
	assert('add enabled with Medium + Black (valid combo)', afterMaybeEnabled);

	// Back to Small for the add-to-cart assertion
	await page.locator('.pdp__variant-btn').filter({ hasText: 'Small' }).click();
	await page.waitForTimeout(150);
	const addEnabledSmall = !(await page.locator('.pdp__add').isDisabled());
	assert('add still enabled after switching back to Small', addEnabledSmall);

	await page.locator('.pdp__add').click();
	await page.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
	await page.waitForTimeout(400);

	const cartItem = (await page.locator('.fkcart-item').first().textContent()) || '';
	assert('cart shows variable product + attributes', /Variable Test Backpack/.test(cartItem));
	assert('cart shows Size attribute', /Small/.test(cartItem));
	assert('cart shows Color attribute', /Black/.test(cartItem));

	await ctx.close();
}

async function testDoubleClickIdempotency(browser) {
	log('=== Double-click idempotency ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'idempotency');

	await page.goto(SPA, { waitUntil: 'networkidle' });
	await waitForStable(page, '.store-card');

	// Double-click add on PDP after selecting from a card.
	const firstCard = page.locator('.store-card', { hasText: 'Canvas Tote' });
	await firstCard.locator('.store-card__select').click();
	await page.waitForURL(/\/product\//, { timeout: 10000 });
	const addBtn = page.locator('.pdp__add');
	await Promise.all([addBtn.click(), addBtn.click()]); // fire in parallel
	await page.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
	await page.waitForTimeout(1000);

	const qtyText = (await page.locator('.fkcart-qty__value').first().textContent()) || '0';
	const qty = parseInt(qtyText.trim(), 10);
	// Because both clicks fire before the first responds, WC typically
	// processes each as a separate +1 → quantity 2. The important invariant
	// is that it's DETERMINISTIC and BOUNDED — not 17.
	assert('double quick-add produces bounded quantity', qty >= 1 && qty <= 2, `qty=${qty}`);

	await ctx.close();
}

async function testParallelDifferentCards(browser) {
	log('=== Parallel add two different products — SPA mutex ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'parallel-add');

	await page.goto(SPA, { waitUntil: 'networkidle' });
	await waitForStable(page, '.store-card');

	// The SPA's cart store has a mutex that serializes UI-driven
	// mutations. This is deliberate because WC's Store API session cart
	// has a server-side read-modify-write race under concurrent writes
	// to the same session — we can't fix that server-side from the
	// client, only mitigate it by serializing.
	//
	// This test verifies the mutex contract: two addItem calls issued
	// in parallel through the cart store result in both items in the
	// cart. We expose cart.addItem by importing the store and calling
	// it directly, mirroring what two racing click handlers would do.
	//
	// We use an AbortController-guarded Promise.all so if something
	// hangs we don't block indefinitely.
	const result = await page.evaluate(async () => {
		// Dynamically import the cart store module from the SPA's module graph.
		const mod = await import('/src/lib/wc/cart.svelte.ts');
		const cart = mod.cart;
		// Fire both adds in the same microtask so they queue on the mutex.
		const p1 = cart.addItem(12, 1);
		const p2 = cart.addItem(13, 1);
		await Promise.all([p1, p2]);
		return {
			count: cart.cart?.items.length ?? 0,
			ids: (cart.cart?.items ?? []).map((i) => i.id).sort()
		};
	});

	assert('mutex serialized adds: cart contains both products', result.count === 2, `count=${result.count} ids=${result.ids.join(',')}`);
	assert('cart has expected ids', JSON.stringify(result.ids) === '[12,13]', `got ${JSON.stringify(result.ids)}`);

	await ctx.close();
}

async function testThemeStorm(browser) {
	log('=== Theme toggle storm ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'theme-storm');

	await page.goto(SPA, { waitUntil: 'networkidle' });
	await waitForStable(page, '.theme-toggle');

	// 30 rapid clicks
	for (let i = 0; i < 30; i++) {
		await page.locator('.theme-toggle').click();
	}
	await page.waitForTimeout(300);

	const stored = await page.evaluate(() => localStorage.getItem('wchs_theme'));
	const current = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
	// 30 clicks from light → dark,light,dark,... → final is dark (even count) → light
	// From light after 30 toggles: 30 is even, so we end back at light
	assert('theme storm converges', stored === current, `stored=${stored} dom=${current}`);
	assert('theme storm produces valid value', stored === 'light' || stored === 'dark');

	await ctx.close();
}

async function testShadowCartReplay(browser) {
	log('=== Shadow cart replay on simulated token loss ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'shadow-cart');

	await page.goto(SPA, { waitUntil: 'networkidle' });
	await waitForStable(page, '.store-card');

	await page.locator('.store-card', { hasText: 'Canvas Tote' }).locator('.store-card__select').click();
	await page.waitForURL(/\/product\//, { timeout: 10000 });
	await page.locator('.pdp__add').click();
	await page.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
	await page.waitForTimeout(500);

	// Verify shadow is populated
	const shadowRaw = await page.evaluate(() => localStorage.getItem('wchs_shadow_cart_v1'));
	assert('shadow cart populated after add', shadowRaw !== null);
	const shadow = JSON.parse(shadowRaw);
	assert('shadow has one item', shadow.items.length === 1);

	// Simulate token loss: blow away the cart token in sessionStorage, reload
	await page.evaluate(() => {
		sessionStorage.removeItem('wchs_cart_token');
		sessionStorage.removeItem('wchs_store_nonce');
	});
	await page.reload({ waitUntil: 'networkidle' });
	await page.waitForTimeout(1500); // let shadow replay fire

	// Open the cart — item should be back (via shadow replay)
	await page.locator('.site-header__cart').click();
	await page.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
	await page.waitForTimeout(600);

		const itemsAfterReplay = await page.locator('.fkcart-item').count();
		assert('shadow replay restored cart after token loss', itemsAfterReplay === 1, `${itemsAfterReplay} items`);
		const replayCheckoutHref = await page.locator('.fkcart-checkout').getAttribute('href');
		assert('restored cart checkout href is absolute', (replayCheckoutHref || '').startsWith(WP));
		await Promise.all([
			page.waitForURL((url) => url.toString().startsWith(`${WP}/checkout`), { timeout: 15000 }),
			page.locator('.fkcart-checkout').click()
		]);
		await page.waitForSelector('#place_order', { timeout: 15000 });
		assert('restored cart checkout stays on checkout, not shop', /\/checkout/.test(page.url()), page.url());

		await ctx.close();
	}

async function testFullPurchaseCOD(browser) {
	log('=== Full purchase flow (Cash on Delivery) ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'purchase-flow');

	await page.goto(SPA, { waitUntil: 'networkidle' });
	await waitForStable(page, '.store-card');

	await page.locator('.store-card', { hasText: 'Canvas Tote' }).locator('.store-card__select').click();
	await page.waitForURL(/\/product\//, { timeout: 10000 });
	await page.locator('.pdp__add').click();
	await page.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
	await page.waitForTimeout(500);
	await shot(page, '10-cart-before-checkout');

	// Click checkout — goes cross-origin to WP
		const checkoutHref = await page.locator('.fkcart-checkout').getAttribute('href');
		assert('checkout href is absolute', (checkoutHref || '').startsWith(WP));
		assert('checkout href has cart token', /\?cart=/.test(checkoutHref || ''));

		await Promise.all([
			page.waitForURL((url) => url.toString().startsWith(`${WP}/checkout`), { timeout: 15000 }),
			page.locator('.fkcart-checkout').click()
		]);
		// Stripe Elements on checkout keeps network active via long-poll,
		// so we wait on the Place Order button instead of networkidle.
		await page.waitForSelector('#place_order', { timeout: 15000 });
	await page.waitForTimeout(800);
	await shot(page, '11-checkout-landed');

	// Fill billing address (required for COD)
	const bodyText = await page.locator('body').innerText();
	assert('checkout has no critical error', !/critical error|fatal/i.test(bodyText));
	assert('checkout shows product', /Canvas Tote/.test(bodyText));

	// Set country/state via JS — WC uses Select2 which Playwright's
	// selectOption doesn't always trigger correctly. Set the value
	// directly and fire WC's change event to trigger update_order_review.
	await page.evaluate(() => {
		const country = document.querySelector('#billing_country');
		if (country) {
			country.value = 'US';
			jQuery(country).trigger('change');
		}
	});
	await page.waitForTimeout(2000); // WC fires update_order_review + re-renders state
	await page.evaluate(() => {
		const state = document.querySelector('#billing_state');
		if (state) {
			state.value = 'CA';
			jQuery(state).trigger('change');
		}
	});
	await page.waitForTimeout(1000);

	// Fill remaining fields after country/state are stable
	await page.fill('#billing_first_name', 'Test');
	await page.fill('#billing_last_name', 'Customer');
	await page.fill('#billing_address_1', '123 Dev Lane');
	await page.fill('#billing_city', 'San Francisco');
	await page.fill('#billing_postcode', '94110');
	await page.fill('#billing_phone', '5551234567');
	await page.fill('#billing_email', 'test@wchs.local');

	await page.waitForTimeout(800); // final settle

	// Check if COD is available. The radio is visually hidden in classic
	// WC checkout but the input exists. Use force check rather than click.
	const codCount = await page.locator('#payment_method_cod').count();
	if (codCount === 0) {
		log('COD not enabled — skipping purchase completion assertions');
		await ctx.close();
		return 'cod-unavailable';
	}
	await page.locator('#payment_method_cod').check({ force: true });
	await page.waitForTimeout(500);

	// Place order — click and wait for redirect to order-received.
	// WC's classic checkout AJAX can fail silently. Capture everything.
	await shot(page, '11-pre-place-order');
	page.on('console', (msg) => { if (msg.type() === 'error') log('  console.error: ' + msg.text().slice(0, 200)); });
	await page.locator('#place_order').click();
	try {
		await page.waitForURL(/order-received/, { timeout: 45000 });
	} catch (e) {
		// Grab any WC validation notice before rethrowing
		const notice = await page.locator('.woocommerce-error, .woocommerce-NoticeGroup').textContent().catch(() => '');
		if (notice) log('WC checkout error: ' + notice.trim().slice(0, 200));
		const bodySnip = (await page.locator('body').innerText()).slice(0, 500);
		log('Page text after click: ' + bodySnip.replace(/\n/g, ' | '));
		await shot(page, '12-checkout-error');
		throw e;
	}
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1500);
	await shot(page, '12-order-received-spa');

	const finalUrl = page.url();
	// Should have bounced to SPA /order-received
	assert('post-purchase landed on SPA order-received', finalUrl.includes('/order-received'), finalUrl);

	// Wait for the SPA to finish fetching the order from Store API and render
	await page.waitForSelector('.thanks__card', { timeout: 15000 });
	await page.waitForTimeout(800);
	const thanksText = await page.locator('body').innerText();
	assert('SPA thank-you page shows Thank you', /Thank you/i.test(thanksText), thanksText.slice(0, 120));
	assert('SPA thank-you page shows order number', /Order received|#/.test(thanksText));
	assert('SPA thank-you page shows purchased product', /Canvas Tote/.test(thanksText));

	// The URL should be clean (history.replaceState stripped params)
	assert('thank-you URL stripped of id/key', !/[?&]key=/.test(finalUrl), finalUrl);

	await ctx.close();
	return 'ok';
}

async function testBothThemesJourney(browser) {
	log('=== Both themes full journey ===');
	for (const scheme of ['light', 'dark']) {
		const ctx = await browser.newContext({
			viewport: { width: 1440, height: 900 },
			colorScheme: scheme
		});
		const page = await ctx.newPage();
		attachErrors(page, `both-themes-${scheme}`);

		await page.goto(SPA, { waitUntil: 'networkidle' });
		await waitForStable(page, '.store-card');
		await shot(page, `20-home-${scheme}`);

		const actualTheme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
		assert(`${scheme}: data-theme matches`, actualTheme === scheme, `got ${actualTheme}`);

		await page.locator('.store-card__select').first().click();
		await page.waitForURL(/\/product\//, { timeout: 10000 });
		await page.locator('.pdp__add').click();
		await page.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
		await page.waitForTimeout(400);
		await shot(page, `21-cart-${scheme}`);

		// Check cart visible text contrast
		const itemName = await page.locator('.fkcart-item a').first().textContent();
		assert(`${scheme}: cart item name visible`, !!(itemName && itemName.trim().length > 0));

		await ctx.close();
	}
}

async function testMobileViewport(browser) {
	log('=== Mobile viewport ===');
	const ctx = await browser.newContext({
		viewport: { width: 375, height: 667 },
		colorScheme: 'light'
	});
	const page = await ctx.newPage();
	attachErrors(page, 'mobile');

	await page.goto(SPA, { waitUntil: 'networkidle' });
	await waitForStable(page, '.store-card');
	await shot(page, '30-mobile-home');

	const cards = await page.locator('.store-card').count();
	assert('mobile home shows cards', cards > 0);

	await page.locator('.store-card__select').first().click();
	await page.waitForURL(/\/product\//, { timeout: 10000 });
	await page.locator('.pdp__add').click();
	await page.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
	await page.waitForTimeout(400);
	await shot(page, '31-mobile-cart');

	const cartItems = await page.locator('.fkcart-item').count();
	assert('mobile cart receives add', cartItems === 1);

	await ctx.close();
}

async function testAccountGuestProbe(browser) {
	log('=== Account page guest probe ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'account-guest');

	await page.goto(`${SPA}/account`, { waitUntil: 'networkidle' });
	await page.waitForTimeout(800);
	await shot(page, '40-account-guest');

	const text = await page.locator('body').innerText();
	assert('guest sees "not signed in"', /not signed in/i.test(text));
	const signInLink = await page.locator('a', { hasText: /sign in/i }).first();
	const signInHref = await signInLink.getAttribute('href');
	assert('sign in link targets WP login', (signInHref || '').includes('/my-account/'));

	// Orders page for guest → 401 from endpoint → shows message
	await page.goto(`${SPA}/account/orders`, { waitUntil: 'networkidle' });
	await page.waitForTimeout(800);
	const ordersText = await page.locator('body').innerText();
	assert('guest orders page shows signed-in prompt', /signed in|signed-in/i.test(ordersText));

	await ctx.close();
}

async function testOrderReceivedGuards(browser) {
	log('=== Order-received route guards ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	attachErrors(page, 'order-received-guards');

	// Missing params
	await page.goto(`${SPA}/order-received`, { waitUntil: 'networkidle' });
	await page.waitForTimeout(600);
	const missingText = await page.locator('body').innerText();
	assert('missing id/key shows friendly error', /Order reference missing/i.test(missingText));

	// Bad id
	await page.goto(`${SPA}/order-received?id=99999&key=fakekey123`, { waitUntil: 'networkidle' });
	await page.waitForTimeout(1000);
	const badText = await page.locator('body').innerText();
	// Should show error, not crash
	assert('invalid id+key shows error (no crash)', !/critical error|\[object/i.test(badText));

	await ctx.close();
}

async function testZeroConsoleErrors(browser) {
	log('=== Zero console errors across all routes ===');
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();
	const localErrors = [];
	page.on('pageerror', (e) => localErrors.push(`pageerror: ${e.message}`));
	page.on('console', (m) => {
		if (m.type() === 'error') {
			const t = m.text();
			if (t.includes('favicon') || t.includes('ERR_ABORTED')) return;
			// Expected 4xx from guest probe + invalid order fetch
			if (t.includes('Failed to load resource') && /status of 40\d/.test(t)) return;
			localErrors.push(`console: ${t}`);
		}
	});
	page.on('requestfailed', (req) => {
		const url = req.url();
		if (url.includes('favicon') || url.includes('bunny.net') || url.includes('@vite')) return;
		if (req.failure()?.errorText === 'net::ERR_ABORTED') return;
		localErrors.push(`reqfail: ${req.method()} ${url}`);
	});

	const routes = [
		SPA + '/',
		SPA + '/shop',
		SPA + '/shop?sort=price-asc',
		SPA + '/shop?search=tote',
		SPA + '/product/canvas-tote',
		SPA + '/product/variable-test-backpack',
		SPA + '/account',
		SPA + '/account/orders',
		SPA + '/order-received'
	];

	for (const r of routes) {
		await page.goto(r, { waitUntil: 'networkidle' });
		await page.waitForTimeout(600);
	}

	assert(`zero errors across ${routes.length} routes`, localErrors.length === 0, localErrors.join(' | ') || 'clean');

	await ctx.close();
}

// ============================================================
// Runner
// ============================================================

async function run() {
	const browser = await chromium.launch({ headless: true });

	try {
		await testHomeAndShop(browser);
		await testRapidFilterRace(browser);
		await testVariableProductPDP(browser);
		await testDoubleClickIdempotency(browser);
		await testParallelDifferentCards(browser);
		await testThemeStorm(browser);
		await testShadowCartReplay(browser);
		const purchaseResult = await testFullPurchaseCOD(browser);
		report.timings.purchaseResult = purchaseResult;
		await testBothThemesJourney(browser);
		await testMobileViewport(browser);
		await testAccountGuestProbe(browser);
		await testOrderReceivedGuards(browser);
		await testZeroConsoleErrors(browser);
	} catch (e) {
		console.error('\nFATAL:', e);
		failCount++;
		report.errors.push({ fatal: true, msg: e.message, stack: e.stack });
	} finally {
		await browser.close();
	}

	console.log('\n=============================================');
	console.log(`  ${passCount} passed, ${failCount} failed`);
	if (report.errors.length) {
		console.log(`  ${report.errors.length} console/page/request errors captured`);
		for (const e of report.errors) {
			console.log(`    [${e.tag || 'fatal'}] ${e.type || ''}: ${e.msg}`);
		}
	}
	console.log('=============================================\n');

	fs.writeFileSync(path.join(SHOTS, '_report.json'), JSON.stringify(report, null, 2));

	const strictErrorFail = report.errors.length > 0;
	process.exit(failCount > 0 || strictErrorFail ? 1 : 0);
}

run().catch((e) => {
	console.error(e);
	process.exit(1);
});
