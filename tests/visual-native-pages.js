// Visual regression for the native WP pages (checkout + my-account +
// wp-login) in both themes. Screenshots saved to tests/screenshots/visual/.
//
// Run: node tests/visual-native-pages.js

const path = require('path');
const fs = require('fs');

const { chromium } = require('./playwright');

const WP = 'http://localhost:8099';
const SPA = 'http://localhost:5175';
const SHOTS = path.resolve(__dirname, 'screenshots', 'visual');
fs.mkdirSync(SHOTS, { recursive: true });

async function shot(page, name) {
	await page.screenshot({ path: path.join(SHOTS, `${name}.png`), fullPage: true });
	console.log(`saved ${name}.png`);
}

async function run() {
	const browser = await chromium.launch({ headless: true });

	for (const scheme of ['light', 'dark']) {
		const ctx = await browser.newContext({
			viewport: { width: 1200, height: 900 },
			colorScheme: scheme
		});
		const page = await ctx.newPage();

		// 1. wp-login.php
		await page.goto(`${WP}/wp-login.php`, { waitUntil: 'networkidle' });
		await shot(page, `${scheme}-01-wp-login`);

		// 2. my-account (not logged in → shows login form)
		await page.goto(`${WP}/my-account/`, { waitUntil: 'networkidle' });
		await shot(page, `${scheme}-02-my-account-guest`);

		// 3. cart page (empty)
		await page.goto(`${WP}/cart/`, { waitUntil: 'networkidle' });
		await shot(page, `${scheme}-03-cart-empty`);

		// 4. Populate cart via SPA, then hit checkout
		await page.goto(SPA, { waitUntil: 'networkidle' });
		await page.waitForSelector('.store-card', { timeout: 10000 });
		await page.locator('.store-card__select').first().click();
		await page.waitForSelector('.fkcart-modal.fkcart-show', { timeout: 5000 });
		await page.waitForTimeout(400);

		const checkoutHref = await page.locator('.fkcart-checkout').getAttribute('href');
		await page.goto(checkoutHref, { waitUntil: 'networkidle' });
		await page.waitForTimeout(400);
		await shot(page, `${scheme}-04-checkout`);

		await ctx.close();
	}

	await browser.close();
	console.log(`\ndone — screenshots in ${SHOTS}`);
}

run().catch((e) => {
	console.error(e);
	process.exit(1);
});
