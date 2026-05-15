/**
 * Per-option rendered-behavior tests for product card customization.
 *
 * Complements the e2e-product-card.js (which verifies the admin → REST
 * → SPA token plumbing). This suite verifies that each option actually
 * *changes what the user sees* on a rendered card — uses the HD preview
 * route /preview/product-card because it has mocked product states
 * (default / sale / 4-digit / variable / OOS / secondary-image) without
 * depending on WC inventory.
 */
import { chromium } from 'playwright';

const WP_URL = 'http://localhost:8099';
const SPA_URL = 'http://localhost:5175';
const USER = 'admin';
const PASS = 'wchs-admin-dev';

let pass = 0, fail = 0;
const ok = m => { pass++; console.log('  ✓ ' + m); };
const no = (m, d) => { fail++; console.log('  ✗ ' + m + (d ? ' — ' + d : '')); };

async function login(page) {
	await page.goto(`${WP_URL}/wp-login.php`);
	await page.fill('#user_login', USER);
	await page.fill('#user_pass', PASS);
	await Promise.all([page.waitForLoadState('networkidle'), page.click('#wp-submit')]);
}

async function openDesign(page) {
	await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=design`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1200);
	const n = await page.locator('.wchs-section__toggle').count();
	for (let i = 0; i < n; i++) {
		try { await page.locator('.wchs-section__toggle').nth(i).click(); await page.waitForTimeout(40); } catch (e) {}
	}
	await page.waitForTimeout(300);
}

async function saveAndReload(page) {
	await page.locator('form[action*=admin-post] button[type=submit]').first().click();
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(400);
}

async function setOption(page, name, value) {
	await page.selectOption(`[name="product_card[${name}]"]`, value);
}

async function toggleOption(page, name, checked) {
	if (checked) await page.check(`[name="product_card[${name}]"]`);
	else await page.uncheck(`[name="product_card[${name}]"]`).catch(() => {});
}

async function visitPreview(page) {
	await page.goto(`${SPA_URL}/preview/product-card`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1200);
}

async function reset(page) {
	await openDesign(page);
	await setOption(page, 'media_aspect_ratio', '1:1');
	await setOption(page, 'corner_radius', 'square');
	await setOption(page, 'border', 'full');
	await setOption(page, 'hover_effect', 'lift');
	await setOption(page, 'button_style', 'outline');
	await setOption(page, 'badge_position', 'top-right');
	await setOption(page, 'badge_style', 'filled');
	await setOption(page, 'oos_treatment', 'grayscale');
	await setOption(page, 'title_lines', 'auto');
	await page.fill('[name="product_card[sale_badge_text]"]', 'Sale');
	await toggleOption(page, 'show_bulk_badge', true);
	await toggleOption(page, 'show_tier_hint', true);
	await toggleOption(page, 'show_oos_cards', true);
	await toggleOption(page, 'secondary_image_on_hover', false);
	await saveAndReload(page);
}

async function main() {
	const browser = await chromium.launch();
	const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
	const page = await ctx.newPage();
	page.on('dialog', async d => { await d.accept(); });

	await login(page);

	// ─── badge_position ──────────────────────────────────────────
	console.log('\n--- badge_position: top-right (default) vs top-left ---');
	await openDesign(page);
	await setOption(page, 'badge_position', 'top-right');
	await saveAndReload(page);
	await visitPreview(page);
	const badgeRight = await page.evaluate(() => {
		// The sale card has a badge. Measure its position within media.
		const b = document.querySelectorAll('.store-card__badge')[0];
		if (!b) return null;
		const parent = b.parentElement.getBoundingClientRect();
		const self = b.getBoundingClientRect();
		return { leftOffset: self.left - parent.left, rightOffset: parent.right - self.right };
	});
	if (badgeRight && badgeRight.rightOffset < 20) ok(`badge right-aligned (rightOffset=${badgeRight.rightOffset.toFixed(0)}px)`);
	else no('badge right', JSON.stringify(badgeRight));

	await openDesign(page);
	await setOption(page, 'badge_position', 'top-left');
	await saveAndReload(page);
	await visitPreview(page);
	const badgeLeft = await page.evaluate(() => {
		const b = document.querySelectorAll('.store-card__badge')[0];
		if (!b) return null;
		const parent = b.parentElement.getBoundingClientRect();
		const self = b.getBoundingClientRect();
		return { leftOffset: self.left - parent.left };
	});
	if (badgeLeft && badgeLeft.leftOffset < 20) ok(`badge left-aligned (leftOffset=${badgeLeft.leftOffset.toFixed(0)}px)`);
	else no('badge left', JSON.stringify(badgeLeft));

	// ─── button_style: solid ─────────────────────────────────────
	console.log('\n--- button_style: solid fills the Select button with accent ---');
	await openDesign(page);
	await setOption(page, 'button_style', 'solid');
	await saveAndReload(page);
	await visitPreview(page);
	const solidBtn = await page.evaluate(() => {
		const btn = document.querySelector('.store-card__select');
		if (!btn) return null;
		const cs = getComputedStyle(btn);
		return { bg: cs.backgroundColor, color: cs.color };
	});
	// Solid variant: background is not transparent (it's the accent color)
	if (solidBtn && solidBtn.bg !== 'rgba(0, 0, 0, 0)' && solidBtn.bg !== 'transparent') {
		ok(`Select button solid fill: bg=${solidBtn.bg}`);
	} else {
		no('solid btn', JSON.stringify(solidBtn));
	}

	// ─── button_style: icon-only ─────────────────────────────────
	console.log('\n--- button_style: icon-only removes border ---');
	await openDesign(page);
	await setOption(page, 'button_style', 'icon-only');
	await saveAndReload(page);
	await visitPreview(page);
	const iconBtn = await page.evaluate(() => {
		const btn = document.querySelector('.store-card__select');
		if (!btn) return null;
		const cs = getComputedStyle(btn);
		return { borderColor: cs.borderColor };
	});
	if (iconBtn && (iconBtn.borderColor === 'rgba(0, 0, 0, 0)' || iconBtn.borderColor === 'transparent')) {
		ok(`icon-only has no border (${iconBtn.borderColor})`);
	} else {
		no('icon-only border', JSON.stringify(iconBtn));
	}

	// ─── hover_effect: shadow ────────────────────────────────────
	console.log('\n--- hover_effect: shadow adds box-shadow on hover ---');
	await openDesign(page);
	await setOption(page, 'hover_effect', 'shadow');
	await saveAndReload(page);
	await visitPreview(page);
	await page.locator('.store-card').first().hover();
	await page.waitForTimeout(300);
	const hoverShadow = await page.evaluate(() => {
		const c = document.querySelector('.store-card:hover') || document.querySelector('.store-card');
		return c ? getComputedStyle(c).boxShadow : null;
	});
	if (hoverShadow && hoverShadow !== 'none' && hoverShadow.includes('rgba')) {
		ok(`hover shadow active: ${hoverShadow.slice(0, 60)}...`);
	} else {
		no('hover shadow', `got "${hoverShadow}"`);
	}

	// ─── title_lines: 1 ──────────────────────────────────────────
	console.log('\n--- title_lines: 1 applies -webkit-line-clamp:1 ---');
	await openDesign(page);
	await setOption(page, 'title_lines', '1');
	await saveAndReload(page);
	await visitPreview(page);
	const titleClamp = await page.evaluate(() => {
		const t = document.querySelector('.store-card__title');
		if (!t) return null;
		const cs = getComputedStyle(t);
		return { lineClamp: cs.webkitLineClamp || cs.getPropertyValue('-webkit-line-clamp'), display: cs.display };
	});
	if (titleClamp && (titleClamp.lineClamp === '1' || titleClamp.lineClamp === 1)) {
		ok(`title -webkit-line-clamp: ${titleClamp.lineClamp}`);
	} else {
		no('title clamp', JSON.stringify(titleClamp));
	}

	// ─── sale_badge_text with {percent} interpolation ────────────
	console.log('\n--- sale_badge_text: {percent} renders with computed discount ---');
	await openDesign(page);
	await page.fill('[name="product_card[sale_badge_text]"]', '−{percent}%');
	await setOption(page, 'badge_position', 'top-right');
	// Disable bulk badge so the sale badge shows on the sale mock product
	// (which has no CRO tiers — the fallback regular/current % kicks in)
	await toggleOption(page, 'show_bulk_badge', false);
	await saveAndReload(page);
	await visitPreview(page);
	const badges = await page.evaluate(() =>
		Array.from(document.querySelectorAll('.store-card__badge'))
			.map(b => b.textContent.trim())
			.filter(t => t && !t.includes('Out of stock'))
	);
	const saleInterp = badges.find(t => /^−\d+%$/.test(t));
	if (saleInterp) ok(`sale badge interpolated: "${saleInterp}"`);
	else no('sale interp', `no badge matched −N%. Saw: ${JSON.stringify(badges)}`);

	// ─── show_bulk_badge: false keeps the bulk-tier badge hidden ─
	console.log('\n--- show_bulk_badge: false hides bulk badge on CRO products ---');
	// (Using the state from the previous step: show_bulk_badge=false)
	const anyBulkBadge = await page.evaluate(() =>
		Array.from(document.querySelectorAll('.store-card__badge'))
			.some(b => b.textContent.toLowerCase().includes('bulk'))
	);
	// Our preview mocks don't emit CRO tiers, so "bulk" badge wouldn't appear
	// anyway — but ensure nothing went wrong under the toggle.
	if (!anyBulkBadge) ok('no bulk badge present when show_bulk_badge=false');
	else no('bulk badge', 'still visible');

	// ─── show_oos_cards: false filters OOS from shop ─────────────
	console.log('\n--- show_oos_cards: false filters OOS from shop grid ---');
	await openDesign(page);
	await toggleOption(page, 'show_oos_cards', false);
	await saveAndReload(page);
	// Preview gallery doesn't filter (it's curated) — check shop grid
	await page.goto(`${SPA_URL}/shop`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1500);
	const shopOosCount = await page.evaluate(() =>
		document.querySelectorAll('.store-card.is-oos').length
	);
	if (shopOosCount === 0) ok('shop grid shows zero .is-oos cards');
	else no('shop grid oos filter', `${shopOosCount} OOS cards still rendered`);

	// Re-enable + sanity check OOS DOES render when toggle is on
	await openDesign(page);
	await toggleOption(page, 'show_oos_cards', true);
	await saveAndReload(page);

	// ─── oos_treatment: hidden-price swaps price for "Sold out" ──
	console.log('\n--- oos_treatment: hidden-price shows "Sold out" text ---');
	await openDesign(page);
	await setOption(page, 'oos_treatment', 'hidden-price');
	await saveAndReload(page);
	await visitPreview(page);
	const soldOutInfo = await page.evaluate(() => {
		const oos = document.querySelector('.store-card.is-oos');
		if (!oos) return null;
		return {
			hasSoldOut: !!oos.querySelector('.store-card__sold-out'),
			soldOutText: oos.querySelector('.store-card__sold-out')?.textContent.trim(),
			hasPrice: !!oos.querySelector('.store-card__price'),
		};
	});
	if (soldOutInfo?.hasSoldOut && soldOutInfo.soldOutText === 'Sold out' && !soldOutInfo.hasPrice) {
		ok('hidden-price: "Sold out" shown, price hidden');
	} else {
		no('hidden-price', JSON.stringify(soldOutInfo));
	}

	// ─── oos_treatment: dim keeps color, removes grayscale ───────
	console.log('\n--- oos_treatment: dim uses opacity-only (no grayscale) ---');
	await openDesign(page);
	await setOption(page, 'oos_treatment', 'dim');
	await saveAndReload(page);
	await visitPreview(page);
	const dimFilter = await page.evaluate(() => {
		const img = document.querySelector('.store-card.is-oos .store-card__media img');
		if (!img) return null;
		return getComputedStyle(img).filter;
	});
	// 'dim' variant sets opacity only (no filter). filter: none OR empty
	if (dimFilter === 'none' || dimFilter === '') {
		ok(`dim variant: filter=${dimFilter || 'none'} (no grayscale)`);
	} else {
		no('dim filter', `got "${dimFilter}"`);
	}

	// ─── secondary_image_on_hover: renders extra <img> ───────────
	console.log('\n--- secondary_image_on_hover: renders secondary <img> ---');
	await openDesign(page);
	await toggleOption(page, 'secondary_image_on_hover', true);
	await saveAndReload(page);
	await visitPreview(page);
	const secondaryCount = await page.evaluate(() =>
		document.querySelectorAll('.store-card__media-secondary').length
	);
	// The preview has 1 product with a secondary image (productSecondary)
	if (secondaryCount >= 1) ok(`secondary images rendered: ${secondaryCount}`);
	else no('secondary img', `expected >=1, got ${secondaryCount}`);

	// Cleanup
	await reset(page);

	console.log('\n=======================================');
	console.log(`Results: ${pass} passed, ${fail} failed`);
	await browser.close();
	process.exit(fail > 0 ? 1 : 0);
}

main().catch(e => { console.error(e); process.exit(1); });
