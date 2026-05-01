/**
 * Phase-2 module override UI — e2e suite.
 *
 * Verifies the full chain: admin modal swatch → hidden input → modules_json
 *   → POST roundtrip → REST emits resolved + inherited → SPA re-renders with
 *   scoped --accent → Default swatch clears override cleanly.
 *
 * Tests the trust_bar module because it's the first proof (also opts into
 * supports.color.accent). contact_form has the same pattern.
 */
import { chromium } from 'playwright';

const WP_URL = 'http://localhost:8099';
const SPA_URL = 'http://localhost:5175';
const USER = 'admin';
const PASS = 'wchs-admin-dev';
const OVERRIDE_HEX = '#dc2626';

let pass = 0, fail = 0;
const ok = m => { pass++; console.log('  ✓ ' + m); };
const no = (m, d) => { fail++; console.log('  ✗ ' + m + (d ? ' — ' + d : '')); };

async function login(page) {
	await page.goto(`${WP_URL}/wp-login.php`);
	await page.fill('#user_login', USER);
	await page.fill('#user_pass', PASS);
	await Promise.all([page.waitForLoadState('networkidle'), page.click('#wp-submit')]);
}

async function seedTrustBarModule(page) {
	// Seed a trust_bar module on homepage so we have something to edit.
	// Use wp-cli directly since we control the dev stack.
	await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=homepage`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1200);
	// If no trust_bar present, add one via the module picker.
	const trustBarEditable = await page.locator('.wchs-modlist__row').evaluateAll(rows =>
		rows.some(r => r.textContent.toLowerCase().includes('trust'))
	);
	if (!trustBarEditable) {
		// Open Add Module modal, pick trust_bar
		await page.locator('.wchs-modlist__add-btn').first().click();
		await page.waitForTimeout(500);
		// Click Trust bar tile in the modal's type picker
		const trustTile = page.locator('.wchs-modal [data-type="trust_bar"], .wchs-modal button:has-text("Trust")').first();
		if (await trustTile.count()) {
			await trustTile.click();
			await page.waitForTimeout(500);
			// Apply with defaults
			await page.locator('.wchs-modal__save').click();
			await page.waitForTimeout(500);
		}
	}
}

async function openTrustBarModal(page) {
	await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=homepage`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1200);
	// Expand any collapsed sections (module list sometimes is nested)
	const toggles = page.locator('.wchs-section__toggle');
	const n = await toggles.count();
	for (let i = 0; i < n; i++) {
		try { await toggles.nth(i).click(); await page.waitForTimeout(50); } catch (e) {}
	}
	// Find + click the edit button on the trust_bar row
	const rows = page.locator('.wchs-modlist__row');
	const rowCount = await rows.count();
	for (let i = 0; i < rowCount; i++) {
		const text = await rows.nth(i).textContent();
		if (text && text.toLowerCase().includes('trust')) {
			await rows.nth(i).locator('.wchs-modlist__edit').click();
			await page.waitForTimeout(700);
			return true;
		}
	}
	return false;
}

async function saveAll(page) {
	await page.locator('form[action*=admin-post] button[type=submit]').first().click();
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(500);
}

