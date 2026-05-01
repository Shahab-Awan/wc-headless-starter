import { chromium } from 'playwright';

const WP_URL = 'http://localhost:8099';
const SPA_URL = 'http://localhost:5175';
const USER = 'admin';
const PASS = 'wchs-admin-dev';

let pass = 0, fail = 0;
const ok = m => { pass++; console.log('  ✓ ' + m); };
const no = (m, d) => { fail++; console.log('  ✗ ' + m + (d ? ' — ' + d : '')); };

async function loginAndOpenDesign(page) {
	await page.goto(`${WP_URL}/wp-login.php`);
	await page.fill('#user_login', USER);
	await page.fill('#user_pass', PASS);
	await Promise.all([page.waitForLoadState('networkidle'), page.click('#wp-submit')]);
	await page.goto(`${WP_URL}/wp-admin/admin.php?page=wchs-settings&tab=design`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1200);
	// Expand all collapsible sections
	const n = await page.locator('.wchs-section__toggle').count();
	for (let i = 0; i < n; i++) {
		try { await page.locator('.wchs-section__toggle').nth(i).click(); await page.waitForTimeout(50); } catch (e) {}
	}
	await page.waitForTimeout(300);
}

async function saveAndWait(page) {
	await page.locator('form[action*=admin-post] button[type=submit]').first().click();
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(400);
}

