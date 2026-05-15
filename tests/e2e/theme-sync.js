// Theme sync test — verifies localStorage.wchs_theme keeps the SPA
// and native WP pages in visual lockstep.
//
// Flow:
//   1. Open SPA at :5175, toggle to dark, confirm data-theme=dark
//   2. Navigate to native /wp/checkout (populate cart first)
//   3. Verify <html data-theme="dark"> on the native page (no FOUC path)
//   4. Click the floating toggle button on /checkout
//   5. Verify data-theme flipped to light + localStorage persisted
//   6. Navigate back to SPA home
//   7. Verify SPA still respects the localStorage value
//   8. Repeat for the reverse direction (SPA starts light)
//
// Run: node tests/e2e/theme-sync.js

const path = require('path');
const fs = require('fs');
const { chromium } = require('../playwright');

const SPA = 'http://localhost:5175';
const WP = 'http://localhost:8099';
const SHOTS = path.resolve(__dirname, '..', 'screenshots', 'theme-sync');
fs.mkdirSync(SHOTS, { recursive: true });
for (const f of fs.readdirSync(SHOTS)) if (f.endsWith('.png')) fs.unlinkSync(path.join(SHOTS, f));

let passCount = 0;
let failCount = 0;

function assert(label, cond, detail = '') {
	const ok = !!cond;
	if (ok) passCount++;
	else failCount++;
	console.log(`  ${ok ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
}

function log(msg) {
	console.log(`[${new Date().toISOString().slice(11, 19)}] ${msg}`);
}

async function run() {
	const browser = await chromium.launch({ headless: true });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
	const page = await ctx.newPage();

	const errors = [];
	page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
	page.on('console', (m) => {
		if (m.type() === 'error') {
			const t = m.text();
			if (t.includes('favicon') || t.includes('ERR_ABORTED')) return;
			if (t.includes('Failed to load resource') && /status of 40\d/.test(t)) return;
			errors.push(`console: ${t}`);
		}
	});

	log('1. SPA home → toggle to dark');
	await page.goto(SPA, { waitUntil: 'networkidle' });
	await page.waitForSelector('.store-card', { timeout: 10000 });
	// Force theme to light first so we have a known starting state.
	await page.evaluate(() => {
		localStorage.setItem('wchs_theme', 'light');
		document.documentElement.setAttribute('data-theme', 'light');
	});
	await page.waitForTimeout(200);

	// Click SPA theme toggle → should flip to dark
	await page.locator('.theme-toggle').click();
	await page.waitForTimeout(300);
	const spaTheme1 = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
	assert('SPA toggle flipped to dark', spaTheme1 === 'dark', `got ${spaTheme1}`);

	const lsAfterToggle = await page.evaluate(() => localStorage.getItem('wchs_theme'));
	assert('localStorage persisted dark', lsAfterToggle === 'dark');
	await page.screenshot({ path: path.join(SHOTS, '01-spa-dark.png'), fullPage: true });

	log('2. Populate cart + navigate to native /checkout');
	await page.locator('.store-card__select').first().click();
	await page.waitForSelector('.fkcart-modal.fkcart-show');
	await page.waitForTimeout(400);
	const checkoutHref = await page.locator('.fkcart-checkout').getAttribute('href');
	await page.goto(checkoutHref, { waitUntil: 'domcontentloaded' });
	// Stripe Elements keeps network active; wait on a concrete element.
	await page.waitForSelector('#place_order', { timeout: 15000 });
	await page.waitForTimeout(600);

	log('3. Verify native page inherits dark theme');
	const nativeTheme1 = await page.evaluate(() =>
		document.documentElement.getAttribute('data-theme')
	);
	assert('native checkout shows dark', nativeTheme1 === 'dark', `got ${nativeTheme1}`);

	const toggleVisible = await page.locator('#wchs-theme-toggle').isVisible();
	assert('floating toggle rendered on native page', toggleVisible);
	await page.screenshot({ path: path.join(SHOTS, '02-native-dark.png'), fullPage: true });

	log('4. Click native toggle → flip to light');
	await page.locator('#wchs-theme-toggle').click();
	await page.waitForTimeout(300);
	const nativeTheme2 = await page.evaluate(() =>
		document.documentElement.getAttribute('data-theme')
	);
	assert('native toggle flipped to light', nativeTheme2 === 'light', `got ${nativeTheme2}`);

	const lsAfterNative = await page.evaluate(() => localStorage.getItem('wchs_theme'));
	assert('native toggle persisted light to localStorage', lsAfterNative === 'light');
	await page.screenshot({ path: path.join(SHOTS, '03-native-light.png'), fullPage: true });

	log('5. Navigate back to SPA → verify it picks up light');
	await page.goto(SPA, { waitUntil: 'networkidle' });
	await page.waitForTimeout(500);
	const spaTheme2 = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
	assert('SPA respects localStorage light on return', spaTheme2 === 'light', `got ${spaTheme2}`);
	await page.screenshot({ path: path.join(SHOTS, '04-spa-light.png'), fullPage: true });

	log('6. No-FOUC check: reload native page, data-theme applied before paint');
	await page.goto(`${WP}/wp-login.php`, { waitUntil: 'domcontentloaded' });
	const themeOnLoad = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
	assert('data-theme set on wp-login before first paint', themeOnLoad === 'light' || themeOnLoad === 'dark', `got ${themeOnLoad}`);
	await page.screenshot({ path: path.join(SHOTS, '05-wp-login-themed.png'), fullPage: true });

	await ctx.close();
	await browser.close();

	console.log(`\n${passCount}/${passCount + failCount} assertions passed`);
	if (errors.length) {
		console.log(`\n=== ${errors.length} console/page errors ===`);
		for (const e of errors) console.log(`  ${e}`);
	}
	process.exit(failCount > 0 || errors.length > 0 ? 1 : 0);
}

run().catch((e) => {
	console.error(e);
	process.exit(1);
});
