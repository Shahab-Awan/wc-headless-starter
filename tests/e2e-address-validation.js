// E2E test: Address validation across all 3 modes
// Run: node tests/e2e-address-validation.js

const path = require('path');
const { execSync } = require('child_process');
const { chromium } = require('./playwright');

const ROOT = path.resolve(__dirname, '..');
const SHOTS = path.resolve(__dirname, 'screenshots', 'address-validation');
const fs = require('fs');
fs.rmSync(SHOTS, { recursive: true, force: true });
fs.mkdirSync(SHOTS, { recursive: true });

function wp(php) {
	return execSync(
		`cd ${ROOT} && docker compose exec -T -u 33:33 wpcli php -d memory_limit=1024M /usr/local/bin/wp eval '${php.replace(/'/g, "'\\''")}'`,
		{ stdio: 'pipe' }
	).toString().trim();
}

function setMode(mode) {
	wp(`
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wchs_av_%'");
		$s = \\WCHS\\Admin\\AdminPage::get_site_settings();
		$s["address_validation_mode"] = "${mode}";
		update_option("wchs_site_settings", $s);
	`);
}

let pass = 0, fail = 0, results = [];
function assert(name, ok) {
	if (ok) { pass++; results.push({ name, s: 'PASS' }); console.log(`  ✓ ${name}`); }
	else { fail++; results.push({ name, s: 'FAIL' }); console.log(`  ✗ ${name}`); }
}

