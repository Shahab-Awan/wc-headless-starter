// E2E test: Offline payment gateways + upsell flow
// Tests CashApp and Venmo gateways end-to-end through WC checkout
// Verifies thank-you page rendering, upsell offer, accept flow
//
// Run: node tests/e2e-offline-gateways.js

const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

const { chromium } = require('./playwright');

const WP = 'http://localhost:8099';
const ROOT = path.resolve(__dirname, '..');
const SHOTS = path.resolve(__dirname, 'screenshots', 'offline-gateways');
fs.rmSync(SHOTS, { recursive: true, force: true });
fs.mkdirSync(SHOTS, { recursive: true });

let stepCounter = 0;
async function shoot(page, label) {
	const n = String(++stepCounter).padStart(2, '0');
	const file = path.join(SHOTS, `${n}-${label}.png`);
	await page.screenshot({ path: file, fullPage: false });
	console.log(`  📸 ${n}-${label}`);
	return file;
}

function wp(php) {
	return execSync(
		`cd ${ROOT} && docker compose exec -T -u 33:33 wpcli php -d memory_limit=1024M /usr/local/bin/wp eval '${php.replace(/'/g, "'\\''")}'`,
		{ stdio: 'pipe' }
	).toString().trim();
}

(async () => {
	const browser = await chromium.launch({ headless: true });
	const results = { pass: 0, fail: 0, tests: [] };

	function assert(name, condition) {
		if (condition) {
			results.pass++;
			results.tests.push({ name, status: 'PASS' });
			console.log(`  ✓ ${name}`);
		} else {
			results.fail++;
			results.tests.push({ name, status: 'FAIL' });
			console.log(`  ✗ ${name}`);
		}
	}

	try {
		// ────────────────────────────────────────────────
		// TEST 1: CashApp checkout + thank-you page
		// ────────────────────────────────────────────────
		console.log('\n=== TEST 1: CashApp Checkout ===');
		const ctx1 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		const page1 = await ctx1.newPage();
		const cashappEmail = `cashapp+${Date.now()}@test.local`;

		// Add product to cart via WP shop page
		const firstProductId = wp(`echo wc_get_products(["limit"=>1,"status"=>"publish"])[0]->get_id();`);
		await page1.goto(`${WP}/?add-to-cart=${firstProductId}`, { waitUntil: 'domcontentloaded' });
		await page1.waitForTimeout(1000);

		// Go to WP checkout
		await page1.goto(`${WP}/checkout/`, { waitUntil: 'domcontentloaded' });
		await page1.waitForSelector('#billing_first_name', { timeout: 15000 });
		await page1.waitForTimeout(1000);

		// Fill billing
		await page1.fill('#billing_first_name', 'CashApp');
		await page1.fill('#billing_last_name', 'Tester');
		await page1.fill('#billing_address_1', '123 Test Lane');
		await page1.fill('#billing_city', 'Testville');
		await page1.fill('#billing_postcode', '90210');
		await page1.fill('#billing_phone', '5555551234');
		await page1.fill('#billing_email', cashappEmail);

		// Select CashApp payment method
		const cashappRadio = await page1.$('input#payment_method_wchs_offline_cashapp');
		if (cashappRadio) {
			await cashappRadio.click();
			await page1.waitForTimeout(500);
			console.log('  → Selected CashApp gateway');
		} else {
			console.log('  ! CashApp radio not found, checking available methods...');
			const methods = await page1.$$eval('#payment ul.payment_methods li', els =>
				els.map(el => el.querySelector('input')?.id || 'no-input')
			);
			console.log('  Available methods:', methods);
		}

		await shoot(page1, 'cashapp-checkout');

		// Place order
		await page1.click('#place_order');
		try {
			await page1.waitForURL(/order-received|wchs_upsell/, { timeout: 15000 });
		} catch (e) {
			console.log('  ! Timeout waiting for redirect, at:', page1.url());
		}
		await page1.waitForTimeout(2000);

		const postUrl1 = page1.url();
		console.log(`  Post-submit URL: ${postUrl1}`);

		// Check if upsell page appeared
		if (/wchs_upsell=1/.test(postUrl1)) {
			console.log('  → Upsell offer page detected');
			await shoot(page1, 'cashapp-upsell-offer');

			assert('Upsell offer rendered for CashApp', true);

			// Click decline to get to thank-you
			const skipLink = await page1.$('a.wchs-offer__skip');
			if (skipLink) {
				await skipLink.click();
				await page1.waitForURL(/order-received/, { timeout: 10000 }).catch(() => {});
				await page1.waitForTimeout(2000);
			}
		}

		await shoot(page1, 'cashapp-thankyou-light');

		// Check thank-you page content
		const tyContent1 = await page1.content();
		assert('Thank-you shows CashApp handle ($mksup3r)', tyContent1.includes('$mksup3r'));
		assert('Thank-you shows payment instructions', tyContent1.includes('wchs-offline-payment'));
		assert('Thank-you shows copy button', tyContent1.includes('Copy'));
		assert('Thank-you shows pay link', tyContent1.includes('cash.app'));
		assert('Thank-you shows QR target', tyContent1.includes('wchs-qr-target'));

		// Switch to dark theme
		const toggleBtn = await page1.$('.wchs-theme-toggle');
		if (toggleBtn) {
			await toggleBtn.click();
			await page1.waitForTimeout(500);
		} else {
			await page1.evaluate(() => {
				document.documentElement.setAttribute('data-theme', 'dark');
			});
			await page1.waitForTimeout(300);
		}
		await shoot(page1, 'cashapp-thankyou-dark');

		await ctx1.close();

		// ────────────────────────────────────────────────
		// TEST 2: Venmo checkout + thank-you page
		// ────────────────────────────────────────────────
		console.log('\n=== TEST 2: Venmo Checkout ===');
		const ctx2 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		const page2 = await ctx2.newPage();
		const venmoEmail = `venmo+${Date.now()}@test.local`;

		await page2.goto(`${WP}/?add-to-cart=${firstProductId}`, { waitUntil: 'domcontentloaded' });
		await page2.waitForTimeout(1000);

		await page2.goto(`${WP}/checkout/`, { waitUntil: 'domcontentloaded' });
		await page2.waitForSelector('#billing_first_name', { timeout: 15000 });
		await page2.waitForTimeout(1000);

		await page2.fill('#billing_first_name', 'Venmo');
		await page2.fill('#billing_last_name', 'Tester');
		await page2.fill('#billing_address_1', '456 Test Ave');
		await page2.fill('#billing_city', 'Testville');
		await page2.fill('#billing_postcode', '90210');
		await page2.fill('#billing_phone', '5555554321');
		await page2.fill('#billing_email', venmoEmail);

		const venmoRadio = await page2.$('input#payment_method_wchs_offline_venmo');
		if (venmoRadio) {
			await venmoRadio.click();
			await page2.waitForTimeout(500);
			console.log('  → Selected Venmo gateway');
		}

		await shoot(page2, 'venmo-checkout');

		await page2.click('#place_order');
		try {
			await page2.waitForURL(/order-received|wchs_upsell/, { timeout: 15000 });
		} catch (e) {
			console.log('  ! Timeout, at:', page2.url());
		}
		await page2.waitForTimeout(2000);

		const postUrl2 = page2.url();
		console.log(`  Post-submit URL: ${postUrl2}`);

		if (/wchs_upsell=1/.test(postUrl2)) {
			console.log('  → Upsell offer page detected');
			await shoot(page2, 'venmo-upsell-offer');
			assert('Upsell offer rendered for Venmo', true);

			// Accept upsell this time
			const acceptLink = await page2.$('.wchs-offer__accept');
			if (acceptLink) {
				console.log('  → Accepting upsell');
				await acceptLink.click();
				await page2.waitForURL(/order-received/, { timeout: 10000 }).catch(() => {});
				await page2.waitForTimeout(2000);
			}
		}

		await shoot(page2, 'venmo-thankyou-light');

		const tyContent2 = await page2.content();
		assert('Thank-you shows Venmo handle (@werewolfbiologics)', tyContent2.includes('werewolfbiologics'));
		assert('Thank-you shows Venmo pay link', tyContent2.includes('venmo.com'));

		// Switch to dark
		await page2.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
		await page2.waitForTimeout(300);
		await shoot(page2, 'venmo-thankyou-dark');

		await ctx2.close();

		// ────────────────────────────────────────────────
		// TEST 3: Verify order data in WP
		// ────────────────────────────────────────────────
		console.log('\n=== TEST 3: Order Verification ===');

		const orderData = JSON.parse(wp(`
			$venmo = wc_get_orders(["limit"=>1,"orderby"=>"date","order"=>"DESC","payment_method"=>"wchs_offline_venmo","billing_email"=>"${venmoEmail}"]);
			$cashapp = wc_get_orders(["limit"=>1,"orderby"=>"date","order"=>"DESC","payment_method"=>"wchs_offline_cashapp","billing_email"=>"${cashappEmail}"]);
			$orders = array_merge($venmo, $cashapp);
			$result = [];
			foreach ($orders as $o) {
				$items = [];
				foreach ($o->get_items() as $i) $items[] = $i->get_name();
				$result[] = [
					"id" => $o->get_id(),
					"status" => $o->get_status(),
					"payment_method" => $o->get_payment_method(),
					"total" => $o->get_total(),
					"items" => $items,
					"upsell_status" => $o->get_meta("_wchs_upsell_status"),
					"has_token" => !empty($o->get_meta("_wchs_upsell_token")),
				];
			}
			echo json_encode($result);
		`));

		console.log('  Recent orders:');
		for (const o of orderData) {
			console.log(`    #${o.id}: ${o.payment_method} | ${o.status} | $${o.total} | upsell=${o.upsell_status} | items=${o.items.join(', ')}`);

			if (o.payment_method === 'wchs_offline_venmo') {
				assert('Venmo order upsell accepted', o.upsell_status === 'accepted');
				assert('Venmo order has 2 items (original + upsell)', o.items.length === 2);
				assert('Venmo upsell token invalidated', !o.has_token);
			}
			if (o.payment_method === 'wchs_offline_cashapp') {
				assert('CashApp order upsell declined', o.upsell_status === 'declined');
				assert('CashApp order has 1 item', o.items.length === 1);
				assert('CashApp upsell token invalidated', !o.has_token);
			}
		}

		// ────────────────────────────────────────────────
		// TEST 4: Security — idempotency / replay
		// ────────────────────────────────────────────────
		console.log('\n=== TEST 4: Security Tests ===');

		// Try to replay the Venmo accept URL (already accepted)
		const ctx4 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		const page4 = await ctx4.newPage();

		const venmoOrder = orderData.find(o => o.payment_method === 'wchs_offline_venmo');
		if (venmoOrder) {
			// Try to hit the upsell offer page again
			const replayUrl = `${WP}/checkout/order-received/${venmoOrder.id}/?wchs_upsell=1&order_id=${venmoOrder.id}&order_key=fake_key&token=fake_token`;
			await page4.goto(replayUrl, { waitUntil: 'domcontentloaded' });
			await page4.waitForTimeout(1000);
			const replayContent = await page4.content();
			assert('Replay with fake token rejected', !replayContent.includes('wchs-offer__accept'));

			// Try with no token
			const noTokenUrl = `${WP}/checkout/order-received/${venmoOrder.id}/?wchs_upsell_accept=1&order_id=${venmoOrder.id}&order_key=fake`;
			await page4.goto(noTokenUrl, { waitUntil: 'domcontentloaded' });
			await page4.waitForTimeout(1000);
			assert('Accept without valid token rejected', !page4.url().includes('wchs_upsell_accept'));
		}

		// Try to enumerate order IDs
		const enumUrl = `${WP}/checkout/order-received/1/?wchs_upsell=1&order_id=1&order_key=wc_fake&token=fake`;
		await page4.goto(enumUrl, { waitUntil: 'domcontentloaded' });
		await page4.waitForTimeout(500);
		const enumContent = await page4.content();
		assert('Order enumeration blocked', !enumContent.includes('wchs-offer__accept'));

		await ctx4.close();

		// ────────────────────────────────────────────────
		// TEST 5: COD SHOULD get upsell (deferred payment)
		// ────────────────────────────────────────────────
		console.log('\n=== TEST 5: COD Upsell Flow ===');
		const ctx5 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		const page5 = await ctx5.newPage();

		await page5.goto(`${WP}/?add-to-cart=${firstProductId}`, { waitUntil: 'domcontentloaded' });
		await page5.waitForTimeout(1000);

		await page5.goto(`${WP}/checkout/`, { waitUntil: 'domcontentloaded' });
		await page5.waitForSelector('#billing_first_name', { timeout: 15000 });
		await page5.waitForTimeout(1000);

		await page5.fill('#billing_first_name', 'COD');
		await page5.fill('#billing_last_name', 'Tester');
		await page5.fill('#billing_address_1', '789 Test Blvd');
		await page5.fill('#billing_city', 'Testville');
		await page5.fill('#billing_postcode', '90210');
		await page5.fill('#billing_phone', '5555559999');
		await page5.fill('#billing_email', `cod+${Date.now()}@test.local`);

		const codRadio = await page5.$('input#payment_method_cod');
		if (codRadio) await codRadio.click();

		await page5.click('#place_order');
		try {
			await page5.waitForURL(/order-received/, { timeout: 15000 });
		} catch (e) {
			console.log('  ! Timeout, at:', page5.url());
		}
		await page5.waitForTimeout(2000);

		const codUrl = page5.url();
		assert('COD order gets upsell offer', codUrl.includes('wchs_upsell'));
		await shoot(page5, 'cod-upsell-offer');

		await ctx5.close();

		// ────────────────────────────────────────────────
		// RESULTS
		// ────────────────────────────────────────────────
		console.log(`\n${'='.repeat(50)}`);
		console.log(`RESULTS: ${results.pass} passed, ${results.fail} failed`);
		for (const t of results.tests) {
			console.log(`  ${t.status === 'PASS' ? '✓' : '✗'} ${t.name}`);
		}
		console.log(`\n${results.fail === 0 ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED'}`);

		if (results.fail > 0) process.exitCode = 1;

	} catch (err) {
		console.error('❌ E2E failed:', err.message);
		console.error(err.stack);
		process.exitCode = 1;
	} finally {
		await browser.close();
	}
})();
