// End-to-end smoke test for wc-headless-starter.
// Run: node tests/e2e-smoke.js
//
// Covers:
//   1. Home (light) — slider + hero render, cards visible
//   2. Home (dark)  — explicit dark mode via prefers-color-scheme
//   3. Theme toggle — click the toggle, verify it flips
//   4. Shop         — grid layout
//   5. PDP          — detail page
//   6. Quick-add    — triggers slide cart
//   7. Quantity bump
//   8. Checkout nav — top-level nav to WP origin, cart intact, no crit error
//   9. Console errors captured end-to-end

const path = require('path');
const fs = require('fs');

const { chromium } = require('./playwright');

const SPA = process.env.SPA_URL || 'http://localhost:5175';
const WP = process.env.WP_URL || 'http://localhost:8099';
const SMOKE_PRODUCT_SLUG = process.env.SMOKE_PRODUCT_SLUG || 'canvas-tote';
const SMOKE_PRODUCT_NAME = process.env.SMOKE_PRODUCT_NAME || 'Canvas Tote';
const SHOTS = path.resolve(__dirname, 'screenshots');
fs.mkdirSync(SHOTS, { recursive: true });
for (const f of fs.readdirSync(SHOTS)) {
	if (f.endsWith('.png') || f.endsWith('.txt')) fs.unlinkSync(path.join(SHOTS, f));
}

const report = {
	steps: [],
	errors: [],
	assertions: []
};

function log(step, detail = '') {
	const line = `[${new Date().toISOString().slice(11, 19)}] ${step}${detail ? ' — ' + detail : ''}`;
	console.log(line);
	report.steps.push(line);
}

function assert(label, cond, detail = '') {
	const ok = !!cond;
	report.assertions.push({ label, ok, detail });
	console.log(`${ok ? '✓' : '✗'} ${label}${detail ? ' (' + detail + ')' : ''}`);
}

function shot(page, name) {
	return page.screenshot({ path: path.join(SHOTS, `${name}.png`), fullPage: true });
}

function attachErrors(page, tag) {
	page.on('pageerror', (e) =>
		report.errors.push({ where: `${tag}:pageerror`, message: e.message })
	);
	page.on('console', (msg) => {
		if (msg.type() === 'error') {
			report.errors.push({ where: `${tag}:console`, message: msg.text() });
		}
	});
	page.on('requestfailed', (req) => {
		// Ignore favicons and hot-reload noise
		const url = req.url();
		if (url.includes('favicon') || url.includes('/@vite/')) return;
		report.errors.push({
			where: `${tag}:requestfailed`,
			message: `${req.method()} ${url} ${req.failure()?.errorText}`
		});
	});
}

