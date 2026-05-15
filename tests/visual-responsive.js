// Visual responsive screenshot sweep.
//
// Captures every important page × every theme × every viewport.
// Output: tests/screenshots/responsive/<viewport>-<theme>-<page>.png
//
// No assertions — this is for manual + eyeball review. Use it alongside
// the e2e tests to diff before/after design changes on mobile + desktop
// in both themes.

const path = require('path');
const fs = require('fs');
const { chromium } = require('./playwright');

const SPA = 'http://localhost:5175';
const WP = 'http://localhost:8099';
const SHOTS = path.resolve(__dirname, 'screenshots', 'responsive');
fs.mkdirSync(SHOTS, { recursive: true });
for (const f of fs.readdirSync(SHOTS)) if (f.endsWith('.png')) fs.unlinkSync(path.join(SHOTS, f));

const VIEWPORTS = [
	{ name: 'mobile', width: 375, height: 667 },
	{ name: 'tablet', width: 768, height: 1024 },
	{ name: 'laptop', width: 1280, height: 800 },
	{ name: 'desktop', width: 1920, height: 1080 }
];

const THEMES = ['light', 'dark'];

async function run() {
	const browser = await chromium.launch({ headless: true });

	// Prime a cart token once so /wp/checkout has something to show
	let cartTokenHref = null;
	{
		const primeCtx = await browser.newContext();
		const primePage = await primeCtx.newPage();
		await primePage.goto(SPA, { waitUntil: 'networkidle' });
		await primePage.waitForSelector('.store-card');
		await primePage.locator('.store-card__select').first().click();
		await primePage.waitForSelector('.fkcart-modal.fkcart-show');
		await primePage.waitForTimeout(400);
		cartTokenHref = await primePage.locator('.fkcart-checkout').getAttribute('href');
		await primeCtx.close();
	}

	for (const vp of VIEWPORTS) {
		for (const theme of THEMES) {
			const ctx = await browser.newContext({
				viewport: { width: vp.width, height: vp.height },
				colorScheme: theme
			});
			const page = await ctx.newPage();

			// Force explicit theme in localStorage before any nav
			await page.addInitScript((t) => {
				try { localStorage.setItem('wchs_theme', t); } catch (e) {}
			}, theme);

			const shoot = async (name) => {
				await page.waitForTimeout(500);
				await page.screenshot({
					path: path.join(SHOTS, `${vp.name}-${theme}-${name}.png`),
					fullPage: true
				});
				console.log(`saved ${vp.name}-${theme}-${name}.png`);
			};

			// SPA pages
			await page.goto(SPA, { waitUntil: 'networkidle' });
			await page.waitForSelector('.store-card', { timeout: 10000 });
			await shoot('01-spa-home');

			await page.goto(`${SPA}/shop`, { waitUntil: 'networkidle' });
			await page.waitForSelector('.store-card', { timeout: 10000 });
			await shoot('02-spa-shop');

			await page.goto(`${SPA}/product/variable-test-backpack`, { waitUntil: 'networkidle' });
			await page.waitForSelector('.pdp__variant-btn', { timeout: 10000 });
			await shoot('03-spa-pdp');

			await page.goto(`${SPA}/account`, { waitUntil: 'networkidle' });
			await page.waitForTimeout(600);
			await shoot('04-spa-account');

			// Native WP pages
			if (cartTokenHref) {
				await page.goto(cartTokenHref, { waitUntil: 'domcontentloaded' });
				// Stripe Elements keeps network active; wait for checkout to hydrate.
				await page.waitForSelector('#place_order', { timeout: 15000 }).catch(() => {});
				await page.waitForTimeout(700);
				await shoot('05-wp-checkout');
			}

			await page.goto(`${WP}/my-account/`, { waitUntil: 'networkidle' });
			await page.waitForTimeout(600);
			await shoot('06-wp-my-account');

			await page.goto(`${WP}/wp-login.php`, { waitUntil: 'domcontentloaded' });
			await page.waitForTimeout(600);
			await shoot('07-wp-login');

			await ctx.close();
		}
	}

	await browser.close();
	console.log(`\ndone — screenshots in ${SHOTS}`);
}

run().catch((e) => {
	console.error(e);
	process.exit(1);
});
