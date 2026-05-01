/**
 * CRO visual capture. Takes screenshots of every new surface that consumes
 * extensions.wchs_cro so we can diff + Gemini-review them.
 *
 * Outputs → tests/screenshots/cro/<viewport>-<theme>-<name>.png
 *
 *   01-spa-shop          — product grid with tier badges
 *   02-spa-pdp-tiers     — PDP with volume savings block
 *   03-spa-pdp-xsell     — PDP scrolled to cross-sell strip
 *   04-cart-empty        — drawer open, no items
 *   05-cart-with-savings — drawer with a qty=3 item (tier 2 reached, next tier nudge)
 *   06-cart-max-tier     — drawer with a qty=8 item (top tier, no nudge)
 *   07-cart-mixed        — drawer with 2 different products, total_savings shown, xsell strip
 */

const path = require('path');
const fs = require('fs');
const { chromium } = require('./playwright');

const SPA = 'http://localhost:5175';
const SHOTS = path.resolve(__dirname, 'screenshots', 'cro');
fs.mkdirSync(SHOTS, { recursive: true });
for (const f of fs.readdirSync(SHOTS)) if (f.endsWith('.png')) fs.unlinkSync(path.join(SHOTS, f));

const VIEWPORTS = [
	{ name: 'laptop', width: 1440, height: 900 },
	{ name: 'mobile', width: 375, height: 800 }
];
const THEMES = ['dark', 'light'];

async function run() {
	const browser = await chromium.launch({ headless: true });
	try {
		for (const vp of VIEWPORTS) {
			for (const theme of THEMES) {
				const ctx = await browser.newContext({
					viewport: { width: vp.width, height: vp.height },
					colorScheme: theme
				});
				const page = await ctx.newPage();
				await page.addInitScript((t) => {
					try { localStorage.setItem('wchs_theme', t); } catch {}
				}, theme);

				const shoot = async (name, fullPage = true) => {
					await page.waitForTimeout(500);
					await page.screenshot({
						path: path.join(SHOTS, `${vp.name}-${theme}-${name}.png`),
						fullPage
					});
					console.log(`saved ${vp.name}-${theme}-${name}.png`);
				};

				// -----------------------------------------------------------
				// 01. Shop grid — tier badges on every card
				// -----------------------------------------------------------
				await page.goto(`${SPA}/shop`, { waitUntil: 'networkidle' });
				await page.waitForSelector('.store-card');
				await shoot('01-spa-shop');

				// -----------------------------------------------------------
				// 02. PDP — tier table + was/now price row
				// -----------------------------------------------------------
				await page.goto(`${SPA}/product/cable-organizer`, { waitUntil: 'networkidle' });
				await page.waitForSelector('.pdp__tiers');
				await shoot('02-spa-pdp-tiers');

				// -----------------------------------------------------------
				// 02b. PDP qty=3 — active tier highlighted + dynamic nudge
				// -----------------------------------------------------------
				await page.fill('.pdp__qty input', '3');
				await page.waitForSelector('.pdp__tiers-nudge');
				await page.evaluate(() => {
					const el = document.querySelector('.pdp__tiers');
					if (el) el.scrollIntoView({ block: 'center' });
				});
				await shoot('02b-spa-pdp-nudge');

				// -----------------------------------------------------------
				// 03. PDP cross-sells — scroll to strip
				// -----------------------------------------------------------
				await page.evaluate(() => {
					const el = document.querySelector('.pdp-xsell');
					if (el) el.scrollIntoView({ block: 'start' });
				});
				await shoot('03-spa-pdp-xsell');

				// -----------------------------------------------------------
				// 04. Cart drawer — empty
				// -----------------------------------------------------------
				await page.goto(`${SPA}/`, { waitUntil: 'networkidle' });
				await page.waitForSelector('.store-card');
				// Click the header cart button
				const cartBtn = page.locator('.header__cart').first();
				if ((await cartBtn.count()) > 0) {
					await cartBtn.click();
				}
				await page.waitForTimeout(400);
				await shoot('04-cart-empty', false);

				// -----------------------------------------------------------
				// 05. Cart with savings — qty 3 (in tier 2, next tier nudge visible)
				// -----------------------------------------------------------
				// Close cart, go to PDP, add 3, open cart
				await page.keyboard.press('Escape');
				await page.goto(`${SPA}/product/cable-organizer`, { waitUntil: 'networkidle' });
				await page.waitForSelector('.pdp__qty input');
				// Set qty=3
				await page.fill('.pdp__qty input', '3');
				await page.click('.pdp__add');
				await page.waitForTimeout(800);
				await page.waitForSelector('.fkcart-modal.fkcart-show');
				await page.waitForSelector('.fkcart-item__saved');
				await shoot('05-cart-with-savings', false);

				// -----------------------------------------------------------
				// 06. Cart max tier — increment qty up to 8 via + button
				// -----------------------------------------------------------
				// Keep clicking + until we reach 8
				let tries = 0;
				while (tries < 10) {
					const qtyText = await page
						.locator('.fkcart-qty__value')
						.first()
						.innerText();
					if (qtyText.trim() === '8') break;
					await page
						.locator('.fkcart-qty__btn')
						.nth(1) // the + button
						.click();
					await page.waitForTimeout(250);
					tries++;
				}
				await page.waitForTimeout(400);
				await shoot('06-cart-max-tier', false);

				// -----------------------------------------------------------
				// 07. Mixed cart with another product — add qty 2
				// -----------------------------------------------------------
				await page.keyboard.press('Escape');
				await page.goto(`${SPA}/product/utility-pouch`, { waitUntil: 'networkidle' });
				await page.waitForSelector('.pdp__qty input');
				await page.fill('.pdp__qty input', '2');
				await page.click('.pdp__add');
				await page.waitForTimeout(800);
				await page.waitForSelector('.fkcart-modal.fkcart-show');
				await shoot('07-cart-mixed', false);

				await ctx.close();
			}
		}
	} finally {
		await browser.close();
	}
	console.log(`\ndone — screenshots in ${SHOTS}`);
}

run().catch((e) => {
	console.error(e);
	process.exit(1);
});