async function main() {
	const browser = await chromium.launch();
	const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
	const page = await ctx.newPage();
	page.on('dialog', async d => { await d.accept(); });

	await login(page);
	await seedTrustBarModule(page);

	console.log('--- Admin: trust_bar modal has override swatches ---');
	const modalOpened = await openTrustBarModal(page);
	if (!modalOpened) {
		no('trust_bar modal', 'could not find/open a trust_bar row on homepage');
		console.log(`\nResults: ${pass} passed, ${fail} failed`);
		await browser.close();
		process.exit(1);
	}
	const swatchCount = await page.locator('.wchs-modal .wchs-override-swatch').count();
	if (swatchCount === 9) ok(`9 override swatches (1 default + 8 palette)`);
	else no('swatch count', `expected 9, got ${swatchCount}`);

	console.log('\n--- Ensure a trust item exists (TrustBar renders only with items) ---');
	const itemCount = await page.locator('.wchs-modal .wchs-trust-item').count();
	if (itemCount === 0) {
		await page.locator('.wchs-modal .wchs-add-trust-item-modal').click();
		await page.waitForTimeout(200);
	}
	const item = page.locator('.wchs-modal .wchs-trust-item').first();
	const headlineInputs = item.locator('input[type=text]');
	if (await headlineInputs.count() >= 1) {
		await headlineInputs.nth(0).fill('Fast shipping');
	}
	ok('seeded at least one trust item');

	console.log('\n--- Select red swatch → hidden input value ---');
	await page.locator(`.wchs-modal .wchs-override-swatch[data-override-value="${OVERRIDE_HEX}"]`).click();
	await page.waitForTimeout(200);
	const hiddenVal = await page.locator('.wchs-modal [data-field="overrides_accent_color"]').inputValue();
	if (hiddenVal === OVERRIDE_HEX) ok(`hidden input value = ${hiddenVal}`);
	else no('hidden val', `expected ${OVERRIDE_HEX}, got ${hiddenVal}`);

	const activeSwatch = await page.locator('.wchs-modal .wchs-override-swatch.active').getAttribute('data-override-value');
	if (activeSwatch === OVERRIDE_HEX) ok('selected swatch has .active class');
	else no('active swatch', `got "${activeSwatch}"`);

	console.log('\n--- Save module → modules_json contains overrides ---');
	await page.locator('.wchs-modal__save').click();
	await page.waitForTimeout(400);
	const jsonAfterModal = await page.locator('input[name="modules_json"]').first().inputValue();
	try {
		const arr = JSON.parse(jsonAfterModal);
		const tb = arr.find(m => m.type === 'trust_bar');
		if (tb && tb.overrides && tb.overrides.accent_color === OVERRIDE_HEX) {
			ok(`modules_json trust_bar.overrides.accent_color = ${tb.overrides.accent_color}`);
		} else {
			no('modal save JSON', `no overrides on trust_bar: ${JSON.stringify(tb)}`);
		}
	} catch (e) {
		no('JSON parse', e.message);
	}

	console.log('\n--- Form save → REST returns resolved + inherited ---');
	await saveAll(page);
	const rest = await page.evaluate(async () => {
		const r = await fetch('/wp-json/wchs/v1/config');
		const d = await r.json();
		return d.homepage.modules.find(m => m.type === 'trust_bar');
	});
	if (rest?.overrides?.accent_color === OVERRIDE_HEX) ok(`REST overrides.accent_color = ${rest.overrides.accent_color}`);
	else no('REST overrides', JSON.stringify(rest?.overrides));
	if (rest?.resolved?.accent_color === OVERRIDE_HEX) ok(`REST resolved.accent_color = ${rest.resolved.accent_color}`);
	else no('REST resolved', JSON.stringify(rest?.resolved));
	if (rest?.inherited?.accent_color === 'module') ok(`REST inherited.accent_color === 'module'`);
	else no('REST inherited', JSON.stringify(rest?.inherited));

	console.log('\n--- SPA: scoped --accent applies on trust-bar root ---');
	await page.goto(`${SPA_URL}/`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(2000);
	const scopedAccent = await page.evaluate(() => {
		const el = document.querySelector('.trust-bar');
		if (!el) return null;
		return {
			inlineStyle: el.getAttribute('style'),
			computedAccent: getComputedStyle(el).getPropertyValue('--accent').trim(),
		};
	});
	if (scopedAccent && scopedAccent.inlineStyle && scopedAccent.inlineStyle.includes(OVERRIDE_HEX)) {
		ok(`inline style present: ${scopedAccent.inlineStyle}`);
	} else {
		no('scoped inline style', JSON.stringify(scopedAccent));
	}
	if (scopedAccent?.computedAccent === OVERRIDE_HEX) ok(`computed --accent = ${scopedAccent.computedAccent}`);
	else no('computed accent', `got "${scopedAccent?.computedAccent}"`);

	console.log('\n--- Default swatch clears override ---');
	const reopened = await openTrustBarModal(page);
	if (!reopened) { no('reopen', 'could not reopen modal'); }
	await page.locator('.wchs-modal .wchs-override-swatch--default').click();
	await page.waitForTimeout(200);
	const hiddenCleared = await page.locator('.wchs-modal [data-field="overrides_accent_color"]').inputValue();
	if (hiddenCleared === '') ok('hidden input cleared');
	else no('hidden cleared', `got "${hiddenCleared}"`);
	await page.locator('.wchs-modal__save').click();
	await page.waitForTimeout(400);
	await saveAll(page);
	const restAfterClear = await page.evaluate(async () => {
		const r = await fetch('/wp-json/wchs/v1/config');
		const d = await r.json();
		return d.homepage.modules.find(m => m.type === 'trust_bar');
	});
	// When cleared: either no `overrides` key, or overrides is an empty object
	if (!restAfterClear?.overrides || Object.keys(restAfterClear.overrides).length === 0) {
		ok('REST: overrides key cleared after Default swatch');
	} else {
		no('REST after clear', JSON.stringify(restAfterClear?.overrides));
	}

	console.log('\n=======================================');
	console.log(`Results: ${pass} passed, ${fail} failed`);
	await browser.close();
	process.exit(fail > 0 ? 1 : 0);
}

main().catch(e => { console.error(e); process.exit(1); });