(async () => {
	const browser = await chromium.launch({ headless: true });

	async function testCheckout(addr, city, zip, label) {
		const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		const page = await ctx.newPage();

		await page.goto(`http://localhost:8099/?add-to-cart=23`, { waitUntil: 'domcontentloaded' });
		await page.waitForTimeout(1000);
		await page.goto(`http://localhost:8099/checkout/`, { waitUntil: 'domcontentloaded' });
		await page.waitForSelector('#billing_first_name', { timeout: 15000 });
		await page.waitForTimeout(2500);

		await page.fill('#billing_first_name', 'Test');
		await page.fill('#billing_last_name', 'User');
		await page.fill('#billing_address_1', addr);
		await page.fill('#billing_city', city);
		try { await page.selectOption('#billing_state', 'CO'); } catch (e) {}
		await page.fill('#billing_postcode', zip);
		await page.fill('#billing_phone', '5555551234');
		await page.fill('#billing_email', `e2e${Date.now()}@test.local`);
		await page.waitForTimeout(500);
		const cod = await page.$('input#payment_method_cod');
		if (cod) await cod.click();
		await page.waitForTimeout(500);

		// Click place order and wait for AJAX
		const ajaxPromise = page.waitForResponse(
			res => res.request().postData()?.includes('wchs_validate_address'),
			{ timeout: 10000 }
		).catch(() => null);

		await page.click('#place_order');
		const ajaxRes = await ajaxPromise;
		let ajaxData = null;
		if (ajaxRes) {
			try { ajaxData = await ajaxRes.json(); } catch (e) {}
		}

		await page.waitForTimeout(3000);

		const modalVisible = await page.evaluate(() => {
			const m = document.getElementById('wchs-av-modal');
			return m && m.style.display === '';
		});

		const modalContent = await page.evaluate(() => {
			const m = document.getElementById('wchs-av-modal');
			if (!m || m.style.display === 'none') return null;
			return {
				subtitle: document.getElementById('wchs-av-subtitle')?.textContent || '',
				hasUseValidated: !!document.getElementById('wchs-av-confirm'),
				hasEdit: !!document.getElementById('wchs-av-edit'),
				hasForce: !!document.getElementById('wchs-av-force'),
				hasFix: !!document.getElementById('wchs-av-fix'),
				hasRadios: !!document.querySelector('input[name="wchs_av_choice"]'),
			};
		});

		const orderPlaced = /order-received/.test(page.url());

		await page.screenshot({ path: path.join(SHOTS, `${label}.png`) });

		// If modal visible, test clicking the primary button
		let afterConfirmUrl = null;
		if (modalVisible) {
			const confirmBtn = await page.$('#wchs-av-confirm');
			if (confirmBtn) {
				await confirmBtn.click();
				await page.waitForTimeout(6000);
				afterConfirmUrl = page.url();
				await page.screenshot({ path: path.join(SHOTS, `${label}-after.png`) });
			}
		}

		await ctx.close();

		return {
			ajaxData,
			modalVisible,
			modalContent,
			orderPlaced,
			afterConfirmUrl,
		};
	}

	try {
		// ═══════════════════════════════════════════
		// STRICT MODE
		// ═══════════════════════════════════════════
		console.log('\n=== STRICT MODE ===');
		setMode('strict');

		// Valid address — should pass through
		console.log('\n  -- Valid address --');
		const s1 = await testCheckout('1213 Liberty Lane', 'Pueblo', '81001', 'strict-valid');
		assert('Strict/valid: order placed', s1.orderPlaced);
		assert('Strict/valid: no modal', !s1.modalVisible);

		// Wrong city — should show modal with only "Use validated" + "Enter different"
		console.log('\n  -- Wrong city --');
		const s2 = await testCheckout('1213 Liberty Lane', 'Pue', '81001', 'strict-corrected');
		assert('Strict/corrected: modal shown', s2.modalVisible);
		assert('Strict/corrected: has Use Validated btn', s2.modalContent?.hasUseValidated === true);
		assert('Strict/corrected: has Enter Different btn', s2.modalContent?.hasEdit === true);
		assert('Strict/corrected: NO radio choices', s2.modalContent?.hasRadios === false);
		assert('Strict/corrected: clicking confirm places order', /order-received/.test(s2.afterConfirmUrl || ''));

		// Fake address — should show modal with only "Fix"
		console.log('\n  -- Fake address --');
		const s3 = await testCheckout('45345 Doodoo Lane', 'Pooptown', '54244', 'strict-fake');
		assert('Strict/fake: modal shown', s3.modalVisible);
		assert('Strict/fake: has Fix btn', s3.modalContent?.hasFix === true);
		assert('Strict/fake: NO force override', s3.modalContent?.hasForce === false);

		// ═══════════════════════════════════════════
		// MODERATE MODE
		// ═══════════════════════════════════════════
		console.log('\n=== MODERATE MODE ===');
		setMode('moderate');

		// Valid — pass through
		console.log('\n  -- Valid address --');
		const m1 = await testCheckout('1213 Liberty Lane', 'Pueblo', '81001', 'mod-valid');
		assert('Moderate/valid: order placed', m1.orderPlaced);

		// Wrong city — modal with radio choices
		console.log('\n  -- Wrong city --');
		const m2 = await testCheckout('1213 Liberty Lane', 'Pue', '81001', 'mod-corrected');
		assert('Moderate/corrected: modal shown', m2.modalVisible);
		assert('Moderate/corrected: has radio choices', m2.modalContent?.hasRadios === true);
		assert('Moderate/corrected: clicking confirm places order', /order-received/.test(m2.afterConfirmUrl || ''));

		// Fake — modal with Fix only (no override)
		console.log('\n  -- Fake address --');
		const m3 = await testCheckout('45345 Doodoo Lane', 'Pooptown', '54244', 'mod-fake');
		assert('Moderate/fake: modal shown', m3.modalVisible);
		assert('Moderate/fake: has Fix btn', m3.modalContent?.hasFix === true);
		assert('Moderate/fake: NO force override', m3.modalContent?.hasForce === false);

		// ═══════════════════════════════════════════
		// LOOSE MODE
		// ═══════════════════════════════════════════
		console.log('\n=== LOOSE MODE ===');
		setMode('loose');

		// Valid — pass through
		console.log('\n  -- Valid address --');
		const l1 = await testCheckout('1213 Liberty Lane', 'Pueblo', '81001', 'loose-valid');
		assert('Loose/valid: order placed', l1.orderPlaced);

		// Fake — modal WITH override button
		console.log('\n  -- Fake address --');
		const l2 = await testCheckout('45345 Doodoo Lane', 'Pooptown', '54244', 'loose-fake');
		assert('Loose/fake: modal shown', l2.modalVisible);
		assert('Loose/fake: HAS force override', l2.modalContent?.hasForce === true);

	} catch (err) {
		console.error('FATAL:', err.message);
		process.exitCode = 1;
	} finally {
		// Restore moderate
		try { setMode('moderate'); } catch (e) {}

		console.log(`\n${'='.repeat(50)}`);
		console.log(`RESULTS: ${pass} passed, ${fail} failed`);
		results.forEach(r => console.log(`  ${r.s === 'PASS' ? '✓' : '✗'} ${r.name}`));
		console.log(`\n${fail === 0 ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED'}`);
		if (fail > 0) process.exitCode = 1;
		await browser.close();
	}
})();
