/**
 * Editor inspection — headed browser screenshot tour of every module modal
 * and the hero image positioning system. Not a test (no pass/fail) — just a
 * visual inspection aid to catch layout bugs, cropping, mis-alignment.
 *
 * Outputs: /tmp/wchs-ui/inspect/*.png (numbered sequentially)
 * Run:     node tests/inspect-editor.js
 * Flags:   HEADLESS=1 to run without visible window
 */
import { chromium } from 'playwright';
import fs from 'node:fs';

const WP_URL = 'http://localhost:8099';
const USER = 'admin';
const PASS = 'wchs-admin-dev';
const SHOT_DIR = '/tmp/wchs-ui/inspect';

// Wipe prior inspection run for clean output
try { fs.rmSync(SHOT_DIR, { recursive: true, force: true }); } catch {}
fs.mkdirSync(SHOT_DIR, { recursive: true });

let shotIdx = 0;
async function shot(page, label, full = false) {
	shotIdx++;
	const fn = `${SHOT_DIR}/${String(shotIdx).padStart(3, '0')}-${label}.png`;
	await page.screenshot({ path: fn, fullPage: full });
	console.log(`  📷 ${fn}`);
	return fn;
}

const MODULE_TYPES = [
	{ type: 'product_slider',  label: 'Product Slider' },
	{ type: 'review_slider',   label: 'Review Slider' },
	{ type: 'trust_bar',       label: 'Trust Bar' },
	{ type: 'accordion',       label: 'Accordion' },
	{ type: 'text_block',      label: 'Text Block' },
	{ type: 'gallery',         label: 'Gallery' },
	{ type: 'shop_grid',       label: 'Shop Grid' },
	{ type: 'contact_form',    label: 'Contact Form' },
	{ type: 'category_grid',   label: 'Category Grid' },
	{ type: 'split_features',  label: 'Split Features' },
];

async function login(page) {
	await page.goto(`${WP_URL}/wp-login.php`);
	await page.fill('#user_login', USER);
	await page.fill('#user_pass', PASS);
	await Promise.all([page.waitForLoadState('networkidle'), page.click('#wp-submit')]);
}

async function goHomepage(page) {
	await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=homepage`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1400);
	// Expand all collapsible sections
	const toggles = await page.locator('.wchs-section__toggle').count();
	for (let i = 0; i < toggles; i++) {
		try { await page.locator('.wchs-section__toggle').nth(i).click(); await page.waitForTimeout(40); } catch {}
	}
	await page.waitForTimeout(300);
}

async function openModuleModal(page, type) {
	// Click the module list's add button
	const addBtn = page.locator('.wchs-modlist__add-btn').first();
	if (!(await addBtn.count())) return false;
	await addBtn.click();
	await page.waitForTimeout(400);
	// Click the tile matching the requested type
	const tile = page.locator(`.wchs-modal [data-type="${type}"]`).first();
	if (!(await tile.count())) {
		// fallback: search by label text
		return false;
	}
	await tile.click();
	await page.waitForTimeout(600);
	return true;
}

async function closeModal(page) {
	const cancel = page.locator('.wchs-modal__cancel, .wchs-modal__close').first();
	if (await cancel.count()) {
		await cancel.click();
		await page.waitForTimeout(400);
	}
}

async function main() {
	const headless = process.env.HEADLESS === '1';
	const browser = await chromium.launch({ headless, slowMo: headless ? 0 : 80 });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	page.on('dialog', async d => { await d.accept(); });
	page.on('pageerror', e => console.log('  ⚠️  page error:', e.message.slice(0, 120)));

	await login(page);

	// ════════════════════════════════════════════════════════
	// Part 1: Hero image positioning (desktop + mobile)
	// ════════════════════════════════════════════════════════
	console.log('\n═══ Part 1: Hero image positioning ═══');
	await goHomepage(page);
	await shot(page, 'homepage-initial');

	// Scroll the panel to Background & media section
	await page.evaluate(() => {
		const h = Array.from(document.querySelectorAll('.wchs-section__toggle'))
			.find(el => /background.*media/i.test(el.textContent || ''));
		if (h) h.scrollIntoView({ behavior: 'instant', block: 'start' });
	});
	await page.waitForTimeout(400);
	await shot(page, 'hero-bg-media-section');

	// Full-page screenshot to see all controls + live canvas
	await shot(page, 'hero-initial-full', true);

	// Exercise desktop position sliders: move horizontal to 0 then 100
	const posX = page.locator('input[name="hero_image_position_x"]');
	if (await posX.count()) {
		await posX.evaluate((el) => { el.value = '0'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
		await page.waitForTimeout(800);
		await shot(page, 'hero-pos-x-0');
		await posX.evaluate((el) => { el.value = '100'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
		await page.waitForTimeout(800);
		await shot(page, 'hero-pos-x-100');
		await posX.evaluate((el) => { el.value = '50'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
		await page.waitForTimeout(500);
	}

	// Exercise desktop zoom
	const zoom = page.locator('input[name="hero_image_zoom"]');
	if (await zoom.count()) {
		await zoom.evaluate((el) => { el.value = '150'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
		await page.waitForTimeout(800);
		await shot(page, 'hero-zoom-150');
		await zoom.evaluate((el) => { el.value = '100'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
		await page.waitForTimeout(500);
	}

	// Switch canvas viewport to mobile preview
	const mobileBtn = page.locator('.wchs-device-btn[data-device="mobile"]');
	if (await mobileBtn.count()) {
		await mobileBtn.click();
		await page.waitForTimeout(1200);
		await shot(page, 'hero-mobile-preview');

		// Exercise mobile position Y (should let me verify mobile positioning separately)
		const mposY = page.locator('input[name="hero_image_position_mobile_y"]');
		if (await mposY.count()) {
			await mposY.evaluate((el) => { el.value = '0'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
			await page.waitForTimeout(800);
			await shot(page, 'hero-mobile-pos-y-0');
			await mposY.evaluate((el) => { el.value = '100'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
			await page.waitForTimeout(800);
			await shot(page, 'hero-mobile-pos-y-100');
		}

		// Switch back to desktop
		const desktopBtn = page.locator('.wchs-device-btn[data-device="desktop"]');
		if (await desktopBtn.count()) { await desktopBtn.click(); await page.waitForTimeout(800); }
	}

	// ════════════════════════════════════════════════════════
	// Part 2: Every module modal
	// ════════════════════════════════════════════════════════
	console.log('\n═══ Part 2: Module modals ═══');
	for (const mod of MODULE_TYPES) {
		await goHomepage(page);
		const opened = await openModuleModal(page, mod.type);
		if (!opened) {
			console.log(`  · ${mod.label} — could not open, skipping`);
			continue;
		}
		console.log(`  — ${mod.label}`);
		// Full-page screenshot of the modal so we see top AND bottom (including common fields strip)
		await shot(page, `modal-${mod.type}-full`, true);
		// Viewport screenshot to see at-a-glance what user sees
		await shot(page, `modal-${mod.type}-viewport`);
		await closeModal(page);
	}

	console.log(`\nDone. ${shotIdx} screenshots saved to ${SHOT_DIR}`);
	await browser.close();
}

main().catch(e => { console.error(e); process.exit(1); });