async function main() {
	const browser = await chromium.launch();
	const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
	const page = await ctx.newPage();
	page.on('dialog', async d => { await d.accept(); });

	console.log('--- Admin: all 14 product_card fields render ---');
	await loginAndOpenDesign(page);
	const fields = await page.evaluate(() =>
		Array.from(document.querySelectorAll('[name^=product_card]')).map(e => e.name)
	);
	if (fields.length === 14) ok('14 fields render');
	else no('field count', `got ${fields.length}: ${fields.join(',')}`);

	console.log('\n--- Round-trip: save + REST reflect each enum change ---');
	// Change a few enum values + save
	await page.selectOption('[name="product_card[media_aspect_ratio]"]', '4:5');
	await page.selectOption('[name="product_card[corner_radius]"]', 'round');
	await page.selectOption('[name="product_card[border]"]', 'bottom-only');
	await page.selectOption('[name="product_card[hover_effect]"]', 'shadow');
	await page.selectOption('[name="product_card[button_style]"]', 'solid');
	await page.selectOption('[name="product_card[badge_position]"]', 'top-left');
	await page.selectOption('[name="product_card[badge_style]"]', 'outline');
	await page.selectOption('[name="product_card[oos_treatment]"]', 'dim');
	await page.selectOption('[name="product_card[title_lines]"]', '2');
	await page.fill('[name="product_card[sale_badge_text]"]', '−{percent}%');
	await page.uncheck('[name="product_card[show_tier_hint]"]').catch(() => {});
	await saveAndWait(page);

	const rest = await page.evaluate(async () => {
		const r = await fetch('/wp-json/wchs/v1/config');
		return (await r.json()).product_card;
	});
	const expected = {
		media_aspect_ratio: '4:5',
		corner_radius: 'round',
		border: 'bottom-only',
		hover_effect: 'shadow',
		button_style: 'solid',
		badge_position: 'top-left',
		badge_style: 'outline',
		oos_treatment: 'dim',
		title_lines: '2',
		sale_badge_text: '−{percent}%',
		show_tier_hint: false,
	};
	let allMatch = true;
	for (const [k, v] of Object.entries(expected)) {
		if (rest[k] === v) ok(`REST ${k} = ${JSON.stringify(v)}`);
		else { no(`REST ${k}`, `expected ${JSON.stringify(v)} got ${JSON.stringify(rest[k])}`); allMatch = false; }
	}

	console.log('\n--- SPA: CSS vars + data attributes on <html> ---');
	// Visit SPA direct; tokens should be applied after config.load()
	await page.goto(`${SPA_URL}/`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1500);
	const tokens = await page.evaluate(() => {
		const root = document.documentElement;
		return {
			aspectVar: getComputedStyle(root).getPropertyValue('--card-aspect-ratio').trim(),
			radiusVar: getComputedStyle(root).getPropertyValue('--card-radius').trim(),
			borderAttr: root.getAttribute('data-card-border'),
			hoverAttr: root.getAttribute('data-card-hover'),
			buttonAttr: root.getAttribute('data-card-button'),
			badgePosAttr: root.getAttribute('data-card-badge-position'),
			badgeStyleAttr: root.getAttribute('data-card-badge-style'),
			oosAttr: root.getAttribute('data-card-oos-treatment'),
			titleLinesAttr: root.getAttribute('data-card-title-lines'),
		};
	});
	if (tokens.aspectVar === '4 / 5') ok(`--card-aspect-ratio = ${tokens.aspectVar}`);
	else no('aspect var', `got "${tokens.aspectVar}"`);
	if (tokens.radiusVar === '8px') ok(`--card-radius = ${tokens.radiusVar}`);
	else no('radius var', `got "${tokens.radiusVar}"`);
	if (tokens.borderAttr === 'bottom-only') ok(`border attr = ${tokens.borderAttr}`);
	else no('border attr', `got "${tokens.borderAttr}"`);
	if (tokens.hoverAttr === 'shadow') ok(`hover attr = ${tokens.hoverAttr}`);
	else no('hover attr', `got "${tokens.hoverAttr}"`);
	if (tokens.buttonAttr === 'solid') ok(`button attr = ${tokens.buttonAttr}`);
	else no('button attr', `got "${tokens.buttonAttr}"`);
	if (tokens.badgePosAttr === 'top-left') ok(`badge-position = ${tokens.badgePosAttr}`);
	else no('badge-position', `got "${tokens.badgePosAttr}"`);
	if (tokens.badgeStyleAttr === 'outline') ok(`badge-style = ${tokens.badgeStyleAttr}`);
	else no('badge-style', `got "${tokens.badgeStyleAttr}"`);
	if (tokens.oosAttr === 'dim') ok(`oos-treatment = ${tokens.oosAttr}`);
	else no('oos-treatment', `got "${tokens.oosAttr}"`);
	if (tokens.titleLinesAttr === '2') ok(`title-lines = ${tokens.titleLinesAttr}`);
	else no('title-lines', `got "${tokens.titleLinesAttr}"`);

	console.log('\n--- Shop page: aspect ratio applies to product-card media ---');
	await page.goto(`${SPA_URL}/shop`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1500);
	const mediaInfo = await page.evaluate(() => {
		const m = document.querySelector('.store-card__media');
		if (!m) return null;
		const cs = getComputedStyle(m);
		return { aspectRatio: cs.aspectRatio };
	});
	if (mediaInfo && (mediaInfo.aspectRatio === '4 / 5' || mediaInfo.aspectRatio.includes('0.8'))) {
		ok(`media aspect-ratio computed: ${mediaInfo.aspectRatio}`);
	} else {
		no('media aspect', `got "${mediaInfo?.aspectRatio}"`);
	}

	console.log('\n--- Card border-radius honors corner_radius under compatible border ---');
	// bottom-only correctly forces 0 radius (rounded-bottom-only looks weird).
	// Flip border → full, re-check that corner_radius=round sets 8px.
	await loginAndOpenDesign(page);
	await page.selectOption('[name="product_card[border]"]', 'full');
	await saveAndWait(page);
	await page.goto(`${SPA_URL}/shop`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1500);
	const radiusNow = await page.evaluate(() => {
		const c = document.querySelector('.store-card');
		return c ? getComputedStyle(c).borderRadius : null;
	});
	if (radiusNow === '8px') ok(`card border-radius = ${radiusNow} with border=full + corner_radius=round`);
	else no('card radius', `got "${radiusNow}" (expected 8px)`);

	console.log('\n--- Reset to defaults + round-trip ---');
	await loginAndOpenDesign(page);
	await page.selectOption('[name="product_card[media_aspect_ratio]"]', '1:1');
	await page.selectOption('[name="product_card[corner_radius]"]', 'square');
	await page.selectOption('[name="product_card[border]"]', 'full');
	await page.selectOption('[name="product_card[hover_effect]"]', 'lift');
	await page.selectOption('[name="product_card[button_style]"]', 'outline');
	await page.selectOption('[name="product_card[badge_position]"]', 'top-right');
	await page.selectOption('[name="product_card[badge_style]"]', 'filled');
	await page.selectOption('[name="product_card[oos_treatment]"]', 'grayscale');
	await page.selectOption('[name="product_card[title_lines]"]', 'auto');
	await page.fill('[name="product_card[sale_badge_text]"]', 'Sale');
	await page.check('[name="product_card[show_tier_hint]"]');
	await saveAndWait(page);
	const restDef = await page.evaluate(async () => (await (await fetch('/wp-json/wchs/v1/config')).json()).product_card);
	if (restDef.media_aspect_ratio === '1:1' && restDef.corner_radius === 'square' && restDef.badge_position === 'top-right')
		ok('Defaults restored cleanly');
	else no('defaults restore', JSON.stringify(restDef));

	// ═════════════════════════════════════════════════════════
	// Backward-compat: schema evolves, older saved states still work
	// ═════════════════════════════════════════════════════════
	const { execSync } = await import('node:child_process');
	// Wrap PHP in single quotes so bash leaves $ alone, then wp-cli evals it.
	const wpEval = (php) => {
		execSync(`docker exec wchs-wpcli wp eval '${php}' --allow-root`, { stdio: 'pipe' });
	};
	const setCardOption = (phpArray) => {
		wpEval(`$o = get_option("wchs_site_settings", array()); $o["product_card"] = ${phpArray}; update_option("wchs_site_settings", $o);`);
	};
	const unsetCardOption = () => {
		wpEval(`$o = get_option("wchs_site_settings", array()); unset($o["product_card"]); update_option("wchs_site_settings", $o);`);
	};

	console.log('\n--- Compat: no product_card in options → defaults emitted ---');
	let noKey = null;
	try {
		unsetCardOption();
		noKey = await page.evaluate(async () => (await (await fetch('/wp-json/wchs/v1/config')).json()).product_card);
	} catch (e) { console.log('  · wp-cli error:', e.message); }
	if (noKey && noKey.media_aspect_ratio && noKey.corner_radius && noKey.button_style) {
		ok(`No saved product_card → defaults filled (ratio=${noKey.media_aspect_ratio}, radius=${noKey.corner_radius})`);
	} else {
		no('no-key defaults', JSON.stringify(noKey));
	}

	console.log('\n--- Compat: partial product_card (only 1 key) → rest from defaults ---');
	let partial = null;
	try {
		setCardOption(`array("media_aspect_ratio" => "2:3")`);
		partial = await page.evaluate(async () => (await (await fetch('/wp-json/wchs/v1/config')).json()).product_card);
	} catch (e) { console.log('  · wp-cli error:', e.message); }
	if (partial && partial.media_aspect_ratio === '2:3' && partial.corner_radius && partial.button_style) {
		ok(`Partial key saved, rest filled (ratio=${partial.media_aspect_ratio}, radius=${partial.corner_radius})`);
	} else {
		no('partial defaults', JSON.stringify(partial));
	}

	console.log('\n--- Compat: invalid enum on disk → next admin save scrubs it ---');
	let sanitized = null;
	try {
		setCardOption(`array("corner_radius" => "garbage")`);
		// Reload Design tab and submit form — this triggers SchemaSanitizer.
		await loginAndOpenDesign(page);
		await saveAndWait(page);
		sanitized = await page.evaluate(async () => (await (await fetch('/wp-json/wchs/v1/config')).json()).product_card);
	} catch (e) { console.log('  · wp-cli error:', e.message); }
	if (sanitized && sanitized.corner_radius && sanitized.corner_radius !== 'garbage') {
		ok(`Invalid enum scrubbed on save → corner_radius="${sanitized.corner_radius}"`);
	} else {
		no('invalid enum fallback', JSON.stringify(sanitized));
	}

	console.log('\n=======================================');
	console.log(`Results: ${pass} passed, ${fail} failed`);
	await browser.close();
	process.exit(fail > 0 ? 1 : 0);
}

main().catch(e => { console.error(e); process.exit(1); });