async function run() {
	const browser = await chromium.launch({ headless: true });

	// === 1. Home, forced light mode ===
	log('1. home / light');
	const lightCtx = await browser.newContext({
		viewport: { width: 1440, height: 900 },
		colorScheme: 'light'
	});
	const lightPage = await lightCtx.newPage();
	attachErrors(lightPage, 'light-home');

	await lightPage.goto(SPA, { waitUntil: 'networkidle' });
	await lightPage.waitForSelector('.store-card', { timeout: 10000 });
	await shot(lightPage, '01-home-light');

	const lightBg = await lightPage.evaluate(
		() => getComputedStyle(document.documentElement).getPropertyValue('--bg')
	);
	assert('home light uses light bg', lightBg.trim() === '#ffffff', `--bg=${lightBg.trim()}`);

	const lightCardCount = await lightPage.locator('.store-card').count();
	assert('home shows cards', lightCardCount > 0, `${lightCardCount} cards`);

	const cartBtnVisible = await lightPage.locator('.site-header__cart').isVisible();
	assert('cart button visible in header', cartBtnVisible);

	await lightPage.waitForSelector('.rail__btn svg', { timeout: 5000 });
	const slideBtnSvgCount = await lightPage.locator('.rail__btn svg').count();
	assert('slider buttons have SVG icons', slideBtnSvgCount >= 2, `${slideBtnSvgCount} SVGs`);

	// === 2. Home, forced dark mode (system preference simulation) ===
	log('2. home / dark (system pref)');
	const darkCtx = await browser.newContext({
		viewport: { width: 1440, height: 900 },
		colorScheme: 'dark'
	});
	const darkPage = await darkCtx.newPage();
	attachErrors(darkPage, 'dark-home');

	await darkPage.goto(SPA, { waitUntil: 'networkidle' });
	await darkPage.waitForSelector('.store-card', { timeout: 10000 });
	await darkPage.waitForTimeout(200);
	await shot(darkPage, '02-home-dark');

	const darkBg = await darkPage.evaluate(
		() => getComputedStyle(document.documentElement).getPropertyValue('--bg')
	);
	assert('home dark uses dark bg', darkBg.trim() === '#000000', `--bg=${darkBg.trim()}`);

	const darkTheme = await darkPage.evaluate(() =>
		document.documentElement.getAttribute('data-theme')
	);
	assert('data-theme=dark when colorScheme=dark', darkTheme === 'dark');

	// === 3. Theme toggle ===
	log('3. theme toggle');
	await darkPage.locator('.theme-toggle:visible').first().click();
	await darkPage.waitForTimeout(300);
	await shot(darkPage, '03-home-after-toggle');

	const afterToggle = await darkPage.evaluate(() =>
		document.documentElement.getAttribute('data-theme')
	);
	assert('toggle flips dark → light', afterToggle === 'light');

	const storedTheme = await darkPage.evaluate(() => localStorage.getItem('wchs_theme'));
	assert('toggle persists to localStorage', storedTheme === 'light');

	await darkCtx.close();

	// === 4. Shop page ===
	log('4. shop grid');
	await lightPage.goto(`${SPA}/shop`, { waitUntil: 'networkidle' });
	await lightPage.waitForSelector('.store-card', { timeout: 10000 });
	await shot(lightPage, '04-shop-light');
	const shopCards = await lightPage.locator('.store-card').count();
	assert('shop grid populated', shopCards > 0, `${shopCards} cards`);

	// === 5. PDP ===
	log('5. pdp');
	await lightPage.goto(`${SPA}/product/${SMOKE_PRODUCT_SLUG}`, { waitUntil: 'networkidle' });
	await lightPage.waitForSelector('h1', { timeout: 10000 });
	await shot(lightPage, '05-pdp-light');
	const pdpTitle = await lightPage.locator('h1').first().textContent();
	assert('pdp shows product name', (pdpTitle || '').includes(SMOKE_PRODUCT_NAME));

	// === 6. Select from home → PDP add → slide cart opens ===
	log('6. select → PDP add → slide cart');
	await lightPage.goto(SPA, { waitUntil: 'networkidle' });
	await lightPage.waitForSelector('.store-card', { timeout: 10000 });

	const smokeCard = lightPage.locator('.store-card', { hasText: SMOKE_PRODUCT_NAME }).first();
	await smokeCard.locator('.store-card__select').click();
	await lightPage.waitForURL(/\/product\//, { timeout: 10000 });
	await lightPage.locator('.pdp__add').click();
	await lightPage.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
	await lightPage.waitForTimeout(500);
	await shot(lightPage, '06-cart-open');

	const itemCount = await lightPage.locator('.fkcart-item').count();
	assert('cart has exactly 1 item after add', itemCount === 1, `${itemCount} items`);

	const itemName = await lightPage.locator('.fkcart-item a').first().textContent();
	assert(
		`cart shows ${SMOKE_PRODUCT_NAME}`,
		(itemName || '').includes(SMOKE_PRODUCT_NAME),
		itemName?.trim()
	);

	// === 7. Increment quantity ===
	log('7. quantity increment');
	// Second .fkcart-qty__btn on the first line item is the "+" button
	await lightPage.locator('.fkcart-item .fkcart-qty__btn').nth(1).click();
	await lightPage.waitForTimeout(800);
	const qtyAfter = await lightPage.locator('.fkcart-qty__value').first().textContent();
	assert('quantity incremented to 2', (qtyAfter || '').trim() === '2', `qty=${qtyAfter}`);

	// === 8. Checkout handoff ===
	log('8. click checkout');
	const checkoutHref = await lightPage.locator('.fkcart-checkout').getAttribute('href');
	assert(
		'checkout href targets WP origin directly',
		(checkoutHref || '').startsWith(`${WP}/checkout/`),
		checkoutHref?.slice(0, 80) + '...'
	);
	assert(
		'checkout href contains cart token',
		/\?cart=/.test(checkoutHref || ''),
		'has ?cart='
	);

	await Promise.all([
		lightPage.waitForURL(/localhost:8099\/checkout/, { timeout: 15000 }),
		lightPage.locator('.fkcart-checkout').click()
	]);
	// Wait for the Place Order button — more reliable than networkidle
	// now that Stripe Elements loads on checkout and its long-polling
	// traffic keeps network active indefinitely.
	await lightPage.waitForSelector('#place_order', { timeout: 15000 });
	await lightPage.waitForTimeout(800);
	await shot(lightPage, '07-checkout-landed');

	const checkoutUrl = lightPage.url();
	assert(
		'landed on WP checkout URL',
		checkoutUrl.includes(`${WP}/checkout`),
		checkoutUrl.slice(0, 80)
	);

	const bodyText = await lightPage.locator('body').innerText();
	assert(
		'no critical error on checkout',
		!/critical error|has been a critical|fatal error/i.test(bodyText),
		bodyText.slice(0, 140).replace(/\s+/g, ' ')
	);
	assert(
		'cart contents visible on checkout page',
		bodyText.includes(SMOKE_PRODUCT_NAME),
		'found product in order review'
	);

	// === 9. Account link → SPA /account route ===
	log('9. account link');
	await lightPage.goto(SPA, { waitUntil: 'networkidle' });
	const accountHref = await lightPage.locator('a:has-text("Account")').first().getAttribute('href');
	assert(
		'account link goes to SPA /account',
		(accountHref || '') === '/account',
		accountHref
	);

	await lightCtx.close();
	await browser.close();

	// Final report
	const passed = report.assertions.filter((a) => a.ok).length;
	const failed = report.assertions.filter((a) => !a.ok);

	console.log(`\n=== ${passed}/${report.assertions.length} assertions passed ===`);
	if (failed.length) {
		console.log('FAILURES:');
		for (const f of failed) console.log(`  ✗ ${f.label}: ${f.detail}`);
	}

	if (report.errors.length) {
		console.log('\n=== console/page/request errors ===');
		for (const e of report.errors) console.log(`  ${e.where}: ${e.message}`);
	}

	// Persist structured report
	fs.writeFileSync(
		path.join(SHOTS, '_run-report.json'),
		JSON.stringify(report, null, 2)
	);

	process.exit(failed.length ? 1 : 0);
}

run().catch((e) => {
	console.error(e);
	process.exit(1);
});
